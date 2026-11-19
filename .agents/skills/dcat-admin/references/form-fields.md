# 表单字段速查（通用方法 + 文本/选择/日期/数值/其他）

> 覆盖 `src/Form.php` `$availableFields` 中的非上传、非编辑器字段及字段通用方法。上传/编辑器见 `form-fields-upload.md`；hasMany/embeds/table/array 见 `form-relations.md`；验证、`when` 联动、布局、事件见 `form.md`。

## 注册与特殊签名

`$form->{name}($column, $label = '')`，`Form::__call` 执行 `new $class($column, array_slice($args, 1))`。**不存在的字段名会 `admin_error` 并返回 `Field\Nullable`（吞掉后续调用，不抛异常）**。

签名与常规不同的：`captcha()` 无参；`dateRange/datetimeRange/timeRange/range($start, $end, $label = '')`；`map($latitude, $longitude, $label = '')`；`divider(?string $title = null)`；`html($html, $label = '')`；`button(string $html)`。

## 通用方法（src/Form/Field.php + HasFieldValidator 等，均返回 `$this`）

- `value($value = null)`：无参取值，`null`/空数组时回退 `default()`。
- `default($default = null, bool $edit = false)`：**仅新增页生效**，`$edit = true` 编辑页也生效。传闭包会在调用时**立即无参执行**，拿不到 `$form`；依赖表单数据时在闭包或 `editing/creating` 中算好再传值。
- `customFormat(\Closure)`：编辑时格式化注入值，`$this` 为当前行数据（`Fluent`），参数 `($value, $column, $field)`。
- `saving(\Closure)`：保存前转换，`$this` 为行数据，在字段 `prepareInputValue` 之后执行，可多次调用。
- `saveAsJson($option = 0)`：**`null` 和标量原样返回**，只编码数组/对象。`saveAsString()`：数组/对象 `json_encode`，其余 `(string)`（适合 NOT NULL 字符串列）。
- `options($options = [])`：基类为合并（闭包以 `$this=行数据, 参数 $value` 立即调用）；select/radio/checkbox/tags/selectTable/autocomplete 各自重写。`replaceOptions()` 覆盖、`mergeOptions()` 合并。
- `label()`（可传闭包）、`help($text, $icon)`、`placeholder()`（默认"输入 + label"）、`width($field = 8, $label = 2)`、`horizontal()`、`display(bool)`、`hideInDialog()`。
- `when($value, $callback)`：**基类上是条件链式调用**（`$value` 为真时执行 `$callback($field, $value)`）；select/multipleSelect/radio/checkbox 上被覆盖为表单联动 `when($operator, $value, $closure)`，见 `form.md`。
- `readOnly()`：值仍会提交。`disable()`：**浏览器不提交 disabled 字段**；file/image 重写为禁用上传组件。
- `required($isLabelAsterisked = true)`：传 `false` 只是不显示星号，仍必填。
- 属性/样式：`attribute()`、`defaultAttribute()`（仅未设置时）、`setLabelClass/setFormGroupClass/setFieldClass($class, bool $append = true)`、`addElementClass/setElementClass/removeElementClass`、`getElementClassSelector()`（写 JS 用）、`setElementName()`、`setErrorKey()`、`setView()`、`script($js)`、`addVariables(array)`。
- 验证：`rules/creationRules/updateRules/removeRule/hasRule/validator/setValidationMessages($key, array)`，见 `form.md`。
- 全局：`Field\Xxx::resolving(callable, bool $once = false)` / `composing(...)`；`Field::macro()`。

### 只在特定字段上存在的方法

| 方法 | 仅在 |
|---|---|
| `prepend()` / `append()` | text 及其子类、selectTable |
| `small()` / `large()` / `size()` | text 子类、select 系、radio、checkbox（switch 自有 small/large） |
| `minLength` / `maxLength` / `same` / `inputmask` / `datalist` / `type` | text 及其子类 |
| `format($fmt)` | date/datetime/time/year/month（**range 类没有**） |
| `min()` / `max()` | number（attribute）；list（条数）；multipleSelectTable 仅 `max` |
| `load` / `loads` | select、multipleSelect、listbox、radio、checkbox、selectTable、multipleSelectTable |
| `ajax()` | select 系、tags、autocomplete |
| `with(Closure)` | display |

## 文本类

- **text**：`same()` 规则见 `form.md`；`inputmask(array)`、`datalist(array)`。**textarea**：`rows(int = 5)`，数组值以格式化 JSON 显示，无 prepend/append。
- 默认规则：email `nullable|email`、url `nullable|url`、ip `nullable|ip`；mobile 无默认规则，inputmask 默认 `['mask' => '99999999999']`，改用 `->options(['mask' => ...])`。2.5.0 起 mobile/ip 的已有值不符合掩码（如 `+86 ...`、IPv6）时保留原值、不挂掩码；ip 掩码仍只支持 IPv4，录入 IPv6 用 `text('ip')->rules('ip')`。
- **password**：不会自动 hash，需在 `saving` 中处理。
- **color**：`hex()/rgb()/rgba()`。**icon**：fontawesome-iconpicker。**tel**：type=tel。
- **captcha**：需 `mews/captcha`，否则抛 `AdminException`；列名固定 `__captcha__`，自动 ignore，规则 `required|captcha`。
- **autocomplete**：`options(array|Closure)`（字符串数组或 `['value'=>..,'data'=>[..]]`）、`groups()`/`groupBy($key)`、`ajax(string $url, string $valueField = '', string $groupField = '')`（请求参数 `query`）、`configs()`、`depends($fields = [], bool $clear = true)`（把其他字段值带到请求）。
- **hidden**：需要额外写库的列先声明 hidden，在 `saving` 中赋值更稳妥。
- **id**：`readonly` 输入框，**会被提交**；只展示主键用 `display('id')`。

## 选择类

- **select**（select2）：
  - `options()`：数组 / Arrayable；闭包 `function ($value, $field)`（`$this` 为 `$form->model()`，渲染时调用，用于 ajax 回显）；URL 字符串 → 页面加载时一次性 ajax 取全部选项（`[{"id":..,"text":..}]`）。
  - `model(string $model, ?string $idField = null, string $textField = 'name')`：按当前值 `whereIn` 回显；`$idField` 默认模型主键。
  - `ajax(string $url, string $idField = 'id', string $textField = 'text')`：请求参数 `q`、`page`，响应需为 Laravel 分页结构（读 `data`、`next_page_url`）。
  - `load(string $field, string $url, string $idField = 'id', string $textField = 'text')` / `loads(array, array, ...)`：值变化时 `?q=当前值`（多值逗号连接）请求，响应为**非分页**数组 `[{"id":..,"text":..}]`。
  - `groups([['label' => 'A', 'options' => [...]]])`、`config($key, $val)`、`addDefaultConfig()`、`disableClearButton()`。
  - 父级下拉树：`options(Category::selectOptions())`（模型 `use ModelTree`），见 `tree.md`。

  > ⚠️ `options(User::class, 'id', 'name')` 在 2.4.0–2.4.7 不可用（`Select.php` 缺 `Model` 导入，2.5.0 已修复，类名被当作 ajax URL，多传参数抛 TypeError）。统一写 `->model(User::class, 'id', 'name')`。
- **multipleSelect**：提交数组（过滤空值）；读取接受数组、JSON 或逗号分隔字符串。多对多见 `form-relations.md`。**listbox**：继承 multipleSelect，`settings(array)`。
- **radio**：`inline()`（默认 true）、`style()`（默认 `primary`）。**checkbox**：继承 multipleSelect，`canCheckAll()`、`inline()`、`style()`。
- **switch**：**保存为 1/0**；颜色 `primary()/green()/.../color($c)`，`small()`（默认）/`large()`。
- **tags**：提交数组；`pluck($visibleColumn, $key = 'id')`（多对多时**必须调用**；options 传 Collection 会自动 pluck）、`ajax($url, $idField = 'id', $textField = 'text')`。
- **tree**（jstree 多选，提交数组）：`nodes(array|Arrayable|Closure)`、`setIdColumn('id')`/`setTitleColumn('name')`/`setParentColumn('parent_id')`、`rootParentId(0)`、`exceptParentNode(bool = true)`（默认不提交父节点）、`treeState(bool = true)`（`false` 允许单选父节点）、`expand()`、`readOnly()`（忽略参数，调用即只读）。
- **selectTable**：`from(LazyRenderable)`（必需，`Grid\LazyRenderable`）、`title()`、`dialogWidth('825px')`、`pluck(?string $visibleColumn, ?string $key = 'id')`、`model(string $model, string $id = 'id', string $text = 'title')`——**默认显示列是 `title`**（select 是 `name`）。**multipleSelectTable**：提交数组，`max(int)`（0 不限）。
- **timezone**：继承 select，全部 PHP 时区。

## 日期类

bootstrap-datetimepicker，`format()` 用 **moment 格式**；`options([...])` 传其他配置（`format`/`locale` 渲染时被覆盖）。空字符串保存为 `null`。默认 format：date `YYYY-MM-DD`、datetime `YYYY-MM-DD HH:mm:ss`、time `HH:mm:ss`、year `YYYY`、month `MM`（**只存两位月份**，要 `2024-03` 写 `->format('YYYY-MM')`）。

- **dateRange / datetimeRange / timeRange**：两列分别保存；**没有 `format()`**，用 `->options(['format' => 'YYYY/MM/DD'])`。

## 数值类

- **number**：**取值强转 `(int)`、空值存 `0`**，小数用 decimal/currency；`min()/max()` 为 HTML 属性。2.5.0 起 `integer(bool $value = true)`：前端失焦/回车时向零取整，并自动追加整数校验（源码：src/Form/Field/Number.php）。
- **decimal**：inputmask decimal。**currency**：`symbol('$')`、`digits(int)`，提交时去千分位逗号；2.5.0 起校验前也会去掉千分位，已有值小数位多于 `digits` 时不再被四舍五入。
- **rate**：**前缀 `%` 的文本框（费率），不是星级评分**。
- **slider**：ion.rangeSlider，`options(['min'=>..,'max'=>..,'step'=>..,'postfix'=>..])`。**range**：两个文本框分别存两列。

## 其他

- **display**：不提交。`with(Closure)`：`$this` 为模型，参数 `$value`，返回值为显示内容。

  > ⚠️ `with()` 返回 `null`，不能在其后继续链式调用。
- **html**：`$html` 可为字符串、Renderable、闭包（`$this` 为模型，参数 `$form`）；`plain()` 不包裹 form-group。
- **button**：`class('btn-primary')`、`on(string $event, string $jsBody)`（`$jsBody` 是回调**函数体**）；不提交值。
- **map**：供应商读 `config('admin.map.provider')`（兼容 `admin.map_provider`）：`baidu`（默认）/`amap`/`tencent`/`google`/`yandex`；密钥读 `config('admin.map.keys.{provider}')`，否则 env `BAIDU_MAP_API_KEY`/`AMAP_API_KEY`/`TENCENT_MAP_API_KEY`/`GOOGLE_API_KEY`。**默认 `config/admin.php` 没有 `map` 段，需自行添加**，如 `'map' => ['provider' => 'amap', 'keys' => ['amap' => env('AMAP_API_KEY')]]`。`height('300px')`；单字段 `amap()` 等只切渲染类型，JS 资源仍按全局 provider 加载（源码：src/Form/Field/Map.php）。
- **keyValue**：保存关联数组；`setKeyLabel()/setValueLabel()`；只有设置了 `rules()` 才校验（作用于每个 value，同时给 key 加 `distinct`）；不设时 key 重复后者覆盖前者。
- **list**：保存索引数组；`max()/min()` 为条数；`rules()` 作用于每项。

## 数组值的存储

checkbox、multipleSelect、listbox、tags、tree、multipleSelectTable、keyValue、list、multipleFile/multipleImage 提交**数组**，EloquentRepository 直接 `setAttribute`，**不自动编码**。三选一：模型 `$casts = ['x' => 'array']`（推荐）；`->saveAsJson()` 或 `->saving(fn ($v) => implode(',', $v))`（读取时自动识别 JSON/逗号分隔）；列名与多对多关联同名并预加载，框架自动 sync。cast 与 saveAsJson 不要同时用，见 `form-relations.md`。

## 自定义字段

- 继承 `Dcat\Admin\Form\Field`：`protected $view`、`protected static $js/$css`（按需加载）；覆写 `prepareInputValue($value)`（先于字段 `saving` 回调）、`formatFieldData($data)`（先于 `customFormat`）。
- 注册（`app/Admin/bootstrap.php`）：`Form::extend('phpEditor', PhpEditor::class)`，同名可覆盖内置字段。别名 `Form::alias('select', 'dropdown')`（参数顺序：原名, 别名）。
- 视图变量：`$name $value $label $column $class $attributes $placeholder $help $viewClass $errorKey $formId $selector $options` + `addVariables()`。
- 视图 JS 用 `<script require="@xxx" init="{!! $selector !!}"> $this... </script>`（`Dcat.init` 监听），才能兼容 hasMany/table/array 动态行。
- 内置 `editor` 是 **TinyMCE**（不是 ckeditor）。
