# 表格列与展示器 Grid\Column

> 覆盖 `Dcat\Admin\Grid\Column` 列设置、`display` 回调、条件显示、内置展示器（含本 fork 新增 `viewable`、`highlightjs`）、行内编辑、Str/Collection 链式调用与扩展。相关文件：`grid.md`（表格级 API）、`grid-filter.md`（列过滤器 `filter()`）。

## 列的基础设置

- `sortable(?string $column = null, ?string $cast = null)`：`$column` 指定实际排序字段，支持关联 `profile.age`（仅 hasOne/belongsTo，不支持多层）与 JSON `options->price`；`$cast` 生成 MySQL `CAST(col AS SIGNED)`。例：`->sortable('profile.options->price', 'SIGNED')`。
- `help($message, ?string $style = null, ?string $placement = null)`：style `green/blue/red/purple`，placement `top/left/right/bottom`。
- `width('300px')`、`setAttributes([...])` / `style($s)`（td）、`setHeaderAttributes([...])`（th）、`addHeader($html)`、`hide()`（列选择器中默认隐藏，需 `showColumnSelector()`）、`filter()` / `filterByValue()`（见 `grid-filter.md`）。

## display 回调与当前行数据

`display($callback, ...$params)`：回调签名 `function ($value, Grid\Column $column, ...$params)`，`$this` 绑定当前行（Eloquent 模型或 `Fluent`）；可多次链式，后一个接收前一个返回值；也可传字符串作为固定输出（如 `->display('详情')->expand(...)`）。不存在的字段也可作为列。

- 行序号 `$this->_index`（从 0 开始）；主键 `$this->getKey()`。
- `$column->getValue()` 当前值、`getOriginal()` 数据库原始值、`getOriginalModel()` 当前行。
- `view(string $view)`：Blade 渲染单元格，变量 `$model`、`$value`、`$name`。
> ⚠️ 无 display 回调时列值会被自动 HTML 转义；一旦用 `display()`，回调收到**未转义的原始值**，输出用户内容要自己 `e()`。

## 条件显示 if / when

`if(?Closure $condition = null)` 返回 `Grid\Column\Condition`，条件闭包 `function (Grid\Column $column)`，`$this` 为当前行；省略参数时以当前值真假为条件。只对显示类方法有效（`sortable/help/filter` 等表头方法不受影响）。

```php
$grid->column('config')->if(fn ($column) => $column->getValue())
    ->display($view)->copyable()
    ->else()->display('');
// 等价：->then(fn (Grid\Column $column) => ...)->else(fn (Grid\Column $column) => ...)
$grid->column('status')->if(...)->display(...)->end()->label();   // end() 之后对所有行生效
```

`when($value, callable $callback)`：构建期判断（与行数据无关），`$value` 为真时执行 `$callback($column, $value)`。

## 展示器速查

所有展示器返回 Column，可链式（源码：src/Grid/Column.php 注册表、src/Grid/Displayers/*、src/Grid/Column/HasDisplayers.php）。

| 方法 | 说明 |
|---|---|
| `using(array $map, $default = null)` | 值映射；支持 BackedEnum（取 `->value` 再映射） |
| `label($style = 'primary', ?int $max = null)` / `badge(...)` | `$style` 可为颜色名、`#hex`、或 `[值 => 颜色, 'default' => 颜色]`（按**原始值**取色）；数组值时 `$max` 截断个数 |
| `dot(array $map = [], $default = 'default')` | 前置彩色圆点，按原始值取色 |
| `bool(array $map = [], $default = false)` | ✓/✗；`$map` 如 `['Y' => true, 'N' => false]` |
| `bold($color = null)` / `button($style = 'primary')` | |
| `link($href = '', $target = '_blank')` | `$href` 可为闭包 `fn ($value) => ...`（`$this` 为行） |
| `image($server = '', int $width = 200, int $height = 200)` | 支持数组多图；相对路径加 `$server` 前缀，否则 `Storage::disk(config('admin.upload.disk'))->url()` |
| `downloadable($server = '', $disk = null)` | 下载链接（支持数组） |
| `progressBar($style = 'primary', $size = 'sm', $max = 100)` | |
| `limit(int $limit = 100, string $end = '...')` | 截断（可点击展开）；数组则取前 N 项 |
| `copyable()` | |
| `viewable()` | **本 fork 新增**：默认显示 `******`，点眼睛图标切换明文（密钥、手机号等） |
| `highlightjs(string $language = 'json', array $styles = ['max-height: 180px', 'max-width: 300px'], string $version = '11.11.1')` | **本 fork 新增**：highlight.js 高亮；数组值先 `json_encode(JSON_PRETTY_PRINT)`；资源从 jsDelivr CDN 加载 |
| `qrcode($formatter = null, $width = 150, $height = 150)` | `$formatter` 闭包 `function ($original)`（`$this` 为行）返回二维码内容 |
| `table($titles = [])` | 二维数组渲染为表格；`$titles` 为 `['key' => '标题']` 或字段名列表 |
| `explode(string $d = ',')` / `prepend($v)` / `append($v)` | `prepend/append` 可传闭包 `function ($value, $original, $column)` |
| `long2ip($default = null)` / `gravatar($size = 30)` | |
| `expand` / `modal` / `showTreeInDialog` | 见下节 |
| `switch` `switchGroup` `select` `radio` `checkbox` `editable`/`input` `textarea` | 行内编辑，见下 |
| `orderable()` / `tree(bool $showAll = false, bool $sortable = true, $defaultParentId = null)` | 见下 / `grid.md` 树状表格 |
| `action(string\|RowAction $action)` | 单元格渲染为 RowAction（须继承 `Dcat\Admin\Grid\RowAction`，否则抛异常）；Action 内可用 `$this->row`、`getKey()`、`$this->column`、`$this->parent`（写法见 `actions.md`） |
| `displayUsing(string $displayerClass, array $args = [])` | 直接用某个 AbstractDisplayer 类 |

颜色名用 `Dcat\Admin\Color` 内置名（`primary`、`success`、`danger`、`warning`、`info`、`gray`、`dark`、`custom`、`blue`、`purple` 等）；label/badge 也接受 `Admin::color()->danger()` 返回的 hex。

## 展开 / 弹窗 / 树形弹窗

**expand($callbackOrButton = null)**：
- 闭包 `function (Grid\Displayers\Expand $expand)`，`$this` 为当前行，返回 HTML/Renderable，或返回 `LazyRenderable` 实例异步加载；`$expand->button('详情')` 设按钮文字（默认按钮文字为当前列值，可先 `->display('详情')`）。
- 传 `LazyRenderable` 实例或类名：异步加载，渲染类中 `$this->key` 为当前行主键，其它参数经 `make([...])` 传入后以 `$this->xxx` / `$this->payload` 读取。传普通字符串：作为按钮文字。

```php
class PostTable extends \Dcat\Admin\Support\LazyRenderable
{
    public function render() { /* Post::where('user_id', $this->key)->where('type', $this->post_type) ... */ }
}
$grid->column('posts')->display('查看')->expand(PostTable::make(['post_type' => 1]));
```

工具表单（`Widgets\Form` + `implements Dcat\Admin\Contracts\LazyRenderable` + `use Dcat\Admin\Traits\LazyWidget`）同样可传给 `expand()` / `modal()`，见 `widgets.md`。

**modal($title = '', $callback = null)**：只传一个参数时视为 `$callback`；两个参数为 `(标题, 内容)`。闭包 `function (Grid\Displayers\Modal $modal)`，`$this` 为当前行；`$modal->title(string)`、`xl()`（默认 lg）、`icon('fa-xxx')`（默认 `fa-clone`，自动加 `fa ` 前缀）**无返回值不可链式**。LazyRenderable 会自动注入 `key`。例：`->modal('文章', PostTable::make(['post_type' => 2]))`。

**showTreeInDialog($callbackOrNodes = null)**：传节点数组（当前列值为已选节点 ID 列表），或闭包 `function (Grid\Displayers\DialogTree $tree)`：`$tree->nodes($nodes)->setIdColumn('id')->setTitleColumn('title')->setParentColumn('parent_id')`（默认 id/name/parent_id）`->title('权限')->area('600px', '600px')->rootParentId(0)`，`checkAll()`。

## 行内编辑

所有行内编辑都向 `{resource}/{主键}` 发 PUT，由控制器 `update()` → `form()` 保存，**`form()` 中必须定义同名字段**，否则不保存。`$refresh = true` 时成功后刷新页面。

```php
$grid->column('title')->editable();                 // = input()；editable(true) 即 ['refresh' => true]
$grid->column('phone')->input(['mask' => '99999999999', 'refresh' => false]); // mask 为 inputmask 配置
$grid->column('remark')->textarea();   // 固定 5 行：传入的 rows 会被 defaultOptions() + options 丢弃
$grid->column('status')->switch('success', true);   // switch(string $color = '', $refresh = false)
$grid->column('flags')->switchGroup(['hot' => '热门', 'image.show' => '显示图片']); // 支持关联字段
$grid->column('flags')->switchGroup(['is_new', 'is_hot'], '', true);   // 第 2 参是颜色，第 3 参才是 refresh
$grid->column('type')->select([1 => 'A', 2 => 'B'], true);   // select 也可传闭包 function ($row)（$this 为展示器）
$grid->column('tags')->checkbox([1 => 'php', 2 => 'go'], true);  // radio()/checkbox() 签名 ($options = [], $refresh = false)
```

> ⚠️ `switch()` / `switchGroup()` 的颜色只接受内置颜色名（如 `'success'`），传 `#333` 或 `Admin::color()->info()`（hex）无效并回退为 primary。`switchGroup([...], true)` 的 `true` 会被当作颜色。`radio()` / `checkbox()` 不支持闭包参数（仅 select 支持）。

开关值不是 0/1 时在 form 侧转换：`$form->switch('status')->customFormat(fn ($v) => $v == '打开' ? 1 : 0)->saving(fn ($v) => $v ? '打开' : '关闭');`

## 排序 orderable

`orderable()` 显示上/下移动按钮，请求 `{resource}/{key}` 带 `_orderable=1|0`，由 Form 调仓库 `moveOrderUp()/moveOrderDown()`；模型必须 `implements Spatie\EloquentSortable\Sortable` + `use SortableTrait`（`spatie/eloquent-sortable` 已是本包依赖），如 `protected $sortable = ['order_column_name' => 'order', 'sort_when_creating' => true];`，并 `$grid->model()->orderBy('order')`。层级数据可改用 `ModelTree` + `implements Sortable` 配合 `->tree()`。

## Str / Collection 链式调用

未注册的方法名会被转发：值为字符串时调 `Illuminate\Support\Str::方法($value, ...$args)`；数组/Arrayable 时调 `collect($value)->方法(...$args)`；其它类型原样返回。例：`->upper()->limit(30)`、`->pluck('name')->implode(', ')`、`->display(fn ($v) => json_decode($v, true))->map(fn ($p) => 'https://cdn/'.$p)->image()`。

> ⚠️ `$grid->title()->title()` 不可用——`title()` 是 Grid 自身方法；应写 `$grid->column('title')->title()`。

## 扩展列

在 `app/Admin/bootstrap.php` 注册；可运行 `php artisan admin:ide-helper` 生成 IDE 提示。

```php
use Dcat\Admin\Grid\Column;

// 1. 闭包展示器：首参为当前值，其余为调用参数；$this 为当前行
Column::extend('color', fn ($value, $color) => "<span style='color: {$color}'>{$value}</span>");
$grid->column('title')->color('#ccc'); // skill-check:ignore

// 2. 展示器类：继承 Grid\Displayers\AbstractDisplayer，实现 display(...$args)；
//    可用 $this->value / row / column / grid / getKey() / resource()；静态 $js / $css 自动加载
Column::extend('popover', Popover::class);

// 3. macro：普通方法扩展，闭包参数就是调用参数（不含 $value），$this 为 Column
Column::macro('myHeader', function ($p1) { return $this->addHeader(new MyHeader($this, $p1)); });
```

> ⚠️ `Column::define()` 不存在（1.x 残留），用 `Column::extend()`。`macro` 闭包收不到 `$value`，需要当前值用 `extend`。同名时已注册的展示器优先于 macro。
