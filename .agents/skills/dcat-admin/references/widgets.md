# Widgets 组件与页面布局

> 覆盖 `src/Widgets/**` 通用组件（Card/Box/Tab/Modal/Alert/Table/Dropdown/工具表单/异步加载等）及 `Layout\Content/Row/Column`。图表与 Metrics 见 widgets-metrics.md；`Admin::script`、`admin_view` 的 `<script init>`、Navbar 见 frontend.md；工具表单字段见 form-fields.md；Action 见 actions.md。

## 通用基类 Widget
（源码：src/Widgets/Widget.php）除 DialogForm、DarkModeSwitcher、Widgets\Form 外的 `Widgets\*` 都继承它。
- `X::make(...$params)` = `new X(...)`；`when($value, $cb)` 为真时执行 `$cb($widget, $value)`。
- `options(array)` 合并；`option($key, $value = null)` 读/写（点语法）。
- HTML 属性魔术方法：`->id()`、`->class('a b')`（默认**覆盖**，`->class('c', true)` 追加）、`->style()`（默认**追加**）、任意 `->attrName($v)` 设属性；**`->attrName()` 无参返回属性值（不可链式）**——不存在的方法名会被静默当成属性。
- `getElementSelector()`：JS 选择器；`view($view)` 自定义模板；`runScript(false)` 不自动注册组件 JS（`getScript()` 自取）。
- 子类 `public static $js / $css` 渲染时自动 `Admin::js/css`。

## 页面布局 Content / Row / Column
（源码：src/Layout/Content.php, Row.php, Column.php）控制器注入 `Dcat\Admin\Layout\Content`。

```php
return $content->header('仪表盘')->description('说明')
    ->breadcrumb(['text' => '首页', 'url' => '/'], ['text' => '仪表盘'])
    ->body(function (Row $row) {
        $row->column(6, Card::make('标题', '内容'));
        $row->column(6, function (Column $column) {
            $column->row(function (Row $row) { $row->column(4, 'a'); });   // 可嵌套
        });
    });
```
- `header()` = `title()`；`breadcrumb('文本', 'url', 'icon')` 也可只设一项。
- `body($c)` = `row($c)`：非 Closure 时整行 `col-md-12`；`prepend($c)` 插到最前。
- `full()` 无菜单/导航全屏布局；`view($view)` 自定义布局；`with($k, $v)` 传模板变量；`withConfig($k, $v)` 覆盖本页 `admin.layout`（如 `sidebar_collapsed`）。
- `withSuccess/withError/withWarning/withInfo($title, $message)` 页面顶部提示；`translation($path)` 设翻译文件。
- 静态钩子：`Content::resolving/composing/composed($cb)`。
- `Row::column($width, $content)` 返回 `void`，**不可链式**；`$width` 为 1–12 整数，小于 1 的小数按比例换算（`0.5` → 6）；`noGutters()`。`Column::row()` 在列内追加行，`append()` 追加内容。

> ⚠️ `column()` 传数组宽度 `['md' => 6, 'sm' => 12]` 不可用（`normalizeWidth` 强转为 1），只传整数。`Admin::content(...)` 是 1.x 写法，2.x 用控制器注入 `Content`。

自定义页面：实现 `Renderable`，`render()` 返回 `admin_view('admin.pages.xxx')`（模板内 `<style>`/`<script>`/`<script init>`/`<script require>` 会被提取并在 pjax 下正确执行，见 frontend.md）；模板不要含 `<html>`/`<body>`。

## Card / Box
- `Card::make($title, $content)`；`withHeaderBorder()`、`padding('0 15px')`/`noPadding()`、`tool($html)`（可多次）、`footer()`。content 传 `LazyRenderable` 自动包 `Lazy`，传 `Grid\LazyRenderable` 自动包 `LazyTable` 且启用 simple。（源码：src/Widgets/Card.php）
- `Box::make($title = '', $content = '')`：`collapsable()`、`removable()`、`style('info'|['info','solid'])`、`solid()`、`tool()`。

> ⚠️ `Card::make('标题')` 只传一个参数时被当作**内容**而非标题。

## Alert / Callout
- `Alert::make($content, $title, $style = 'danger')`：`primary/info/success/warning/danger()`、`icon()`、`removable()`。
- `Callout::make($content, $title)`：第三参数默认 `null`，**不设颜色时 class 为 `callout-`（无颜色）**，需显式调 `primary()/info()/...`。

## Tab
（源码：src/Widgets/Tab.php）`add($title, $content, $active = false, $id = null)`（支持 LazyRenderable）、`addLink($title, $href, $active = false)`；`withCard()`、`vertical()`、`theme('primary')`、`tabStyle($cls)`、`noPadding()`、`dropdown([['名称', 'url'], ...])`。内置脚本按 URL hash 切换并写回 hash。

## Table
（源码：src/Widgets/Table.php）构造 `($headers = [], $rows = false, $style = [])`，**只传一个参数时作为 rows**；单参数关联数组 → 键值两列表，嵌套关联数组递归成子表。`setStyle()` 追加 class、`withBorder()`、`depth(int)`。

> 安全：Table/Card/Markdown 等内容 `{!! !!}` 原样输出、**不转义**，用户数据先 `e()`。

## Modal
（源码：src/Widgets/Modal.php）`Modal::make($title = null, $content = null)`；`content()` = `body()`（字符串/Closure/Renderable/LazyRenderable）。
- 尺寸 `sm()/lg()/xl()`（默认 500px，lg 800，xl 1140）；`centered()`、`scrollable()`；`delay(int $ms)` 异步内容加载延迟（默认 10，图表建议 300）。
- `button($html)` 纯文本自动包 `<a>`；`footer()`。
- 事件 `onShow/onShown/onHide/onHidden($js)`、`on($event, $js)`，JS 可用 `target`、`body`、`event`；`onLoad($js)` **仅 body 为 LazyRenderable 时**生效。
- 默认弹窗 HTML 放页面尾部（`Admin::html`），`render()` 只返回按钮；异步/局部渲染场景用 `join()` 让弹窗与按钮一起返回。
- `body(Grid\LazyRenderable)` 自动转 `LazyTable`（simple），`show.bs.modal` 时加载。

## Dropdown
（源码：src/Widgets/Dropdown.php）
```php
Dropdown::make([1 => '分类1', 'd1' => Dropdown::DIVIDER, 3 => '分类3'])   // 分割线给独立键，否则被同号键覆盖
    ->button('分类导航')
    ->map(fn ($label, $key) => "<a href='".admin_url('c/'.$key)."'>{$label}</a>");   // $this 绑定 Dropdown，须返回字符串
```
- `options(array, ?string $title = null)` 可多次调用分组；`click(?string $defaultLabel = null)` 点击后按钮文本变为该项；`buttonClass()`（默认 `btn btn-sm btn-white waves-effect`）、`up()/down()`。
- `divider()` 只在第一个渲染的选项后插一次；任意位置请用 `Dropdown::DIVIDER`。
- 未设 `button()` 且未 `click()` 时只渲染 `<ul class="dropdown-menu">`；选项不含 `</a>` 时自动包 `<a href='javascript:void(0)'>`。

## Radio / Checkbox（非表单字段）
构造 `(?string $name = null, array $options = [], string $style = 'primary')`；`check()`、`inline()`、`disable($values = null)`（无参整体禁用）、`size('sm'|'lg')`、`right('16px')`；Checkbox 另有 `checkAll($excepts = [])`。

> ⚠️ `small()` / `large()` 不存在（被魔术方法当取属性返回 null，断链），用 `->size('sm')`。

## 其他小组件
- `Tooltip::make('.sel')->title()->purple()->right()->maxWidth(300)`：layer.tips，**构造即注册脚本**，无需放入页面；`title` 为空取元素 `data-title`；maxWidth 默认 210。
- `Markdown::make($md)`（`htmlDecode` 默认过滤 `style,script,iframe`）；`Code::make($contentOrFile, $start = 1, $end = 1000)` 数组自动 JSON 美化、`lang('sql')`、`section($file, $line, $context = 5)`；`Dump::make($content)`；`Terminal::call('migrate:status')` 执行 Artisan 并展示。
- `Widgets\Tree::make($nodes)->setTitleColumn('name')->check([1, 3])`：jstree 只读树（默认列 id/name/parent_id）。可编辑用 `$form->tree()`，模型树见 tree.md。
- DarkModeSwitcher：配置 `admin.layout.dark_mode_switch = true` 即自动加入 navbar，无需手动使用。

## 异步加载 Lazy / LazyTable / LazyRenderable
通过 `admin/dcat-api/render` 路由 ajax 渲染，支持按需加载 `static $js/$css`。
- 定义：继承 `Dcat\Admin\Support\LazyRenderable` 实现 `render()`；`$this->id` 读取 payload（`__get` 代理）。
- 传参：`PostChart::make(['id' => 1])` 或 `->payload([...])`；payload 拼进 URL 查询串，**只能放标量/简单数组**。
- 服务端 `new $class()` **无参重新实例化**后从请求回填 payload，构造函数不能有必填参数。
- payload 可被篡改；鉴权在类中 `use Dcat\Admin\Traits\HasAuthorization;` 覆写 `protected function authorize($user): bool`（RenderableController 调 `passesAuthorization()`）。

`Lazy::make($renderable, $load = true)->onLoad($js)`；`load(false)` 后手动触发 `$(selector).trigger('lazy:load')`，完成事件 `lazy:loaded`。Card/Box/Tab/Modal 直接传渲染类实例会自动包 `Lazy`。

> ⚠️ `Lazy` 没有 `getLoadScript()`；`LazyTable::getLoadScript()` 是 protected。手动加载请触发 `lazy:load` / `table:load` 事件。

异步表格：继承 `Dcat\Admin\Grid\LazyRenderable` 实现 `grid(): Grid`。
- `LazyTable::make(UserTable::make()->simple(), $load = true)`；`onLoad()`，事件 `table:load` / `table:loaded`。
- `simple()`：禁用新增/每页条数/批量删除/刷新，筛选器简洁面板，行选择器单击选中。
- 传入 Card/Box/Modal/DialogTable 时**自动 simple**，Tab 不会；不要 simple 就自己包 `LazyTable::make(UserTable::make())`。

## DialogTable
（源码：src/Widgets/DialogTable.php）layer 弹窗异步表格（`selectTable` 字段内部即用它）。构造 `($title = null, ?Grid\LazyRenderable $table = null)`，首参也可直接传表格；自动 simple。`width()` 默认 825px（屏宽 ≤850 全屏）、`button()`、`footer()`、`maxmin(false)`、`resize(false)`、`onShown/onHidden/onLoad()`、`getTable()`。

## 工具表单 Widgets\Form
（源码：src/Widgets/Form.php；提交：src/Http/Controllers/HandleFormController.php）独立表单，无需路由，ajax 提交内置接口。生成：`php artisan admin:form Setting` → `app/Admin/Forms/Setting.php`。

```php
class Setting extends Form
{
    public function handle(array $input)   // $input 已移除内部字段，只保留 form() 中定义的字段
    {
        return $this->response()->success('保存成功')->refresh();
    }

    public function form() { $this->text('name')->required(); }

    public function default() { return ['name' => 'John']; }   // 可选：未 fill 时的初始值
}
// 使用：$content->body(new Card(new Setting()));
```
- 字段同数据表单，支持 `column()/tab()/row()/fieldset()` 布局；`confirm($title, $content)` 提交前确认。
- 构造 `new Setting($data = [], $key = null)` 或 `fill($data)`。
- `disableResetButton()/disableSubmitButton()`、`width($field = 8, $label = 2)`、`action($url)`、`useFormTag(false)`、`validationErrorToastr(false)`；`ajax(false)` 改为原生提交，此时 `handle()` 不会被内置接口调用。
- 覆写 `protected function savedScript()/errorScript()` 返回 JS（可用 `data`/`response`，`return false` 阻止默认行为）。
- 鉴权：覆写 `protected function authorize($user): bool`（已内置 HasAuthorization）。

> ⚠️ 提交时服务端用 `app(FormClass)` 重新实例化 → `form()` → 校验 → `handle($input)`；**构造参数和 `default()` 在提交时不生效**，上下文请用 payload。

### 弹窗 + payload
表单 `implements Dcat\Admin\Contracts\LazyRenderable` 并 `use Dcat\Admin\Traits\LazyWidget;`，即可放进 Modal：
```php
Modal::make()->lg()->title('修改密码')
    ->body(ResetPassword::make()->payload(['id' => $this->getKey()]))   // handle() 中 $this->payload['id']
    ->button('修改密码');
```
- payload 以隐藏字段 `_payload_` JSON 回传，**客户端可篡改**，`handle()` 中务必校验权限。
- 批量操作弹窗：表单加 `$this->hidden('id')->attribute('id', 'reset-password-id')`，BatchAction 中 `Modal::...->onLoad("$('#reset-password-id').val({$this->getSelectedKeysScript()})")`（异步表单用 `onLoad`，非异步用 `onShow`）。

## DialogForm（数据表单弹窗）
（源码：src/Widgets/DialogForm.php）把数据表单 create/edit 页加载到 layer 弹窗提交。`Form::dialog()` 创建，析构时自动注册脚本。
```php
Form::dialog('新增角色')
    ->click('.create-form')            // 按钮选择器；按钮 data-url 优先于 url()
    ->url('auth/roles/create')         // 经 admin_url()
    ->width('700px')->height('650px')  // 默认 ['700px', '670px']
    ->success('Dcat.reload()');        // 也有 saved($js)/error($js)，JS 可用 success, response
```
- `forceRefresh()` 关闭后销毁、下次重新加载；`resetButton(false)`。
- 被加载页自动识别 `_dialog_form_` 参数去掉布局；表单中 `$form->inDialog()` 判断，或 `$form->inDialog(fn ($form) => ...)` 仅弹窗时执行。
