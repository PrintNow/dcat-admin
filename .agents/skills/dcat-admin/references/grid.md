# 数据表格 Grid

> 覆盖 `Dcat\Admin\Grid` 表格级 API：数据源、全局选项、工具栏、行/批量操作接入、行选择器、分页、导出、快捷创建、组合表头、固定列、树状表格、事件、头脚部、异步渲染。相关文件：`grid-column.md`（列与展示器）、`grid-filter.md`（筛选/快捷搜索/selector）、`actions.md`（Action 类）、`repository.md`（数据仓库、外部 API）。

## 构建与数据源

- `Grid::make($repository = null, ?Closure $builder = null)`：`$repository` 可为 Repository、Eloquent 模型实例或 Builder（如 `Movie::with('tags')->where(...)`，用于预加载/默认条件）；仓库构造参数传关联数组等同 `with`。闭包在 `render()` 时才执行。
> ⚠️ 控制器基类是 `Dcat\Admin\Http\Controllers\AdminController`（不是 `Dcat\Admin\Controllers\...`）；2.x 用 `Grid::make(..., fn)`，不要写 1.x 的 `new Grid(...)` + `return $grid`。
- `$grid->model()->where(...)->with(...)->orderBy(...)` / `->onlyTrashed()`：`Grid\Model` 记录任意 Eloquent 查询方法，取数时再 apply。
- 关联字段用点号 `$grid->column('profile.age')`；一对多/多对多得到数组，可接 Collection 方法：`->pluck('name')->label()`。
- 默认排序支持关联字段 `$grid->model()->orderBy('profile.age')`，仅 `hasOne`/`belongsTo`（join 实现，源码：src/Repositories/EloquentRepository.php `setRelationSort`）。
- 外部 API / 自定义数据：覆写仓库 `get(Grid\Model $model)`，见 `repository.md`。也可不建仓库：`$grid->model()->setData($collectionOrArray)` 或 `setData(fn (Grid\Model $model) => ...)`（闭包返回数组/Collection/Paginator，源码：src/Grid/Model.php `fetch()`）。
> ⚠️ 取快捷搜索值不要调 `$grid->quickSearch()`（无参调用会把搜索字段配置重置为 null），用 `$grid->getQuickSearch()?->value()`。`Grid\Filter\Scope::QUERY_NAME` 不存在，见 `repository.md`。

## 列的增删

`column(string $name, string $label = '')` 返回 `Grid\Column`（label 空时走字段翻译）；`columns('a', 'b')` / `columns(['a' => 'A'])` 无返回值；`number(?string $label = null)` 加从 1 开始的序号列；`prependColumn($name, $label = '')`；`dropColumn(string|Column $column)`；`$grid->foo` / `$grid->foo('Label')` 魔术加列。

> ⚠️ 字段名与 Grid 方法同名（`title`、`description`、`model`、`filter`、`header`、`footer`、`tools`、`actions`、`export`、`show`、`view`、`with`、`option`、`rows`、`number`、`resource` 等）时不能魔术调用，必须 `$grid->column('title')`。建议一律用 `column()`。

## 外观与全局选项

| 方法 | 说明 |
|---|---|
| `withBorder(bool $v = true)` | 默认关；开启后自动关闭 table_collapse |
| `tableCollapse(bool $v = true)` | 默认开（紧凑卡片式） |
| `scrollbarX()` / `addTableClass(['table-text-center'])` | 横向滚动 / 给 `<table>` 加类 |
| `toolsWithOutline(bool $v = true)` | 默认开；单个按钮加 class `disable-outline` 可排除 |
| `title($t)` / `description($d)` | 卡片标题/描述（有 title 时工具栏会显示） |
| `wrap(Closure $fn)` | 替换外层容器：`fn (Renderable $view) => Card::make($view)` |
| `view($view)` / `with(array $vars)` | 自定义视图（默认 `admin::grid.table`）及变量 |
| `show(bool $v = true)` / `option($key, $value = null)` / `setKeyName($name)` | 是否渲染 / 读写 options / 主键 |

全局默认写在 `app/Admin/bootstrap.php`：`Grid::resolving(function (Grid $grid) { $grid->tableCollapse(false); });`。

## 创建按钮与资源路径

```php
$grid->disableCreateButton();          // showCreateButton()
$grid->enableDialogCreate();           // 弹窗创建（createMode(Grid::CREATE_MODE_DIALOG)）
$grid->setDialogFormDimensions('50%', '60%'); // 默认 '700px', '670px'（也作用于快捷编辑）
$grid->setResource('auth/users');      // 创建/编辑/删除/行内编辑等按钮的路由前缀（内部调用 admin_url）
```

## 行操作

```php
$grid->disableActions();               // 以下 disable* 均有 show* 对应方法
$grid->disableViewButton(); $grid->disableEditButton(); $grid->disableDeleteButton();
$grid->showQuickEditButton();          // 快捷编辑默认关闭

$grid->actions(function (Grid\Displayers\Actions $actions) {
    // $this 与 $actions->row 是当前行模型；$actions->getKey() 主键
    if ($this->status == 1) {
        $actions->disableDelete();     // 另有 disableEdit/disableView/disableQuickEdit，delete(bool)/edit(bool)...
    }
    $actions->append(new \App\Admin\Actions\Grid\Copy());   // 也可 append/prepend HTML 字符串
});
$grid->actions([new Copy(), new Restore()]);   // 简写，每行 clone
```

- 行序号：`actions` / `display` 闭包里 `$this->_index`（从 0 开始）。
- 显示方式：配置 `admin.grid.grid_action_class`（发布的 config 默认 `DropdownActions`；未配置时为 `Actions`），单表格 `$grid->setActionClass(Grid\Displayers\Actions::class)`。可选 `Actions`（平铺）、`DropdownActions`（下拉，其 `prepend()` 等同 `append()`）、`ContextMenuActions`（右键）。调用 `fixColumns()` 时 `DropdownActions` 会自动切为 `Actions`。
- 默认按钮类可在 `admin.grid.actions.{view,edit,quick_edit,delete,batch_delete}` 替换。
- 某列显示为行操作：`$grid->column('star')->action(Star::class)`（见 `grid-column.md`）；RowAction 写法见 `actions.md`。

## 工具栏与批量操作

```php
$grid->disableToolbar(); $grid->disableRefreshButton(); $grid->disableFilterButton(); // 均有 show*
$grid->disableBatchActions();          // 隐藏批量操作下拉
$grid->disableBatchDelete();           // 仅去掉默认批量删除

// tools()：string / Renderable / AbstractTool / 数组 / 闭包
$grid->tools(function (Grid\Tools $tools) {
    $tools->append(new SendMessage());     // 也有 prepend()
    $tools->batch(fn (Grid\Tools\BatchActions $batch) => $batch->disableDelete());
});
// batchActions()：BatchAction 实例、数组或闭包
$grid->batchActions(function (Grid\Tools\BatchActions $batch) {
    $batch->add(new ReleasePost('发布', 1));   // add(BatchAction $action, ?string $key = null)
    $batch->divider();
});
```

- `BatchActions` 另有 `disableDelete(bool = true)`、`disableDeleteAndHideSelectAll()`。
- 基类：工具 `Dcat\Admin\Grid\Tools\AbstractTool`（`$style = 'btn btn-white waves-effect'`）、批量 `Dcat\Admin\Grid\BatchAction`、行 `Dcat\Admin\Grid\RowAction`，都继承 `Grid\GridAction`（`$this->parent` 为 Grid，`$this->resource()` 为列表 URL）。BatchAction 在 `handle()` 中 `$this->getKey()` 取选中 ID 数组，JS 端用 `$this->getSelectedKeysScript()`。
> ⚠️ 命名空间不是 `Grid\Tools\BatchAction`、`Grid\Tools\RowAction`、`Grid\AbstractTool`。覆写 `actionScript()` 必须返回完整 JS 函数表达式 `function (data, target, action) { ... }`（拼进 `before: ...`，源码：src/Grid/BatchAction.php）。
- 自定义批量操作容器类：配置 `admin.grid.batch_action_class` 或 `$grid->option('batch_actions_class', MyBatchActions::class)`。

## 行选择器

`$grid->disableRowSelector()` 同时禁用批量操作。`$grid->rowSelector()` 可链：`titleColumn('full_name')`（选中后显示的标题，默认取 name/title/username）、`idColumn('new_id')`（默认主键）、`style('success')`（默认 primary）、`background($color)`、`click()`（点整行选中）、`check([0, 2])`（行下标或闭包 `fn () => $this->id > 10`）、`disable(fn () => $this->status == 0)`。

## 分页 / 列选择器

- `paginate(15)`（默认 20，**无返回值不能链式**）、`perPages([10, 20, 50])`（默认 `[10,20,30,50,100,200]`）、`disablePerPages()`、`disablePagination()`、`simplePaginate()`（不查总数）、`setPaginatorClass()`（或配置 `admin.grid.paginator_class`）。
- 列选择器默认关闭：`showColumnSelector()`；`hideColumns([...])` 或列 `->hide()` 默认隐藏。状态存储配置 `admin.grid.column_selector.store`：`Grid\ColumnSelector\SessionStore`（默认）或 `CacheStore`（`store_params.driver` 默认 `file`）。

## 组合表头 / 固定列

```php
// combine(string $column, array $columnNames, ?string $label = null)：至少 2 个子列，自动开启 withBorder
$grid->combine('avgCost', ['avgMonthCost', 'avgYearCost'], '平均成本')->style('color:#1867c0')->help('说明'); // 也可 ->hide()
// fixColumns(int $head, int $tail = -1)：固定前 head 列和后 |tail| 列；返回 FixColumns
$grid->fixColumns(2, -2)->height(500);
```

## 树状表格

适合大数据量层级数据，支持分页与点击加载子节点（拖拽模型树见 `tree.md`）。模型 `use Dcat\Admin\Traits\ModelTree`（默认字段 parent_id/order/title，可用 `$parentColumn`/`$orderColumn`/`$titleColumn` 改名；`getOrderColumn()` 返回 null 可禁用排序字段）。

```php
$grid->column('title')->tree();          // tree(bool $showAll = false, bool $sortable = true, $defaultParentId = null)
$grid->column('order')->orderable();     // 模型需 implements Spatie\EloquentSortable\Sortable
```

- 默认只查顶级节点（`parent_id = 0`，或 `$defaultParentId` / 模型 `getDefaultParentId()`）；`$showAll = true` 一次加载下一级全部节点（不分页）。
- 使用 filter / 快捷搜索后取消"只查顶级"限制；列过滤、selector、scope 不会取消。

## 导出

默认不开启；默认导出器依赖 `dcat/easy-excel`，未安装时链式配置导出器或执行导出抛 `RuntimeException`。

```php
$grid->export(['id' => 'ID', 'name' => '名称']);   // 返回 Grid\Exporter；传数组同时设置导出列（顺序/数量以此为准）
$grid->export()->titles([...])
    ->filename('用户数据')               // 默认 {标题}-{Ymd-His}-{随机}
    ->csv()                              // xlsx()（默认）/ csv() / ods() / extension('xls')
    ->disableExportAll()->disableExportCurrentPage()->disableExportSelectedRow()
    ->chunkSize(2000)                    // 导出全部时分块，默认 5000
    ->rows(fn ($rows) => $rows->map(function ($row) { $row['full'] = $row['a'].$row['b']; return $row; }));
```

> ⚠️ `rows()` 回调参数是 `Illuminate\Support\Collection`（元素通常为模型），不能声明 `array $rows`，不能 `foreach ($rows as &$row)`；必须返回 Collection。

自定义导出器：必须继承 `Dcat\Admin\Grid\Exporters\AbstractExporter` 实现 `export()`（只实现 `ExporterInterface` 不够：导出时会调用 `withScope()`）。在其中用 `$this->titles()`（`['field' => 'label']`，未设置取表格列）、`$this->buildData(?int $page = null, ?int $perPage = null)`（已应用筛选/排序/导出范围，嵌套字段转点号键）、`$this->getFilename()`，自行 `response(...)->send(); exit;`。使用：`$grid->export(new CsvExporter())`，或 `Grid\Exporter::extend('csv-custom', CsvExporter::class)` 后 `$grid->export('csv-custom')`。

> ⚠️ `AbstractExporter` 没有 `getTable()` / `getData()`。范围常量 `Grid\Exporter::SCOPE_ALL / SCOPE_CURRENT_PAGE / SCOPE_SELECTED_ROWS`，当前范围在 `$this->scope`。

## 快捷创建

表格顶部内嵌一行表单，提交到当前资源的 store；**每个字段在 `form()` 中必须有同名字段**。

```php
$grid->quickCreate(function (Grid\Tools\QuickCreate $create) {
    $create->text('name', '名称');                 // 第二参数是 placeholder
    $create->select('type')->options([1 => 'A', 2 => 'B']);
    $create->action('auth/users');                 // 可选提交地址；另有 method('POST')
});
```

可用字段：`text`、`hidden`、`email`、`ip`、`url`、`password`、`mobile`、`integer`、`select`、`multipleSelect`、`tags`、`datetime`、`time`、`date`（源码：src/Grid/Tools/QuickCreate.php）。
> ⚠️ QuickCreate 没有 `selectResource`（1.x 残留），也没有 `IFrameGrid`；弹窗选表格数据请在 Form 用 `selectTable`（见 `form.md`）。

## 头部 / 脚部 / 行回调

- `header(fn ($collection) => ...)` / `footer(fn ($collection, Grid $grid) => ...)`：参数为当前页数据 Collection，返回 string / Renderable / Htmlable。
- `$grid->rows(fn (Collection $rows) => $rows->each(fn (Grid\Row $row) => ...))`：`$row->style('color:#999')` / `setAttributes([...])` 改 tr；`$row->column('name', fn ($v) => "<b>{$v}</b>")` 改单元格输出。
- 按当前筛选条件做统计：遍历 `$grid->model()->getQueries()`（每项 `['method' => ..., 'arguments' => [...]]`）重放到新查询，排除 `paginate/get/orderBy/orderByDesc`。

## 事件

- 全局（bootstrap.php）：`Grid::resolving(fn)`（构造时）、`Grid::composing(fn)`（渲染前），第二参数 `true` 表示只监听一次。
- 实例级 `$grid->listen(string $eventClass, Closure $cb)`，回调参数 `($grid, ...payload)`：`Grid\Events\Fetching`、`Fetched`（`$rows`，可 `$rows->transform(...)`）、`ApplyFilter`（`array $conditions`）、`ApplyQuickSearch`（`$keyword`）、`ApplySelector`（`array $selected`）、`Exporting`（`$scope`）。Apply* 仅在对应功能有输入时触发。

## 异步渲染 / 多表格 / 嵌入弹窗

- `$grid->async()`：工具栏以下异步加载；`isAsyncRequest()` 判断。同页多个表格时不要开 async。
- 同页多个表格必须 `$grid->setName('orders')` 设不同名称（影响分页/排序/筛选参数前缀）。
- 放进弹窗/展开行：继承 `Dcat\Admin\Grid\LazyRenderable` 实现 `public function grid(): Grid`，可 `->simple()`；配合 `Widgets\Modal`、列 `modal()/expand()`、表单 `selectTable()`（见 `widgets.md` / `form.md`）。

## 软删除（回收站）

```php
$filter->scope('trashed', '回收站')->onlyTrashed();
// actions / batchActions 中按 scope 追加恢复操作；设置了 setName 时参数名为 {name}__scope_
if (request('_scope_') == 'trashed') { $actions->append(new Restore(Post::class)); }
```

Restore 在 `handle()` 中 `$model::withTrashed()->findOrFail($this->getKey())->restore()`；自定义构造参数必须有默认值，PHP 8.4 下写 `?string $model = null`。

## 字段翻译

列标题未传 label 时调用 `admin_trans_field($name)`：读与控制器名对应的语言包（`UserProfileController` → `lang/{locale}/user-profile.php` 的 `fields`），找不到再读 `global.php`。改包名：控制器 `protected $translation = 'user1';` 或 `Admin::translation('user1')`。
