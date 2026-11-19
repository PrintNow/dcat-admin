# 已知源码问题

整理 skill 编写、文档核对和评测过程中发现的源码 bug（不是文档错误），基于 2.4.7。修复前，skill 中已给出规避写法。

状态说明：
- **已核实**：已对照源码确认，或在评测中被多个 agent 独立发现。
- **待复现**：静态阅读源码得出，尚未实际运行。

修复时请遵循向后兼容原则：先补测试再改，并在 PR 中逐场景对比修复前后的行为。

## 高优先级

需要优先处理：本 fork 引入的回归、常用功能不可用，或会导致报错、误操作。

| # | 问题 | 位置 | 影响 | 状态 |
|---|---|---|---|---|
| 1 | `select()->options(Model::class, 'id', 'name')` 传模型类名失效。重构提交 f676ac3c 删掉了 `use Illuminate\Database\Eloquent\Model;`，`Model::class` 被解析成不存在的 `Dcat\Admin\Form\Field\Model`，类名被当作 ajax URL，带第 2、3 个参数时抛 TypeError | src/Form/Field/Select.php:45 | **本 fork 回归**，原版 2.x 的写法直接报错 | 已修复（2.4.8，#46） |
| 1a | 筛选器 `select()` 传远程 URL（如 `$filter->equal('x')->select('/api/xxx')`）时抛 `Class Dcat\Admin\Grid\Filter\Presenter\Helper not found`；options 为 Collection 时 `instanceof Arrayable` 永远为 false。同样是 f676ac3c 删除了 `use Dcat\Admin\Support\Helper;`、`use Illuminate\Contracts\Support\Arrayable;` | src/Grid/Filter/Presenter/Select.php:97、135 | **本 fork 回归**（2.4.0 起），远程筛选下拉直接报错 | 已修复（2.4.8，#46） |
| 2 | `Show\Field::json()` 只对第一层数组元素调用 `htmlspecialchars`，值中有嵌套数组或对象时抛 TypeError | src/Show/Field.php:409-414 | **本 fork 回归**（2.4.x XSS 修复引入），详情页 500 | 已核实 |
| 3 | `month` 筛选器的日期选择器提交 `YYYY-MM`，查询却用 `whereMonth`，按默认用法永远查不到数据 | src/Grid/Filter/AbstractFilter.php:411-414、src/Grid/Filter/Month.php | 功能不可用 | 已核实 |
| 4 | Action 设置了非空 `href()` 后仍会绑定 `handle()` 请求脚本，点击时既跳转又发 AJAX 请求 | src/Actions/Action.php:189-199、240 | 可能触发意外写操作 | 已核实 |
| 5 | `HasModelOptions::model()` 用 `array_column` 读取 `multipleSelect` 的 ID 列表，得到空数组，多选编辑时无法回显 | src/Traits/HasModelOptions.php | 多选回显失效 | 待复现 |
| 6 | `admin:ext-install` 不带 `--path` 时调用 `Manager::requestDetails()` / `download()`，这两个方法不存在 | src/Console/ExtensionInstallCommand.php:29-35 | 命令 Fatal | 待复现 |
| 7 | `ModelTree::saveOrder()` 写死 `$branch['id']` | src/Traits/ModelTree.php:169 | 主键不是 `id` 的树无法保存拖拽排序 | 待复现 |

## 中优先级

功能静默无效，或行为与 API 语义不符。

| # | 问题 | 位置 | 状态 |
|---|---|---|---|
| 8 | `Grid\Filter` 自己的 `__call` 覆盖了 `Macroable` 的 `__call`：`Filter::macro()` 永远不生效；调用不存在的方法时静默 `return $this`，1.x 写法（如 `$filter->column()`）会被悄悄丢弃 | src/Grid/Filter.php:777-793 | 待复现 |
| 9 | Grid 列 `textarea()` 展示器的 `rows` 选项被忽略，始终是 5 行（`defaultOptions() + $this->options` 中左侧优先） | src/Grid/Displayers/Editable.php:24、Textarea.php | 已核实 |
| 10 | `Tree::composing()` 注册的回调不会触发（Tree 没有调用 `callComposing()`） | src/Tree.php | 已核实 |
| 11 | `Tree::callResolving()` 在构造和 `render()` 中各调用一次，`Tree::resolving` 回调执行两次 | src/Tree.php:163、644 | 待复现 |
| 12 | `Form\Field\Tree::readOnly(bool $value = true)` 忽略参数，始终只读 | src/Form/Field/Tree.php | 待复现 |
| 13 | `Field::default()` 传闭包时，`bindTo($this->data())` 的返回值被丢弃，闭包内 `$this` 没有绑定到行数据；同时 `value($default)` 无参调用闭包，`fn ($form) => ...` 会抛 ArgumentCountError | src/Form/Field.php:615 附近 | 待复现 |
| 14 | `HasMany` 整体设置的 `rules()` 只有在至少一个子字段也有规则时才会执行 | src/Form/Field/HasMany.php:170-172 | 待复现 |
| 15 | `forgetIgnored()` 按数组下标删除，传字段名无效；`removeIgnoredFields()` 也不能取消忽略，目前无法按字段名取消 ignore | src/Form/Concerns/HandleCascadeFields.php 或 Form.php（ignore 相关） | 待复现 |
| 16 | `WhereBetween` 继承了 `toTimestamp()`，但 `condition()` 不处理它，调用后无效果 | src/Grid/Filter/WhereBetween.php | 待复现 |
| 17 | `Layout\Column` 构造时把数组宽度先 `(int)` 强转，`['md' => 6]` 变成 1 | src/Layout/Column.php:30-58 | 待复现 |
| 18 | `Grid\Column` 的 `prepend()` 中判断 `instanceof \StringBackedEnum`，PHP 没有这个类型，条件永远为 false（应为 `\BackedEnum`） | src/Grid/Column/HasDisplayers.php | 已核实（PHPStan class.notFound；修复会改变 backed enum 的显示值，待评估） |
| 19 | `SwitchDisplay::display(string $color = '')` 参数声明为 string，却在内部判断 `instanceof \Closure`，传闭包会 TypeError | src/Grid/Displayers/SwitchDisplay.php | 待复现 |
| 20 | `Dcat.color.alpha('primary', 0.1)` 传颜色名时返回 `rgba(0,0,0,...)`，只有传 hex 才正确 | resources/assets/dcat/js/extensions/Color.js | 待复现 |
| 21 | QuickSearch 语法中日期函数的正则未锚定，`title:update,x` 会被识别为 `whereDate` | src/Grid/Concerns/HasQuickSearch.php:160 | 待复现 |
| 22 | 扩展升级时先调用 `ServiceProvider::update()`，再执行本版本的迁移脚本，`update()` 中无法使用本版本新建的表 | src/Extend/VersionManager.php:54-64 | 待复现（需确认是否为设计行为） |
| 23 | 扩展 `updates/` 目录中的迁移不支持匿名类（Laravel 9+ 默认的迁移写法） | src/Extend/VersionManager.php | 待复现 |
| 32 | `EmbeddedForm::prepareValue()` 遇到没有对应子字段的键时 `$field` 为 null，`method_exists(null, 'prepare')` 在 PHP 8 抛 TypeError；embeds 提交值中混入额外键（如 saving 中与原值合并）会导致保存失败 | src/Form/EmbeddedForm.php:186-194 | 已核实 |
| 33 | `same('password')` 把 `confirmed` 规则追加到对方字段的默认 `rules`，而设置了 `creationRules`/`updateRules` 的字段在对应模式下会整体忽略默认 `rules`，导致一致性校验只剩前端 | src/Form/Field/Text.php:72-73、src/Form/Concerns/HasFieldValidator.php:119-127 | 已核实 |
| 34 | 重写后的 `$.post(options)` 直接执行 `Object.assign(options.data, {_token})`，未传 `data` 时抛 TypeError，请求不会发出 | resources/assets/dcat/js/extensions/Ajax.js:40-42 | 已核实 |
| 35 | 未启用导出时 `handleExportRequest(true)` 读取未定义的 `$scope`，返回 500 | src/Grid/Concerns/HasExporter.php:61-76 | 已修复（2.4.8，#50） |
| 36 | 普通 Widget Form（未 use `LazyWidget`）在请求带 `_payload_` 参数时调用不存在的 `payload()`，任意用户可通过 URL 参数触发 500 | src/Widgets/Form.php `initPayload()` | 已修复（2.4.8，#50） |
| 37 | `File`/`Image` 字段 `deleteFileAndResponse()` 误调被 `UploadField::deleteFile($paths)` 覆盖的方法：未传磁盘时不删除却返回成功，传入磁盘时抛 TypeError | src/Traits/HasUploadedFile.php:76-81、src/Form/Field/UploadField.php | 已修复（2.4.8，#50） |
| 38 | `AuthController` 使用 `HasFormResponse` 但未定义 `CURRENT_URL_NAME`，子类调用 `getCurrentUrl()` 报 Undefined constant | src/Http/Controllers/AuthController.php | 已修复（2.4.8，#50） |
| 39 | `ImageField` 仅适配 intervention/image v2：装 v3（Laravel 11+ 新项目默认）时 `__call` 误报未安装，`thumbnail()` 直接 Class not found | src/Form/Field/ImageField.php:52、79、173 | 已核实 |

## 低优先级

无效选项、样式问题、命令描述与实现不一致。

| # | 问题 | 位置 | 状态 |
|---|---|---|---|
| 24 | `Select::disableSelectAll()`（筛选器）设置的 `selectAll` 变量在视图中未使用，调用无效 | src/Grid/Filter/Presenter/Select.php | 待复现 |
| 25 | `Filter::hiddenResetButtonText()` 生成的 CSS 选择器 `a.reset .d-none d-sm-inline` 有误，`containerClass` 默认为空 | src/Grid/Filter.php:683 | 待复现 |
| 26 | `Grid::scrollbar()` 设置的 `table_scrollbar` 选项在所有视图中都未使用 | src/Grid.php | 待复现 |
| 27 | `Show` 的 `panel()->style()` 设置的变量在内置视图中未使用 | src/Show/Panel.php | 待复现 |
| 28 | `Callout` 未指定样式时输出 class `callout-`，没有颜色 | src/Widgets/Callout.php | 待复现 |
| 29 | `Dcat.color.lighten` 计算结果没有补零，结果小于 `0x100000` 时少位 | resources/assets/dcat/js/extensions/Color.js | 待复现 |
| 30 | `admin:ext-rollback` 的描述说 `ver` 可以省略，但命令签名中它是必填参数 | src/Console/ExtensionRollbackCommand.php | 待复现 |
| 31 | `Admin::js('@组件')` 只取组件的 js，不替换 `{lang}` 等占位符 | src/Layout/Asset.php `get()` | 待复现 |
