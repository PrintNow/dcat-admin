
<div align="center">
    <img src="resources/dist/images/logo-rectangle.png" height="80">
</div>
<br>

<p align="center">
    <a href="https://packagist.org/packages/printnow/laravel-admin"><img src="https://poser.pugx.org/printnow/laravel-admin/v/stable" /></a>
    <a href="https://packagist.org/packages/printnow/laravel-admin"><img src="https://img.shields.io/packagist/dt/printnow/laravel-admin.svg" /></a>
    <a href="https://packagist.org/packages/printnow/laravel-admin"><img src="https://img.shields.io/packagist/v/printnow/laravel-admin.svg" /></a>
    <img src="https://img.shields.io/badge/PHP-8.1~8.5-59a9f8.svg?style=flat" />
    <img src="https://img.shields.io/badge/Laravel-10~13-59a9f8.svg?style=flat" />
</p>

[jqhph/dcat-admin](https://github.com/jqhph/dcat-admin) 的永久维护 fork。原项目已停止维护，本项目持续跟进 Laravel / PHP 新版本兼容、安全修复和功能增强。

## 兼容性

| | Laravel 10 | Laravel 11 | Laravel 12 | Laravel 13 |
|---|:---:|:---:|:---:|:---:|
| PHP 8.1 | ✅ | ✅ | ✅ | ✅ |
| PHP 8.2 | ✅ | ✅ | ✅ | ✅ |
| PHP 8.3 | ✅ | ✅ | ✅ | ✅ |
| PHP 8.4 | ✅ | ✅ | ✅ | ✅ |
| PHP 8.5 | ✅ | ✅ | ✅ | ✅ |

## 安装

```bash
composer require printnow/laravel-admin
```

发布资源并完成安装：

```bash
php artisan admin:publish
php artisan admin:install
```

## 相较原项目的改动

### 兼容性修复

- **Laravel 13**：修复 `redirect()->with()` 将 `MessageBag` 序列化为数组导致视图报错的问题，引入 `SessionMessage` 值对象替代，兼容 PHP session JSON / PHP 两种序列化模式
- **Laravel 12**：新增支持
- **PHP 8.4 / 8.5**：修复隐式 nullable 参数声明等兼容性问题，最低 PHP 版本提升至 8.1

### 安全修复

- `toastr` 消息输出改用 `json_encode()`，防止单引号 / 反斜杠破坏 JS 语法及潜在 XSS

### 功能增强

- 支持通过 `admin.assets_version` 配置项（或 `ADMIN_ASSETS_VERSION` 环境变量）独立控制 JS/CSS 缓存版本号，不再强制跟随框架版本
- 新增 `Viewable` 列展示类，支持点击眼睛图标切换显示 / 隐藏值
- 新增 `HighlightJs` 组件，支持代码块语法高亮
- 增强枚举（`BackedEnum`）字段的渲染支持
- 支持 `.5` 列宽，如 `col-sm-1.5`
- 优化 HTTPS 站点的资源 URL 生成
- **新增 URL 主键加密**：可对路由路径中的主键（如 `/admin/books/{id}/edit`）进行加密（默认 36 位 UUID 样式密文），防止直接暴露数据库主键；支持 `encrypt` / `cipher` / `cipher_salt` 配置与手动加解密 Helper，详见 [加密功能使用说明](docs/url-cipher-guide.md)

### Bug 修复

- 修复 Grid 以 `LazyRenderable` 渲染时 Group Filter 筛选不生效的问题（[@deflinhec](https://github.com/PrintNow/dcat-admin/commit/4390a8f494c20910828e7c93513d13032f0d01dd)）

## 文档

功能用法与原项目一致，参考原项目文档：

- [中文文档](https://learnku.com/docs/dcat-admin)
- [English documentation](http://www.dcatadmin.com/docs/en-2.x/quick-start.html)
- [原项目 README（功能特性、扩展、鸣谢）](README.origin.md)

项目扩展能力专项说明：

- [URL 主键加密功能使用说明](docs/url-cipher-guide.md)

## 扩展包

| 扩展 | 描述                                           | dcat-admin 版本 |
| --- |----------------------------------------------|---------------|
| [zgy-dcat/wangeditor](https://github.com/gy23rm/dcat-wangeditor) | wangEditor 5 富文本编辑器表单字段                      | dev-master    |
| [zgy-dcat/distpicker](https://github.com/gy23rm/dcat-distpicker) | 省市区三级联动（super-eggs/dcat-distpicker 的维护 fork） |dev-master           |
| [zgy-dcat/master-detail](https://github.com/gy23rm/dcat-master-detail) | 左树右表联动（Master-Detail）扩展                      |dev-master           |

## 免责声明

本项目已通过测试并在实际生产环境中使用，但无法保证所有修改绝对无误，上线前请充分测试。

如果你在使用过程中遇到任何问题，欢迎[提交 Issue](https://github.com/PrintNow/dcat-admin/issues)，感谢每一位反馈和贡献的朋友。

## License

[MIT](LICENSE)
