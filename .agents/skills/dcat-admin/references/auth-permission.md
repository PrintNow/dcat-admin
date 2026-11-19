# 认证、权限、菜单、多应用与常用命令

> 覆盖：RBAC 权限（路由级/代码级/中间件）、菜单（动态菜单与缓存）、自定义登录、多后台、Octane、`admin_*` 帮助函数、`config/admin.php`、Artisan 命令。相关文件：frontend.md（script/section/navbar）、extension.md（扩展命令）。

## 权限模型
- 表 `admin_users/roles/permissions/menu` 及中间表 `admin_role_users/role_permissions/role_menu/permission_menu`；模型可经 `admin.database.*_model` 替换为子类。
- **用户只关联角色，权限只挂在角色上**：`Administrator` 只有 `roles()`，没有 `permissions()` 关系。
- 超管 = 拥有 slug `administrator`（`Role::ADMINISTRATOR`）的角色，自动通过一切检查；不要改该 slug。
- `admin.permission.enable = false`：权限中间件跳过、`Permission::check/allow/deny` 直接返回 true、角色/权限管理路由不注册。

## 路由级权限（后台配置）
权限的「HTTP方法」+「HTTP路径」命中任一角色权限即放行（源码：`src/Models/Permission.php::shouldPassThrough`，`src/Support/Helper.php::matchRequestPath`）。

| HTTP 路径 | 含义 |
|---|---|
| `/users` | 精确匹配 `admin/users`（自动加前缀） |
| `/users*` | `admin/users` 及其下所有路径 |
| `/users/*/edit` | 编辑页 |
| `GET:users*` | 为单个路径限定**一个**方法；多方法用「HTTP方法」多选或拆成多条。`GET,POST:xxx` 只适用于 config 的 except 数组，后台 http_path 按逗号拆分会变成错误路径 |
| `users.index` / `users.*` | 含 `.` 视为**路由别名**，与去掉 `dcat.{app}.` 前缀的别名比较 |

- `*` 被替换为 `[0-9a-z-_,]*`，**不跨 `/`**，且正则不锚定。
- 资源路由覆盖列表页必须写 `users*`；写 `users/*` 时列表页 `admin/users` 无权访问。
- `HTTP方法` 留空 = 所有方法。

> ⚠️ 路由别名写成 `admin.users.show` 不匹配，应写 `users.show`。

## 代码中判断权限
- `Admin::user()`（未登录 null）：`roles`、`allPermissions()`、`can($slugOrId)`（超管恒 true）、`cannot()`、`isAdministrator()`、`isRole($slugOrId)`、`inRoles([...])`（任一）、`visible([...])`（空数组返回 false，超管 true，否则同 inRoles）。`Admin::guard()`。
- `Dcat\Admin\Http\Auth\Permission` 静态方法，无权限时直接中断输出错误页（ajax 返回 403）：`check($p)`（数组须**全部**拥有）、`allow($roles)`（任一）、`deny($roles)`、`free()`、`error()`、`isAdministrator()`、`registerErrorHandler(\Closure $cb)`（返回值交 `admin_exit`）。

> ⚠️ `use Dcat\Admin\Auth\Permission;` 是 1.x 命名空间，2.x 为 `Dcat\Admin\Http\Auth\Permission`。
> ⚠️ `Admin::user()->permissions` 不存在，用 `allPermissions()` 或 `can()`。

在 Grid/Form/Show 中：控制器方法内 `Permission::check('create-post')` 整页拦截；`$grid->disableCreateButton(! $user->can(...))`、`disableDeleteButton/disableBatchDelete/disableEditButton/disableQuickEditButton/disableViewButton`，Form/Show 也有 `disableDeleteButton` 等，均为 `(bool $disable = true)` 返回 `$this`；逐行在 `$grid->actions(fn (Grid\Displayers\Actions $actions) => ...)` 中 `$actions->disableDelete()`。
- 隐藏按钮不等于禁止访问：务必配合路由权限或 `Permission::check()`。
- Action、工具表单、`LazyRenderable` 可覆写 `protected function authorize($user): bool` 做接口级鉴权，失败调 `failedAuthorization()`（默认 403）（源码：`src/Traits/HasAuthorization.php`）。

## 权限中间件 admin.permission
```php no-check
Route::group(['middleware' => 'admin.permission:allow,editor,developer'], ...);
Route::group(['middleware' => 'admin.permission:check,edit-post,create-post'], ...); // 需同时拥有
$router->get('public-report', 'ReportController@index')->middleware('admin.permission:free');
```
- 语法 `admin.permission:{method},{args...}`，`method` 须为 `Http\Auth\Permission` 上存在的静态方法（`check/allow/deny/free`），否则抛 `RuntimeException`。
- 机制：带参中间件本身直接放行，**由 `admin` 中间件组里的无参 `admin.permission` 读取路由上的带参中间件并执行**，且**替代**后台配置的路径匹配。因此必须在 `admin` 中间件组下使用。
- 超管、`permission.enable=false`、except 路径、`dcat-api.*` 内部路由先行放行（源码：`src/Http/Middleware/Permission.php`）。

## 跳过认证/权限
- `config('admin.auth.except')` 免登录；`admin.permission.except`（默认 `['/', 'auth/login', 'auth/logout', 'auth/setting']`）免权限但仍需登录。写法同权限路径（`*`、`GET:xxx`、路由别名）；`auth.except` 的路径也会被权限中间件跳过。
- 运行时追加：`Admin::context()->merge('permission.except', ['reports*'])`。中间件顺序 `admin.auth → admin.pjax → admin.bootstrap → admin.permission → ...`，`app/Admin/bootstrap.php` 在 `admin.bootstrap` 中加载，所以 bootstrap.php 里追加 `permission.except` 有效、追加 `auth.except` 无效（扩展用 `$exceptRoutes`，见 extension.md）。

## 菜单
`admin_menu` 字段：`parent_id, order, title, icon, uri, extension, show`。
- `uri` 填不含前缀的路径或完整外链。
- 可绑定多个角色和多个权限（后者需 `admin.menu.bind_permission=true` 且权限开启）。均未绑定 = 所有人可见；绑定后满足任一角色或任一权限即可见。
- `extension` 字段填扩展名，扩展未启用时隐藏；`show=0` 隐藏。
- 精细控制：自定义用户模型覆写 `canSeeMenu($menu): bool`（源码：`src/Models/Administrator.php`、`src/Layout/Menu.php::visible`）。

动态菜单（bootstrap.php）：
```php
Admin::menu(function (Menu $menu) {
    $menu->add([
        ['id' => 1, 'title' => '报表', 'icon' => 'feather icon-bar-chart', 'uri' => '', 'parent_id' => 0],
        ['id' => 2, 'title' => '销售', 'uri' => 'reports/sales', 'parent_id' => 1,
         'permission_id' => 'view-report',            // 权限 slug 或 id，可为数组
         'roles' => [['slug' => 'finance']]],         // 必须是含 slug 的数组列表
    ]);
});
```
- `add(array $nodes = [], int $priority = 10): void`，注入侧栏底部 section；`id` 只需在本次数组内唯一。也支持 `'permissions' => [['slug' => 'x']]`、`'show' => false`、`'extension'`。`$menu->view(...)` 替换菜单项视图。

> ⚠️ `'roles' => 'finance'` 字符串写法被静默忽略（内部 `array_column($roles, 'slug')`），必须写 `[['slug' => 'finance']]`。

- 标题翻译：`lang/{locale}/menu.php` 的 `['titles' => ['work_units' => '工作单位']]`，键 = 标题小写、空格换 `_`。
- 缓存：`admin.menu.cache.enable`（默认 false），永久保存，菜单模型 `saved/deleting` 时自动清除；直接改库或部署后执行 `php artisan admin:menu-cache`（清除并重建）。仅缓存数据库菜单，`add()` 动态菜单不受影响。其他键：`bind_permission`、`role_bind_menu`、`permission_bind_menu`、`default_icon`。

## 自定义登录与认证
`Admin::routes()` 注册 `GET/POST auth/login`、`GET auth/logout`、`GET/PUT auth/setting`，控制器取自 `admin.auth.controller`（安装后为 `App\Admin\Controllers\AuthController`）。`admin.auth.enable=false` 时这些路由及 `auth/users|menu|roles|permissions|extensions` 都不注册，`admin.auth` 中间件直接放行。

- 方式一：继承 `Dcat\Admin\Http\Controllers\AuthController`，属性 `$view`（默认 `admin::pages.login`）、`$redirectTo`（默认 `admin_url('/')`）；可覆写 `username()`（默认 `'username'`）、`getLogin/postLogin/getLogout`、`getSetting/putSetting`、`settingForm()`、`guard()`、`getFailedLoginMessage()`、`getRedirectPath()`、`sendLoginResponse()`。
- `postLogin` 为 ajax 提交，响应须为 `$this->response()->success(trans('admin.login_successful'))->redirect($path)->send()`（`success()`/`redirect()` 参数必填）或 `validationErrorsResponse()`（源码：`src/Http/Controllers/AuthController.php`）。
- 方式二：在 `app/Admin/routes.php` 中 `Admin::routes()` 之后，于相同 prefix/namespace/middleware 的路由组内重新注册同方法同 URI 的路由即可覆盖。

> ⚠️ 基类是 `Dcat\Admin\Http\Controllers\AuthController`，不是 `Dcat\Admin\Controllers\AuthController`。

自定义 UserProvider：`admin.auth.guards/providers` 启动时合并进 Laravel `auth.guards/providers`，可用 `Auth::provider('custom-admin', ...)` 后配置 `'providers' => ['admin' => ['driver' => 'custom-admin']]`。`retrieveByCredentials` 接收 `[username, password]`；返回的用户若不是 `Administrator`，需自行提供 `roles`、`can()`、`isAdministrator()`、`getAvatar()` 等（建议继承 `Administrator` 或 use `Dcat\Admin\Traits\HasPermissions`）。

## 多应用（多后台）
- `php artisan admin:app NewAdmin`（大驼峰）生成 `app/NewAdmin/` 和 `config/new-admin.php`（`route.prefix = 'new-admin'`、独立 guard/provider，仍用 `Administrator` 与同一套表）。
- 启用：在 `config/admin.php` **手动添加** `'multi_app' => ['new-admin' => true]`（默认配置无此键）。
- 机制（源码：`src/Application.php`）：每个应用路由组带 `admin.app:{app}` 中间件，请求进入时把 `config('admin')` 整体切换为 `config('new-admin')`，故 `config('admin.xxx')`、`admin_url()`、`admin_path()` 都指向当前应用。路由别名前缀 `dcat.{app}.`（默认 `dcat.admin.`），`admin_route()` 自动用当前应用前缀。
- 启用多应用后 `admin.session` 把 session cookie path 设为各应用前缀，实现各自登录。
- 独立菜单/用户：建表、模型继承 `Models\Menu` 等并设 `$table`，在 `config/new-admin.php` 的 `database.menu_table/menu_model/role_menu_table/permission_menu_table`（用户同理及 `auth.providers.*.model`）指向它们。域名区分：`route.domain`，`route.prefix` 设 `''`。

## Laravel Octane
本包**不会自动注册**，需在 `config/octane.php` 的 `RequestReceived::class` 监听器数组中追加 `Dcat\Admin\Octane\Listeners\FlushAdminState::class`。它在每个请求开始时清除 `admin.*` 单例并重新执行 `AdminServiceProvider` 注册与 boot；自定义代码避免把请求数据存入静态属性。

## 帮助函数
（`src/Support/helpers.php`）
- `admin_url($path)`（完整 URL 原样返回）、`admin_base_path($path)`（`/admin/xxx`）、`admin_route($name, $params)`（自动加 `dcat.{当前应用}.`）、`admin_route_name('users.*')`（用于 `routeIs`）、`admin_api_route_name()`、`admin_path()`（`admin.directory`）、`admin_extension_path()`、`admin_asset()`。
- `admin_redirect($to, $status = 302)`：兼容 pjax/ajax，`$to` 不需带前缀。
- 下一次页面渲染时提示：`admin_toastr($msg, $type = 'success')`、`admin_success/error/warning($title, $message = '')`、`admin_info($title, $message = '', $type = 'info')`。

> ⚠️ `admin_alert()` 不存在。ajax 表单/Action 中用 `$this->response()->success(...)`，flash 消息只在下一次整页/pjax 渲染时出现。

- `admin_exit($response)`：抛 `HttpResponseException` 中断；可传 `Content`、字符串、`Response`、数组（转 JSON）、`Admin::json()->error('...')`。
- 翻译：语言包文件名 = 控制器名去 `Controller` 转中划线（`UserProfileController` → `user-profile.php`），找不到回退 `global.php`，再找不到返回键的最后一段。`admin_trans_field($f)` → `{slug}.fields.{f}`；`admin_trans_label($l)` → `{slug}.labels.{l}`；`admin_trans_option($v, $field)` → `{slug}.options.{field}.{v}`；`admin_trans($key)`。`Admin::translation('xxx')` 改包名。
- `admin_setting('key', $default)` 读、`admin_setting(['key' => 'v'])` 写入 `admin_settings` 表；`admin_setting_array()`；`admin_extension_setting()` 见 extension.md。
- 其他：`admin_color()`、`admin_view()`、`admin_javascript()/admin_javascript_json()`、section 系列（见 frontend.md）、`admin_controller_name()/admin_controller_slug()`、`format_byte($input, $dec = 0)`。

## config/admin.php 关键项
- `route.prefix`（env `ADMIN_ROUTE_PREFIX`，默认 `admin`，空串为根路径）、`route.domain`、`route.namespace`、`route.middleware`（`['web', 'admin']`）、`route.enable_session_middleware`（多应用时自动启用）。
- `directory`（默认 `app_path('Admin')`，需在 `admin:install` 前设置）、`assets_server`、`assets_version`（null 用包版本，`''` 关闭）、`https`（已废弃，配置 `TrustProxies`）。
- `grid.grid_action_class`（默认 `DropdownActions`，可换 `Actions`、`ContextMenuActions`）、`grid.actions.*`、`grid.batch_action_class`、`grid.paginator_class`。
- `helpers.enable`（默认 true，还需 `app.debug=true`）、`upload.disk`（`public`）/`upload.directory.image|file`、`exception_handler`、`enable_default_breadcrumb`、`extension.dir`、`layout.*`（见 frontend.md）、`multi_app`（需手动添加）。

## 安装与常用命令
`composer require printnow/laravel-admin` → `php artisan admin:publish` → `php artisan admin:install`（migrate + 用户表为空时填充 + 生成 `app/Admin`；目录已存在则跳过生成）。默认账号 `admin`/`admin`。`app/Admin/bootstrap.php` 每个后台请求在 `admin.bootstrap` 中间件加载，用于注册扩展字段、全局资源、`Admin::menu()` 等。

新增 CRUD：`helpers/scaffold` 页面（`app.debug=true` 且 `admin.helpers.enable=true`）生成模型/控制器/仓库/迁移/语言包，然后在 routes.php 加 `$router->resource(...)` 并在 `auth/menu` 添加菜单。

| 命令 | 说明 |
|---|---|
| `admin:publish [--force] [--lang] [--assets] [--migrations] [--config] [--skills] [--claude]` | 不带选项发布 config/迁移/assets/lang；`--skills` 发布 AI skill 到 `.agents/skills`，`--claude` 另发布到 `.claude/skills` |
| `admin:update` | 升级后：`admin:publish --assets --migrations --lang --force` + `migrate` |
| `admin:uninstall` | 删除 `admin.directory`、发布的资源与 `config/admin.php`（不删表） |
| `admin:create-user` / `admin:reset-password` | 交互式 |
| `admin:menu-cache` | 清除并重建菜单缓存 |
| `admin:export-seed {classname=AdminTablesSeeder} [--users]` | 导出菜单/角色/权限为 Seeder |
| `admin:ide-helper` | 生成 Grid/Form/Show 字段 IDE 提示 |
| `admin:form {name}` | 生成工具表单，默认命名空间 `App\Admin\Forms` |
| `admin:action` | 交互式生成 Action（`default/grid-batch/grid-row/grid-tool/form-tool/show-tool/tree-row/tree-tool`） |
| `admin:app {name}` / `admin:minify` / `admin:ext-*` | 多应用 / 主题编译（见 frontend.md）/ 扩展（见 extension.md） |

> ⚠️ 本包**没有** `admin:make` 和 `admin:make:scaffold` 命令；CRUD 通过 `helpers/scaffold` 页面生成，或手写继承 `Dcat\Admin\Http\Controllers\AdminController` 的控制器。
