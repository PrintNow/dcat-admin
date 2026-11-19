# 表格筛选：Filter / 列筛选 / 快捷搜索 / 规格选择器

> 覆盖 `$grid->filter()` 查询过滤器（筛选类型与 Presenter）、`$grid->column()->filter()` 列筛选、`$grid->quickSearch()` 快捷搜索、`$grid->selector()` 规格选择器。相关文件：grid.md（表格级 API）、grid-column.md（列与展示器）。

## 1. 开启与容器设置

Grid 层（源码：src/Grid/Concerns/HasFilter.php）：`filter(Closure $cb)`（不传参返回 `Grid\Filter`）、`disableFilter()`/`showFilter()`（禁用后 scope 仍可在按钮下拉中用）、`disableFilterButton()`/`showFilterButton()`、`expandFilter()`。

`Grid\Filter` 容器方法（源码：src/Grid/Filter.php）：

| 方法 | 说明 |
|---|---|
| `rightSide()` / `panel()` | 默认右侧滑出 / 表格上方面板；panel 需给每项设 `width()`（默认 10 列，会每行一个） |
| `mode(?string $mode)` | 不传返回当前模式；`Filter::MODE_PANEL` / `MODE_RIGHT_SIDE` |
| `expand(bool $value = true)` | 未调用 `expand()` 时，存在筛选条件会自动展开（panel 模式下即使 `expand(false)` 也会展开） |
| `view($v)` | 默认 `admin::filter.right-side-container` / `admin::filter.container` |
| `withoutInputBorder()`、`withBorder()`/`withoutBorder()`、`style()`、`padding($top='15px',$right='15px',$bottom='5px',$left='')`、`noPadding()` | 样式 |
| `disableResetButton()`、`disableCollapse()`、`setAction(string $url)` | |
| `use(AbstractFilter $filter)` | 直接添加筛选器实例 |
| `input($key = null, $default = null)` | 读当前筛选输入（已去掉 grid 名前缀） |
| `filters()` / `scopes()` / `getCurrentScope()` | |

> ⚠️ `Grid\Filter::__call()` 对**未知方法直接 `return $this` 不报错**。1.x 的 `$filter->column(1/2, function ($filter) {...})`（闭包根本不执行，里面的筛选项全部丢失）、`$filter->disableIdFilter()` 都静默无效；拼错筛选类型名同样静默。

## 2. 筛选类型

签名均为 `$filter->xxx($column, $label = '')`（label 空时取 `admin_trans_field`），返回 `Dcat\Admin\Grid\Filter\Xxx`。

| 方法 | 查询 / 备注 |
|---|---|
| `equal` `notEqual` `like` `ilike`(PostgreSQL) | `=` `!=` `like %v%` `ilike %v%` |
| `startWith` / `endWith` | `like v%` / `like %v`；可链 `->ilike()` |
| `gt` / `lt` / `ngt` / `nlt` | `>` / `<` / `<=` / `>=`（ngt = not greater than） |
| `between` | `whereBetween`；只填一端时 `>=` 或 `<=`；默认宽度 12 |
| `in` / `notIn` | 值为数组或逗号分隔字符串；默认宽度 12；配 `multipleSelect()`/`checkbox()` |
| `date` / `day` / `month` / `year` | `whereDate`/`whereDay`/`whereMonth`/`whereYear`，自动用对应日期选择器；month 见下 |
| `findInSet` | `FIND_IN_SET(?, col)`，不支持关联字段 |
| `where($column, Closure $query, $label = '')` | 自定义；**label 在第三位** |
| `whereBetween($column, Closure $query, $label = '')` | 自定义范围 |
| `group($column, ?Closure $builder = null, $label = '')` | 同一字段多种操作符 |
| `hidden($name, $value)` / `newline()` | 隐藏 input（不参与查询）/ panel 布局强制换行 |

**between**：`->datetime($options = [])`（默认 format `YYYY-MM-DD HH:mm:ss`）、`->date()`、`->time()`、`->toTimestamp()`（提交值 `strtotime()` 后查询，字段存 int 时）均返回 Between 自身，可任意顺序链式。只支持文本输入和日期时间选择，不能接 `select()` 等 Presenter。
> ⚠️ `toTimestamp()` **只存在于 `between`**（及列筛选 `Grid\Column\Filter\Between`）。`$filter->equal('col')->datetime()->toTimestamp()` 抛 `Call to undefined method`；单值时间戳比较用 `where()` 自行 `strtotime($this->input)`。

> ⚠️ `month` 筛选器（及 `->month()` Presenter）默认选择器格式 `YYYY-MM`，但查询是 `whereMonth`，Laravel 把 `2024-05` 格式化为 `2024`，永远查不到。按月份查：`$filter->month('created_at')->datetime(['format' => 'MM'])`；按"年-月"查用 `where()` 或 `between()->date()`。（源码：src/Grid/Filter/AbstractFilter.php `month()`、src/Grid/Filter/Month.php）

**where**：闭包绑定到筛选器实例，`$this->input` 为输入值（`multipleSelect` 时为数组）；`$column` 只作表单 name，不必是真实字段；闭包被包进 `$query->where(function)`，内部 `orWhere` 自动分组。

```php
$filter->where('search', function ($query) {
    $query->where('title', 'like', "%{$this->input}%")->orWhere('content', 'like', "%{$this->input}%");
}, '标题或内容');
```

**whereBetween**：`$this->input` 为 `['start' => ..., 'end' => ...]`（未填一端可能不存在）；可接 `datetime()/date()/time()`。它继承了 `toTimestamp()` 但 `condition()` 不处理时间戳，调用无效，需在闭包里自行转换。

**group**：输入框左侧下拉选操作符，`$builder` 接收 `Grid\Filter\Group`：

```php
$filter->group('rate', function (Grid\Filter\Group $group) {
    $group->equal('等于');
    $group->where('自定义', function ($query) {          // label 在前且必填
        $query->where('rate', '>=', $this->input);
    });
}, '评分');
```

Group 方法（参数 `$label = ''`，空时显示操作符）：`equal` `notEqual` `gt` `lt` `nlt` `ngt` `match`(REGEXP) `like` `contains`(= like) `ilike` `startWith` `endWith`、`where(string $label, Closure $builder)`。

## 3. Presenter（表单类型）

默认 `Text`。调用 Presenter 方法会**替换**表单类型并**返回 Presenter 对象**（不再是筛选器）。

- Text：`placeholder()`、`url()`、`email()`、`integer()`、`ip()`、`mac()`、`mobile($mask = '19999999999')`、`decimal($options = [])`、`currency()`、`percentage()`（Inputmask numeric 选项）、`inputmask($options = [], $icon = 'pencil')`。
- `select($options)`：数组；`UnitEnum` 数组（BackedEnum 用 value=>name，纯枚举 name=>name）；闭包（绑定到筛选器，参数为当前值）；**字符串 = 页面加载时一次性 ajax 拉全部选项**，响应 `[{id, text}, ...]`。 ⚠️ 2.4.0–2.4.7 中传字符串 URL 会抛 `Presenter\Helper not found`（缺少 import 的回归），这些版本请改用 `ajax()` 或先查出数组再传入。`in()->multipleSelect([...])`。

Select 可链（源码：src/Grid/Filter/Presenter/Select.php）：

| 方法 | 说明 |
|---|---|
| `placeholder(?string)` / `config($key, $val = null)` | 默认 `admin.choose` / select2 配置 |
| `ajax(string $url, string $idField = 'id', string $textField = 'text')` | 输入时远程搜索；请求带 `q`、`page`，响应须为 Laravel 分页 JSON（`data`、`next_page_url`） |
| `model(string $model, ?string $idField = null, string $textField = 'name')` | 配合 `ajax()` 回显已选文本；`$idField` 默认主键。仅单选可靠：`multipleSelect` 时 `HasModelOptions` 用 `array_column` 取 ID 得空数组，无法回显（源码：src/Traits/HasModelOptions.php） |
| `load(string $target, string $url, $idField = 'id', $textField = 'text')` | 联动：改变时以 `?q=当前值` 请求 url 刷新目标 select 筛选项（`$target` 为目标字段名）；`loads(array, array, ...)` 多个；多选用 `MultipleSelect::loadMore()` |

```php
$filter->equal('user_id')->select()->ajax('api/users')->model(User::class, 'id', 'name');
$filter->equal('province_id')->select(Province::pluck('name', 'id'))->load('city_id', 'api/cities');
```

- `radio([...])` / `checkbox([...])`：可链 `stacked()`、`showLabel(bool)`；`checkbox` 提交数组，应配 `in`/`notIn`/`where`。
- datetime 系列：`datetime($options = [])`（bootstrap-datetimepicker，默认 `YYYY-MM-DD HH:mm:ss`）、`date()`、`time()`、`day()`(DD)、`month()`（`YYYY-MM`，不是 MM）、`year()`。DateTime Presenter 无 `toTimestamp()`、无 `format()`，格式只能经 `datetime(['format' => ...])` 传入。
- `selectTable(UserTable::make([...]))` / `multipleSelectTable(...)`：参数为 `Dcat\Admin\Grid\LazyRenderable` 实例（实现 `public function grid(): Grid`）。可链 `title()`、`dialogWidth()`（默认 825px）、`placeholder()`、`model(string $model, string $id = 'id', string $text = 'title')`（回显，注意 text 默认 `title`）、`options(Closure $fn)`（`fn (array $values, $presenter)` 返回 `[id => label]`）、`pluck(?string $visibleColumn, ?string $key = 'id')`；Multiple 额外 `max(int)`（0 不限）。数据无 `name`/`title`/`username` 字段时需在渲染类 `$grid->rowSelector()->titleColumn('xxx')`。

## 4. 通用方法与链式顺序

筛选器（AbstractFilter）：`width(int|float|string)`（数字为栅格 1–12，支持 `2.5`；字符串如 `'250px'` 固定宽度；默认 10，`between`/`in`/`notIn` 为 12）、`default($value)`（`filled()` 为假时忽略；只用于表单回显，**不会自动产生查询条件**）、`ignore()`（仍显示但不参与查询，可外部 `$filter->input('col')` 自行处理）、`setPresenter()`、`getValue()`/`getLabel()`/`getId()`/`parent()`。

Presenter 也有 `width()`、`default()`、`ignore()`、`value()` 且返回 Presenter，所以 `->select($o)->width(3)->default(1)` 与 `->width(3)->default(1)->select($o)` 都可用。
> ⚠️ 筛选器专属方法要写在 Presenter 之前：`$filter->startWith('name')->placeholder('x')->ilike()` 报错（Text Presenter 无 `ilike`），应 `->ilike()->placeholder('x')`。

## 5. 关联 / scope / 自定义过滤器

- 字段名含 `.` 时自动转 `whereHas`（装了 `dcat/laravel-wherehasin` 则 `whereHasIn`），最后一段为关联表字段：`$filter->like('profile.first_name')`。
- `scope(string $key, string $label = '')` 返回 `Grid\Filter\Scope`，可链**任意** Eloquent 查询方法（`__call` 记录后应用到 Grid 模型），如 `->where(...)`、`->whereHas(...)`、`->onlyTrashed()`（需 SoftDeletes）。URL 参数 `_scope_`（带 grid 名前缀）。
> ⚠️ scope 条件不自动分组，or 条件要包在 where 闭包里：`$filter->scope('new')->where(fn ($q) => $q->whereDate(...)->orWhereDate(...))`。
- 自定义过滤器：继承 `Dcat\Admin\Grid\Filter\AbstractFilter`，重写 `condition($inputs)`：取 `Arr::get($inputs, $this->column)`，为 null 时 `return;`，否则设 `$this->value`，可改 `$this->query`（默认 `where`，如 `'whereJsonContains'`），`return $this->buildCondition($this->column, $value);`（参数即 builder 方法参数，字段含 `.` 自动处理关联）。bootstrap.php 注册 `Grid\Filter::extend('jsonContains', JsonContains::class)` 后 `$filter->jsonContains('tags')`。可覆盖 `protected $view`（默认 `admin::filter.where`）、`formatId()`、`formatName()`、`setupDefaultPresenter()`；参考 src/Grid/Filter/Between.php。

## 6. 列筛选 Column Filter

表头筛选图标：`$grid->column('xx')->filter(Grid\Column\Filter $filter)`（源码：src/Grid/Column/HasHeader.php、src/Grid/Column/Filter/）。

| 类 | 可链方法 |
|---|---|
| `Equal` / `Like` / `StartWith` / `Gt` / `Lt` / `Ngt` / `Nlt` | `make(?string $placeholder = null)`、`date()`、`time()`、`datetime(string $format = 'YYYY-MM-DD HH:mm:ss')`、`placeholder()`、`valueFilter($valueKey = null)` |
| `Between` | `make()`、`date()`、`time()`、`datetime()`（无参数）、`toTimestamp()` |
| `In` | `make(array $options)`（多选 whereIn，参数必须是 array） |
| 全部 | `setColumnName(string $name)`、`hide()`、`display(bool)`、`resolving(Closure)` |

```php
$grid->column('created_at')->filter(Grid\Column\Filter\Between::make()->datetime()->toTimestamp());
$grid->column('label')->filter(Grid\Column\Filter\Equal::make()->setColumnName('meta->label')); // JSON 字段
$grid->column('user.name')->filter(Grid\Column\Filter\Like::make());   // 关联：whereHas('user')
```

**值筛选**（点击单元格值即筛选，以字段**原始值**为查询值，不受 `display()` 影响）：`->filter()`（= `filter(Equal::make()->valueFilter())`）；`->filter('name')` 用当前行 name 字段值；`->filter('user.id')` 行数据 `toArray()` 后按点语法取值；`->filter(fn () => $this->user['id'])` 闭包绑定行模型；`filterByValue($valueKey = null)` = 值筛选 + 隐藏表头图标。

## 7. 快捷搜索 quickSearch

`$grid->quickSearch($search = null)` 返回 `Grid\Tools\QuickSearch`（源码：src/Grid/Concerns/HasQuickSearch.php）：
- `quickSearch('title', 'desc', 'user.name')` 或数组：各字段 OR like `%kw%`，整体分组，支持关联字段。
- `quickSearch(function ($query, $keyword) {...})`：`$query` 已包在 where 分组中。
- 可链 `placeholder()`、`width(int $rem)`（默认 18）、`auto(bool $value = true)`（默认输入停顿 1.2s 自动提交；`false` 需回车）。

**不传参：语法搜索** `字段:条件`，字段必须是**已定义的表格列名或列 label**（如 `用户状态:(1,2)`），否则忽略；全部无效时返回空结果。

| 输入 | 查询 |
|---|---|
| `title:foo` / `title:!=foo` | `=` / `!=` |
| `rate:>10` `<10` `>=10` `<=10` | 比较 |
| `status:(1,2,3)` / `status:!(1,2)` | `whereIn` / `whereNotIn`（`NULL` 视为 null） |
| `score:[1,10]` | `whereBetween` |
| `created_at:date,2019-06-08` | `whereDate`；另有 `time,` `day,` `month,` `year,` |
| `content:%foo%` / `foo%` / `%foo` | like `%foo%` / `foo%` / `%foo%` |
| `username:/song/` | `REGEXP` |
| `a:1 b:2` / `a:1 \|b:2` | 空格为 AND / 前缀 `\|` 为 OR |
| `updated_at:"2019-06-08 09:57:45"` | 值含空格加双引号 |

> ⚠️ 不等于是 `title:!=foo`，`title:!foo` 会被当作 `where title = '!foo'`。

## 8. 规格选择器 selector

渲染在表格 header（源码：src/Grid/Tools/Selector.php、src/Grid/Concerns/HasSelector.php）。签名 `select(string $column, $label, $options = [], ?Closure $query = null)`（多选 whereIn）、`selectOne(...)`（单选 where）；关联字段 `brand.id` 用 whereHas；省略 label 时第二参直接传选项（label 取 `admin_trans_field`），第三参可传查询闭包。

```php
$grid->selector(function (Grid\Tools\Selector $selector) {
    $selector->select('price', '价格', ['0-999', '1000-1999'], function ($query, $value) {
        $query->whereBetween('price', [[0, 999], [1000, 1999]][current($value)]);
    });
});
```

- `$options` 必须是数组。闭包 `function ($query, $value)`：`select` 的 `$value` 为字符串数组，`selectOne` 为单个字符串。`$grid->selector()` 不传参返回 Selector 实例。

## 9. 筛选事件与顺序

`$grid->listen(...)`：`Grid\Events\ApplyFilter`（`array $conditions`）、`ApplyQuickSearch`（`string $keyword`）、`ApplySelector`（`array $selected`）。应用顺序：快捷搜索 → 列筛选 → selector → filter 条件与 scope（源码：`HasFilter::processFilter()`）。
