# 前端：静态资源、JS API、pjax、主题配色、section、导航栏

> 覆盖 `Admin::script/style/js/css/requireAssets` 资源注入、Asset 别名、pjax 下脚本写法、全局 `Dcat.*` JS API、主题与 `Color`、section、Navbar。相关文件：widgets.md（组件）、auth-permission.md（菜单/配置/命令）。

## 资源注入（PHP 端）
`Dcat\Admin\Admin` 静态方法（`Traits/HasAssets`，委托 `Admin::asset()` 即 `Layout\Asset` 单例）。

| 方法 | 要点 |
|---|---|
| `Admin::js/css(string\|array)` | 路径、`@别名/路径` 或组件别名（如 `'@editor-md'`，只加载其 js/css 部分） |
| `Admin::requireAssets($name, array $params = [])` | 按组件别名同时引入 js+css |
| `Admin::script($script, bool $direct = false)` | 默认包在 `Dcat.ready()` 里；`$direct = true` 立即执行（不等 JS 文件加载） |
| `Admin::headerJs($js, $merge = true)` / `baseJs` / `baseCss` | **仅整页加载时输出**，pjax 不输出；`$merge=false` 整体替换 |
| `Admin::fonts([])` | 去掉默认在线字体 `@nunito` |
| `Admin::html($content)` | 追加隐藏 HTML（`.extra-html`）；无参返回已收集内容 |
| `Admin::jsVariables(array)` | 前端 `Dcat.config.xxx` 读取；`['lang' => [...]]` 追加/覆盖 `Dcat.lang` |
| `Admin::title()/favicon()`、`Admin::disablePjax()` | 无参返回当前值；当前请求禁用 pjax |

helpers：`admin_js/admin_css/admin_script($script, $direct = false)/admin_style`、`admin_require_assets($asset)`（**只接收一个参数**）、`admin_asset($path)`（返回完整 URL，支持 `@别名`）。

PHP 变量注入 JS 用 `admin_javascript_json($data)`：JSON 编码，并把 `admin_javascript('function(){...}')` 占位符还原成原始 JS 表达式。

资源 URL（源码：`src/Layout/Asset.php::url()`）：不含 `//` 的路径拼 `config('admin.assets_server')` 再走 `asset()`；自动追加 `?v{版本}`，取 `admin.assets_version`（env `ADMIN_ASSETS_VERSION`，2.2.2 起），未配置回退 `Admin::VERSION`，设为 `''` 不追加。

## Asset 别名与组件
在 `app/Admin/bootstrap.php` 注册：
```php
Admin::asset()->alias('@my', 'assets/admin');                // 路径别名；名称可省略 @
Admin::asset()->alias('@my-chart', [                         // 组件别名，内部路径可用别名
    'js' => ['@my/chart/chart.min.js', '@my/chart/locale/{lang}.js'], 'css' => '@my/chart/chart.css',
]);
Admin::requireAssets('@my-chart', ['lang' => 'zh_CN']);      // 或 '@my-chart?lang=zh_CN'
```
- `alias()` 也可传数组批量注册；`alias('@name')` 不传值返回解析结果。
- `requireAssets` 加载时未替换的 `{xxx}` 路径会被丢弃；`Admin::js('@组件')` 不替换也不丢弃，原样输出（可能 404）。
- 内置路径别名：`@admin` => `vendor/dcat-admin`，`@extension` => `vendor/dcat-admin-extensions`。组件别名完整列表见 `src/Layout/Asset.php` 的 `$alias`（如 `@select2`、`@editor-md`、`@apex-charts`、`@chartjs`、`@jstree`、`@moment`、`@qrcode`、`@sortable`）。
- 全局已加载无需 require：jQuery、Bootstrap4、`layer`、`toastr`、sweetalert2、`jquery-pjax`、`bootstrap-validator`、`jquery.initialize`、NProgress。

## Admin::view：带脚本的 Blade 视图
`Admin::view($view, $data)` / `admin_view()` 渲染视图，并把**顶层**的 `<script>`、`<style>`、`<link rel="stylesheet">`、`<template>` 抽出交给 Asset 管理，返回剩余 HTML（源码：`src/Traits/HasHtml.php`）。
- `<script src>` 等同 `Admin::js()`；无 src 的脚本包成 `(function(){...})()` 放进 `Dcat.ready`；`require="@xxx"` 等同 `Admin::requireAssets`。
- `<script init=".sel">` 包成 `Dcat.init('.sel', function ($this, id) {...})`，元素出现时执行一次。
- `<script once>` 去重单独注入；`<script first>` 以 `$direct=true` 注入；`<template>` 内容放入 `Admin::html()`。
- 只处理顶层标签，嵌在 `<div>` 内的 `<script>` 不会被抽取。

## pjax 注意事项（必读）
后台用 jquery-pjax 局部刷新 `#pjax-container`，切页只替换内容区，**不**重新执行整页加载流程。
1. PHP 端优先 `Admin::script()`：输出在内容区 `<script data-exec-on-popstate>`，每次 pjax 进入、前进/后退都重新执行。**不要**在里面再套 `Dcat.ready` 或 `$(function(){})`。
2. Blade 内联 JS 必须写在 `Dcat.ready(function () {...})` 里：pjax 下 JS 文件异步按序加载，`Dcat.ready` 等本页新增 JS 加载完（`pjax:loaded`）再执行。
3. JS 文件在一次整页生命周期内只加载/执行一次（seajs 缓存）：文件里只放定义，DOM 初始化放 `Admin::script()`。
4. 绑在 `document` 上的事件会在切页后累积：用 `$(document).off(...).on(...)` 或 `Dcat.init(sel, fn)`；绑在内容区元素上的随 DOM 销毁。
5. 导航栏、菜单、`headerJs/baseJs/baseCss` 只在整页加载时渲染：`Admin::navbar()`、`Admin::baseJs()` 放 `app/Admin/bootstrap.php`，不要放控制器。
6. 定时器在离开页面时清理：`Dcat.onPjaxComplete(function () { clearInterval(timer); })`（默认只触发一次）。
7. 跳转用 `Dcat.reload(url)`；不走 pjax 的链接加 `target="_blank"` 或 `location.href`。
8. `pjax:complete` 时系统自动移除残留的 `.modal-backdrop`、`body.modal-open`。

> ⚠️ Blade 中写 `<script>$(function(){ ... })</script>` 或在 JS 文件顶层直接操作页面元素，pjax 切页后不生效或依赖脚本未加载。用 `Admin::script()` 或 `Dcat.ready()`。

## Dcat JS API
源码 `resources/assets/dcat/js/Dcat.js`、`dcat-app.js`、`extensions/*.js`。
- `Dcat.ready(cb)`：整页时等价 `$(cb)`，pjax 时在 `pjax:loaded` 后执行一次。
- `Dcat.init(selector, cb($this, id), options?)`：MutationObserver 监听元素生成，每个元素只执行一次（`initialized` 属性标记），无 id 自动生成；下次 `pjax:complete` 自动注销；`Dcat.offInit(selector)` 手动注销。
- `Dcat.onPjaxComplete(cb, once = true)`（内容替换完，JS 未必加载完）/ `Dcat.onPjaxLoaded(cb, once = true)`（本页 JS 加载完）。
- `Dcat.triggerReady()`：异步插入含脚本的 HTML 后手动触发 ready。
- `Dcat.booting(cb, once = true)` / `Dcat.bootingEveryRequest(cb)`、`Dcat.addAction(name, fn)`：须在 `Dcat.boot()` 前注册（通常在扩展 headerJs 中）。内置 `[data-action]`：`refresh`、`delete`（`data-url`/`data-redirect`/`data-message`）、`batch-delete`、`preview-img`、`popover`。
- `Dcat.config` / `Dcat.token` / `Dcat.lang.trans(key, {num: 3})`（替换 `:num`）。
- toastr：`Dcat.success/error/warning/info(message, title?, options?)`。
- `Dcat.confirm(title, message, success, fail?, options?)`：返回值**不是** Promise；`Dcat.swal.success/error/info/warning(title, message?)` 返回 Swal Promise（`Dcat.swal` 即 Swal）。
- Loading：`Dcat.loading()` / `Dcat.loading(false)` 全屏；`$(el).loading()` 元素内（元素需有定位）；`$(btn).buttonLoading()`；`Dcat.NP.start()/done()`。
- `Dcat.grid.selected(name?)` 选中主键数组、`selectedRows(name?)` 返回 `[{id, label}]`（`name` 为 `$grid->setName()`）。
- `Dcat.helpers.asyncRender(url, done)`、`previewImage(src)`、`debounce(fn, wait)`、`get(obj, 'a.b', def)`；`Dcat.assets.load(urls, cb)`；`Dcat.Slider({target})`；`Dcat.darkMode.toggle()`。

### Ajax
- 已全局 `$.ajaxSetup` 带 `X-CSRF-TOKEN`，错误默认交 `Dcat.handleAjaxError`（401 跳转、403/419/422/500 提示）。`$.get` 与 jQuery 一致。
- **`$.post / $.put / $.delete` 被重写为只接收一个 options 对象**，自动加 `_token`（put/delete 用 POST + `_method`；delete 会覆盖 data）：`$.post({url, data: {id: 1}, success: fn})`。

> ⚠️ `$.post(url, data, callback)` 在 dcat 后台中不可用（url 字符串被当 options 处理而报错）；`$.post/$.put` 的 `data` 必须是对象（内部 `Object.assign(options.data, ...)`）。

- `Dcat.handleJsonResponse(response, options?)`：处理后端 `JsonResponse`：按 `data.type`/`status` 弹 toastr（`data.alert` 为真用 swal），执行 `data.then`（`refresh/redirect/location/download/script`）；`response.html` + `options.target` 时填充 HTML。

### 表单提交与验证
（源码：`resources/assets/dcat/js/extensions/Form.js`）`$('#my-form').form({...})` 拦截 submit 并 ajax 提交；`Dcat.Form({form: $el, ...})` 立即提交一次。选项：
- `validate: true` 启用 bootstrap-validator（规则写在 input 属性，错误放 `.with-errors`）；`confirm: {title, content}`。
- 回调 `before(fields, $form, opt, form)`、`after(success, response, form)`、`success(response, form)`、`error(xhr, form)`，均 `return false` 阻止后续默认处理。
- `redirect`（默认 `true`，`false` 时忽略响应中的 `then` 跳转）、`validationErrorToastr`、`errorClass`、`autoRemoveError`。

> ⚠️ 选项名是 `redirect`，不存在 `autoRedirect`。

自定义前端规则：`Dcat.validator.extend(rule, callback($el), message?)`，callback 返回真值表示**校验失败**；HTML 上 `data-{rule}="..."`、`data-{rule}-error="..."`。

## 主题、布局配置
`config('admin.layout')`：`color`（`default/blue/blue-light/green`）、`body_class`（`'dark-mode'` 默认深色、`'sidebar-separate'`）、`horizontal_menu`、`sidebar_collapsed`、`sidebar_style`（`light/primary/dark`，旧键 `sidebar_dark` 已弃用）、`dark_mode_switch`、`navbar_color`（如 `bg-primary`）。单页覆盖用 `$content->withConfig(...)`；`Admin::isDarkMode()` 依据 `body_class` 含 `dark-mode`。

自定义主题色：包目录下 `php artisan admin:minify orange --color fbbd08 --publish`（需 Node），再在 bootstrap.php `Dcat\Admin\Color::extend('orange', ['primary' => ..., 'primary-darker' => ..., 'link' => ...])` 并设 `layout.color = orange`。非 default 主题时 `@adminlte`、`@dcat` 的 css 自动换成 `xxx-{color}.css`；每次升级包后需重新编译。

## 颜色 Color
PHP：`Admin::color()` / `admin_color()`，`admin_color('primary')` 直接取值。`get($name, $default)` 支持别名链（`success` → `green` → `#21b978`）；`lighten/darken($color, $amt)`、`alpha($color, $a)` 返回 `rgba()`；魔术方法 `->primaryDarker(10)` 驼峰转中划线后调 `darken`（负数变亮）。色名列表见 `src/Color.php`。JS：`Dcat.color.primary`、`Dcat.color.get('primary')`、`lighten/darken/alpha/all()`。

> ⚠️ JS 端 `Dcat.color.alpha('primary', 0.1)` 传别名会得到 `rgba(0, 0, 0, 0.1)`（`toRBG` 解析失败），应传十六进制：`Dcat.color.alpha(Dcat.color.get('primary'), 0.1)`。PHP 端 `Admin::color()->alpha('primary', 0.1)` 正常。JS 端没有 `Dcat.color.primary(10)` 这类魔术方法。

## section 区块
（底层 `Layout\SectionManager`，常写在 bootstrap.php）
- `admin_inject_section($section, $content = null, bool $append = true, int $priority = 10)`：`$priority` 越大越靠前；`$append=false` 丢弃之前（优先级更高的）已拼接内容。
- `admin_inject_section_if($cond, $section, $content, bool $append = false, ...)`：注意默认 `$append = false`。
- `admin_inject_default_section($section, $content)`：该 section 一旦有 `admin_inject_section` 注入（无论先后），默认内容失效。
- `admin_section($section, $default, $options)` 输出；`admin_has_section()`。
- `$content` 可为字符串、Renderable/Htmlable 或闭包；闭包接收 `Fluent $options`，`$options->previous` 为之前已拼接内容。

预定义 `Admin::SECTION[...]`（源码 `src/Admin.php`）：`HEAD`、`BODY_INNER_BEFORE/AFTER`、`APP_INNER_BEFORE/AFTER`（随 pjax 刷新）、`NAVBAR_BEFORE/AFTER`、`NAVBAR_USER_PANEL`（位于 `<ul class="nav navbar-nav">` 内，内容应为 `<li>`）、`NAVBAR_AFTER_USER_PANEL`、`LEFT_SIDEBAR_USER_PANEL`、`LEFT_SIDEBAR_MENU`（默认内容由 `Admin::menu()->register()` 以 default section 注入，覆盖它即替换整个菜单）、`LEFT_SIDEBAR_MENU_TOP/BOTTOM`（`Admin::menu()->add()` 注入 BOTTOM）。

## Navbar
在 bootstrap.php 中 `Admin::navbar(function (Navbar $navbar) { $navbar->left(...); $navbar->right(...); })`（仅整页渲染，不要按页在控制器设置）。右侧元素自带 `<ul class="nav navbar-nav"><li class="nav-item">` 结构；元素内 `<script>` 原样输出，需写在 `Dcat.ready()` 中。修改默认用户面板：复制 `resources/views/partials/navbar-user-panel.blade.php` 后注入 `Admin::SECTION['NAVBAR_USER_PANEL']`。
