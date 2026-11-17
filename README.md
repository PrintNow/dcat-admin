
<div align="center">
    <img src="resources/dist/images/logo-rectangle.png" height="80">
</div>
<br>

<p align="center">
    <a href="https://packagist.org/packages/printnow/laravel-admin"><img src="https://poser.pugx.org/printnow/laravel-admin/v/stable" /></a>
    <a href="https://packagist.org/packages/printnow/laravel-admin"><img src="https://img.shields.io/packagist/dt/printnow/laravel-admin.svg" /></a>
    <img src="https://img.shields.io/badge/PHP-8.1~8.5-59a9f8.svg?style=flat" />
    <img src="https://img.shields.io/badge/Laravel-10~13-59a9f8.svg?style=flat" />
</p>

[jqhph/dcat-admin](https://github.com/jqhph/dcat-admin) 的永久维护 fork。原项目已停止维护，本项目持续跟进 Laravel / PHP 新版本兼容、安全修复和功能增强。

与原版 2.x 保持 API 兼容：命名空间（`Dcat\Admin`）、数据表结构、配置项均未改变，已有项目可直接替换。

- [安装](#安装)
- [从原版 dcat-admin 迁移](#从原版-dcat-admin-迁移)
- [相较原项目的改动](#相较原项目的改动)
- [文档](#文档)

## 兼容性

| | Laravel 10 | Laravel 11 | Laravel 12 | Laravel 13 |
|---|:---:|:---:|:---:|:---:|
| PHP 8.1 | ✅ | ✅ | ✅ | ✅ |
| PHP 8.2 | ✅ | ✅ | ✅ | ✅ |
| PHP 8.3 | ✅ | ✅ | ✅ | ✅ |
| PHP 8.4 | ✅ | ✅ | ✅ | ✅ |
| PHP 8.5 | ✅ | ✅ | ✅ | ✅ |

> 实际可用的组合以 Laravel 本身对 PHP 版本的要求为准（如 Laravel 12 需要 PHP 8.2+）。

## 安装

全新项目：

```bash
composer require printnow/laravel-admin

# 发布配置、语言包、迁移文件和前端资源
php artisan admin:publish

# 执行迁移并初始化管理员账号（admin / admin）
php artisan admin:install
```

## 从原版 dcat-admin 迁移

适用于正在使用 `dcat/laravel-admin` 2.x 的项目，**业务代码无需修改**。

前提：项目已升级到 **Laravel 10+、PHP 8.1+**。如果还在更低版本上，请先完成 Laravel 升级。原版 1.x 不在迁移范围内。

### 1. 替换 Composer 依赖

```bash
# 从 composer.json 中移除原版（--no-update：避免依赖原版的扩展阻止移除）
composer remove dcat/laravel-admin --no-update

# 安装本项目
composer require printnow/laravel-admin
```

> [!IMPORTANT]
> 不要跳过第一步直接 `composer require`，否则原版不会被卸载，两个包会同时存在于 `vendor` 中，`Dcat\Admin\*` 类实际加载哪一份是不确定的。

本项目在 `composer.json` 中声明了 `"replace": {"dcat/laravel-admin": "self.version"}`，依赖原版的第三方扩展会自动使用本项目，不会再把原版装回来。

完成后确认原版已被移除：

```bash
composer show dcat/laravel-admin   # 应提示未安装，或显示由 printnow/laravel-admin 替代
ls vendor/dcat/laravel-admin       # 目录应不存在
```

### 2. 更新前端资源

```bash
php artisan admin:publish --assets --force
```

### 3. 检查已发布 / 已覆盖的文件

| 项目 | 是否需要处理 |
|---|---|
| 数据库 | 无新增迁移，无需执行 `migrate` |
| `config/admin.php` | 无需修改。可选新增 `assets_version` 配置，见[功能增强](#功能增强) |
| 语言包 | 如果之前发布过语言包（`lang/vendor/admin`），需手动补充新增的 `click_to_view` 键；或执行 `php artisan admin:publish --lang --force` 覆盖（会丢失自定义翻译） |
| 视图 | 如果之前覆盖过 `resources/views/vendor/admin` 下的视图，请对比以下文件的改动，否则对应修复不会生效：`partials/alerts`、`partials/toastr`、`partials/exception`、`form/multipleselect` |
| `doctrine/dbal` | 本项目不再依赖 `doctrine/dbal`。如果你的项目代码（如 Laravel 10 下迁移文件中的 `->change()`）依赖它，请在项目中显式 `composer require doctrine/dbal` |

### 4. 清理缓存

```bash
php artisan optimize:clear
```

## 相较原项目的改动

### 兼容性

- 支持 Laravel 11 / 12 / 13，最低要求提升至 Laravel 10、PHP 8.1
- **Laravel 13**：修复 `redirect()->with()` 将 `MessageBag` 序列化为数组导致视图报错的问题，引入 `SessionMessage` 值对象替代，兼容 session 的 JSON / PHP 两种序列化模式
- **PHP 8.4 / 8.5**：修复隐式 nullable 参数声明、动态属性等弃用警告
- **Octane / FrankenPHP**：修复常驻进程下主题 CSS 路径被重复追加的问题
- 移除 `doctrine/dbal` 依赖

### 安全修复

- 修复 `Show` 详情页 `json()` 方法可能导致的 XSS
- `toastr` 消息输出改用 `json_encode()`，防止单引号 / 反斜杠破坏 JS 语法及潜在 XSS
- 统一 HTML 转义标志为 `ENT_QUOTES | ENT_HTML5`（如 `Code` 组件原先使用 `ENT_NOQUOTES`）
- 移除 `extract()` 调用，避免变量污染

### 功能增强

- 新增 `admin.assets_version` 配置项（或 `ADMIN_ASSETS_VERSION` 环境变量），可独立控制 JS/CSS 缓存版本号，不再强制跟随框架版本
- 新增 `Viewable` 列展示类，点击眼睛图标切换显示 / 隐藏值
- 新增 `HighlightJS` 列展示类，支持代码块语法高亮
- 支持 `BackedEnum` 枚举：表格列取值、数据仓库字段渲染、`Select` 筛选器
- 支持 `.5` 列宽，如 `col-sm-1.5`（目前仅 `blue` 主题的预编译 CSS 包含这些样式）
- 优化 HTTPS 站点的资源 URL 生成（推荐配置 `TrustProxies`，`admin.https` 配置已标记为废弃）

### Bug 修复

- 修复表单隐藏字段被渲染两次、`MultipleSelect` 提交空值，导致提交参数重复的问题
- 修复 `Radio`、`Checkbox`、`Switch` 等组件的 `name` 属性中下划线被编码为 `&lowbar;`，导致表单提交异常的问题
- 修复 Grid 以 `LazyRenderable` 渲染时 Group Filter 筛选不生效的问题（[@deflinhec](https://github.com/PrintNow/dcat-admin/commit/4390a8f494c20910828e7c93513d13032f0d01dd)）
- 修复 `Scope` 筛选器条件方法参数名错误的问题
- 修复管理员用户保存密码的逻辑问题

### 其他

- 基于 PHP 8.0 / 8.1 新特性进行现代化改造（`match`、`str_contains` 等），并优化多处循环内数组合并的性能
- 引入 PHPStan 静态分析

## 文档

功能用法与原项目一致，请参考原项目文档：

- [中文文档](https://learnku.com/docs/dcat-admin)
- [English documentation](http://www.dcatadmin.com/docs/en-2.x/quick-start.html)
- [原项目 README（功能特性、扩展、鸣谢）](README.origin.md)

## 免责声明

本项目已通过测试并在实际生产环境中使用，但无法保证所有修改绝对无误，上线前请充分测试。

如果你在使用过程中遇到任何问题，欢迎[提交 Issue](https://github.com/PrintNow/dcat-admin/issues)，感谢每一位反馈和贡献的朋友。

## License

[MIT](LICENSE)
