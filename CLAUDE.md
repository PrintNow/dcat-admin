# CLAUDE.md

本文件为 AI 编码代理（Claude Code、Codex 等）在本仓库工作时提供指引。`AGENTS.md` 是指向本文件的符号链接。

## 项目概述

Dcat Admin（`printnow/laravel-admin`）是一个 Laravel 后台管理面板构建包，是 `jqhph/dcat-admin` 的永久维护分支。提供快速 CRUD 脚手架，包含数据表格、表单、详情页、树形结构、RBAC 权限管理和插件系统。前端基于 AdminLTE 3 + Bootstrap 4 + jQuery 3。

支持 Laravel 10–13，PHP 8.1+（以 `composer.json` 为准）。

## 常用命令

```bash
# 静态分析（PHPStan）
composer phpstan

# 运行测试（PHPUnit — 主要是 Dusk 浏览器测试）
composer test

# Dusk 浏览器测试（需要一个已安装本包的 Laravel 应用并运行中）
php artisan dusk

# 编译前端资源（Laravel Mix）
npm run dev          # 开发环境
npm run production   # 生产环境
npm run watch        # 监听模式

# 将包安装到 Laravel 应用
php artisan admin:install

# 代码生成器（CRUD 脚手架没有 artisan 命令，在后台 helpers/scaffold 页面中使用，需开启 admin.helpers.enable）
php artisan admin:form FormName          # 工具表单
php artisan admin:action                 # Action 类
php artisan admin:ext-make vendor/name   # 扩展骨架

# 发布包资源（配置、视图、迁移文件、语言包）
php artisan admin:publish

# 菜单缓存（修改菜单后重建）
php artisan admin:menu-cache
```

## 架构

### 命名空间与自动加载

- 根命名空间：`Dcat\Admin\` → `src/`
- 辅助函数自动加载自 `src/Support/helpers.php`
- 服务提供者：`Dcat\Admin\AdminServiceProvider`（Laravel 自动发现）

### 核心组件（src/ 顶层）

| 类 | 职责 |
|---|---|
| `Admin.php` | 主门面/服务单例 — 认证、菜单、导航栏、版本号、页面区域 |
| `Grid.php` | 数据表格构建器，支持列定义、筛选器、导出、批量操作 |
| `Form.php` | 表单构建器，60+ 种字段类型，支持嵌套表单、标签页、分步表单 |
| `Show.php` | 详情/只读页面构建器 |
| `Tree.php` | 树形结构展示构建器 |

### 关键子目录

- **`Form/Field/`** — 表单字段类型（Text、Select、Image、File、Editor、Date、HasMany、Embeds、KeyValue 等；注册表见 `Form.php`）
- **`Grid/Filter/`** — 筛选类型（Like、Equal、Between、Date、Scope、Group 等）
- **`Grid/Displayers/`** — 列展示渲染器（Badge、Editable、Image、QRCode、Copyable 等；注册表见 `Grid/Column.php`）
- **`Http/Controllers/`** — 控制器（Auth、Dashboard、User、Role、Permission、Menu、Scaffold 等）
- **`Http/Middleware/`** — 中间件：`admin.auth`、`admin.pjax`、`admin.permission`、`admin.bootstrap`、`admin.session`、`admin.upload`、`admin.app`
- **`Console/`** — Artisan 命令，模板文件在 `Console/stubs/`
- **`Scaffold/`** — 代码生成器（Controller、Form、Grid、Model、Show、Migration、Repository 创建器），模板文件在 `Scaffold/stubs/`
- **`Models/`** — Eloquent 模型：Administrator、Role、Permission、Menu、Setting、Extension
- **`Extend/`** — 插件/扩展系统（Manager、ServiceProvider、Setting、Version 管理）
- **`Repositories/`** — 仓库模式（EloquentRepository、QueryBuilderRepository）
- **`Widgets/`** — 可复用组件（Form、Table、Tree、Box、Card、Modal、DialogForm、ApexCharts、Metrics 等）
- **`Traits/`** — 共享 Trait（HasAssets、HasPermissions、HasHtml、InteractsWithRenderApi 等）
- **`Support/`** — 工具类：Helper、Setting、WebUploader、Translator、Context、Composer
- **`Layout/`** — 布局系统：Asset、Content、Column、Row、Menu、Navbar、SectionManager
- **`Octane/`** — Laravel Octane 兼容（FlushAdminState 监听器）

### 中间件栈

`admin` 中间件组（在 AdminServiceProvider 中注册）应用于所有后台路由：
`admin.auth` → `admin.pjax` → `admin.bootstrap` → `admin.permission` → `admin.session` → `admin.upload`

### 容器单例

服务容器中注册的关键服务：`admin.app`、`admin.asset`、`admin.color`、`admin.sections`、`admin.extend`、`admin.navbar`、`admin.menu`、`admin.context`、`admin.setting`、`admin.web-uploader`、`admin.translator`。

### 测试

测试代码位于 `tests/`，主要使用 Laravel Dusk 进行浏览器测试，依赖 MySQL。Feature 测试覆盖安装和页面区域功能。根目录没有 `phpunit.xml`，只有 `phpunit.dusk.xml`。

### 前端

前端资源通过 Laravel Mix（`webpack.mix.js`）编译。源文件在 `resources/assets/`，编译产物在 `resources/dist/`。前端技术栈为 AdminLTE 3、Bootstrap 4、jQuery 3，内置多个插件（editor-md、webuploader、datetimepicker、fontawesome-iconpicker 等）。

### 国际化

语言包目录：`resources/lang/`，包含 `en`、`zh_CN`、`zh_TW` 三种语言。

### 代码风格

- StyleCI 预设：`laravel`（配置在 `.styleci.yml`）
- EditorConfig：4 空格缩进、UTF-8、LF 换行符
- 遵循 Laravel 代码规范

## 编码规范

### PHP 8.0/8.1 现代化（已全面采用）

- **字符串判断**：使用 `str_starts_with()`、`str_contains()`、`str_ends_with()`，不要用 `strpos`/`mb_strpos`/`substr` 做前缀/包含/后缀判断。**改造旧代码时先确认原语义**：只有 `strpos(...) === 0` 才等价于 `str_starts_with()`；`!== false`、`! strpos(...)` 等写法通常对应 `str_contains()`，不能机械替换（Dropdown、Asset 的回归都源于此）
- **match 表达式**：简单的 `switch`（每个 case 直接 return/赋值）应使用 `match` 替代；复杂 fall-through 逻辑保留 `switch`
- **call_user_func**：使用 PHP 8.0+ 直接调用语法 `$fn($args)`、`$obj->method(...$args)`；属性闭包需加括号 `($this->builder)(...)` 避免歧义
- **数组判断**：使用 `array_is_list()` 替代手动 `array_keys() === range()` 判断
- **uniqid**：始终传入第二个参数 `true` 以获得更高唯一性，如 `uniqid('prefix-', true)`。**但用作 DOM id/class 或 CSS/jQuery 选择器的值必须用 `Helper::uniqueSelector('prefix-')`**——`uniqid(..., true)` 的结果含 `.`，会让选择器匹配不到元素

### 安全实践

- **HTML 转义**：`htmlspecialchars()` 和 `htmlentities()` 必须使用 `ENT_QUOTES | ENT_HTML5` 标志，不要用 `ENT_NOQUOTES`
- **禁止 extract()**：不允许使用 PHP 内置 `extract()` 函数，用数组访问代替
- **in_array 严格比较**：涉及用户输入、ID 比较等场景，`in_array()` 必须传第三个参数 `true`

### 性能优化模式

- **循环内避免 array_merge**：收集到数组后用 `array_merge(...$arrays)` 一次性合并，或用 `array_push($arr, ...$spread)`
- **展开运算符**：`[...$a, ...$b]` 替代 `array_merge($a, $b)`（适用于关联数组合并）
- **+= 不替代 array_merge**：`+=` 只添加左侧不存在的键，会静默丢弃同名键，验证属性/消息合并场景必须用 `array_merge`

### 静态分析（PHPStan）

- **禁止 baseline**：不要运行 `phpstan --generate-baseline`，也不要新建 `phpstan-baseline.neon`（CI 会拒绝）。baseline 会把真实 bug 一起冻结，f676ac3c 删除 import 导致的回归就曾被它掩盖。
- 新报错优先修复；确属误报（可选依赖已有 `class_exists` 保护、`Closure::call` 运行时绑定等）时，在 `phpstan.neon` 的 `ignoreErrors` 中按 `identifier` + `path`（尽量加 `message`）添加，并在上方注释写明理由。
- Eloquent 魔术方法、动态属性等误报，优先通过 `@mixin`、`@property`、`@method` 等 docblock 消除，不要直接忽略。
