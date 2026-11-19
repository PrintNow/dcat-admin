# dcat-admin 2.x 官方文档勘误

本清单记录 [dcat-admin/docs](https://github.com/dcat-admin/docs) `2.x/` 目录（最后提交 cb535380，2022-06，对应 v2.2.2-beta）与本仓库 2.4.7 源码不一致的地方。英文版 `en-2.x/` 由中文版翻译而来，以下错误同样存在。

- 核对方式：逐条对照 `src/` 源码；"源码依据"列给出路径与行号（以 2.4.7 为准，后续版本行号可能偏移）。
- 覆盖范围：文档中的全部 PHP 示例经 `tools/skill-check` 脚本扫描，叙述性内容（默认值、行为说明）为人工核对的重点页面，**不保证穷尽**。
- 正确写法已写入 AI skill：`.agents/skills/dcat-admin/`。

共 203 条，按类型统计：

| 类型 | 数量 |
|---|---|
| 描述错误 | 61 |
| 描述不全 | 46 |
| 示例过时 | 26 |
| 方法不存在 | 21 |
| 1.x 残留 | 19 |
| 方法名错误 | 9 |
| 结构问题 | 9 |
| 默认值错误 | 7 |
| 签名变化 | 5 |

## 目录

- [Grid 表格与列](#grid-表格与列)（34 条）
- [Grid 筛选、快捷搜索、规格选择器](#grid-筛选快捷搜索规格选择器)（18 条）
- [Form 表单、关联、JSON 存储](#form-表单关联json-存储)（18 条）
- [Form 字段与上传](#form-字段与上传)（14 条）
- [Show、Tree、Repository、Action、响应](#showtreerepositoryaction响应)（33 条）
- [Widgets、前端、权限、扩展、安装](#widgets前端权限扩展安装)（86 条）
- [待确认](#待确认)
- [疑似源码问题](#疑似源码问题)

## Grid 表格与列

| 原文档位置 | 错误描述 | 源码依据 | 正确写法 | 类型 |
|---|---|---|---|---|
| 2.x/model-grid.md:59 | 控制器基类命名空间错误 | src/Http/Controllers/AdminController.php:3 | `use Dcat\Admin\Http\Controllers\AdminController;` | 描述错误 |
| 2.x/model-grid-tree.md:104 | 同上，AdminController 命名空间错误 | src/Http/Controllers/AdminController.php:3 | `Dcat\Admin\Http\Controllers\AdminController` | 描述错误 |
| 2.x/model-grid-quick-create.md:164 | 同上，AdminController 命名空间错误 | src/Http/Controllers/AdminController.php:3 | `Dcat\Admin\Http\Controllers\AdminController` | 描述错误 |
| 2.x/model-grid.md:187 | 使用已在 Laravel 6+ 移除的 `str_limit()` 辅助函数 | composer.json:28（Laravel 10–13） | `Str::limit($text, 30)` 或 `->limit(30)` | 示例过时 |
| 2.x/model-grid-actions.md:54 | `return Grid(new Model(), ...)` 缺 `::make`，语法错误 | src/Grid.php:864 | `return Grid::make(new Model(), function (Grid $grid) {...});` | 示例过时 |
| 2.x/model-grid-column.md:39 | 称默认排序支持“一对一以及一对多”关联；实际只对 hasOne/belongsTo 做 join | src/Repositories/EloquentRepository.php:322-340 (`joinParameters`) | 仅 `hasOne` / `belongsTo`，不支持多层 | 描述错误 |
| 2.x/model-grid-column.md:173 | `Column::macro` 闭包首参写成 `$value`；Macroable 调用只传调用参数，`$value` 实际会收到 `$p1` | src/Grid/Column.php:782-789（`__macroCall`） | `Column::macro('myHeader', function ($p1, $p2 = null) {...})`；需要当前值用 `Column::extend` | 描述错误 |
| 2.x/model-grid-column-display.md:850-856 | `$grid->title()->title()`：`title()` 是 Grid 方法（设置表格标题），会被拦截且需参数 | src/Grid.php:931 | `$grid->column('title')->title()` | 示例过时 |
| 2.x/model-grid-column-display.md:1014,1020 | `Column::define()` 不存在 | src/Grid/Column.php:215（仅有 `extend`） | `Column::extend('name', $closureOrClass)` 后显式调用 | 方法不存在 |
| 2.x/model-grid-column-display.md:394 | modal 描述照抄 expand（“显示在表格下一行”），实际是弹窗 | src/Grid/Displayers/Modal.php（`WidgetModal::make()`） | 点击后以弹窗显示内容 | 描述错误 |
| 2.x/model-grid-column-display.md:823 | 行操作示例用 1.x 写法 `$grid = new Grid(new $this->model()); ... return $grid;` | src/Grid.php:864 | `return Grid::make(new Model(), function (Grid $grid) {...});` | 1.x 残留 |
| 2.x/model-grid-editable.md:30-31 | `switch(Admin::color()->info())` / `switch('#333')`：SwitchDisplay 用 `Admin::color()->get($color)` 解析，hex 无匹配返回 null，回退 primary | src/Grid/Displayers/SwitchDisplay.php（`color()`）；src/Color.php:239-254 | 只传内置颜色名：`switch('info')` | 描述错误 |
| 2.x/model-grid-editable.md:103 | `switchGroup([...], true)`：第 2 参是 `$color`，`true` 不会开启刷新 | src/Grid/Displayers/SwitchGroup.php（`display($columns, $color, $refresh)`） | `switchGroup([...], '', true)` | 签名变化 |
| 2.x/model-grid-editable.md:143,163 | 称 radio/checkbox 支持闭包参数；实现中 `$options['options'] = $options` 对闭包会报错 | src/Grid/Displayers/Radio.php、Checkbox.php（`display()`） | 仅 `select()` 支持闭包；radio/checkbox 传数组 | 描述错误 |
| 2.x/model-grid-export.md:67-73 | `rows(function (array $rows) { foreach ($rows as &$row) ... })`：回调实际收到 Collection（类型声明报 TypeError，且对象不能按引用 foreach），且必须返回 Collection | src/Grid/Exporters/AbstractExporter.php（`callBuilder(Collection &$data)`、`normalize(Collection $data)`） | `->rows(fn ($rows) => $rows->map(function ($row) { ...; return $row; }))` | 签名变化 |
| 2.x/model-grid-export.md:92-127 | 使用 maatwebsite/excel ~2.1 的 `Excel::create()` API，Laravel 10+ 不可用 | composer.json:28 | 按当前所用 Excel 库实现 `AbstractExporter::export()`，取数用 `buildData()` | 示例过时 |
| 2.x/model-grid-exporter.md:16,18 | `$this->getTable()` / `$this->getData()` 不存在 | src/Grid/Exporters/AbstractExporter.php（仅 `buildData()`/`titles()`/`getFilename()`） | `$this->getFilename()`、`$this->buildData()`、`$this->titles()` | 方法不存在 |
| 2.x/model-grid-exporter.md:3 | 称“默认支持导出 csv”；实际默认导出器是 ExcelExporter（依赖 dcat/easy-excel，默认 xlsx） | src/Grid/Exporter.php（`makeDefaultDriver`）；src/Grid/Exporters/ExcelExporter.php | 默认 easy-excel / xlsx | 描述错误 |
| 2.x/model-grid-data.md:155 | 覆写 `getPrimaryKeyColumn()` 设主键无效：Grid 读取 `getKeyName()`，而 `getPrimaryKeyColumn()` 只是代理 `getKeyName()` | src/Grid.php:211-213；src/Repositories/Repository.php:21,33,180 | `protected $keyName = '_id';` 或覆写 `getKeyName()` | 描述错误 |
| 2.x/model-grid-data.md:177,238 | `Grid\Filter\Scope::QUERY_NAME` 常量不存在 | src/Grid/Filter/Scope.php（无常量）；src/Grid/Filter.php:560-597 | `$model->filter()->getCurrentScopeName()` | 方法不存在 |
| 2.x/model-grid-data.md:174,228 | `getSort()` 实际返回 3 元素 `[column, type, cast]`（无排序时 `[null, null, null]`） | src/Grid/Model.php:587-598 | `[$column, $type, $cast] = $model->getSort();` | 描述不全 |
| 2.x/model-grid-data.md:252 | `$model->grid()->quickSearch()->value()`：无参调用 `quickSearch()` 会把搜索字段配置重置为 null | src/Grid/Concerns/HasQuickSearch.php（`quickSearch()` 首行赋值 `$this->search`） | `$model->grid()->getQuickSearch()?->value()` | 描述错误 |
| 2.x/model-grid-quick-create.md:116-191 | QuickCreate 没有 `selectResource`，`Dcat\Admin\IFrameGrid` 类不存在 | src/Grid/Tools/QuickCreate.php:61-217（字段列表） | 删除；弹窗选择用 Form `selectTable` + `Grid\LazyRenderable` | 1.x 残留 |
| 2.x/model-grid-softdelete.md:36 | `$grid->filter(function () { $filter->scope(...) })` 闭包缺 `$filter` 参数 | src/Grid/Concerns/HasFilter.php（`$callback($this->filter)`） | `$grid->filter(function (Grid\Filter $filter) {...})` | 示例过时 |
| 2.x/model-grid-softdelete.md:71,132 | `string $model = null` 隐式可空参数在 PHP 8.4 弃用 | composer.json:27（PHP>=8.1，支持 8.4） | `?string $model = null` | 示例过时 |
| 2.x/action-grid.md:20,41,70,94,133,158 | `class X extend Y` 关键字错误、`use` 语句缺分号 | — | `class X extends Y` | 示例过时 |
| 2.x/action-grid.md:68 | `Dcat\Admin\Grid\AbstractTool` 不存在 | src/Grid/Tools/AbstractTool.php | `Dcat\Admin\Grid\Tools\AbstractTool` | 描述错误 |
| 2.x/action-grid.md:81 | `Dcat\Admin\Grid\Tools\BatchAction` 不存在 | src/Grid/BatchAction.php | `Dcat\Admin\Grid\BatchAction` | 描述错误 |
| 2.x/action-grid.md:121 | `Dcat\Admin\Grid\Tools\RowAction` 不存在 | src/Grid/RowAction.php | `Dcat\Admin\Grid\RowAction` | 描述错误 |
| 2.x/action-grid.md:99-111 | `actionScript()` 示例返回裸语句；源码将其拼进 `before: {...}`，必须是函数表达式，否则 JS 语法错误 | src/Actions/HasActionHandler.php:113；src/Grid/BatchAction.php:12-27 | `return "function (data, target, action) { var key = {$this->getSelectedKeysScript()}; ... action.options.key = key; }";` | 示例过时 |
| 2.x/model-grid-events.md:（全文） | 未记录 `Grid\Events\Exporting` 事件（导出前触发，payload 为导出范围） | src/Grid/Events/Exporting.php；src/Grid/Concerns/HasExporter.php（`fire(new Exporting([$scope]))`） | `$grid->listen(Grid\Events\Exporting::class, fn ($grid, $scope) => ...)` | 描述不全 |
| 2.x/model-grid-column-display.md:（全文） | 未记录 `viewable()`、`highlightjs()`、`downloadable()`、`button()`、`table()`、`bold()`、`long2ip()`、`gravatar()`、`displayUsing()`、`textarea()`、`input()` 选项（`mask`） | src/Grid/Column.php:18-53,75-101；src/Grid/Column/HasDisplayers.php | 见 references/grid-column.md 展示器速查 | 描述不全 |
| 2.x/model-grid-tree.md:136-139 | `Column::tree()` 漏第 3 参 `$defaultParentId` | src/Grid/Column/HasDisplayers.php（`tree(bool $showAll = false, bool $sortable = true, $defaultParentId = null)`） | 补充第 3 参 | 描述不全 |
| 2.x/model-grid.md:496-501 | 未说明 `paginate()` 无返回值（不可链式） | src/Grid/Concerns/HasPaginator.php（`paginate()` 无 return） | 单独一行调用 | 描述不全 |

## Grid 筛选、快捷搜索、规格选择器

| 原文档位置 | 错误描述 | 源码依据 | 正确写法 | 类型 |
|---|---|---|---|---|
| 2.x/model-grid-filters.md:534-537 | `$filter->equal('column')->datetime($options)->toTimestamp()` 不存在：`datetime()` 返回 DateTime Presenter，无 `toTimestamp`，经 AbstractFilter::__call 抛 RuntimeException | src/Grid/Filter/Presenter/DateTime.php:1-48；src/Grid/Filter/Between.php:30 | 时间戳只能用 `$filter->between('col')->datetime()->toTimestamp()`（列筛选 `Grid\Column\Filter\Between::make()->datetime()->toTimestamp()`）；单值请用 `where()` 自行 `strtotime($this->input)` | 方法不存在 |
| 2.x/model-grid-filters.md:526-527 | 称 `month()` 相当于 `datetime(['format' => 'MM'])`，实际是 `YYYY-MM` | src/Grid/Filter/AbstractFilter.php:411-414 | `month()` = `datetime(['format' => 'YYYY-MM'])` | 描述错误 |
| 2.x/model-grid-filters.md:199-204 | `$filter->month('column')` 未说明默认选择器格式为 `YYYY-MM`，与 `whereMonth` 不匹配（Laravel `sprintf('%02d','2024-05')` 得 `2024`），默认用法查不到数据 | src/Grid/Filter/Month.php:5-16；src/Grid/Filter/Date.php:20-25；src/Grid/Filter/AbstractFilter.php:411-414 | `$filter->month('created_at')->datetime(['format' => 'MM'])` | 描述不全 |
| 2.x/model-grid-filters.md:546 | 称 width 默认值是 3 | src/Grid/Filter/AbstractFilter.php:91；src/Grid/Filter/Between.php:18；src/Grid/Filter/In.php:17 | 默认 10；`between`/`whereBetween`/`in`/`notIn` 为 12；支持 `.5` 小数 | 默认值错误 |
| 2.x/model-grid-filters.md:414 | selectTable `dialogWidth` 注释“默认 800px” | src/Widgets/DialogTable.php:26 | 默认 `825px` | 默认值错误 |
| 2.x/model-grid-filters.md:483 | 表格筛选里的 multipleSelectTable 示例使用 `UserTable::make(['id' => $form->getKey()])`，grid filter 上下文没有 `$form` | src/Grid/Filter/AbstractFilter.php:342-345 | `UserTable::make([...自定义参数])` | 示例过时 |
| 2.x/model-grid-filters.md:265-273 | whereBetween 示例闭包 `use ($start)` 却使用 `$end`，运行时 `$end` 未定义 | src/Grid/Filter/WhereBetween.php:48 | `function ($q) use ($start, $end)` | 描述错误 |
| 2.x/model-grid-filters.md:298-322 | group 方法列表未给签名；`$group->where()` 实际为 `where(string $label, Closure $builder)`，label 必填且在前 | src/Grid/Filter/Group.php:169 | `$group->where('自定义', function ($query) { ... $this->input ... })` | 描述不全 |
| 2.x/model-grid-filters.md:662-663 | 自定义过滤器示例 `$this->parent->getGrid()->getName()`：Filter 无 `getGrid()` 方法 | src/Grid/Filter.php:320-323；src/Grid/Filter/Between.php（formatId） | `$this->parent->grid()->makeName('filter-column-')` | 方法不存在 |
| 2.x/model-grid-filters.md:728 | 自定义过滤器示例 `DateTime::collectAssets()` 不存在 | src/Grid/Filter/Presenter/Presenter.php:115 | `DateTime::requireAssets()` | 方法不存在 |
| 2.x/model-grid-filters.md:726,735-756 | 示例视图名 `admin::filter.betweenDatetime` 与 `setupDatetime()` + `Admin::script()` 写法已过时 | src/Grid/Filter/Between.php:121-133；resources/views/filter/between-datetime.blade.php | 视图 `admin::filter.between-datetime`，`datetime()` 内 `return $this->addVariables(['dateOptions' => $options])`，脚本写在模板里 | 示例过时 |
| 2.x/model-grid-filters.md:761-783 | 模板使用 Bootstrap 3 的 `input-group-addon`/`fa fa-calendar`，与当前模板不一致 | resources/views/filter/between.blade.php；between-datetime.blade.php | 使用 `input-group-prepend` + `input-group-text`、`feather icon-calendar` | 示例过时 |
| 2.x/model-grid-filters.md:645-647 | 自定义过滤器示例把类放在 `Dcat\Admin\Grid\Filter` 命名空间（即覆盖 vendor 内置 Between），对用户误导 | src/Grid/Filter.php:799-802 | 放在 `App\Admin\Filters`，再 `Filter::extend('name', Class::class)` | 描述错误 |
| 2.x/model-grid-filters.md:363-538 | 表单类型缺少已存在的 `radio()`、`checkbox()`（含 `stacked()`/`showLabel()`）、Select 的 `ajax()`/`model()`/`load()`/`loads()`/`config()`/闭包与枚举选项、`MultipleSelect::loadMore()`、SelectTable `pluck()`/`placeholder()`；筛选类型缺 `hidden()`/`newline()`；容器缺 `disableResetButton()`/`withBorder()`/`noPadding()`/`mode()`/`setAction()` | src/Grid/Filter/AbstractFilter.php:315-363；src/Grid/Filter/Presenter/Select.php；Radio.php；src/Grid/Filter.php:89-113,248-395 | 见 references/grid-filter.md 第 1、3 节 | 描述不全 |
| 2.x/model-grid-filters.md:158-169 | between 未说明 `toTimestamp()`、`date()`，以及只填一端时自动转为 `>=`/`<=` | src/Grid/Filter/Between.php:30,80-115 | `$filter->between('created_at')->date()->toTimestamp()` | 描述不全 |
| 2.x/model-grid-quick-search.md:71-76 | 称 `title:!foo` 生成 `!=` 查询；实际正则只识别 `!=`，`!foo` 会被当作 `where title = '!foo'` | src/Grid/Concerns/HasQuickSearch.php:180-183 | `title:!=foo` | 描述错误 |
| 2.x/model-grid-quick-search.md:1-187 | 未说明语法搜索的字段必须是已定义的表格列名/label（否则忽略，全部无效时返回空结果），未提及 `width(int $rem)` | src/Grid/Concerns/HasQuickSearch.php:141-145,194-224；src/Grid/Tools/QuickSearch.php:50 | 见 references/grid-filter.md 第 7 节 | 描述不全 |
| 2.x/model-grid-column-filter.md:167-192 | 列筛选 Between 未提及 `toTimestamp()`；未说明 `Between::datetime()` 无参数而 `Equal::datetime(string $format)` 有参数 | src/Grid/Column/Filter/Between.php:32,64；src/Grid/Column/Filter/Equal.php:56 | `Grid\Column\Filter\Between::make()->datetime()->toTimestamp()` | 描述不全 |

## Form 表单、关联、JSON 存储

| 原文档位置 | 错误描述 | 源码依据 | 正确写法 | 类型 |
|---|---|---|---|---|
| 2.x/model-form.md:253-254 | `$form->removeIgnoredFields(['column1'])` 被描述为"取消已忽略的字段"；实际该方法是从传入的输入数组中剔除已忽略字段并返回，不改变忽略列表 | src/Form.php:726-731 | 没有可用的按字段名取消忽略 API（`forgetIgnored()` 按数组下标删除，见 src/Form.php:1068-1073，`$ignored` 为 flatten 后的列表），应在条件分支中决定是否 `ignore()` | 描述错误 |
| 2.x/model-form.md:408, 427 | `$form->mutipleFile('files')` 拼写错误 | src/Form.php:159 | `$form->multipleFile('files')` | 方法名错误 |
| 2.x/model-form-callback.md:89 | `return $form->error('数据保存失败');` —— Form 没有 `error()`，会被 `__call` 当作字段类型并报 "Field type [error] does not exist" | src/Form.php:1803-1821 | `return $form->response()->error('数据保存失败');` | 方法不存在 |
| 2.x/model-form-callback.md:98-104 | `$form->repository()->eloquent()` 不存在 | src/Repositories/EloquentRepository.php:724 | `$form->repository()->model()` | 方法不存在 |
| 2.x/model-form-callback.md:75-77 | 称新增时 `$result` 为"自增ID"；实际是 `$model->getKey()`（任意主键，如 UUID） | src/Repositories/EloquentRepository.php:465; src/Form.php:636 | `$result` / `$form->getKey()` 为新记录主键值 | 描述不全 |
| 2.x/model-form-callback.md:1-185 | 事件列表缺少 `fileDeleting` / `fileDeleted`；也未说明 `submitted/saving` 同样会被上传、删除文件、行内编辑、排序请求触发（输入只含部分字段） | src/Form/Concerns/HasEvents.php:141-159; src/Form.php:675-701, 849-892 | 补充 `$form->fileDeleting(fn (Form $form, $field) => ...)`；回调中先判断输入是否存在 | 描述不全 |
| 2.x/model-form-data.md:120-134 | 自定义 Repository 删除方法写作 `destroy(Form $form, $deletingData)`，实际接口方法为 `delete` | src/Contracts/Repository.php:103; src/Form.php:564 | `public function delete(Form $form, array $deletingData)` | 方法名错误 |
| 2.x/model-form-modal.md:62 | `use Dcat\Admin\Controllers\HasResourceActions;` 命名空间错误 | src/Http/Controllers/HasResourceActions.php:3 | `use Dcat\Admin\Http\Controllers\HasResourceActions;` | 描述错误 |
| 2.x/model-form-modal.md:112 | `->prepareForSave(function ($value) {...})` 方法不存在 | src/Form/Field.php:840（仅有 `saving`） | `->saving(function ($value) { return $value; })` | 方法不存在 |
| 2.x/model-form-modal.md:35-36 | 弹窗默认宽高写作 720px / 690px | src/Widgets/DialogForm.php:19 | 默认 `['700px', '670px']` | 默认值错误 |
| 2.x/model-form-layout.md:132, 137 | `tab` 内 `$form->column(6, function (Form\BlockForm $form) {...})`：`Form::column` 回调传入的是 `Form` 本身，类型提示 `BlockForm` 会 TypeError | src/Form.php:1675-1682 | `function (Form $form)`；仅 `block()` 回调内才是 `Form\BlockForm` | 描述错误 |
| 2.x/model-form-layout.md:145-158 | 在添加 tab 之前调用 `$form->getTab()->active('标题2')`；`active()` 只对已存在的 tab 生效，示例写法无效 | src/Form/Tab.php:127-136, 58-70 | 在所有 `tab()` 之后调用，或 `$form->tab('标题2', fn, true)` 第三参数 `$active` | 描述错误 |
| 2.x/model-form-validation.md:147 | `maxLength` 示例误写成 `minLength(50, '不能超过50个字符')` | src/Form/Field/Text.php:114 | `$form->text('title')->maxLength(50, '不能超过50个字符');` | 描述错误 |
| 2.x/model-form-validation.md:3-48 | 未说明：编辑时规则中 `{{id}}` 会替换为当前主键；`validator()` 自定义验证器；`removeRule/hasRule`；只有提交数据中存在的字段才会被验证；`minLength/maxLength/same` 同时追加后端规则 | src/Form/Concerns/HasFieldValidator.php:148, 339-388; src/Form/Field/Text.php:67-129 | 见 references/form.md「验证」 | 描述不全 |
| 2.x/model-form.md:196-437 | 常用方法缺少 `if()/then()/else()` 条件构建、`ajax()`、`validationErrorToastr()`、`inDialog()`、`resolvingField()`、`title()`、`setResource()` | src/Form.php:433-459, 1174-1177, 1698-1707; src/Form/Condition.php; src/Form/ResolveField.php | 见 references/form.md | 描述不全 |
| 2.x/model-json.md:41 | `->saving(funtion ($v) {` 拼写错误，语法错误 | — | `->saving(function ($v) {` 或直接 `->saveAsJson()` | 描述错误 |
| 2.x/model-relationship.md:58-66 | Repository 示例 `use User as UserModel;` 导入错误（且同时 `use EloquentRepository` 又写全限定名） | — | `use App\Admin\Models\User as UserModel;` + `class User extends EloquentRepository` | 示例过时 |
| 2.x/model-form-fields.md:1332-1430（hasMany） | 未说明 hasMany 子模型保存使用 `fill()`，子模型须配置 `$fillable`；未列出 `useTab()`、`disableCreate()`、`disableDelete()`、`setRelationKeyName()` | src/Repositories/EloquentRepository.php:914（`$instance->fill($related)`）; src/Form/Field/HasMany.php:407-500 | 见 references/form-relations.md | 描述不全 |

## Form 字段与上传

| 原文档位置 | 错误描述 | 源码依据 | 正确写法 | 类型 |
|---|---|---|---|---|
| 2.x/model-form-fields.md:189, 350 | `options(Model::class, 'name', 'id')` 参数顺序反了；字符串 Model 类名会转发到 `model($model, $idField, $textField)`，按示例会把 `name` 当 id 列 | src/Form/Field/Select.php `options()`（`return $this->model(...func_get_args())`）；src/Traits/HasModelOptions.php:16 | `options(User::class, 'id', 'name')` 或 `->model(User::class, 'id', 'name')` | 描述错误 |
| 2.x/model-form-fields.md:140 | `$form->text('nickname')->saveAsJson()` 无效果：`saveAsJson` 对 `null`/标量原样返回，只编码数组/对象 | src/Form/Field.php:1265-1274 | 用于数组值字段，如 `$form->checkbox('x')->saveAsJson()` | 描述错误 |
| 2.x/model-form-fields.md:445 | selectTable 弹窗默认宽度写成 800px | src/Widgets/DialogTable.php:26 (`$width = '825px'`) | 默认 `825px` | 默认值错误 |
| 2.x/model-form-fields.md:789-790 | `removable()` 注释为“添加文件删除按钮”；实际默认就可删除，`removable(false)` 才是“只在前端移除、不请求服务器删除” | src/Form/Field/WebUploader.php:125-130；resources/assets/dcat/extra/Upload/AddUploadedFile.js:83 | `->removable(false)` 禁止从服务器删除 | 描述错误 |
| 2.x/model-form-fields.md:1095-1097 | 地图配置写为 `map_provider`，仅列 tencent/google/yandex；漏掉默认 `baidu` 与 `amap`，以及 `admin.map.provider`/`admin.map.keys` 配置；且 `config/admin.php` 默认没有 map 段需自行添加 | src/Form/Field/Map.php:28-62, `getUsingMap()` | `config('admin.map.provider')`（兼容 `admin.map_provider`），可选 baidu(默认)/amap/tencent/google/yandex；key 读 `admin.map.keys.*` 或 env `BAIDU_MAP_API_KEY`/`AMAP_API_KEY`/`TENCENT_MAP_API_KEY`/`GOOGLE_API_KEY` | 描述不全 |
| 2.x/model-form-fields.md:1124-1126 | display `with()` 示例 PHP 字符串引号语法错误；且 `with()` 无返回值不可继续链式 | src/Form/Field/Display.php `with()`（无 return） | `->with(fn ($v) => "<img src=\"{$v}\" />")`，放在链尾 | 描述错误 |
| 2.x/model-form-fields.md:920 | `editor()->height('600')` 传字符串；签名为 `height(int $height)`，调用方开启 strict_types 时 TypeError | src/Form/Field/Editor.php `height(int $height)` | `->height(600)` | 签名变化 |
| 2.x/model-form-fields.md:全文 | 已注册字段未文档化：button、captcha、decimal、id、month、year、tel、timezone、keyValue、list | src/Form.php:119-174 | 见 references/form-fields.md | 描述不全 |
| 2.x/model-form-fields.md:741-759 | range 类字段未说明改格式方式；DateRange/DatetimeRange/TimeRange 没有 `format()` 方法 | src/Form/Field/DateRange.php（构造中 `options(['format' => ...])`，无 format 方法） | `->options(['format' => 'YYYY/MM/DD'])` | 描述不全 |
| 2.x/model-form-upload.md:396, 402 | `on()` 示例 heredoc 结束标记写成 `<<<JS`，PHP 语法错误 | — | 结束标记为 `JS`（或使用 `<<<'JS' ... JS`） | 描述错误 |
| 2.x/model-form-upload.md:507 | `dimensions(['min_width' = 100, ...])` 数组语法错误 | src/Form/Field/Image.php `dimensions(array $options)` | `['min_width' => 100, 'max_width' => 300]` | 描述错误 |
| 2.x/model-form-upload.md:108-470 | 未记录 `sequenceName()`、`downloadable()`、`withFormData()`、`withDeleteData()`、`mimeTypes()`、`once()`、`ratio()` | src/Form/Field/UploadField.php:300；WebUploader.php:37,151,164,203；File.php `once()`；Image.php `ratio()` | 见 references/form-fields-upload.md | 描述不全 |
| 2.x/model-form-upload.md:118-130 | `Resizable::thumbnail()` 返回的是缩略图相对路径而非可访问 URL，文档未说明 | src/Traits/Resizable.php:16-48 | `Storage::disk(...)->url($photo->thumbnail('small', 'photo'))` | 描述不全 |
| 2.x/model-form-field-management.md:10 | 称内置 editor “基于 ckeditor”；2.x 内置为 TinyMCE | src/Form/Field/Editor.php（TinyMCE 配置项 plugins/toolbar/images_upload_url） | 内置 editor 为 TinyMCE | 描述错误 |

## Show、Tree、Repository、Action、响应

| 原文档位置 | 错误描述 | 源码依据 | 正确写法 | 类型 |
|---|---|---|---|---|
| 2.x/model-show.md:65 | 称字段宽度“默认值为 3” | src/Show/Field.php:95, 139 | 默认 `['field' => 8, 'label' => 2]`；签名 `width(int $field, int $label = 2)` | 默认值错误 |
| 2.x/model-show.md:73-77 | 称 `panel()->style('danger')` 可改面板样式 | src/Show/Panel.php:59,95；resources/views/show/panel.blade.php（未使用 `$style`） | `style()` 只写入未被视图使用的变量，无可见效果；如需样式请用 `$show->wrap()` / `$show->view()` | 描述错误 |
| 2.x/model-show-field.md:120-131 | `append` 小节的闭包示例写成了 `prepend(...)` | src/Show/Field.php:453 | `$show->email->append(function ($value, $original) { ... })` | 示例过时 |
| 2.x/model-show-field.md:207 | 称 `filesize()` 显示为 `190.56 MB` | src/Show/Field.php:771；src/Support/helpers.php:583 | 默认 `$dec = 0` 且无空格，显示 `191MB`；需 `filesize(2)` 得到 `190.56MB` | 描述错误 |
| 2.x/model-show-extend.md:65-70 | 称 `AbstractField::$escape` 默认 `true`（会转义） | src/Show/AbstractField.php:36 | 基类默认 `public $escape = false`（不转义）；需要转义时子类显式设为 `true` | 默认值错误 |
| 2.x/model-show-relation.md:66, 87 | `$show->resource('/users')` 设置资源路径 | src/Show.php:567（无参 getter）、587 | `$show->setResource('users')` | 方法名错误 |
| 2.x/model-show-relation.md:180, 281 | `$grid->resource('comments')` 设置资源路径 | src/Grid.php:853（无参 getter）、957 | `$grid->setResource('comments')` | 方法名错误 |
| 2.x/model-repository.md:211-218 | `getUpdatedAtColumn` 小节示例方法名写成 `getCreatedAtColumn` | src/Contracts/Repository.php:39 | `public function getUpdatedAtColumn() { return 'updated_at'; }` | 描述错误 |
| 2.x/model-repository.md:296 | `$show->getId()` 获取主键 | src/Show.php:204 | `$show->getKey()` | 方法不存在 |
| 2.x/model-repository.md:322 | 代码块语言标记 ```` ```phpjie ```` | — | ```` ```php ```` | 结构问题 |
| 2.x/model-repository.md:369-373 | 删除接口写成 `destroy(Form $form, array $deletingData)` | src/Contracts/Repository.php:103 | `delete(Form $form, array $deletingData)` | 方法名错误 |
| 2.x/model-repository.md:378 | 称 `$deletingData` 来自 `getDataWhenDeleting` 接口 | src/Contracts/Repository.php:111 | 来自 `deleting(Form $form)` 的返回值 | 1.x 残留 |
| 2.x/model-repository.md:245；model-grid-data.md:177, 238 | 使用 `Grid\Filter\Scope::QUERY_NAME` 取 scope 值 | src/Grid/Filter/Scope.php（无此常量）；src/Grid/Filter.php:560, 594 | `$model->filter()->getCurrentScopeName()`；参数名 `getScopeQueryName()` | 方法不存在 |
| 2.x/model-repository.md:559-613 | `QueryBuilderRepository` 示例未提 `$table` / `$connection` 属性，且必须覆写 `getTable()` | src/Repositories/QueryBuilderRepository.php:20-25, 63 | 可直接 `protected $table = 'xxx'; protected $connection = 'xxx';` | 描述不全 |
| 2.x/model-grid-data.md:155-158 | 外部 API 示例用 `getPrimaryKeyColumn()` 定义表格主键 | src/Grid.php:210-212；src/Repositories/Repository.php:33, 180 | 表格主键取 `getKeyName()`，应 `protected $keyName = '_id';`（`getPrimaryKeyColumn` 仅 Tree 使用） | 描述错误 |
| 2.x/model-grid-data.md:220-229 | 称 `getSort()` 返回 `[$column, $type]` | src/Grid/Model.php:587-598 | 返回 `[$column, $type, $cast]`，未排序为 `[null, null, null]` | 描述不全 |
| 2.x/model-tree.md:85 | `use Dcat\Admin\Controllers\AdminController;` | src/Http/Controllers/AdminController.php:3 | `use Dcat\Admin\Http\Controllers\AdminController;` | 1.x 残留 |
| 2.x/model-tree.md:42-72, 113-157 | 未说明 `defaultParentId` 属性、`selectOptions()`、`expand/disable*Button` 等常用 API，及排序保存依赖控制器 `store()` | src/Traits/ModelTree.php:77, 289, 351-376；src/Tree.php:269-378 | 见 references/tree.md | 描述不全 |
| 2.x/action.md:23-32 | `admin:action` 类型列表为 7 项（`[6] tree-tool`） | src/Console/ActionCommand.php:74-86 | 8 项：`[6] tree-row`、`[7] tree-tool`（action-form.md/action-show.md 同列表也缺 tree-row） | 示例过时 |
| 2.x/action.md:233 | 属性 `selectorPrefix`（默认 `.admin-action-`） | src/Actions/Action.php:34, 149 | 属性为 `$selector`，默认随机 `.act-xxxxxxxx`（`makeSelector()`） | 1.x 残留 |
| 2.x/action.md:237 | 属性 `usingHandler` | src/Actions/Action.php:54 | `protected $allowHandler = true;` | 方法名错误 |
| 2.x/action.md:441 | `$this->key()` 获取主键 | src/Actions/Action.php:99 | `$this->getKey()` | 方法不存在 |
| 2.x/action.md:452 | 外部调用 `MyAction::make()->getElementClass()` | src/Actions/Action.php:120（`protected`） | 外部用 `selector()`（含 `.`）；`getElementClass()` 仅子类内可用 | 签名变化 |
| 2.x/action.md:363-372 | 未说明 `authorize()` 仅在请求 handle 时校验，不隐藏按钮 | src/Http/Controllers/HandleActionController.php:22；src/Actions/Action.php:204-219 | 隐藏按钮需 `->disable(! Admin::user()->can(...))` | 描述不全 |
| 2.x/action-grid.md:18-20, 39-41, 68-70, 92-94, 131-133, 156-158 | `use ... ` 缺分号、`extend` 应为 `extends` | — | `use Dcat\Admin\Grid\RowAction;` / `class X extends RowAction` | 示例过时 |
| 2.x/action-grid.md:68 | `use Dcat\Admin\Grid\AbstractTool` | src/Grid/Tools/AbstractTool.php:3 | `Dcat\Admin\Grid\Tools\AbstractTool` | 描述错误 |
| 2.x/action-grid.md:81, 92 | 称批量基类为 `Dcat\Admin\Grid\Tools\BatchAction` | src/Grid/BatchAction.php:3 | `Dcat\Admin\Grid\BatchAction` | 描述错误 |
| 2.x/action-grid.md:121 | 称行操作基类为 `Dcat\Admin\Grid\Tools\RowAction` | src/Grid/RowAction.php:3 | `Dcat\Admin\Grid\RowAction` | 描述错误 |
| 2.x/action-grid.md:99-111 | `actionScript()` 返回裸 JS 片段并 `Object.assign(data, {_key:key})` | src/Grid/BatchAction.php:12-29；src/Actions/HasActionHandler.php:113 | 必须返回 `function (data, target, action) {...}`；设置 `action.options.key = key`，未选中时 `return false` | 示例过时 |
| 2.x/response.md:16 | `Admin::make()->success(...)` | src/Admin.php:434 | `Admin::json()->success(...)` | 方法不存在 |
| 2.x/response.md:127-133 | `script()` 示例中使用未定义变量 `target` | resources/assets/dcat/js/extensions/Ajax.js:122-146 | eval 作用域内可用 `response`、`data`、`options.target` | 描述错误 |
| 2.x/response.md:141-147 | 条件调用写成 `ifRefresh()` / `ifLocation()` | src/Http/JsonResponse.php:388-401（`*If` 后缀） | `refreshIf($cond)` / `locationIf($cond, 'auth/users')` | 方法名错误 |
| 2.x/response.md:29-133 | 缺少 `info/message/timeout/data/options/statusCode/withValidation/withException/with/redirectToIntended/locationToIntended`；未说明 redirect/location/refresh/download/script 共用一个 `then`（只最后一个生效） | src/Http/JsonResponse.php:93-386 | 见 references/actions.md「JsonResponse 响应方法」 | 描述不全 |

## Widgets、前端、权限、扩展、安装

| 原文档位置 | 错误描述 | 源码依据 | 正确写法 | 类型 |
|---|---|---|---|---|
| 2.x/widgets-checkbox.md:85 | `$radio->small()` 不存在；`__call` 无参时返回 HTML 属性值（null），链式断裂 | src/Widgets/Radio.php:47；src/Widgets/Widget.php:304 | `$radio->size('sm')` | 方法不存在 |
| 2.x/widgets-checkbox.md:90 | `$radio->large()` 不存在 | src/Widgets/Radio.php:47 | `$radio->size('lg')` | 方法不存在 |
| 2.x/widgets-checkbox.md:76-78 | "单选框支持3种尺寸"但只列 2 种，且 style 支持值未全列 | src/Widgets/Radio.php:47；resources/views/widgets/radio.blade.php | `size('sm' | 'lg')`，不设为默认尺寸 | 描述错误 |
| 2.x/lazy.md:227 | `Lazy` 没有 `getLoadScript()` 方法 | src/Widgets/Lazy.php（无此方法） | `$('{$lazy->getElementSelector()}').trigger('lazy:load')` | 方法不存在 |
| 2.x/lazy.md:423 | `LazyTable::getLoadScript()` 是 protected，外部调用会报错 | src/Widgets/LazyTable.php:134 | `$('{$lazy->getElementSelector()}').trigger('table:load')` | 方法不存在 |
| 2.x/lazy.md:393-405 | "LazyTable 的 onLoad" 小节示例写的是 `Lazy::make(PostChart::make())` | src/Widgets/LazyTable.php:111 | `LazyTable::make(UserTable::make())->onLoad('...')` | 示例过时 |
| 2.x/widgets-data-card.md:174 等 20 余处 | 示例 `class MyCard extend Card` / `extend Line` 语法错误 | — | `class MyCard extends Card` | 示例过时 |
| 2.x/widgets-data-card.md:415 | 称"基本卡片默认是启用图表功能的"；实际基础 Card 的 `$chart` 默认为 null，需 `useChart()` 或调用 `chart*()` 才启用 | src/Widgets/Metrics/Card.php:124,160 | 基础 Card 默认不启用图表 | 描述错误 |
| 2.x/widgets-data-card.md:929-935 | `contentWidth()` 示例写在 `extends Line` 中，Line 无此方法 | src/Widgets/Metrics/Line.php:10（无 contentWidth）；src/Widgets/Metrics/Donut.php:89 | 在 `Donut`（或 RadialBar 系列）子类中调用 | 示例过时 |
| 2.x/widgets-data-card.md:944 | 称 Bar 继承自 Card，实际继承 RadialBar（因而有 footer/contentWidth/chartPullRight） | src/Widgets/Metrics/Bar.php:12 | `Bar extends RadialBar` | 描述不全 |
| 2.x/widgets-data-card.md:1077 | Round 被称为"柱状图卡片"，且实际继承 RadialBar | src/Widgets/Metrics/Round.php:13 | 多环形图卡片，`Round extends RadialBar` | 描述错误 |
| 2.x/widgets-data-card.md:42 | 示例构造函数 `$this->data = [];` 丢弃了传入参数 | — | `$this->data = $data;` | 示例过时 |
| 2.x/widgets-charts.md:324 | `use use Dcat\Admin\Support\JavaScript;` 语法错误 | — | `use Dcat\Admin\Support\JavaScript;` | 示例过时 |
| 2.x/custom-chart.md:3 | 称"dcat-admin 1.5 已移除所有图表组件"，2.x 内置 ApexCharts 与 Metrics | src/Widgets/ApexCharts/Chart.php | 优先使用 `Dcat\Admin\Widgets\ApexCharts\Chart` | 1.x 残留 |
| 2.x/custom-chart.md:68 | `Admin::content(function (Content $content) {...})` 2.x 不存在 | src/Admin.php（无 content 方法） | 控制器注入 `Content $content` 并 `return $content->body(...)` | 1.x 残留 |
| 2.x/custom-chart.md:79 | 链接 `layout.md` 在 2.x 文档中不存在 | 2.x 目录无 layout.md | 指向 custom-page.md | 结构问题 |
| 2.x/model-form-modal.md:35-36 | 注释称 DialogForm 默认宽 720px、高 690px | src/Widgets/DialogForm.php:25 | 默认 `['700px', '670px']` | 默认值错误 |
| 2.x/widgets-dialog-form.md:1 | 空页（0 行），且未在目录中链接；DialogForm 无独立文档 | src/Widgets/DialogForm.php | 见 model-form-modal.md / skill widgets.md | 结构问题 |
| 2.x/widgets-table.md:1 | 空页；`Widgets\Table`（键值表/嵌套子表）无文档 | src/Widgets/Table.php:37,81 | 见 skill widgets.md Table 节 | 结构问题 |
| 2.x/widgets-navbar.md:1 | 空页 | src/Layout/Navbar.php | 见 custom-navbar.md / frontend.md | 结构问题 |
| 2.x/widgets-js.md:1 | 空页 | — | 见 js.md / frontend.md | 结构问题 |
| 2.x/widgets-modal.md:59-71 | 未提及 `sm()`、`size()`、`delay()`、`join()`、`onLoad()`、`on()`；"按钮"小节无内容 | src/Widgets/Modal.php:116,234；src/Traits/InteractsWithRenderApi.php:28 | 见 skill widgets.md Modal 节 | 描述不全 |
| 2.x/（全部 widgets-*.md） | 未记录 DialogTable（含 2.2.2 后新增 `maxmin()`/`resize()`，默认宽 825px）、Code、Dump、Terminal、Tree、DarkModeSwitcher、Callout 样式方法 | src/Widgets/DialogTable.php；commit 7138b52d | 见 skill widgets.md | 描述不全 |
| 2.x/lazy.md:107 | 未说明渲染类由 `new $class()` 无参实例化、payload 经 URL 传递且可被客户端篡改，也未提及可用 `HasAuthorization` 鉴权 | src/Http/Controllers/RenderableController.php:54,17 | 构造函数无必填参数；`use HasAuthorization` + `authorize()` | 描述不全 |
| 2.x/widgets-charts.md:173 | 未说明 ajax 时服务端 `app($key)` 重新实例化、构造参数丢失（只说"参数要设默认值"） | src/Http/Controllers/ValueController.php:49 | 上下文只能经 `parameters()` 传递，`handle()` 内用 `$request->get()` | 描述不全 |
| 2.x/theme.md:5 | 内置主题列为 `indigo`、`blue`、`blue-light`、`green`，不存在 `indigo` 主题 | src/Color.php:14-43；config/admin.php:331-332 | 内置主题为 `default`、`blue`、`blue-light`、`green`，默认 `default` | 描述错误 |
| 2.x/theme.md:231,235,257,279,291,329 | 示例输出 primary 为 `#5c6bc6`（indigo），默认主题实际 primary 为 `#586cb1`，示例输出值均不符 | src/Color.php:17 | 默认主题 `primary` => `#586cb1`；`#5c6bc6` 是固定色 `indigo` | 描述错误 |
| 2.x/theme.md:363 | 称 JS `Dcat.color.darken`/`lighten` 可"通过魔术方法"调用，JS 端无魔术方法 | resources/assets/dcat/js/extensions/Color.js:2-48 | 只能 `Dcat.color.lighten(name, amt)` / `darken(name, amt)`；PHP 端才有 `Admin::color()->primary(10)` | 描述错误 |
| 2.x/theme.md:399 | `Dcat.color.alpha('primary', 0.1)` 实际返回 `rgba(0, 0, 0, 0.1)`：`toRBG` 先去掉 `#` 再 `get()` 得到带 `#` 的值，`parseInt` 失败 | resources/assets/dcat/js/extensions/Color.js:22-35 | `Dcat.color.alpha(Dcat.color.get('primary'), 0.1)` 或直接传十六进制 | 描述错误 |
| 2.x/theme.md:157 | 未说明 `admin.layout.navbar_color`、`sidebar_collapsed` 配置及 `Content::withConfig()` 单页覆盖 | config/admin.php:339-347；src/Layout/Content.php:387 | 补充 `navbar_color`（bg-primary 等）、`sidebar_collapsed`、`$content->withConfig()` | 描述不全 |
| 2.x/js.md:401 | `Dcat.Form` 选项 `autoRedirect`（默认 false）不存在 | resources/assets/dcat/js/extensions/Form.js:10-44 | 选项为 `redirect`（默认 `true`）；另有未文档化的 `confirm`、`validationErrorToastr`、`tabSelector` | 方法名错误 |
| 2.x/js.md:403-406 | 回调签名不全：`before(fields, form, opt)` 实为 4 参；`after/success/error` 参数未写 | resources/assets/dcat/js/extensions/Form.js:73-139 | `before(fields, $form, opt, form)`、`after(success, response, form)`、`success(response, form)`、`error(xhr, form)` | 签名变化 |
| 2.x/js.md:436 | 示例 `if (data.redirect)` 中 `data` 未定义 | — | 使用 `response.data` | 示例过时 |
| 2.x/js.md:281-289 | `Dcat.loading` 参数表缺 `shade`、`top`、`svg`，且 `color` 默认值来自 `$.fn.loading`（`Dcat.color.dark60`） | resources/assets/dcat/js/extensions/Loading.js:51-61 | 补充 `shade`（默认 'rgba(255, 255, 255, 0.1)'）、`top`(200) | 描述不全 |
| 2.x/js.md（全文） | 未记录 `$.post/$.put/$.delete` 被重写为只接受 options 对象，`$.post(url, data, cb)` 不可用 | resources/assets/dcat/js/extensions/Ajax.js:38-57 | `$.post({url, data: {...}, success})` | 描述不全 |
| 2.x/js.md（全文） | 未记录 `Dcat.onPjaxComplete/onPjaxLoaded/offInit/handleJsonResponse/handleAjaxError/grid.selected/Slider/addAction/lang.trans/helpers` 等常用 API | resources/assets/dcat/js/Dcat.js；extensions/*.js | 见 references/frontend.md「Dcat JS API」 | 描述不全 |
| 2.x/assets.md:96,113 | 示例 `class UserController extend Controller` 语法错误 | — | `extends` | 示例过时 |
| 2.x/assets.md（全文） | 未记录 `admin.assets_version` 缓存版本参数、`Admin::headerJs/baseJs/baseCss/fonts`、`Admin::script($js, true)` 直接执行、`Admin::view()` 脚本抽取 | src/Layout/Asset.php:574-707；src/Traits/HasHtml.php；config/admin.php:120 | 见 frontend.md「资源注入」「Admin::view」 | 描述不全 |
| 2.x/section.md:37 | 称闭包 `$options->previous` 为"上一个注入此区块的内容"，实为按优先级排在它之前、已拼接的结果（高优先级先执行，第一个执行者得到空串） | src/Layout/SectionManager.php:160-189 | 说明为"排在其前（优先级更高）已拼接内容" | 描述错误 |
| 2.x/section.md（全文） | 漏 `admin_inject_section_if`（默认 `$append=false`）及 `LEFT_SIDEBAR_MENU_TOP/BOTTOM` 区块 | src/Support/helpers.php:121；src/Admin.php:60-63 | 见 frontend.md「section 区块」 | 描述不全 |
| 2.x/section.md:166-176 | 自定义菜单示例手动 `Helper::buildNestedArray` + `view('admin::partials.menu')`，可直接用 `Admin::menu()->toHtml()` | src/Layout/Menu.php:81-90 | `Admin::menu()->toHtml((new $menuModel())->allNodes()->toArray())` | 示例过时 |
| 2.x/permission.md:15 | HTTP 路径填路由别名示例 `admin.users.show` 无法匹配：别名会与 `dcat.{app}.` 前缀拼接后比较 | src/Support/Helper.php:277; src/Application.php:121-126 | 填 `users.show`（或 `users.*`） | 描述错误 |
| 2.x/permission.md:57 | 称可在用户编辑页把权限直接附加给用户；2.x 用户只关联角色，权限只挂在角色上 | src/Http/Controllers/UserController.php:158; src/Models/Administrator.php:76; src/Traits/HasPermissions.php:18-29 | 权限分配给角色，再把角色分配给用户 | 描述错误 |
| 2.x/permission.md:61 | `use Dcat\Admin\Auth\Permission;` 命名空间不存在 | src/Http/Auth/Permission.php:3 | `use Dcat\Admin\Http\Auth\Permission;` | 1.x 残留 |
| 2.x/permission.md:111 | `Admin::user()->permissions` 不存在（用户模型无 permissions 关系） | src/Models/Administrator.php:76; src/Traits/HasPermissions.php:18 | `Admin::user()->allPermissions()` | 方法不存在 |
| 2.x/permission.md:139-176 | 权限中间件未说明：带参中间件本身直接放行，需由 `admin` 组内无参 `admin.permission` 解析执行；且会替代后台路径匹配；漏 `free` 方法 | src/Http/Middleware/Permission.php:33,58-78; src/Http/Auth/Permission.php:59 | 在 `admin` 中间件组路由内使用 `admin.permission:check | allow | deny | free,...` | 描述不全 |
| 2.x/menu.md:9 | “一个菜单最多能绑定一个权限和一个角色”错误，菜单表单为多选角色 + 权限树 | src/Http/Controllers/MenuController.php:44,47 | 可绑定多个角色与多个权限，满足任一即可见 | 描述错误 |
| 2.x/menu.md:12-19 | 菜单翻译位置写为 `resources/lang/{locale}/admin.php` 的 `menu_titles` | src/Layout/Menu.php:248-257 | `lang/{locale}/menu.php` 中 `'titles' => ['work_units' => '...']`（键 `menu.titles.xxx`） | 描述错误 |
| 2.x/menu.md:42-43 | `Menu::add` 示例 `'roles' => 'test-roles'` 字符串会被 `array_column(..., 'slug')` 丢弃，角色绑定静默失效 | src/Layout/Menu.php:222-223 (checkPermission) | `'roles' => [['slug' => 'test-roles']]`；`permission_id` 可为 slug 字符串或数组 | 示例过时 |
| 2.x/menu.md:22-23 | 菜单缓存只提开关，未提缓存永久保存、需 `admin:menu-cache` 重建及 `store` 配置 | src/Models/MenuCache.php:22-27; src/Console/MenuCacheCommand.php:14 | 开启 `admin.menu.cache.enable` 后，改库/部署执行 `php artisan admin:menu-cache` | 描述不全 |
| 2.x/custom-authentication.md:7 | 默认登录控制器写为 `App\Admin\AuthController` | src/Console/InstallCommand.php (createAuthController); config/admin.php `auth.controller` | `App\Admin\Controllers\AuthController` | 描述错误 |
| 2.x/custom-authentication.md:14 | 基类 `Dcat\Admin\Controllers\AuthController` 不存在 | src/Http/Controllers/AuthController.php:3; src/Console/stubs/AuthController.stub:5 | `Dcat\Admin\Http\Controllers\AuthController` | 1.x 残留 |
| 2.x/custom-authentication.md:39-40 | `config('admin.prefix')`、`Admin::controllerNamespace()` 均不存在 | config/admin.php `route.prefix`; src/Admin.php（无 controllerNamespace） | `config('admin.route.prefix')`、`config('admin.route.namespace')` | 方法不存在 |
| 2.x/multi-app.md:32-46 | 文字称生成 `app/NewAdmin`，目录树却画成 `app/Admin` | src/Console/AppCommand.php:61-64 | 目录为 `app/NewAdmin`，配置 `config/new-admin.php` | 结构问题 |
| 2.x/multi-app.md:147-175 | 要求手动把 guard 改为新名字；实际 `admin:app` 生成的配置已使用以应用名命名的独立 guard/provider | src/Console/stubs/config.stub:150-163 | 仅需替换 provider 的 `model` 为新用户模型 | 描述不全 |
| 2.x/function.md:16 | 标题 `admin_exist` 拼写错误 | src/Support/helpers.php:554 | `admin_exit` | 方法名错误 |
| 2.x/function.md:170 | `admin_toastr` 小节示例调用 `admin_alert()`，该函数不存在 | src/Support/helpers.php:307 | `admin_toastr('更新成功', 'success')` | 方法不存在 |
| 2.x/helpers.md:15 | 扩展管理地址 `admin/helpers/extensions` 不存在 | src/Admin.php:596, 656-659 | `admin/auth/extensions` | 描述错误 |
| 2.x/installation.md:5-6 | 环境要求 PHP >= 7.1、Laravel 5.5~9 已过时 | composer.json:27-28 | PHP >= 8.1，Laravel 10/11/12/13 | 描述错误 |
| 2.x/installation.md:38 | 包名 `dcat/laravel-admin:"2.*"`（本 fork 不适用） | composer.json:2 | `composer require printnow/laravel-admin` | 描述错误 |
| 2.x/quick-start.md:90-93 | 文字说 `menu_titles` 索引，示例却是 `menu.php` 的 `titles`，自相矛盾 | src/Layout/Menu.php:248-257 | `lang/{locale}/menu.php` 的 `titles` | 描述错误 |
| 2.x/extension-dev.md:35 | 默认命名空间示例写成 `Jiangqh/OperationLog`（斜杠） | src/Console/ExtensionMakeCommand.php `getRootNameSpace()`（`Str::title($vendor).'\\'.Str::title($name)` 去 `-`） | `Jiangqh\OperationLog`；不传 `--namespace` 时会交互询问，输入 `default` 用自动值 | 描述错误 |
| 2.x/extension-dev.md:50 | 生成目录写成 `dcat-admin/extensions/dcat-admin/operation-log` | src/Support/helpers.php:427-434 `admin_extension_path()`；config/admin.php:375 | `{项目根}/dcat-admin-extensions/dcat-admin/operation-log`（`admin.extension.dir`） | 描述错误 |
| 2.x/extension-dev.md:55,108 | 目录树含 `logo.png`，但 `admin:ext-make` 不生成 logo | src/Console/ExtensionMakeCommand.php `copyFiles()` | logo.png 需开发者自行放到扩展根目录（可选） | 描述不全 |
| 2.x/extension-dev.md:127 | 用 `php artisan make:migration` 生成迁移再放入 `updates/`；Laravel 9+ 生成匿名类迁移，扩展更新器无法解析（取到类名 `extends`） | src/Support/DatabaseUpdater.php:84-99,162-165 `resolve()/getClassFromFile()` | `updates/` 脚本必须是具名类 `class CreateXxxTable extends Migration`（或 Seeder），且类名不与项目迁移重复 | 示例过时 |
| 2.x/extension-dev.md:220-221 | `OperationLogServiceProvider:trans(...)` 单冒号语法错误 | src/Extend/ServiceProvider.php:529-532 | `OperationLogServiceProvider::trans('log.title')` | 描述错误 |
| 2.x/extension-dev.md:224-260（定义菜单） | 未说明 `parent` 仅按本扩展菜单 title 匹配，也未说明菜单只在版本更新时写入 | src/Extend/CanImportMenu.php:87-99；src/Extend/ServiceProvider.php:350-353 | `parent` 可为数字 ID 或本扩展菜单 title；改 `$menu` 后需 `admin:ext-refresh` 或卸载重装 | 描述不全 |
| 2.x/extension-f.md:49 | 只说不能重写 `boot`，未说明 `init()` 默认实现承担路由/视图/语言/中间件/资源别名注册 | src/Extend/ServiceProvider.php:84-123 | 重写 `init()` 必须先 `parent::init()`；`boot()` 为 final | 描述不全 |
| 2.x/extension-f.md:87 | 未说明 `ver` 为必填参数及 `--force` 选项 | src/Console/ExtensionRollbackCommand.php:10-13 | `admin:ext-rollback {name} {ver} {--force}`，完全卸载用 `admin:ext-uninstall` | 描述不全 |
| 2.x/extension-f.md:99 | 视图目录写成 `resources/view` | src/Extend/ServiceProvider.php:465-468 | `resources/views` | 描述错误 |
| 2.x/extension-f.md:154-155 | `Admin::js(['@dcat-admin.form-step/js/index.js'])` 别名错误：`@dcat-admin.form-step` 不是路径别名（为 js/css 集合或不存在），无法解析 | src/Extend/ServiceProvider.php:547-560；src/Layout/Asset.php:515-545 | `Admin::js('@dcat-admin.form-step.path/js/index.js')` / `Admin::css('@dcat-admin.form-step.path/css/index.css')` | 描述错误 |
| 2.x/extension-f.md:78-82 | 未说明静态资源仅在扩展「更新」时发布，开发期改资源不会自动生效 | src/Extend/UpdateManager.php:101-108 | `php artisan vendor:publish --force --tag=vendor.package` | 描述不全 |
| 2.x/extension-f.md:14-36 | 未列出 `admin:ext-install/update/enable/disable/uninstall/refresh` 命令 | src/AdminServiceProvider.php:43-50；src/Console/Extension*Command.php | 见 extension.md 命令表 | 描述不全 |
| 2.x/extension-helpers.md:7-9 | 1.x 残留：`composer require dcat-admin-ext/helpers` + `php artisan admin:import helpers`；2.x 无 `admin:import`，scaffold/icons 已内置（需 `app.debug`） | src/Admin.php:645-659；src/AdminServiceProvider.php 命令列表 | 直接访问 `admin/helpers/scaffold`、`admin/helpers/icons`（`admin.helpers.enable` 且 `app.debug=true`） | 1.x 残留 |
| 2.x/extension-helpers.md:23-43 | 数据库终端、artisan 终端、路由列表工具在 2.x 核心不存在 | src/Admin.php:645-659 | 删除 | 1.x 残留 |
| 2.x/extend.md:23,30 | `admin:extend` 命令不存在 | src/AdminServiceProvider.php:43-50 | `php artisan admin:ext-make vendor/name --namespace=...` | 1.x 残留 |
| 2.x/extend.md:26 | 配置项 `extension_dir` 不存在 | config/admin.php:372-376 | `admin.extension.dir` | 1.x 残留 |
| 2.x/extend.md:100-130 | 扩展类 `Dcat\Admin\Extension`（`NAME`、`$serviceProvider`、`$assets`、`$composerJson`）不存在 | src/Extend/ServiceProvider.php（无 src/Extension.php） | 继承 `Dcat\Admin\Extend\ServiceProvider`，入口由 composer.json `extra.dcat-admin` 指定 | 1.x 残留 |
| 2.x/extend.md:210-260 | ServiceProvider 中重写 `boot()` 并用 `Admin::menu()->add()` 注册菜单；2.x 扩展 `boot()` 为 final | src/Extend/ServiceProvider.php:84；src/Extend/CanImportMenu.php | 用 `init()` + `protected $menu` 属性 | 1.x 残留 |
| 2.x/extend.md:266-285 | `import(Command $command)` 自定义安装逻辑不存在 | src/Extend/ServiceProvider.php:350-361 | 重写 `update($currentVersion, $stopOnVersion)` / `uninstall()`，或在 `updates/` 中放迁移/Seeder | 1.x 残留 |
| 2.x/extend.md:499 | 用 `vendor:publish --provider=...` 发布资源 | src/Extend/ServiceProvider.php:366-373 | 资源 tag 为扩展名：`vendor:publish --force --tag=vendor.package`（更新扩展时自动执行） | 1.x 残留 |
| 2.x/extend.md:541-567 | `admin:import`、`config/admin-extension.php` 启用、`admin/helpers/extensions` 页面均不存在 | src/Extend/Manager.php:83-108；src/Admin.php:596 | 启用状态存 `admin_extensions.is_enabled`；`admin:ext-update` + `admin:ext-enable` 或页面 `admin/auth/extensions` | 1.x 残留 |
| 2.x/extend.md:1-10 | 整页为 1.x 时代扩展文档（含「2.0 预告」），与 extension-dev.md 冲突 | — | 应删除或明确标注已废弃 | 结构问题 |
| 2.x/documentation.md:37 | 目录中"异步渲染"链接指向 `model-grid-softdelete.md` | — | 指向 `model-grid-async.md` | 结构问题 |
| 2.x/model-form-step.md | 分步表单 `multipleSteps()`、`Form\StepForm`、`Form\DoneStep` 已在 commit 900e1210 移出核心，整页在核心包中不可用 | git log -S multipleSteps | 安装 `dcat-admin/form-step` 扩展并以其文档为准 | 方法不存在 |
| 2.x/extend.md:382 | `$grid->filter()->resetPosition()` 不存在（本页与 1.x 文档逐字节相同） | src/Grid/Filter.php | 删除该调用 | 1.x 残留 |

## 待确认

以下条目疑似有误，但未能仅凭源码静态核实（需要运行环境或依赖第三方包行为）。


**Grid 表格与列**

- 2.x/model-grid.md:345 rowSelector `style()` 可选值列表（`default/primary/success/info/danger/purple/inverse`）依赖前端 `vs-checkbox-*` CSS，未逐一核对样式表。
- 2.x/model-grid-actions.md:26-30 写“以下两种”却列了三种显示方式（文字小错，未计入表）。

**Grid 筛选、快捷搜索、规格选择器**

- model-grid-filters.md:425 `Administrator::find($v)->pluck('name', 'id')`：`$v` 为数组时 `find` 返回集合可用，但若为单值会返回模型导致 `pluck` 语义不同；建议改为 `whereIn`。未在运行环境验证。
- Radio 筛选没有“全部”选项时，用户只能通过“重置”取消选择；是否需要文档建议加 `'' => '全部'`（空值会被 inputs 过滤掉，逻辑上可行，未实测渲染）。

**Form 表单、关联、JSON 存储**

- model-form-fields.md:1455（embeds）"内嵌表单不支持图片和文件上传表单"：commit b51c39cc 后 `HasFiles::getFieldByRelationName` 已能在 `Embeds` 内查找上传字段（src/Form/Concerns/HasFiles.php:155-168），是否已完整支持需实测；references 中未声明支持也未声明不支持。
- model-relationship.md:189-215 称驼峰关联名"必须"使用下划线风格字段名：源码回显（`Field::getValueFromData` 回退 `Str::snake`）与保存（`getRelationInputs` 回退 `Str::camel`）对两种写法均有兼容，"必须"可能过严；references 中写为"推荐 snake 风格"。
- model-form-callback.md:132 "删除成功提醒，此处跳转参数无效"：未核实前端删除动作是否忽略 `redirect`，references 未写。

**Form 字段与上传**

- model-form-upload.md:204-209 `storagePermission(777)`：该值作为 `putFileAs()` 第 4 个参数（options）传入，Laravel 对非字符串会 `(array)` 化为 `[777]`，看起来不会生效；应为 `'public'`/`'private'` 可见性字符串。未在本仓库 vendor 中核实 Laravel 源码。
- model-form-fields.md:1097 `Form\Field\Map::requireAssets()` 写在 bootstrap.php：现源码在 `Form::pushField` 时已自动调用 `$field::requireAssets()`（src/Form.php:329），手动调用可能已不必要；未在浏览器 pjax 场景验证。
- model-form-upload.md:254 threads 默认 `3`：源码默认 options 未设置 threads，依赖 WebUploader 库默认值，未核实。

**Show、Tree、Repository、Action、响应**

- model-grid-data.md:252 `$model->grid()->quickSearch()->value()`：`quickSearch()` 无参调用会把已配置的搜索列重置为 `null`（src/Grid/Concerns/HasQuickSearch.php:36-42）。在仓库 `get()` 中调用时快捷搜索条件是否已 apply 未完全确认；skill 中改用无副作用的 `getQuickSearch()?->value()`。
- `ModelTree::saveOrder()` 硬编码 `$branch['id']`（src/Traits/ModelTree.php:169），非 `id` 主键的树无法保存排序。

**Widgets、前端、权限、扩展、安装**

- 2.x/widgets-dropdown.md 示例 `map()` 回调中 `$this` 被绑定为 Dropdown（`Closure::call`）——文档未说明，非错误。
- `Admin::js('@组件')` 仅取组件 js 且不替换 `{lang}` 等占位符（`Asset::get()` 不处理参数），含占位符的路径会照原样请求；未实测，frontend.md 中仅注明"不会替换占位符"。
- `Dcat.color.lighten` 计算结果未做零填充（如结果 < 0x100000 会少位），未在文档提及。
- 2.x/permission.md:180 “`auth/users/*` 会提示无权访问”仅对列表页成立（正则不锚定、`*` 不跨 `/`），未单列为勘误。

## 疑似源码问题

核对过程中发现的源码 bug（不是文档错误）统一记录在 [known-issues.md](known-issues.md)。
