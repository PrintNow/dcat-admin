# 扩展开发

> 覆盖 2.x 扩展（插件）的生命周期、ServiceProvider、路由/菜单/中间件/静态资源/设置、version.php、`admin:ext-*` 命令。主题扩展、扩展升级不在此列。相关文件：frontend.md（资源别名）、auth-permission.md（菜单、权限、helpers）。

## 核心概念与生命周期
- 扩展 = composer 包，入口继承 `Dcat\Admin\Extend\ServiceProvider`。
- 扩展名 `name` = 包名 `/` 换 `.`（`dcat-admin/operation-log` → `dcat-admin.operation-log`），用于视图/语言包命名空间、资源别名、容器别名、设置 key（源码：src/Extend/ServiceProvider.php `getName()`）。
- 加载来源：① 本地目录 `admin.extension.dir`（默认 `base_path('dcat-admin-extensions')`）：`Extend\Manager::load()` 扫描 `{dir}/{vendor}/{package}/composer.json`，读 `extra.dcat-admin` 得 ServiceProvider，按 `autoload.psr-4` 动态注册加载（二者缺一即忽略该扩展）；② composer 安装：靠 `extra.laravel.providers` 自动发现，`boot()` 时注册到 Manager。
- 状态表：`admin_extensions`（name/version/is_enabled）、`admin_extension_histories`（版本说明与已执行脚本）。
- `register()` 对所有扩展都执行；`boot()` 是 `final`，内部先自动注册，**未启用时直接 return**，启用时才调用 `init()`。

> ⚠️ 重写 `boot()` 会 Fatal（final）。初始化写在 `init()`，且必须先 `parent::init()`，否则路由、视图、语言包、中间件、资源别名都不会注册。

## 创建
`php artisan admin:ext-make vendor-name/ext-name --namespace="VendorName\ExtName"`（`--theme` 生成主题骨架）。
- name 须匹配 `^[\w\-_]+\/[\w\-_]+$`；`--namespace` 不传会交互询问，输入 `default` 自动生成（`jiangqh/operation-log` → `Jiangqh\OperationLog`）。
- 生成在 `admin_extension_path('vendor-name/ext-name')`，已存在则报错；扩展管理页 `admin/auth/extensions` 也可快速创建。
- 固定路径（对应方法为 `final`，不可改）：`resources/views`、`resources/lang`、`resources/assets`、`src/Http/routes.php`；另有 `version.php`、`updates/`、`src/Setting.php`。可选 `logo.png`（建议 100x100）放根目录。
- composer.json：`require: {"dcat/laravel-admin": "~2.0"}`（本 fork `printnow/laravel-admin` 声明 `replace: dcat/laravel-admin`，可满足）、`autoload.psr-4`、`extra.dcat-admin` 与 `extra.laravel.providers` 均指向 ServiceProvider；可选 `alias` 为管理页显示名。
- 本地 zip 安装要求包内有 `src/`、`composer.json`、`version.php`，且 composer.json 含 `name` 与 `extra.dcat-admin`（源码：src/Extend/Manager.php `checkFiles()`）。

## ServiceProvider 成员
| 成员 | 说明 |
|---|---|
| `protected $js / $css` | 相对 `resources/assets/`，注册为别名 `@{name}`；完整 URL 原样保留 |
| `protected $middleware` | `['before'=>[], 'middle'=>[], 'after'=>[]]`（见下） |
| `protected $exceptRoutes` | `['auth'=>[], 'permission'=>[]]`，合并到 `Admin::context()` 的 `auth.except` / `permission.except`；值为相对后台前缀的路径（支持 `*`）或路由名 |
| `protected $menu` | 菜单数组（trait `CanImportMenu`） |
| `protected $type` | 主题扩展为 `self::TYPE_THEME` |
| `init()` | 启用时初始化；默认实现注册视图/语言/路由/中间件/免认证路由/资源别名 |
| `settingForm()` | 返回 `Extend\Setting` 实例；存在时管理页显示「设置」按钮 |
| `update($currentVersion, $stopOnVersion)` | 版本更新时调用，默认 `refreshMenu()`；重写时调 `parent::update(...)` |
| `uninstall()` | 默认 `flushMenu()` |
| `path($path = null)` | 扩展根目录（ServiceProvider 所在目录的上一级） |
| `getVersion()` / `getLocalLatestVersion()` | 已安装版本 / version.php 最新版本 |
| `config($key, $default)` / `static setting($key, $default)` | 读设置；传数组则合并保存 |
| `static trans($key)` / `static instance()` | 翻译扩展语言包 / `app(static::class)` |

`Admin::extension('vendor-name.ext-name')`（也接受斜杠名）取扩展实例；无参返回 `Extend\Manager`。

中间件合并（源码：src/Admin.php `mixMiddlewareGroup()`）：`before` 放 `admin.route.middleware` 最前，`after` 放最后，`middle` 插入 `admin` 组中 `admin.permission` **之前**（`admin.auth` 之后），适合需登录用户又要记录无权限请求的场景（如操作日志）。

## 路由、视图、语言包
- `src/Http/routes.php` 存在即在 `init()` 中注册，自动包在后台 `prefix` + `admin.route.middleware` 组内（走 `Admin::app()->routes()`，多应用下默认应用和每个已启用应用各注册一份）。路由路径建议带扩展前缀。
- 视图 `view('vendor-name.ext-name::index')`，或 `Admin::view(...)`（处理 `<script>`/`<style>`，支持 `<script require="@vendor-name.ext-name">`）。
- 语言包 `ExtNameServiceProvider::trans('log.title')` = `trans('vendor-name.ext-name::log.title')`。

> ⚠️ 原文档写 `OperationLogServiceProvider:trans(...)`（单冒号），正确为 `::trans(...)`。

## 菜单
`protected $menu = [['title' => 'Log', 'uri' => '', 'icon' => '...'], ['parent' => 'Log', 'title' => 'List', 'uri' => 'auth/logs']];`（源码：src/Extend/CanImportMenu.php）
- `title` 必填，`uri/icon/parent` 可选；校验不通过的项静默跳过。
- `parent` 为菜单 ID 或**本扩展**菜单的 `title`（找不到则作顶级）；父菜单须写在子菜单之前。写入时 `extension` 字段为扩展名，`order` 追加到最后。
- 菜单只在**版本更新**（`update()` → `refreshMenu()`：先删本扩展菜单再重建）时写入，卸载时 `flushMenu()` 删除；**启用/禁用不增删菜单**。
- 开发中改 `$menu` 后生效：卸载再更新、`php artisan admin:ext-refresh vendor-name/ext-name`，或新增版本后更新；开启了菜单缓存时可能需刷新缓存。

## 静态资源
- 源目录 `resources/assets/`；扩展**更新**时 `vendor:publish --force --tag={name}` 发布到 `public/vendor/dcat-admin-extensions/{vendor}/{package}/`。
- `init()` 注册两个别名：`@{name}.path` → 发布目录；`@{name}` → `$js`/`$css` 集合（二者非空时）。用法：`Admin::requireAssets('@vendor-name.ext-name')`、`Admin::js('@vendor-name.ext-name.path/js/other.js')`。

> ⚠️ 资源修改后页面不变：资源只在扩展更新时发布，开发期手动 `php artisan vendor:publish --force --tag=vendor-name.ext-name`。原文档 `@dcat-admin.form-step/js/index.js` 少了 `.path`，正确为 `@dcat-admin.form-step.path/js/index.js`。

## 设置表单（Setting）
`Dcat\Admin\Extend\Setting` 继承 `Widgets\Form` 并实现 `LazyRenderable`，在管理页弹窗展示。
- 必须实现 `form()`；`handle()` 已内置：`extension()->config(formatInput($input))` 保存并刷新。覆写 `protected function formatInput(array $input)` 预处理（返回值被保存）；`title()` 返回弹窗标题（空则默认「设置 - vendor/name」）。
- `default()` 自动取已保存配置。存储于 `admin_settings` 表，key 为扩展名 `.` 换 `:`（如 `vendor-name:ext-name`），值为 JSON。
- 读写：`ExtNameServiceProvider::setting('except', [])`；`::setting(['except' => [...]])` 合并写入；`admin_extension_setting('vendor-name.ext-name', 'except', [])`（首参为点分扩展名或类名）。

> ⚠️ `admin_extension_setting('vendor-name/ext-name', ...)` 斜杠包名会解析失败（内部 `app($extension)`，容器别名是点分名）。

## 版本管理（version.php + updates/）
```php no-check
return [
    '1.0.0' => ['Initialize extension.', 'create_operation_log_table.php'],   // 键不加 v 前缀，按 version_compare 排序
    '1.0.1' => ['Add index.', 'update_operation_log_table.php'],             // 匹配 /^[a-z0-9_\-.\/\\]+\.php$/i 的条目视为 updates/ 下脚本
];
```
- 更新：先调 `ServiceProvider::update()`（默认刷新菜单），再从当前版本起依次执行未执行过的脚本（`admin.database.connection` 事务中），记录历史、写新版本号，最后发布资源。重写 `update()` 时本次新版本迁移建的表**还不存在**（源码：src/Extend/VersionManager.php、src/Support/DatabaseUpdater.php）。
- 脚本须为继承 `Migration`（`up()`/回滚 `down()`）或 `Seeder`（`run()`，无回滚）的**具名类**；有 `setExtension()` 方法会被注入扩展实例。回滚/卸载按历史倒序执行 `down()`。

> ⚠️ Laravel 10+ `make:migration` 生成的匿名类迁移（`return new class extends Migration`）放进 `updates/` 会报错（解析器取到类名 `extends`）。必须改成具名类，且不要与项目迁移重名（`require_once` 加载）。

## 安装、启用、更新、卸载
管理页 `{admin前缀}/auth/extensions`（需 `admin.auth.enable`）。
1. 获得代码：`admin:ext-make` / 页面上传 zip / `composer require`。
2. 安装（更新至版本）：页面更新按钮或 `admin:ext-update`：执行迁移、写菜单、发布资源、建 `admin_extensions` 记录。
3. 启用：`admin:ext-enable`；未安装时抛 `Please install the extension(...) first!`；下一个请求起 `init()` 生效。
4. 禁用：不删数据、菜单。卸载：回滚全部脚本（**会删表/数据**）、删菜单与版本记录，代码目录需手动删。

> ⚠️ 只 `composer require` 或只创建扩展就访问路由 404：需先「更新到版本」再「启用」。

## Artisan 命令
`name` 均为 `vendor/package`（也接受点分名）（源码：src/Console/Extension*Command.php）：`admin:ext-make {name} {--namespace=} {--theme}`、`admin:ext-install {name} {--path=}`（zip 路径或目录，目录自动拼 `/{vendor.package}.zip`）、`admin:ext-update {name} {--ver=}`、`admin:ext-enable`、`admin:ext-disable`、`admin:ext-uninstall`（交互确认）、`admin:ext-rollback {name} {ver} {--force}`（ver 必填，可带 `v`）、`admin:ext-refresh {name}`（完全回滚后重新更新）。

> ⚠️ `admin:ext-install` 不带 `--path` 会调用不存在的市场下载方法而报错。1.x 的 `admin:extend`、`admin:import` 与 `config/admin-extension.php` 在 2.x 中不存在。

## 内置开发工具
2.x 已内置（无需 `dcat-admin-ext/helpers`）：`admin.helpers.enable`（默认 true）且 `app.debug=true` 时注册 `{前缀}/helpers/scaffold`（脚手架）、`helpers/icons`。1.x helpers 的数据库终端、artisan 终端、路由列表不提供。
