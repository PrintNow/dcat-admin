
<div align="center">
    <img src="resources/dist/images/logo-rectangle.png" height="80"> 
</div>
<br>

<p align="center">
    <a href="https://packagist.org/packages/printnow/laravel-admin" ><img src="https://poser.pugx.org/printnow/laravel-admin/v/stable" /></a> 
    <a href="https://packagist.org/packages/printnow/laravel-admin"><img src="https://img.shields.io/packagist/dt/printnow/laravel-admin.svg?color=" /></a> 
    <a><img src="https://img.shields.io/badge/php-8.1+-59a9f8.svg?style=flat" /></a> 
    <a><img src="https://img.shields.io/badge/laravel-10~13-59a9f8.svg?style=flat" ></a>
</p>

由于原项目([jqhph/dcat-admin](https://github.com/jqhph/dcat-admin))已停止维护，故基于原作者的项目进行二次开发，修复了部分问题，并添加了更多功能。

新增功能如下：

> 更多功能正在持续开发... 欢迎交流评论

- [2025/08/05 增强对枚举字段的渲染支持](https://github.com/PrintNow/dcat-admin/pull/5)
- [@deflinhec : 修正 Grid 以 LazyRenderable 呈現時Group Filter 沒有正確篩選](https://github.com/PrintNow/dcat-admin/commit/4390a8f494c20910828e7c93513d13032f0d01dd)
- [新增 highlightjs 类以支持代码块高亮显示](https://github.com/PrintNow/dcat-admin/commit/52050882eea475b9d3863ace98048404f7d320b7)
- [新增 Viewable 类以支持点击眼睛图标展示隐藏值](https://github.com/PrintNow/dcat-admin/commit/c823a4ee04b808e4c12b7ee5468fecda855d0752)
- [支持 `.5` 列宽展示，如 `col-sm-1.5` 等](https://github.com/PrintNow/dcat-admin/commit/87679261725e1b05c4f6f9e9039d3a6f2d29e604)
- [优化对枚举类型的支持](https://github.com/PrintNow/dcat-admin/commit/7e67e59e1e29302546b5967766e814724fe65e84)
- [优化对 https 站点支持](https://github.com/PrintNow/dcat-admin/commit/c1227a2593f05e5210dd79e5761ac8a6246c83b4)
- [最低 PHP 版本调整为 8.1，并支持 8.4](https://github.com/PrintNow/dcat-admin/commit/70886e3f950ff28e1fe0d104b88a254e58125178)
- [支持 Laravel 12](https://github.com/PrintNow/dcat-admin/commit/062601e6bca6ebc9c3bcc68dbb4c8ed3e1328275)
- [支持 Laravel 13](https://laravel.com/docs/13.x/upgrade)

> [!IMPORTANT]
> 免责声明：
> 尽管我已在**实际生产项目**中使用过该项目，但我无法保证所做的修改和调整绝对无误。因此，在将其应用于你的生产项目环境时，请务必保持谨慎，充分测试后再使用。
> 
> 安装方法：
> `composer require printnow/laravel-admin:@dev`

---

> [!NOTE]
> 以下内容是原 repo README.md 介绍并作出了微调

<p align=""><code>Dcat Admin</code>是一个基于<a href="https://www.laravel-admin.org/" target="_blank">laravel-admin</a>二次开发而成的后台系统构建工具，只需很少的代码即可快速构建出一个功能完善的高颜值后台系统。内置丰富的后台常用组件，开箱即用，让开发者告别冗杂的HTML代码，对后端开发者非常友好。</p>


- [官方网站](http://www.dcatadmin.com)
- [中文文档](https://learnku.com/docs/dcat-admin)
- [English documentions](http://www.dcatadmin.com/docs/en-2.x/quick-start.html)
- [Demo / 在线演示](http://103.39.211.179:8080/admin)
- [Demo源码](https://github.com/jqhph/dcat-admin-demo)
- [Demo源码 (码云)](https://gitee.com/jqhph/dcat-admin-demo)
- [扩展](#)


![](https://cdn.learnku.com/uploads/images/202101/28/38389/YLmL7PLqH7.png!large)


### 功能特性

- [x] 简洁优雅、灵活可扩展的API
- [x] 用户管理
- [x] RBAC权限管理，支持无限极权限节点
- [x] 菜单管理
- [x] 使用pjax构建无刷新页面，支持**按需加载**静态资源，可以无限扩展组件而不影响整体性能
- [x] 松耦合的页面构建与数据操作设计，可轻松切换数据源
- [x] 自定义页面
- [x] 自定义主题配色
- [x] 多主题切换功能，内置多种主题色
- [x] 可轻松构建无菜单栏的独立页面（如可用于构建弹窗选择器等功能）
- [x] 插件功能
- [x] 可视化代码生成器，可根据数据表一键生成增删改查页面
- [x] 数据表格构建工具，内置丰富的表格常用功能（如组合表头、数据导出、搜索、快捷创建、批量操作等）
- [x] 树状表格功能构建工具，支持分页和点击加载
- [x] 数据表单构建工具，内置丰富的表单类型，支持表单异步提交
- [x] 分步表单构建工具
- [x] 弹窗表单构建工具
- [x] 数据详情页构建工具
- [x] 无限层级树状页面构建工具，支持用拖拽的方式实现数据的层级、排序等操作
- [x] 内置丰富的常用页面组件（如图表、数据统计卡片、下拉菜单、Tab卡片、提示工具等）
- [x] `Section`功能（类似`Wordpress`的`Filter`和`blade`模板的`section`标签）
- [x] 异步文件上传表单，支持分块多线程上传
- [x] 多应用
- [ ] 插件市场，只需在管理页面轻轻点击鼠标即可完成插件的安装、更新和卸载等操作


### 环境
 - PHP >= 7.1.0
 - Laravel 5.5.0 ~ 9.*
 - Fileinfo PHP Extension

### 安装

首先需要安装`laravel`框架，如已安装可以跳过此步骤。如果您是第一次使用`laravel`，请务必先阅读文档 [安装 《Laravel中文文档》](https://learnku.com/docs/laravel/8.x/installation/9354) ！
```bash
composer create-project --prefer-dist laravel/laravel 项目名称 9.*
# 或
composer create-project --prefer-dist laravel/laravel 项目名称
```

安装完`laravel`之后需要修改`.env`文件，设置数据库连接设置正确

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=dcat-admin
DB_USERNAME=root
DB_PASSWORD=
```

安装`dcat-admin`


```
cd {项目名称}

composer require printnow/laravel-admin
```

然后运行下面的命令来发布资源：

```
php artisan admin:publish
```

在该命令会生成配置文件`config/admin.php`，可以在里面修改安装的地址、数据库连接、以及表名，建议都是用默认配置不修改。

然后运行下面的命令完成安装：

> 执行这一步命令可能会报以下错误`Specified key was too long ... 767 bytes`，如果出现这个报错，请在`app/Providers/AppServiceProvider.php`文件的`boot`方法中加上代码`\Schema::defaultStringLength(191);`，然后删除掉数据库中的所有数据表，再重新运行一遍`php artisan admin:install`命令即可。

```
php artisan admin:install
```

上述步骤操作完成之后就可以配置`web`服务了，**注意需要把`web`目录指向`public`目录**！如果用的是`nginx`，还需要在配置中加上伪静态配置
```dotenv
location / {
	try_files $uri $uri/ /index.php?$query_string;
}
```

启动服务后，在浏览器打开 `http://localhost/admin`，使用用户名 `admin` 和密码 `admin`登陆。


<a name="extensions"></a>
### 扩展

| 扩展                                        | 描述                              | dcat-admin 版本                             |
| ------------------------------------------------ | ---------------------------------------- |---------------------------------------- |
| [mosiboom/dcat-iframe-tab](https://github.com/mosiboom/dcat-iframe-tab)    | IFRAME TAB标签切换 | 2.x |
| [super-eggs/dcat-distpicker](https://github.com/super-eggs/dcat-distpicker)    | 省市区联动 | 2.x |
| [ueditor](https://github.com/jqhph/dcat-admin-ueditor) | 百度在线编辑器     | 1.x |
| [grid-sortable](https://github.com/jqhph/dcat-admin-grid-sortable) | 表格拖曳排序工具      | 1.x |


### 鸣谢
`Dcat Admin` 基于以下组件:

+ [Laravel](https://laravel.com/)
+ [Laravel Admin](https://www.laravel-admin.org/)
+ [AdminLTE3](https://github.com/ColorlibHQ/AdminLTE)
+ [bootstrap4](https://getbootstrap.com/)
+ [jQuery3](https://jquery.com/)
+ [Eonasdan Datetimepicker](https://github.com/Eonasdan/bootstrap-datetimepicker/)
+ [font-awesome](http://fontawesome.io)
+ [jquery-form](https://github.com/jquery-form/form)
+ [moment](http://momentjs.com/)
+ [webuploader](http://fex.baidu.com/webuploader/)
+ [jquery-pjax](https://github.com/defunkt/jquery-pjax)
+ [Nestable](http://dbushell.github.io/Nestable/)
+ [toastr](http://codeseven.github.io/toastr/)
+ [editor-md](https://github.com/pandao/editor.md)
+ [fontawesome-iconpicker](https://github.com/itsjavi/fontawesome-iconpicker)
+ [layer弹出层](http://layer.layui.com/)
+ [char.js](https://www.chartjs.org)
+ [nprogress](https://ricostacruz.com/nprogress/)
+ [bootstrap-validator](https://github.com/1000hz/bootstrap-validator)
+ [Google map](https://www.google.com/maps)
+ [Tencent map](http://lbs.qq.com/)

### Contributors

#### Code Contributors

This project exists thanks to all the people who contribute. [[Contribute](CONTRIBUTING.md)].
<a href="https://github.com/jqhph/dcat-admin/graphs/contributors"><img src="https://opencollective.com/dcat-admin/contributors.svg?width=890&button=false" /></a>

### Financial Contributors

Become a financial contributor and help us sustain our community. [[Contribute](https://opencollective.com/dcat-admin/contribute)]

#### Individuals

<a href="https://opencollective.com/dcat-admin"><img src="https://opencollective.com/dcat-admin/individuals.svg?width=890"></a>

#### Organizations

Support this project with your organization. Your logo will show up here with a link to your website. [[Contribute](https://opencollective.com/dcat-admin/contribute)]

<a href="https://opencollective.com/dcat-admin/organization/0/website"><img src="https://opencollective.com/dcat-admin/organization/0/avatar.svg"></a>
<a href="https://opencollective.com/dcat-admin/organization/1/website"><img src="https://opencollective.com/dcat-admin/organization/1/avatar.svg"></a>
<a href="https://opencollective.com/dcat-admin/organization/2/website"><img src="https://opencollective.com/dcat-admin/organization/2/avatar.svg"></a>
<a href="https://opencollective.com/dcat-admin/organization/3/website"><img src="https://opencollective.com/dcat-admin/organization/3/avatar.svg"></a>
<a href="https://opencollective.com/dcat-admin/organization/4/website"><img src="https://opencollective.com/dcat-admin/organization/4/avatar.svg"></a>
<a href="https://opencollective.com/dcat-admin/organization/5/website"><img src="https://opencollective.com/dcat-admin/organization/5/avatar.svg"></a>
<a href="https://opencollective.com/dcat-admin/organization/6/website"><img src="https://opencollective.com/dcat-admin/organization/6/avatar.svg"></a>
<a href="https://opencollective.com/dcat-admin/organization/7/website"><img src="https://opencollective.com/dcat-admin/organization/7/avatar.svg"></a>
<a href="https://opencollective.com/dcat-admin/organization/8/website"><img src="https://opencollective.com/dcat-admin/organization/8/avatar.svg"></a>
<a href="https://opencollective.com/dcat-admin/organization/9/website"><img src="https://opencollective.com/dcat-admin/organization/9/avatar.svg"></a>

### License
------------
`dcat-admin` is licensed under [The MIT License (MIT)](LICENSE).
