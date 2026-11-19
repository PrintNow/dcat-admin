# 动作（Action）与 JSON 响应

> 覆盖 `Dcat\Admin\Actions\Action` 基类、Grid 行/批量/工具栏动作、Form/Show/Tree 工具与行操作、确认、参数传递、权限、弹窗表单类动作，以及 `Dcat\Admin\Http\JsonResponse` 全部响应方法。相关文件：show.md、tree.md；表格接入见 grid.md，工具表单见 widgets.md，表单回调中的响应见 form.md。

## 类层级

| 用途 | 基类 | 注册 |
|---|---|---|
| 任意位置按钮 | `Dcat\Admin\Actions\Action` | 任意可渲染位置输出 |
| 表格行操作 | `Dcat\Admin\Grid\RowAction` | `$grid->actions(...)` |
| 表格批量操作 | `Dcat\Admin\Grid\BatchAction` | `$grid->batchActions(...)` |
| 表格工具栏 | `Dcat\Admin\Grid\Tools\AbstractTool` | `$grid->tools(...)` |
| 表单 / 详情工具栏 | `Dcat\Admin\Form\AbstractTool` / `Dcat\Admin\Show\AbstractTool` | `$form->tools()` / `$show->tools()` |
| 模型树行操作 / 工具栏 | `Dcat\Admin\Tree\RowAction` / `Dcat\Admin\Tree\AbstractTool` | `$tree->actions()` / `$tree->tools()` |

Grid 三类都继承 `Dcat\Admin\Grid\GridAction`（再继承 `Action`）。生成：`php artisan admin:action`（交互选择类型 default/grid-batch/grid-row/grid-tool/form-tool/show-tool/tree-row/tree-tool，命名空间默认 `App\Admin\Actions[\Grid|\Form|\Show|\Tree]`）。

> ⚠️ 命名空间是 `Grid\RowAction`、`Grid\BatchAction`、`Grid\Tools\AbstractTool`，不是 `Grid\Tools\RowAction` / `Grid\Tools\BatchAction` / `Grid\AbstractTool`。

## 工作原理（必读）

1. **渲染**（页面请求）：`render()` 输出按钮；类中有 `handle()`（且 `$allowHandler` 为 true）时生成 `Dcat.Action({...})` JS 绑定 `$event`（默认 click）。此时 `parameters()`、`confirm()`、`getKey()`、`$this->row` 都可用。
2. **请求**：前端 POST 到 `admin_url('dcat-api/action')`（路由名 `dcat-api.action`），数据 = 按钮 `data-*` 属性 + `parameters()` + `_key`（主键）+ `_action`（类名，`\` 换成 `_`）。
3. **处理**：`HandleActionController` 用 `app($class)` **重新实例化**，`setKey($request->get('_key'))`，先 `authorize(Admin::user())`，通过后调用 `handle(Request $request)`。返回 `$this->response()` 自动 `send()`，也可返回任意 Laravel 响应。

硬性规则：
- 构造函数参数必须**全部有默认值**。
- `handle()` 中只有 `$this->getKey()` 与 `$request`；渲染时传入构造函数的值、`$this->row`、`$this->parent` **都不存在**，需要的数据放进 `parameters()`。
- 动作类名（含命名空间）**不能含下划线 `_`**，否则类名还原失败。
- 只能 POST（路由只注册 `post`），不要改 `$method`。
- 定义了 `href()` 不会阻止 `handle()` 绑定（`prepareHandler()` 先于 href 判断，src/Actions/Action.php `render()`），点击会同时跳转并发 AJAX；纯链接动作不要定义 `handle()`，或 `protected $allowHandler = false`。

## Action 属性与方法（源码：src/Actions/Action.php）

属性（protected）：`$title`（可含 HTML，也可 `new MyAction('标题')`）、`$method = 'POST'`、`$event = 'click'`、`$disabled = false`（true 不渲染）、`$allowHandler = true`、`$primaryKey`（用 `setKey()/getKey()`）、`$selector`（默认随机 `.act-xxxx`）。

| 方法 | 说明 |
|---|---|
| `handle(Request $request)` | 自行定义；不定义则不绑定请求 |
| `response()` | `Dcat\Admin\Actions\Response`（继承 `JsonResponse`） |
| `parameters()` | protected，附加到请求的数组 |
| `confirm()` | 返回 `string` 或 `[标题, 内容]`；非空先弹确认框 |
| `actionScript()` | protected，返回 JS 函数 `function (data, target, action) {}`，请求前执行，`return false` 中断；可改 `action.options.key` |
| `resolverScript()` / `rejectScript()` | protected，`function (target, results) {}`，`return false` 阻止默认处理 |
| `handleHtmlResponse()` | protected，处理 `->html()` 响应，默认 `target.html(html)` |
| `authorize($user): bool` | protected，默认 `true`；`failedAuthorization()` 默认返回 `__('admin.deny')` 错误 |
| `html()` / `script()` | protected，按钮 HTML（默认 `<a {$this->formatHtmlAttributes()}>{$this->title()}</a>`）/ 额外 JS |
| `title()` | 可覆写为动态标题 |
| `disable(bool = true)` / `allowed()` | 隐藏 / 是否显示 |
| `selector()` / `makeSelector()` | 选择器（`getElementClass()` 为 protected） |
| `addHtmlClass()`、`setHtmlAttribute($key, $value = null)`、`defaultHtmlAttribute()`、`appendHtmlAttribute()`、`forgetHtmlAttribute()`、`getHtmlAttribute()` | HTML 属性 |

> ⚠️ `authorize()` 只在**请求 handle 时**校验，不隐藏按钮；隐藏在注册处 `MyAction::make()->disable(! Admin::user()->can('x'))`。

> ⚠️ 主键用 `$this->getKey()`，不存在 `$this->key()`；不存在 `selectorPrefix`、`usingHandler` 属性（实为 `$selector`、`$allowHandler`）。

## Grid：行操作 RowAction

```php
class Publish extends RowAction
{
    public function title() { return $this->row->published ? '下架' : '发布'; }  // 渲染时可访问行
    protected function parameters() { return ['to' => ! $this->row->published]; } // 行数据经此带到 handle
    public function handle(Request $request)
    {
        Post::whereKey($this->getKey())->update(['published' => (bool) $request->get('to')]);
        return $this->response()->success('操作成功')->refresh();
    }
}
```

- 注册：`$grid->actions(new Publish())`（可传数组，每行 clone）；或 `$grid->actions(function (Grid\Displayers\Actions $actions) { ... })`，闭包 `$this` 为当前行，`append()/prepend()`、`disableDelete/disableEdit/disableQuickEdit/disableView`。
- `RowAction`：`$this->row`、`row($key = null)`、`getRow()`、`setRow()`、`getKey()`、`setColumn()`。`GridAction`：`$this->parent`（Grid）、`resource()`、`setGrid()`。
- 行操作外观由 `admin.grid.grid_action_class` 决定（默认 `Grid\Displayers\DropdownActions`，可设 `Grid\Displayers\Actions` 图标平铺）。

## Grid：批量操作 BatchAction

- `handle()` 中 `$this->getKey()` 为**选中主键数组**（行操作为单个主键）。
- 注册：`$grid->batchActions([new BatchPublish()])` 或闭包 `Grid\Tools\BatchActions $batch`：`add(BatchAction $action, ?string $key = null)`、`divider()`、`disableDelete(bool)`、`disableDeleteAndHideSelectAll()`。
- 默认 `actionScript()` 读取选中行写入 `action.options.key`，未选中时提示并中断；覆写时必须保留该逻辑（返回 JS **函数**）。
- `getSelectedKeysScript()` 返回 JS 表达式 `Dcat.grid.selected('{gridName}')`。

## Grid：工具栏 AbstractTool

- `$style` 默认 `'btn btn-white waves-effect'`，`html()` 默认渲染 `<button>`。注册 `$grid->tools(new X())` 或闭包 `Grid\Tools $tools` 的 `append()/prepend()`（可传 HTML 字符串）。
- 要处理勾选行：AbstractTool **没有** `getSelectedKeysScript()`，自己在 `actionScript()` 中取：

```php
protected function actionScript()
{
    return <<<JS
function (data, target, action) {
    var keys = Dcat.grid.selected('{$this->parent->getName()}');
    if (keys.length === 0) { Dcat.warning('请先选择数据'); return false; }
    action.options.key = keys;
}
JS;
}
```

## Form / Show / Tree 工具

- **Form 工具**（默认 `$style = 'btn btn-sm btn-primary'`）：`$form->tools(fn (Form\Tools $tools) => $tools->append(...))`（闭包 `$this` 为 Form）；`$this->parent` 为 Form，`getKey()` 为编辑主键（新增页 null）。静态构造 `allowOnlyCreating(...$params)` / `allowOnlyEditing(...$params)` 限定显示页面。
- **Show 工具**（同上默认样式）：闭包 `$this` 为当前记录；`$this->parent` 为 Show（`->model()` 取记录），`getKey()` 为详情主键。
- **Tree**：`Tree\Tools` **只有 `add()`**（Grid/Form/Show 用 `append()/prepend()`）；`$tree->actions(new X())` 每行 clone，或闭包 `Tree\Actions $actions` 的 `append()`。`Tree\RowAction`：`getKey()`、`getRow()`、`resource()`、`getActions()`。
- 所有工具类同样支持 `handle/confirm/parameters/authorize/href`。

## 弹窗表单类动作

**方式一（推荐）：动作 + `Modal` + 异步工具表单**

```php
class ResetPassword extends \Dcat\Admin\Widgets\Form implements \Dcat\Admin\Contracts\LazyRenderable
{
    use \Dcat\Admin\Traits\LazyWidget;
    public function handle(array $input) { $id = $this->payload['id'] ?? null; /* ... */ return $this->response()->success('ok')->refresh(); }
    public function form() { $this->password('password')->required(); }
}

// RowAction 中（无 handle()）：
public function render()
{
    $form = ResetPassword::make()->payload(['id' => $this->getKey()]);
    return Modal::make()->lg()->title($this->title)->body($form)->button($this->title);
}
```

- 提交由工具表单的 `handle(array $input)` 处理（路由 `dcat-api/form`），表单类 `authorize()` 同样生效。
- 批量场景：表单加 `$this->hidden('id')->attribute('id', 'batch-ids');`，动作中 `Modal::make()->...->onLoad("$('#batch-ids').val({$this->getSelectedKeysScript()});")`（异步表单用 `onLoad`，非异步用 `onShow`），`handle()` 中 `explode(',', $input['id'])`。

> ⚠️ `payload` 以隐藏字段 JSON 回传，**前端可篡改**；`handle()` 中涉及权限的 ID 必须重新校验。

**方式二：`Form::dialog()` 复用资源 create/edit 页**（见 `form.md`）：在 RowAction 的 `render()` 中 `Form::dialog('编辑')->click(".{$this->getElementClass()}")->success('Dcat.reload()');`，再 `$this->setHtmlAttribute('data-url', "{$this->resource()}/{$this->getKey()}/edit");` 后 `return parent::render();`。

## JsonResponse 响应方法

Action 的 `$this->response()`、工具表单的 `$this->response()`、`$form->response()`、`JsonResponse::make()` / `Admin::json(array $data = [])` 是同一套 API，均可链式（源码：src/Http/JsonResponse.php）。

| 方法 | 说明 |
|---|---|
| `success/error/warning/info(?string $msg)` | 提示；`error()` 置 status=false |
| `message()` / `detail()` / `alert(bool = true)` / `timeout($s)` | 文案 / 弹窗详情 / 用 SweetAlert 代替 toastr / toastr 秒数 |
| `redirect(?string $url)` | PJAX 局部跳转（`admin_url()` 处理） |
| `location(?string $url = null)` | 1 秒后整页跳转；不传则整页刷新 |
| `redirectToIntended()` / `locationToIntended()` | 优先 session `url.intended` |
| `refresh()` | PJAX 刷新当前页 |
| `download($url)` | 新窗口打开下载 |
| `script($js)` | 前端 eval（作用域有 `response`、`options.target`） |
| `html($html)` | 字符串/Renderable/Htmlable，交给 `handleHtmlResponse()` |
| `data(array)` / `options(array)` | 合并到 `data` / 响应顶层 |
| `status(bool)` / `statusCode(int)` | 状态 / HTTP 码 |
| `withValidation($errors)` | 422 + 字段错误（Validator/MessageBag/数组） |
| `withException(\Throwable)` | 异常转错误提示（`ValidationException` 转 422） |
| `with($key, $value = null)` | session flash |
| `send()` | 转 `Illuminate\Http\JsonResponse`（控制器中直接返回时需要，如 `return Admin::json()->success('ok')->refresh()->send();`） |
| `toArray()` | `['status', 'data', 'html'] + options` |

- `redirect/location/refresh/download/script` 共用一个 `then` 槽位，**只有最后调用的生效**。
- **条件方法**：任意方法加 `If` 后缀，第一个参数为条件（布尔或闭包），如 `refreshIf($ok)`、`locationIf(fn () => $done, 'auth/users')`。

> ⚠️ 条件方法是后缀 `refreshIf()` / `locationIf()`，不是 `ifRefresh()`；不存在 `Admin::make()`，应为 `Admin::json()` 或 `JsonResponse::make()`。
