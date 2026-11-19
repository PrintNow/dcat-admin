# 数据仓库（Repository）

> 覆盖 `Repository` / `TreeRepository` 接口、`EloquentRepository`、`QueryBuilderRepository`、自定义数据源（外部 API）及 `Grid\Model` 常用方法。相关文件：show.md、tree.md、grid.md。

## 传参方式

`Grid`/`Form`/`Show`/`Tree` 传入的数据源经 `Admin::repository()` 统一转换（源码：src/Admin.php `repository()`）：实现 `Dcat\Admin\Contracts\Repository` 的实例原样使用；Eloquent 模型或 Builder → `EloquentRepository::make()`（Builder 会被 clone 复用）；类名字符串 → `new $class($args)` 后再判断。
> ⚠️ 传入非 Repository、非 Eloquent 模型/Builder 的对象抛 `InvalidArgumentException`；Tree 额外要求实现 `TreeRepository`。

## EloquentRepository

自定义仓库设 `protected $eloquentClass = Model::class;`，可覆写 `getGridColumns()` / `getFormColumns()` / `getDetailColumns()` 限制查询字段（默认 `['*']`）。

构造 `new EloquentRepository($modelOrRelations = [])`：关联数组/字符串（预加载，Grid/Form/Show/Tree 查询都会 `with()`）、模型类名（等价 `$eloquentClass`）、模型实例、Builder（每次 `clone`）。

方法（源码：src/Repositories/EloquentRepository.php）：
- `static with($relations = [])`（如 `PostRepository::with(['author'])`）、`setRelations()` / `getRelations()`、`model()`、`createModel(array $data = [])`。
- `isSoftDeletes()`：用 `class_uses` 识别 `SoftDeletes`，只看模型类自身直接 use 的，继承自父类的识别不到，需手动 `setIsSoftDeletes(true)`。
- `edit()` / `detail()` 按主键 `findOrFail`，软删除时含已删除记录；`store()` / `update()` 事务内保存并自动写关联，返回主键 / bool；`delete(Form $form, array $originalData)` 支持逗号分隔多 ID，软删除的已删除记录会强删并删除文件。
- `moveOrderUp()` / `moveOrderDown()`：模型需实现 `Spatie\EloquentSortable\Sortable`。

关联写入（store/update）：表单字段名与模型**公开**关联方法同名（或其 camelCase）时自动处理：`BelongsToMany`/`MorphToMany` → `sync()`；`HasOne`/`MorphOne`/`BelongsTo`/`MorphTo` → 更新或创建；`HasMany`/`MorphMany` → 逐条 `findOrNew` 保存，带 `_remove_ = 1` 的删除。

表格排序支持：普通字段、JSON（`meta.price` → `JSON_EXTRACT`）、`cast` 转换、`关联.字段`（仅 `HasOne`/`BelongsTo`，自动 `join`）。

## QueryBuilderRepository

无模型直接查表（`DB::table()`）。属性：`$table`（或覆写 `getTable()`）、`$connection`（可选）、`$keyName`、`$createdAtColumn`、`$updatedAtColumn`。
- 构造函数**无参数**；可覆写 `getGridColumns()` 等；`edit/detail` 查不到时 `abort(404)`。
- **不支持**：关联写入、软删除、`moveOrderUp/Down`、除 `getPrimaryKeyColumn()` 外的 TreeRepository 方法（抛 `RuntimeException('Not support.')`）。

## 自定义数据源（Repository 接口）

`Dcat\Admin\Contracts\Repository` 方法：`getKeyName()`、`getCreatedAtColumn()` / `getUpdatedAtColumn()`（无则 null）、`isSoftDeletes()`、`get(Grid\Model $model)`、`edit(Form $form)` / `detail(Show $show)`（返回 array|Arrayable）、`store(Form $form)`（新主键或 bool）、`updating(Form $form)`（更新前原始数据，删除旧文件用）、`update(Form $form)`（bool）、`deleting(Form $form)`（删除前原始数据，二维数组）、`delete(Form $form, array $deletingData)`（bool）。

推荐继承 `Dcat\Admin\Repositories\Repository`，只覆写需要的方法（未覆写的抛 `RuntimeException`）。基类提供 `protected $keyName = 'id'`、`$isSoftDeletes = false`、`setKeyName()`、`setIsSoftDeletes()`、`static make(...$params)`、`Macroable`，以及 TreeRepository 默认实现（`parent_id` / `title` / `order`）。

```php
class Setting extends Repository
{
    protected $keyName = 'key';
    public function edit(Form $form): array { return ['key' => $form->getKey(), 'value' => cache($form->getKey())]; }
    public function update(Form $form) { cache([$form->getKey() => $form->updates()['value']]); return true; } // updates() 为待保存数据
    public function deleting(Form $form): array
    {
        return array_map(fn ($id) => ['key' => $id], explode(',', $form->getKey())); // 批量删除时主键逗号分隔
    }
    public function delete(Form $form, array $deletingData) { /* 遍历 $deletingData 删除 */ return true; }
}
```

> ⚠️ 删除方法是 `delete(Form $form, array $deletingData)`，不是 `destroy()`；第二参数来自 `deleting()` 返回值。
> ⚠️ 详情主键用 `$show->getKey()`，`Show` 没有 `getId()`。

## 外部 API 表格

覆写 `get(Grid\Model $model)`：

```php
public function get(Grid\Model $model)
{
    $page = $model->getCurrentPage();                     // 未分页为 null
    $perPage = $model->getPerPage();
    [$orderColumn, $orderType] = $model->getSort();
    $scope = $model->filter()->getCurrentScopeName();     // 当前 scope
    $title = $model->filter()->input('title');            // 其它筛选值，未填为 null
    $keyword = $model->grid()->getQuickSearch()?->value(); // 快捷搜索关键字
    $data = Http::get('...', ['start' => ($page - 1) * $perPage, 'count' => $perPage])->json();
    return $model->makePaginator($data['total'] ?? 0, $data['subjects'] ?? []);
}
```

- 分页时返回 `$model->makePaginator($total, $data)`（`LengthAwarePaginator`）；不分页时返回 `array`/`Collection`，并在表格 `$grid->disablePagination()`。
> ⚠️ 表格主键由 `getKeyName()`（`$keyName`）决定，覆写 `getPrimaryKeyColumn()` 只影响 Tree，不影响 Grid。
> ⚠️ `Grid\Filter\Scope::QUERY_NAME` 常量不存在；当前 scope 用 `$model->filter()->getCurrentScopeName()`，请求参数名用 `getScopeQueryName()`。

## Grid\Model 常用方法（源码：src/Grid/Model.php）

| 方法 | 说明 |
|---|---|
| `getCurrentPage()` / `getPerPage()` | 禁用分页时返回 `null` |
| `getSort()` | `[$column, $type, $cast]`，未排序时 `[null, null, null]` |
| `filter()` | `Grid\Filter`；`->input($key = null, $default = null)` |
| `makePaginator($total, $data, ?string $url = null)` | `simplePaginate` 模式下为简单分页 |
| `grid()` / `allowPagination()` | 所属 Grid / 是否分页 |
| `getQueries()` / `findQueryByMethod($method)` | 读取 `$grid->model()->where(...)` 等累积的查询 |

`$grid->model()` 上调用的查询方法会被记录，`EloquentRepository::get()` 统一 apply；自定义仓库需自行读 `getQueries()` 处理。

## TreeRepository 接口

非 Eloquent 层级数据需同时实现 `Repository` 与 `Dcat\Admin\Contracts\TreeRepository`：`getPrimaryKeyColumn()`、`getParentColumn()`、`getTitleColumn()`、`getOrderColumn()`（可返回空）、`saveOrder($tree = [], $parentId = 0)`（`$tree` 形如 `[['id' => 1, 'children' => [['id' => 2]]]]`，参考 `ModelTree::saveOrder()`）、`withQuery($queryCallback)`（返回 `$this`）、`toTree()`（嵌套数组，子节点在 `children`）。

生成嵌套数组可用 `Dcat\Admin\Support\Helper::buildNestedArray($nodes, $parentId = 0, $primaryKeyName = 'id', $parentKeyName = 'parent_id', $childrenKeyName = 'children')`。
