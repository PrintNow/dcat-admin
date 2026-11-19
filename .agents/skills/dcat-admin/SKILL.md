---
name: dcat-admin
description: 使用 Dcat Admin 2.x（printnow/laravel-admin、dcat/laravel-admin）开发 Laravel 后台时使用：编写或修改 AdminController、Grid 数据表格（列、展示器、筛选 filter、快捷搜索、导出、行内编辑）、Form 表单（字段、验证、事件回调、hasMany/embeds 关联）、Show 详情、Tree 模型树、Action 动作与 JsonResponse、Widgets/Metrics 图表、权限菜单、前端 JS/pjax、扩展开发。Use for any Dcat Admin / dcat-admin admin panel code.
license: MIT
metadata:
  dcat-admin: "2.4.x (printnow/laravel-admin)"
  source-docs: "dcat-admin/docs 2.x @ cb535380，已逐条对照源码核实并修正"
---

# Dcat Admin 2.x

适用于 `printnow/laravel-admin` 2.4.x，API 与原版 `dcat/laravel-admin` 2.x 兼容；PHP 8.1+，Laravel 10–13。原官方文档有大量错误和 1.x 残留，与本 skill 冲突时以本 skill 为准。

**用法**：先过一遍下面的高频陷阱，再按任务只读对应的 reference。仍不确定时直接打开表中列出的源码文件，不要全局 grep。源码路径相对于 `vendor/printnow/laravel-admin/`（原版为 `vendor/dcat/laravel-admin/`）。

| 任务 | reference | 源码 |
|---|---|---|
| 表格整体：工具栏、行/批量操作接入、导出、快捷创建、树状表格、事件 | `references/grid.md` | `src/Grid.php`、`src/Grid/Concerns/*`、`src/Grid/Tools/*`、`src/Grid/Exporters/AbstractExporter.php` |
| 列与展示器、行内编辑、条件显示 | `references/grid-column.md` | `src/Grid/Column.php`（`$displayers`）、`src/Grid/Column/HasDisplayers.php`、`src/Grid/Displayers/*` |
| 筛选、列筛选、快捷搜索、selector | `references/grid-filter.md` | `src/Grid/Filter.php`（`$defaultFilters`）、`src/Grid/Filter/*`、`Filter/Presenter/*`、`src/Grid/Concerns/HasQuickSearch.php` |
| 表单流程、验证、事件、布局、`when`、弹窗表单 | `references/form.md` | `src/Form.php`（`store/update/prepareInsert/prepareUpdate`）、`src/Form/Concerns/HasEvents.php`、`HasFieldValidator.php` |
| 表单字段（含 select/tree/selectTable/map） | `references/form-fields.md` | `src/Form.php`（`$availableFields`）、`src/Form/Field.php`、`src/Form/Field/*` |
| 上传、富文本、markdown | `references/form-fields-upload.md` | `src/Form/Field/File.php`、`Image.php`、`src/Form/Concerns/HasFiles.php` |
| 关联、hasMany、embeds、JSON 列 | `references/form-relations.md` | `src/Repositories/EloquentRepository.php`、`src/Form/Field/HasMany.php`、`src/Form/EmbeddedForm.php` |
| 详情页 | `references/show.md` | `src/Show.php`、`src/Show/Field.php` |
| 模型树 | `references/tree.md` | `src/Tree.php`、`src/Traits/ModelTree.php` |
| Repository、外部数据源 | `references/repository.md` | `src/Repositories/*` |
| Action（行/批量/工具栏/表单/详情/树）、JsonResponse | `references/actions.md` | `src/Actions/Action.php`、`HasActionHandler.php`、`src/Http/Controllers/HandleActionController.php`、`src/Http/JsonResponse.php` |
| Widgets、工具表单、Lazy、自定义页面布局 | `references/widgets.md` | `src/Widgets/*`、`src/Layout/*`、`src/Http/Controllers/HandleFormController.php`、`RenderableController.php` |
| 图表、Metrics 统计卡片 | `references/widgets-metrics.md` | `src/Widgets/Metrics/*`、`src/Http/Controllers/ValueController.php` |
| 前端 JS、pjax、资源注入、主题 | `references/frontend.md` | `src/Layout/Asset.php`、`src/Traits/HasHtml.php`、`resources/assets/dcat/js/Dcat.js`、`extensions/Ajax.js`、`extensions/Grid.js` |
| 权限、菜单、登录、多后台、helpers、配置、artisan 命令 | `references/auth-permission.md` | `src/Http/Middleware/Permission.php`、`src/Models/Permission.php`、`src/Support/Helper.php`（`matchRequestPath`） |
| 扩展开发 | `references/extension.md` | `src/Extend/ServiceProvider.php`、`VersionManager.php`、`Setting.php` |

## 高频陷阱

**通用**

1. 用 `Grid::make($model, fn)`、`Form::make(...)`、`Show::make($id, $model, fn)` 加闭包构建，不要用 1.x 的 `new Grid()` 后再 `return`。控制器基类是 `Dcat\Admin\Http\Controllers\AdminController`。
2. 主键一律用 `getKey()`，不存在 `key()`、`getId()`。命名空间：`Dcat\Admin\Grid\RowAction`、`Grid\BatchAction`、`Grid\Tools\AbstractTool`、`Dcat\Admin\Http\Auth\Permission`。1.x 的 `Dcat\Admin\Controllers\*`、`Dcat\Admin\Auth\*` 都不存在。
3. 不存在 `Admin::content()`，自定义页面在控制器中注入 `Dcat\Admin\Layout\Content`。也不存在 `admin:make`、`admin:make:scaffold` 命令：脚手架在后台 `helpers/scaffold` 页面，可用的生成命令是 `admin:form`、`admin:action`、`admin:ext-make`。

**Grid**

4. 字段名与 Grid 方法同名时（`title`、`description`、`model`、`filter`、`header`、`footer`、`tools`、`actions`、`export`、`number` 等），必须写 `$grid->column('title')`。建议一律用 `column()`。
5. `display()` 回调收到的是未转义的原始值，输出用户数据要先 `e()`；回调里 `$this` 是当前行模型。
6. 行内编辑类展示器（`editable`/`input`、`switch`、`switchGroup`、`select`、`radio`、`checkbox`、`textarea`）提交到 `update`，要求 `form()` 中有同名字段。`switchGroup($columns, $color, $refresh)` 的第 3 个参数才是刷新。
7. 在 `Filter` 上调用不存在的方法会**静默无效**，比如 1.x 的 `$filter->column()`、`disableIdFilter()`。
8. `toTimestamp()` 只有 `between()` 有：`$filter->between('t')->datetime()->toTimestamp()`。筛选器专属方法要写在 `select()`、`datetime()` 等 Presenter 方法之前。
9. 内置 `$filter->month()` 筛选器提交 `YYYY-MM` 却用 `whereMonth` 查询，按默认用法查不到数据。按年月筛选请用 `where()` 自行计算月份区间。
10. 导出的 `rows()` 回调参数是 Collection，返回值也必须是 Collection。自定义导出器继承 `AbstractExporter` 并实现 `export()`，在其中用 `$this->buildData()` 取数据。

**Form**

11. 回调中中断保存：`return $form->response()->error('...')`，没有 `$form->error()`。条件响应用 `*If` 后缀：`refreshIf()`、`locationIf()`、`redirectIf()`。
12. 未在表单中声明的列不会被保存：`saving` 里给 `$form->updated_by` 赋值前，必须先 `$form->hidden('updated_by')`。非数据库字段（如 `password_confirmation`）要用 `$form->ignore()` 排除。
13. `creationRules()` / `updateRules()` 会**替换**对应模式下的 `rules()`，而不是叠加，所以 `same()` 加的 `confirmed` 也会随之失效。unique 规则忽略自身写 `'unique:users,email,{{id}}'`。
14. 数组值字段（`checkbox`、`multipleSelect`、`tags`、`multipleImage`/`multipleFile`、`embeds`、`table`、`array`、`keyValue`、`list`）不会自动编码。模型 `$casts` 和字段 `->saveAsJson()` 二选一，两者都配会双重编码。
15. 编辑带关联的表单要预加载：`Form::make(Post::with('tags'), ...)`。多对多还需 `->customFormat(fn ($v) => array_column($v ?: [], 'id'))`。`hasMany` 的子模型需要 `$fillable`。
16. select 的选项：全量用 `->options(Category::pluck('title', 'id'))`；远程搜索用 `->ajax(url)`，并配合 `->model(Category::class, 'id', 'title')` 回显当前值。⚠️ 2.4.0–2.4.7 中 `options(Model::class, ...)` 传类名不可用（`Select.php` 缺少 `Model` 导入）。
17. `disable()` 的字段不会被提交，只读展示用 `readOnly()` 或 `display()`。`number()` 回显时会转成 int，小数请用 `decimal()` 或 `currency()`。分步表单已移出核心，需要安装 `dcat-admin/form-step` 扩展。

**Show**

18. `->json()` 遇到嵌套数组或对象会抛 TypeError，改用 `->as(fn ($v) => json_encode($v, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))`。`as()` 的输出默认会转义；`label()` 等会关闭转义，但不会帮你转义值。

**Action / 异步组件**

19. Action、带 `handle()` 的 Metrics 卡片、Lazy 组件、工具表单在处理请求时会被**无参重新实例化**，构造参数和 `$this->row` 都会丢失，需要用 `parameters()` 或 `payload()` 传递。这些值来自客户端，必须自行鉴权。`authorize()` 只拦截请求，不会隐藏按钮。
20. 工具栏 `AbstractTool` 没有 `getSelectedKeysScript()`（只有 `BatchAction` 有），取勾选行用前端的 `Dcat.grid.selected(name)`，见 `references/actions.md`。
21. Metrics 卡片首次加载时 `option` 为空，默认值要写成 `$request->get('option') ?: 7`。卡片子类不要定义 `$options` 属性，否则会和 `Widget::$options` 冲突。

**前端 / 权限 / 扩展**

22. 页面通过 pjax 切换：PHP 端用 `Admin::script()`（已自动包裹 ready）；Blade 内联脚本用 `Dcat.ready()`；绑定在 `document` 上的事件要先 `.off()`。后台重写了 `$.post`，必须用对象写法 `$.post({url, data, success})`，并且 `data` 必须传（没有参数也要写 `data: {}`），否则会抛 TypeError。
23. `Admin::navbar()`、`Admin::menu()` 和全局资源注册写在 `app/Admin/bootstrap.php` 中；写在控制器里只在整页加载时生效。
24. 资源路由的权限路径写 `users*`，而不是 `users/*`（后者会让列表页无权限）。带参数的 `admin.permission:check,slug` 中间件会取代路径匹配。
25. 扩展的 ServiceProvider 不能重写 `final boot()`，初始化逻辑写在 `init()` 中，并先调用 `parent::init()`。扩展要先"更新到版本"再"启用"，菜单只在版本更新时写入。

**本 fork 新增**：列展示器 `viewable()`（掩码显示，点击切换明文）和 `highlightjs()`（代码高亮）。color、markdown 字段和 `Show::json()` 的输出改为转义。
