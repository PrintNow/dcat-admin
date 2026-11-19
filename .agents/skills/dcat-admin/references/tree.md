# 模型树（Tree / ModelTree）

> 覆盖 `Dcat\Admin\Tree` 拖拽树组件、`Dcat\Admin\Traits\ModelTree` 模型 trait、分支显示、行操作与工具栏、排序保存原理、与表单下拉联动。相关文件：actions.md（Tree 行操作/工具类）、repository.md（`TreeRepository` 接口）。

## 表结构与模型

必需字段：父 ID、排序、标题；**父 ID 字段默认值必须是 0**（与 `defaultParentId` 一致）。模型 `use ModelTree;`，可选属性：`$parentColumn`（默认 `parent_id`）、`$orderColumn`（默认 `order`）、`$titleColumn`（默认 `title`）、`$depthColumn`（默认 `''` 不保存层级；定义后保存排序时写入层级，从 1 开始）、`$defaultParentId`（默认 `'0'`）。

- `ModelTree` 内部 `use Spatie\EloquentSortable\SortableTrait`，模型同时具备 `moveOrderUp()/moveOrderDown()/moveToStart()` 等。
- Eloquent 模型（或继承 `EloquentRepository` 的仓库）可直接用于 `Tree`；`EloquentRepository` 的 `getParentColumn()/getTitleColumn()/getOrderColumn()/saveOrder()/withQuery()/toTree()` 都转发给模型。

| ModelTree 方法 | 说明 |
|---|---|
| `getParentColumn()` / `getTitleColumn()` / `getOrderColumn()` / `getDepthColumn()` / `getDefaultParentId()` | 读属性 |
| `withQuery(?Closure $query = null)` | 回调 `function ($model) { return $model->where(...); }`，**必须 return** |
| `allNodes()` | 应用查询回调后按排序字段升序取全部节点 |
| `toTree(?array $nodes = null)` | 嵌套数组（子节点在 `children`） |
| `static selectOptions(?Closure $closure = null, $rootText = null)` | 下拉选项 `[id => '├─ 标题']`，首项 `0 => 根节点文本`（默认 `admin_trans_label('root')`）；文本自带 `├─`/`└─` 与 `&nbsp;` |
| `static saveOrder($tree = [], $parentId = 0, $depth = 1)` | 按嵌套数组保存父 ID、排序、层级 |

> ⚠️ `withQuery()` / `Tree::query()` / `selectOptions()` 的闭包必须 `return` 查询对象，否则后续链式在 `null` 上报错。

## 在控制器中使用

`index(Content $content)` 返回 `$content->title('分类')->body(Tree::make(new Category(), fn (Tree $tree) => ...))`，**同时保留 `form()`**（拖拽排序保存与新增/编辑都依赖它）：

```php
Tree::make(new Category(), function (Tree $tree) {
    $tree->maxDepth(3);
    $tree->query(fn ($model) => $model->where('type', 1));
    $tree->branch(fn ($branch) => $branch['id'].' - '.e($branch['title']));
});
```

- `Tree::make($repository, ?Closure $callback)`：`$repository` 可为 Eloquent 模型实例/类名字符串/Builder 或实现 `Dcat\Admin\Contracts\TreeRepository` 的仓库（否则抛异常）。闭包在**构造时立即执行**。
- `$branch` 为模型对象（`$branch['title']` 与 `$branch->title` 均可）；默认显示 `"{主键} - {标题}"`。
> ⚠️ `branch()` 返回值**不转义**直接输出（`{!! !!}`），用户输入字段要 `e()` / `htmlspecialchars(..., ENT_QUOTES | ENT_HTML5)`。
> ⚠️ 控制器基类是 `Dcat\Admin\Http\Controllers\AdminController`。

## Tree 方法（源码：src/Tree.php）

- `branch()`、`query()`、`maxDepth(int)`（nestable 默认 5）、`nestable(array $options)`（jquery.nestable 选项）、`expand(bool $value = true)`（`false` 初始折叠）。
- 按钮：`disableCreateButton()`/`showCreateButton()`（跳转 `{url}/create`）、`disableQuickCreateButton()`（弹窗新增）、`setDialogFormDimensions($w, $h)`（默认 `700px × 670px`）、`disableSaveButton()`、`disableRefreshButton()`；行按钮 `showEditButton()`（编辑**默认不显示**）、`disableQuickEditButton()`、`disableDeleteButton()`（这两个默认显示）。
- `actions(Closure|RowAction|array)`、`setActionClass($class)`（默认 `Tree\Actions`）、`tools(Closure|AbstractTool|array|string)`、`setResource($path)` / `resource()`（新增/编辑/删除/保存都基于它，默认当前请求路径）、`view()` / `branchView()` / `wrap(Closure)`、`getItems()`（`withQuery(query)->toTree()`）。
> ⚠️ `expand()` 和全部 `disable*()` / `show*()` **无返回值**，不要链式；其余 setter 返回 `$this`。
> ⚠️ 只有 `Tree::resolving()` 生效（构造和 `render()` 时各触发一次）；`Tree::composing()` 能注册但 Tree 从不触发。

## 行操作与工具栏

```php
$tree->actions(function (Tree\Actions $actions) {
    if ($actions->row->id > 5) { $actions->disableDelete(); }   // $this 与 $actions->row 都是当前行
    $actions->edit();                 // 开启普通编辑按钮
    $actions->append(new CopyRow());  // extends Dcat\Admin\Tree\RowAction；也可 prepend HTML
});
$tree->actions([new CopyRow(), '<span>...</span>']);  // 直接追加（每行 clone）
$tree->tools(fn (Tree\Tools $tools) => $tools->add(new Refresh()));   // 是 add()，不是 append()；或 $tree->tools(new Refresh())
```

- `Tree\Actions`：`append/prepend`、`getKey()`、`getRow()`、`parent()`、`quickEdit/disableQuickEdit(bool)`、`edit/disableEdit(bool)`、`delete/disableDelete(bool)`；公开属性 `$row`。`Tree\Tools` 只有 `add($tool)` 和 `render()`。类写法见 actions.md。

## 排序保存与删除行为

- 点"保存"时前端把 nestable 序列化结果以 `_order`（`Tree::SAVE_ORDER_NAME`）**POST 到 `$tree->resource()`**（资源路由 `store`）。
- `ModelTree` 在模型 `saving` 事件中拦截 `_order`，调用 `saveOrder()` 并中止本次保存。所以控制器必须有可用的 `form()`（使用同一 ModelTree 模型），否则保存排序失败。
- `saveOrder()` 用 `$branch['id']` 查找节点，主键名不是 `id` 需自行覆写。
- `saving` 还会阻止把父级设为自身（提示 `admin.parent_select_error`）；`deleting` 会**递归删除所有子节点**。
- 非 Eloquent 数据源需实现 `TreeRepository::saveOrder()` 等，见 repository.md。

## 与表单 select 联动

`$form->select('parent_id')->options(Category::selectOptions())->default(0)->saving(fn ($v) => (int) $v);`；带条件/自定义根文本：`Category::selectOptions(fn ($query) => $query->where('type', 1), '顶级分类')`。也可用树形字段 `$form->tree('parent_id')->nodes(...)`（`Form\Field\Tree`：`setIdColumn()`、`setTitleColumn()`、`setParentColumn()`、`rootParentId()`、`exceptParentNode()`、`treeState()`、`expand()`），见 form-fields.md。
