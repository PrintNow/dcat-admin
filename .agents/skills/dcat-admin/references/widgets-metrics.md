# 图表（ApexCharts）与数据统计卡片（Metrics）

> 覆盖 `Widgets\ApexCharts\Chart`、`Widgets\Metrics\*`（Card/Line/Donut/Bar/RadialBar/Round/SingleRound）、与后端 API 交互、引入第三方图表库。通用组件与布局见 widgets.md；静态资源见 frontend.md。

## ApexCharts\Chart
（源码：src/Widgets/ApexCharts/Chart.php）资源别名 `@apex-charts` 自动加载。
- `Chart::make($selector = null, $options = [])`：首参非字符串时被当作 options。
- 快捷方法 `title/series/labels/colors/stroke/xaxis/yaxis/tooltip/fill/chart/dataLabels()` 均**整体覆盖**该键；`dataLabels(false)` 转为 `['enabled' => false]`。
- 其余配置：`options([...])`（顶层 `array_merge`，非深合并）、`option('plotOptions.bar.horizontal', true)`（点语法单项）。
- 不传选择器时自动输出 `<div id="apex-chart-xxx">`；传已有容器选择器时**只注册 JS、不输出 HTML**（构造时即注册）。
- 自定义图表类：继承 Chart，构造中 `parent::__construct($selector, $options)` 后设默认配置，在 `render()` 里 `option()` 填数据再 `return parent::render()`。

## 与后端交互（handle / parameters / click）
（源码：src/Traits/InteractsWithApi.php，src/Http/Controllers/ValueController.php）类中定义 `handle(Request $request)` 后，渲染时不直接画图，而是 ajax（默认 POST 内置 `dcat-api/value`）取配置再渲染。

```php
class MyAjaxBar extends Chart
{
    public function __construct($userId = null) { parent::__construct(); $this->userId = $userId; }  // 参数必须有默认值

    public function parameters(): array { return ['user_id' => $this->userId]; }   // 随请求发给 handle()

    public function handle(Request $request)
    {
        $days = (int) ($request->get('option') ?: 7);   // 被点击元素的 data-option
        $this->option('series', [['data' => [44, 55, 41]]]);
        // 不返回 → 自动以 valueResult() 返回新配置；也可直接返回自定义响应
    }

    protected function authorize($user): bool { return $user->can('stats'); }   // 可选，内置 HasAuthorization
}

MyAjaxBar::make($userId)->fetching('$("#box").loading()')->fetched('$("#box").loading(false)')
    ->click('.switch-bar');   // 点击元素的 data-* 作为参数重新请求
```
- `request($method, $url, $query = [])` 改请求自定义 URL（经 `admin_url()`）；`fetched` 中可用 `response`。

> ⚠️ ajax 时服务端用 `app(YourChart::class)` **重新实例化**，构造参数全部丢失；上下文必须经 `parameters()` 传递、在 `handle()` 用 `$request->get()` 读。参数来自客户端，涉及数据权限要在 `authorize()`/`handle()` 中校验。

## 配置中嵌入可执行 JS
`JavaScript::make("function () { return {$n}; }")`（`Dcat\Admin\Support\JavaScript`）作为配置值时输出为 JS 函数而非字符串。`ApexCharts\Chart` 用 `option('a.b', JavaScript::make(...))`（没有 `chartOption()`，误调会被 `Widget::__call` 当作 HTML 属性静默吞掉）；Metrics 卡片用 `chartOption()` / `chart()`。

## Metrics\Card
（源码：src/Widgets/Metrics/Card.php）可带下拉、与后端交互的统计卡片，默认**不启用**图表。`admin:install` 生成的 `app/Admin/Metrics/Examples/{TotalUsers,NewUsers,NewDevices,Sessions,ProductOrders,Tickets}.php` 可直接参考。

```php
class TotalUsers extends Card
{
    protected function init()   // 构造函数中调用
    {
        parent::init();
        $this->title('Total Users');
        $this->dropdown(['7' => 'Last 7 Days', '30' => 'Last Month']);
    }

    public function handle(Request $request)
    {
        $this->content(User::where('created_at', '>=', now()->subDays((int) ($request->get('option') ?: 7)))->count());
    }
}
```
- `title()`、`subTitle()`（右上角小标签，与 dropdown 二选一）、`icon()`、`style('primary'|...)`（图标/图表默认色）。
- `header()/content()`：string/Closure/Renderable；可覆写 `renderHeader()/renderContent()`。
- `dropdown(array)`：点击以 `option=key` 请求 `handle()`；首次渲染也请求一次（`option` 为空）。
- `height($v)` 最小高度默认 165；覆写 `parameters(): array` 传自定义参数。
- 构造 `new Card($title = null, $icon = null)`；子类自定义构造函数时参数必须有默认值并调 `parent::__construct()`。

图表：`useChart()` 启用 `$this->chart`（调任一 `chart*` 方法自动启用）；`chart(array|Closure)` 合并配置（Closure 接收 `Chart`）；`chartOption('stroke.curve', 'smooth')`；`chartHeight/chartMarginTop/chartMarginBottom/chartLabels/chartColors()`；覆写 `protected function defaultChartOptions(): array`。**基础 Card 不自动输出图表**，需在自定义 `renderContent()` 中调 `renderChart()`。

## Line / Donut / Bar / RadialBar / Round / SingleRound
`Line`、`Donut`、`RadialBar` 继承 `Card`；`Bar`、`Round` 继承 `RadialBar`；`SingleRound` 继承 `Round`。均已在 `init()` 中 `useChart()` 并自动渲染图表。

| 类 | 默认 chartHeight / 卡片高度 | 专有方法 |
|---|---|---|
| `Line` 面积折线 | 57 / 165 | `chartStraight()`、`chartSmooth()`（默认 smooth） |
| `Donut` 圆环 | 100 / 165 | `contentWidth(int $left, int $right)` 默认 [6, 6] |
| `RadialBar` | 150 / 250 | `footer()`、`contentWidth()` 默认 [2, 10]、`chartPullRight(bool)` |
| `Bar` | 180 / 250 | `chartBarColumnWidth()`、`contentWidth()` 默认 [4, 8] |
| `Round` / `SingleRound` | 210 / 250 | `chartRadialBarSize()`、`chartRadialBarMargin()`、`chartTotal(string $label, int $number)`、`chartLabelName/ValueFontSize()`、`...OffsetY()`、`contentWidth()` 默认 [5, 7] |

- 典型 `handle()`：`$this->content('<h2>89.2k</h2>'); $this->chart(['series' => [['name' => 'x', 'data' => $data]]]);`。
- 无需后端交互时在 `render()` 中填数据（`chartLabels()`、`chart(['series' => ...])`）后 `return parent::render()`。

> ⚠️ `contentWidth()` 只存在于 Donut 和 RadialBar 系列（Bar/Round/SingleRound），`Line` 没有；继承写 `extends` 不是 `extend`。

## 引入其它图表库
库放 `public/vendor/...`，在 `app/Admin/bootstrap.php` 中 `Admin::asset()->alias(...)` 注册或直接 `Admin::js(...)`，然后用 `admin_view()` 模板 + `<script require="@chartjs" init="#myChart">`（`$this` 为元素 jQuery 对象）初始化，pjax 下才会重复执行。细节见 frontend.md。
