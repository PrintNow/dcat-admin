# 数据详情（Show）

> 覆盖 `Dcat\Admin\Show` 详情页：构建与数据来源、字段展示方法、面板/工具、多列布局、关联、扩展、事件。相关文件：actions.md（Show 工具按钮）、repository.md（`detail()` 数据来源）。

## 构造与数据来源

`AdminController::show($id, Content $content)` 调用 `$this->detail($id)`。`Show::make(...$params)` 等价 `new Show($id = null, $model = null, ?Closure $builder = null)`（源码：src/Show.php `__construct`/`initModel`）：

| 写法 | 说明 |
|---|---|
| `Show::make($id, new PostRepository(['author']), fn)` | 调仓库 `detail(Show $show)`；构造参数传关联等价 `with` |
| `Show::make($id, new Post(), fn)` / `Show::make($id, Post::with('author'), fn)` | 无主键模型实例 → `EloquentRepository`；Builder 可预加载/加条件 |
| `Show::make($post, fn)` | 已查询好的模型（有主键）→ 直接用，不再查询 |
| `Show::make(['id' => 1, 'name' => 'x'], fn)` | 数组 / `Arrayable` → 包成 `Fluent` |

- 数据在**构造函数中**就已查询，构建闭包在 `render()` 时才执行，所以闭包里可直接用 `$show->model()`（Eloquent 模型或 `Fluent`）读当前记录。
- `getKey()` / `setKey($id)` / `setKeyName('uuid')`；`repository()` 返回仓库。软删除模型：`EloquentRepository::detail()` 自动 `withTrashed()`。

## 添加字段

- `$show->field('title', '标题')` 返回 `Show\Field`；魔术 `$show->title('标题')` / `$show->title`；`fields(['id', 'title'])` 或 `fields(['id' => 'ID'])`；`all()` 全部字段。
- **未添加任何字段时 `render()` 自动调用 `all()`**；同名字段重复添加会覆盖前一个；label 空时 `admin_trans_field($name)` 并把 `_` 替换为空格。
- 关联字段用点语法且为**下划线风格**：关联方法 `userProfile()` → `field('user_profile.postcode')`（数据取自 `$model->toArray()`）。
- 魔术方法 `$show->xxx(Closure)` / `$show->xxx('标签', Closure)` 会被当作**关联面板**，不是普通字段。

## 字段展示方法

`Show\Field` 方法均可链式，按调用顺序依次处理值（源码：src/Show/Field.php）：

| 方法 | 说明 |
|---|---|
| `as(Closure $callback, ...$params)` | 闭包参数 `($value, ...$params)`，`$this` 为当前记录；输出默认转义，返回 HTML 需 `unescape()` |
| `using(array $values, $default = null)` | 值映射 |
| `image($server = '', $width = 200, $height = 200)` | 路径/URL/数组；无 `$server` 时用 `admin.upload.disk` 生成 URL |
| `file($server = '', $download = true)` | 附件列表（含大小、下载按钮） |
| `link($href = '', $target = '_blank')` | `$href` 空则用字段值 |
| `label($style = 'primary')` / `badge($style = 'blue')` | 数组值逐个渲染 |
| `dot(array $options = [], $default = 'default')` / `bool(array $map = [], $default = false)` / `bold($color = null)` | |
| `json()` | 以 `Dump` 组件显示 JSON/数组 |
| `prepend($val)` / `append($val)` | 闭包 `function ($value, $original)`，`$this` 为当前记录 |
| `explode(string $d = ',')` | 常配 `label()`/`badge()` |
| `view($view, array $data = [])` | 视图变量 `model`、`value`、`name` + `$data` |
| `filesize($dec = 0)` | 字节转可读大小 |
| `unescape()` / `escape(bool $escape = true)` | 默认转义 |
| `wrap(bool $wrap = true)` | 是否带边框盒子 |
| `width(int $field, int $label = 2)` | 栅格宽度 |
| `when($value, Closure $callback)` | `$value` 为真时 `$callback($field, $value)` |

- 未定义的方法透传给 `Str`（字符串值）或 `Collection`（数组值），如 `->limit(30)`、`->upper()`。
- `image/file/link/label/badge/dot/bool/bold/json/view/filesize` 会自动 `unescape()`。
> ⚠️ `filesize()` 默认 `$dec = 0` 且单位与数字间无空格（`191MB`），两位小数需 `filesize(2)`（→ `190.56MB`）。
> ⚠️ `width()` 默认 `field=8, label=2`（栅格），不是"默认 3"。
> ⚠️ `json()` 只对第一层数组元素做 `htmlspecialchars`，值含**嵌套数组**会抛 TypeError；嵌套 JSON 用 `as()` 自行处理（源码：src/Show/Field.php `json()`）。

非字段内容：`$show->divider()`、`newline()`、`html($htmlOrViewOrClosure)`（无 label、不转义；闭包 `function ($value)`，`$this` 为当前记录）。

## 面板与工具按钮

- `$show->panel()->title('文章详情')`（默认 `trans('admin.detail')`）。`panel()->style()` 只写入视图变量，内置视图 `admin::show.panel` 未使用，无可见效果。
- `$show->panel()->tools(function (Show\Tools $tools) {...})` **返回 null，不可继续链式**。`Show\Tools` 方法：`append($tool)`、`prepend()`、`disableList/disableEdit/disableDelete/disableQuickEdit(bool)`、`showQuickEdit(?w, ?h)`（弹窗编辑，会隐藏普通编辑按钮）、`resource()`。
- 快捷方法（返回 `$this` 可链式）：`disableEditButton()`、`disableListButton()`、`disableDeleteButton()`、`showQuickEdit(?string $width = null, ?string $height = null)`（默认 700px × 670px）、`disableQuickEdit()`。
- `$show->tools($callback)`：闭包以 `Show\Tools` 为参数且 **`$this` 绑定为当前记录**；也可传 `AbstractTool`/HTML/数组直接 `append`。自定义按钮见 actions.md「Show 工具」。
- 列表/编辑/删除按钮 URL 来自 `$show->resource()`（默认当前 URL 去掉最后一段）；指向其它资源用 `$show->setResource('users')`（内部 `admin_url()`）。
- 面板视图定制：`$show->view()` / `with([...])` / `wrap(fn ($view) => ...)` 代理到 `Panel`。
> ⚠️ `$show->resource('/users')` / `$grid->resource('comments')` 不会设置路径（`resource()` 无参仅返回），必须用 `setResource()`。

## 多列布局（row）

```php
$show->row(function (Show\Row $show) {
    $show->width(3)->id;          // width($width = 12) 设置"之后所有字段"的 col-md-*，持续生效
    $show->name;                  // 仍为 3
    $show->width(6)->field('profile.postcode', '邮编');
});
```

> ⚠️ 一旦使用 `row()`，**面板只渲染各 row 中的字段**，未放进 row 的字段、`divider()`/`html()` 都不显示。

## 关联数据

- 一对一/简单关联：预加载（`Post::with(['author'])` 或 `new PostRepository(['author'])`）后用点语法 `field('author.name')`。
- 关联面板：`$show->relation($name, $label, Closure $builder)` 或魔术写法。闭包参数为当前记录 `$model`，可返回 `Show`、`Grid` 或任意可渲染内容，渲染在主面板下方（源码：src/Show/Relation.php）。

```php
$show->relation('comments', '评论', function ($model) {
    return Grid::make(new Comment(), function (Grid $grid) use ($model) {
        $grid->model()->where('post_id', $model->id);
        $grid->setResource('comments');   // 行操作链接指向 comments 资源（嵌套 Show 同理 setResource）
        $grid->column('content');
    });
});
```

- 返回 `Show` 时自动设面板标题为 `$label`；返回 `Grid` 时自动 `setName($name)`、`title($label)` 并**禁用批量删除**。`Relation::width(int $width)` 默认 12。

## 自定义展示扩展

```php
use Dcat\Admin\Show\Field;

Field::extend('color', fn ($value, $prefix = '') => "{$prefix}<span style='color:{$value}'>{$value}</span>"); // 相当于 as()
Field::macro('yuan', function () { return $this->as(fn ($v) => '¥'.number_format($v, 2)); });
Field::extend('unserialize', UnSerialize::class);
$show->field('data')->unserialize('xxx'); // skill-check:ignore
```

类扩展继承 `Dcat\Admin\Show\AbstractField`，实现 `render($arg = '')`（调用参数按顺序传入；`$this->value` 字段值、`$this->model` 当前记录），属性 `public $escape`、`public $border`（是否带边框盒子），在 bootstrap.php 注册。
> ⚠️ `AbstractField::$escape` 源码默认是 `false`（不转义），需要转义设为 `true`。

## 事件与其他

- `Show::resolving(fn)`（实例化时，构建闭包执行前）、`Show::composing(fn)`（`render()` 中、构建闭包之后）；签名 `(callable $callback, bool $once = false)`。`Show\Field` 同样支持 `Field::resolving()`。
- `Show` 与 `Show\Field` 均 `Macroable`；字段翻译同表格。
