# 表单关联关系与 JSON 字段存储

> 覆盖 Form 中一对一 / 一对多 / 多对多 / 多态关联的编辑与保存（`profile.xxx`、`hasMany`、多选同步），以及 JSON 列的结构化编辑（`embeds`、`table`、`array`、`keyValue`、`list`）。生命周期、事件、验证见 `form.md`；字段见 `form-fields.md`；Repository 见 `repository.md`。

## 关联保存原理

`EloquentRepository`（直接传模型时自动使用）的 `store/update` 在一个 `DB::transaction` 中：

1. 把输入中**键名等于模型上 public 关联方法**（也匹配 `Str::camel` 后的名字）且返回 `Relation` 的项拆出来；
2. 其余列 `setAttribute()` 到主模型后 `save()`/`update()`；
3. 再按关联类型写入（源码：`src/Repositories/EloquentRepository.php` `getRelationInputs/updateRelation`）：

| 关联类型 | 保存方式 |
|---|---|
| `HasOne` / `MorphOne` | 取已有关联模型，不存在则新建（MorphOne 用 `$relation->make()`）并设外键，`setAttribute` 后 `save()` |
| `BelongsTo` / `MorphTo` | 修改**父模型**字段并 `save()`；主模型外键为空时自动关联新父模型 |
| `HasMany` / `MorphMany` | 逐行 `findOrNew(主键)`，`_remove_=1` 的行 `delete()`，其余 `fill()` 后 `save()` |
| `BelongsToMany` / `MorphToMany` | `$relation->sync($ids)` |

> ⚠️ `hasMany` 子模型用 `fill()`，**必须配置 `$fillable`（或 `$guarded = []`）**，否则字段被静默丢弃；主模型和一对一关联用 `setAttribute()`，不受影响。

## 加载关联数据

编辑页回填来自 `repository->edit()`，**必须显式预加载关联**，否则关联字段为空：`Form::make(User::with(['profile', 'tags']), ...)`；自定义 Repository 用 `UserRepo::with([...])` 或 `new UserRepo(['profile', 'tags'])`。

## 一对一 HasOne / BelongsTo / MorphOne

- 点语法 `$form->text('profile.age')`；新增时自动创建关联记录并写外键，编辑时更新。
- 关联名为驼峰（`userProfile()`）时字段推荐写 `user_profile.postcode`，与 `toArray()` 键一致（源码两种写法都兼容：`Field::getValueFromData` 回退 `Str::snake`，`getRelationInputs` 回退 `Str::camel`）。
- `BelongsTo` 用点语法（如 `user.name`）是**编辑父记录的字段**，不是选择父记录；选择父记录用外键字段 `$form->select('user_id')`。

## 一对多 hasMany

```php
$form->hasMany('paintings', '画作', function (Form\NestedForm $form) {
    $form->text('title')->required();
});
```

- 签名 `hasMany($relationName, $labelOrCallback, $callback = null)`，返回 `Field\HasMany`：`useTable()`、`useTab()`、`disableCreate()`、`disableDelete()`、`setRelationKeyName(?string $key)`（子表主键默认 `'id'`，非 `id` 时必须设置）。
- `rules('array|min:1')` 是对整个数组的规则，但**只有至少一个子字段设置了规则时整体规则才执行**（源码：`Field/HasMany.php` `getValidator`）；子字段规则按行校验，已删除行跳过。
- 回调参数为 `Dcat\Admin\Form\NestedForm`，支持几乎全部字段、`column()`/`row()` 与上传字段。
- 每行自动带隐藏字段主键（`id`）和 `_remove_`，不要再手动声明 `id`。
- 子表单字段的 JS 需用 `Dcat.init` 监听才能在动态新增行生效。

## 多对多 BelongsToMany / MorphToMany

```php
Form::make(Post::with('tags'), function (Form $form) {
    $form->multipleSelect('tags')->options(Tag::pluck('name', 'id'))
        ->customFormat(fn ($v) => $v ? array_column($v, 'id') : []);
});
```

- 字段名即关联方法名；提交的 ID 数组 `sync()` 到中间表，提交空选择会清空关联。
- **`customFormat` 不能省**：预加载出的是对象数组，需转为 ID 一维数组才能回显。
- 同样适用于 `checkbox`、`listbox`、`tree`、`multipleSelectTable`；内置角色权限即用 `$form->tree('permissions')->nodes(...)->customFormat(...)`（源码：`src/Http/Controllers/RoleController.php`）。tags 需 `pluck()`，见 `form-fields.md`。

## JSON 字段存储

`embeds`、`table`、`array`、`keyValue`、`list`、非关联的多值字段，保存前都是 **PHP 数组**。列为 JSON/TEXT 时二选一：模型 `$casts = ['extra' => 'json']`（或 `array`，推荐）；或字段 `->saveAsJson()`。读取时字段用 `Helper::array()` 自动解析 JSON，所以只用 `saveAsJson()` 也能回显。

> ⚠️ 既配 `$casts` 又 `saveAsJson()` 会双重编码；两者都不配报 "Array to string conversion"。

### embeds 固定键对象

`$form->embeds('extra', '附加信息', fn ($form) => ...)`（label 可省略）。回调内为 `Form\EmbeddedForm`，用法同主表单，子字段规则校验为 `extra.phone` 等。

> ⚠️ 在 `saving` 中把原值合并回 embeds 的提交值时，若合并后出现没有对应子字段的键，`EmbeddedForm::prepareValue` 找不到字段会抛 TypeError（源码：`src/Form/EmbeddedForm.php:186-194`）；只合并已声明的子字段键。

### table / array 二维数组

- `table($column, $labelOrCallback, $callback = null)`（紧凑表格）/ `array(...)`（与 hasMany 相同的卡片布局），回调参数 `Form\NestedForm`。
- `ArrayField` 继承 `HasMany`（`Table` 继承 `ArrayField`）：同样有 `useTable()`、`disableCreate()`、`disableDelete()`，但没有关联、不写子表。
- 保存值是去掉 `_remove_` 行、`array_values` 重排后的列表。

### keyValue / list

见 `form-fields.md`。

## 常见问题

- **"Unknown column 'xxx'"**：声明了非数据库列的字段（如 `password_confirmation`）需 `$form->ignore('xxx')`；或关联方法不是 public 被当成普通列。
- **保存后处理关联**：`saved` 中 `$form->repository()->model()->tags()->attach(...)`。
- **非 Eloquent 数据源**：自定义 Repository 自行实现关联写入，`$form->updates()` 取全部待保存数据，见 `repository.md`。
- 文件路径存到其它表：见 `form-fields-upload.md`「常见片段」。
