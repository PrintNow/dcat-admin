# 数据表单 Form

> 覆盖 `Dcat\Admin\Form` 表单级 API：生命周期、验证、事件与响应、数据读写、布局、条件构建、工具栏/底部、弹窗表单、全局初始化与翻译。字段见 `form-fields.md`；关联/hasMany/embeds/JSON 见 `form-relations.md`；Action 见 `actions.md`；仓库见 `repository.md`。

## 基本约定

- `Form::make($repository, ?Closure $callback, ?Request $request)`：`$repository` 可为 Repository 实例、模型实例、`Model::with([...])` 或类名，内部经 `Admin::repository()` 转换。
- `AdminController` 的 `create/edit/store/update/destroy` 都调用同一个 `form()`；单独复用可 `use Dcat\Admin\Http\Controllers\HasResourceActions`。
- 新增页自动移除 `id/created_at/updated_at` 的 `display` 字段（源码：`src/Form/Builder.php` `removeReservedFields`）。

> ⚠️ 在 `form()` 里 `Form::make()` 之后、闭包之外调用 `$form->isEditing()` / `$form->model()` 无效——此时控制器还没调用 `->edit($id)`，模式恒为 create、model 为空。依赖编辑数据或模式的逻辑写在闭包或事件回调里（闭包在渲染/提交时才执行）。

## 生命周期

闭包（`build()`）在每个请求中都执行一次（源码：`src/Form.php` `store/update/destroy/render`）：

| 请求 | 顺序 |
|---|---|
| GET `create` | 闭包 → `creating` → `Form::composing` → 渲染 |
| GET `edit($id)` | `repository->edit()` → 闭包 → 填充字段 → `editing` → `composing` → 渲染 |
| POST `store()` | 闭包 → `submitted` → 上传请求处理 → 验证 → 剔除 `ignore` 字段 → `saving` → 字段 `prepare`（含字段 `saving()`）→ `repository->store()`（事务）→ `saved($newId)` → 响应 |
| PUT `update($id)` | `repository->updating()` → 闭包 → `submitted` → 上传/行内编辑/删除文件/排序处理 → 验证 → 剔除 ignore → `saving` → 字段 `prepare` → `repository->update()` → `saved($bool)` → 响应 |
| DELETE `destroy($id)` | `repository->deleting()` → 闭包 → `deleting` → `repository->delete()`（非软删除时同时删文件）→ `deleted($result)` → 响应 |

- `store(?array $data = null, $redirectTo = null)` / `update($id, ?array $data = null, $redirectTo = null)` / `destroy($id)` 均为 public；传 `$data` 会替换 request 数据。`destroy` 的 `$id` 可为逗号分隔多个 ID。
- 保存成功默认跳转：勾选"继续编辑/继续创建/查看"则跳对应页，否则回来源页（`_previous_`）或列表；"继续编辑"在编辑模式下为刷新当前页。
- `isCreating()` / `isEditing()` / `isDeleting()` 对页面请求和提交请求都有效。

> ⚠️ `submitted`/`saving` 不只在点"提交"时触发——上传字段的独立请求、编辑页删除文件、Grid 行内编辑（`editable`/`switch` 等）、排序请求都走 `store/update`，input 只含部分字段。回调中读字段前先判断 `$form->input('x') !== null`。

## 读写表单数据

| 方法 | 说明 |
|---|---|
| `$form->model()` | 编辑/更新时为当前记录（EloquentRepository 下是 Eloquent 模型）；新增时为空 `Fluent`；删除时为 `Fluent`，内容是待删行组成的二维数组 |
| `$form->getKey()` | 主键；新增页为 `null`，`saved` 中为新记录主键 |
| `$form->input()` / `input('a.b')` / `$form->a` | 全部输入 / 单个输入（点语法） |
| `$form->input('a', 'v')` / `$form->a = 'v'` / `input([...])` | 修改 / 批量合并输入 |
| `$form->deleteInput('a'\|[...])` | 删除输入，不再保存 |
| `$form->updates()` | 最终写入仓库的数据（字段 `prepare` 之后），`saved` 中用 |
| `$form->repository()` | 当前 Repository；`saved` 中 `->model()` 即刚保存的 Eloquent 模型（仅 EloquentRepository）。`->eloquent()` 不存在 |

> ⚠️ `$form->input('a', null)` 会被当作读取；置空写 `$form->a = null;`。

> ⚠️ 在 `saving` 中给**没有声明字段**的列赋值不会被保存——只保留有对应字段的输入（源码：`Form::prepareInsert/prepareUpdate`）。先声明 `$form->hidden('author_id');` 再在 `saving` 中赋值；或在 `saved` 中 `$form->repository()->model()->update([...])`。

### 忽略字段 ignore

`$form->ignore(string|array $columns)`，可多次调用。
- 被忽略字段**仍参与验证**、`submitted` 中可读，但在 `saving` 前已从 input 移除。`saving` 内丢弃输入用 `deleteInput()`（此时再 `ignore()` 无效）。
- 编辑页被 ignore 的字段不回填数据库值。

> ⚠️ `removeIgnoredFields(['col'])` 不是"取消忽略"，它从传入数组剔除已忽略字段并返回；`forgetIgnored($keys)` 按数组下标删除，传字段名无效。条件忽略直接在分支里决定是否调用 `ignore()`。

### 字段级转换

- `$field->saving(fn ($value) => ...)`：保存前转换，`$this` 为当前行数据（`Fluent`），可多次调用依次执行。`customFormat(fn ($value) => ...)`：编辑回填前转换。`saveAsJson()` / `saveAsString()`：见 `form-fields.md`。

## 验证

- `rules($rules = null, $messages = [])`：`string` / `array` / `Closure`（`$this` 为字段，参数 `Form`）；多次调用合并。编辑时规则中的 `{{id}}` 替换为当前主键（如 `unique:users,email,{{id}}`）。
- `creationRules($rules, $messages = [])` / `updateRules(...)`：**一旦设置，对应模式下 `rules()` 的规则全部不生效**（源码：`HasFieldValidator::getRules`）。
- `required($isLabelAsterisked = true)`、`removeRule/removeCreationRule/removeUpdateRule`、`hasRule`。
- `validator(callable $fn)`：完全自定义，`function (array $input)`，`$this` 为字段，返回 `Validator` 或 `false`。
- 只有**提交数据中存在该字段**时才验证它（源码：`HasFieldValidator::getValidator`），所以部分提交不会触发其它字段的 `required`。
- 验证失败返回 422 JSON；`$form->validationErrorToastr(false)` 关闭同时弹出的 toastr。
- `when()` 联动显示的字段不要用 `required()`，改用 `->rules('required_if:type,1')->setLabelClass(['asterisk'])`。

> ⚠️ 同时写 `rules('required|email')` 和 `creationRules('unique:...')`，新增时只剩 `unique`，不会叠加；需要叠加就在 `creationRules` 里写全。

### 手动返回字段错误

- `$form->responseValidationMessages($column, $messages)`（也接受 `['col' => 'msg']` 或 `MessageProvider`）：只在验证阶段之前登记才生效——闭包中（编辑提交为 PUT）或 `submitted` 中，会与字段验证错误合并返回。
- 验证之后（`saving` 等）：`return $form->validationErrorsResponse(['title' => ['标题已存在']]);`

### 前端验证（Text 及其子类）

- `type('number')` + `->attribute('min', 10)`；`minLength(int, ?string $error)`（同时加后端 `nullable|min:`）、`maxLength(...)`（加 `max:`）；`pattern($regexp, $error = null)`（所有字段）；`setClientValidationError(string $error, ?string $key = null)`。
- `same($field, ?string $error = null)`：目标字段须先声明；若当前列为 `{目标}_confirmation`，给**目标字段**加 `confirmed`，否则给自己加 `nullable|same:目标`。

> ⚠️ `same()` 给目标字段加的 `confirmed` 是写进 `rules()` 的；目标字段若设置了 `creationRules`/`updateRules`，对应模式下 `confirmed` 失效，需在其中手动加 `confirmed`（源码：`src/Form/Field/Text.php:72-73`、`src/Form/Concerns/HasFieldValidator.php:119-127`）。

- 自定义前端规则：`Dcat.validator.extend('name', function ($el) { return 出错条件; })` + 字段属性 `data-name` / `data-name-error`。

## 事件回调

均为 `$form->xxx(Closure): Form`，可注册多个；第一个参数为 `Form`，闭包 `$this` 绑定 `$form->model()`。

| 方法 | 额外参数 | 时机 | 可返回响应中断 |
|---|---|---|---|
| `creating` / `editing` | — | 渲染新增/编辑页 | 否（返回值忽略） |
| `submitted` | — | 提交后、验证前 | 是 |
| `saving` | — | 验证通过后、写库前 | 是 |
| `saved` | `$result` | 写库后；新增为新主键，更新为 bool | 是 |
| `deleting` / `deleted` | — / `$result` | 删除前 / 后 | 是 |
| `uploading` | `$field, UploadedFile $file` | 上传请求，存文件前 | 是 |
| `uploaded` | `$field, UploadedFile $file, $response` | 上传完成 | 是 |
| `fileDeleting` / `fileDeleted` | `$field` | 删除文件前 / 后 | 否 |

- 只有返回 `Dcat\Admin\Http\JsonResponse`（`$form->response()`）或 Symfony `Response`（含 `redirect()`）才会中断，其它返回值忽略。上传事件是独立请求，返回 redirect 无意义。
- 回调中 `throw new \Exception('x')`：`APP_DEBUG=false` 时返回错误提示 `[Exception] x`，调试时重新抛出；`HttpResponseException` 总是直接抛出（源码：`src/Exception/Handler.php`）。稳定提示优先 `return $form->response()->error()`。

> ⚠️ `$form->error()` / `$form->success()` 不存在（报 "Field type [error] does not exist"），应 `return $form->response()->error('...');`，忘记 `return` 不生效。

## 响应与跳转

`$form->response()` 返回 `Dcat\Admin\Http\JsonResponse`，方法全集见 `actions.md`「JsonResponse 响应方法」。要点：`redirect()` 为 admin 相对路径的 pjax 跳转；`location()` 整页跳转（无参整页刷新）；`refresh()` 局部刷新；后续动作只能有一个（后设覆盖先设）；条件版本是后缀 `redirectIf/locationIf/refreshIf`，不是 `ifRedirect`。`creating/editing` 中返回响应无效。

## 布局

- `column(int|float $width, Closure)`：宽度 1–12，`<1` 小数按 12 换算（`0.5` → 6）；回调参数是 **`Form`**。也可在 `hasMany`/`array`/`tab`/`block` 内使用。`$form->width(9, 2)` 设全部字段栅格。
- `row(fn (Form\Row $row) => ...)`：`$row->width(4)->text(...)` 的 `width()` 只作用于下一个字段，默认 12，可 `defaultWidth(6)`；另有 `horizontal()`、`model()`、`getKey()`。
- `tab($title, Closure $content, $active = false, ?string $id = null)`，可链式；也可在全部 `tab()` 之后 `$form->getTab()->active('标题')` / `activeByIndex(1)`。
- `fieldset($title, Closure)->collapsed()`。
- `block(int $width, fn (Form\BlockForm $form) => ...)`：`BlockForm` 有 `title()`、`showFooter()`、`next(Closure)`（同列下方追加一块）、`width()`、`column()`、`row()`、字段方法；`model()/getKey()` 代理主表单。

> ⚠️ `tab` 内 `column` 回调参数写成 `Form\BlockForm` 会 TypeError，应为 `Form`；只有 `block()` 内才是 `BlockForm`。在添加 tab **之前**调用 `getTab()->active()` 无效。

> ⚠️ 使用 `block()` 时必须在某一块中调用 `$form->showFooter()`，否则没有提交按钮。

## 条件构建 if

源码 `src/Form/Condition.php`。

```php
$form->if(fn (Form $form) => $form->model()->status == 1)
    ->then(fn (Form $form) => $form->number('rate'))
    ->else(fn (Form $form) => $form->display('rate'));
$form->if($isAdmin)->ignore('secret');          // 未知方法代理为 $form->xxx(...)，满足条件时执行
```

- `if($condition)` 的闭包条件与 `then()` 链在闭包（`build`）结束后才处理，所以能拿到 `model()`；`->now(Closure)` 立即判断并执行。

## 字段联动显示 when

`radio` / `select` / `checkbox` / `multipleSelect` 支持：

```php
$form->radio('type')->options([1 => '文本', 2 => '编辑器'])
    ->when(1, fn (Form $form) => $form->text('text1')->rules('required_if:type,1'))
    ->when([2, 3], fn (Form $form) => $form->editor('editor'))
    ->default(1);
$form->checkbox('nation')->options([...])->when('notIn', 2, fn (Form $form) => $form->text('idcard'));
```

- `when($operator, $value, $closure)`；两参数时默认操作符：单选 `=`，多选（checkbox/multipleSelect）`in`。操作符：`=` `>` `>=` `<` `<=` `!=` `in` `notIn` `has`。
- 被隐藏的字段仍会提交、仍会验证，不要用 `required()`。可在 `tab`/`column` 内使用。

## 工具栏与底部

- `$form->tools(fn (Form\Tools $tools) => ...)`：`disableList/disableView/disableDelete`、`append/prepend`（string/Renderable/Action）；也可 `$form->tools(new MyTool())`。`disableHeader()` 去掉头部。
- `$form->footer(fn (Form\Footer $footer) => ...)`：`disableReset/disableSubmit/disableViewCheck/disableEditingCheck/disableCreatingCheck`、`defaultViewChecked/defaultEditingChecked/defaultCreatingChecked`、`view($view, $data)`。`disableFooter()`。
- `Form` 上的快捷方法都接受 `bool $disable = true`（传 `false` 可在全局禁用后恢复）：`disableListButton` `disableViewButton` `disableDeleteButton` `disableSubmitButton` `disableResetButton` `disableViewCheck` `disableEditingCheck` `disableCreatingCheck` `defaultViewChecked` `defaultEditingChecked` `defaultCreatingChecked`。
- 自定义工具继承 `Dcat\Admin\Form\AbstractTool`（Action），见 `actions.md`。

## 其它表单级方法

`title($title)`、`action($url)`（经 `admin_url()`；无参返回当前值）、`setResource($path)`（影响默认 action、跳转、工具栏链接）、`width($field = 8, $label = 2)`、`confirm(?string $title, ?string $content)`（提交前确认）、`wrap(fn (Renderable $view) => Card::make($view))`、`ajax(bool)`（关闭后服务端仍返回 JSON）、`field($name)` / `fields()` / `removeField($column)`、`resolvingField(fn (Field $field, Form $form) => ...)`（每个字段加入时）、`view($view)` / `addVariables(array)`、`Form::extend/alias`（见 `form-fields.md`）。

## 弹窗表单

- Grid 内置：`enableDialogCreate()`、`showQuickEditButton()`、`setDialogFormDimensions('50%', '600px')`，见 `grid.md`。
- `Form::dialog(?string $title)` 返回 `Dcat\Admin\Widgets\DialogForm`，**对象析构时自动输出 JS**。原理：Ajax 加载资源的 `create`/`edit` 页（URL 带 `_dialog_form_=1`），提交仍走 `store/update`。

```php
Form::dialog('编辑角色')
    ->click('.edit-form')                   // 触发元素（必填）；元素上的 data-url 优先于 ->url()
    ->url('auth/roles/create')
    ->dimensions('50%', '400px')            // 默认 700px × 670px
    ->forceRefresh()                        // 每次点击重新拉取表单
    ->success('Dcat.reload()');             // 另有 error()/saved()，JS 作用域内有 success、response
```

- 另有 `resetButton(bool)`。弹窗中自动隐藏表单头部/底部、宽度设为 `width(9, 2)`。
- `$form->inDialog()` 判断是否弹窗请求，`inDialog(fn (Form $form) => ...)` 仅弹窗时执行；字段 `->hideInDialog()`。
- 不依赖资源控制器的弹窗用工具表单（`Widgets\Form` + Modal），见 `widgets.md`。

## 分步表单

`multipleSteps()` 已从核心移除（核心无 `multipleSteps`/`StepForm`/`DoneStep`），需扩展 `dcat-admin/form-step`，API 以扩展文档为准；最终通过 `Form::store()` 保存，没有编辑模式，编辑页一般 `if ($form->isEditing()) { 普通字段...; return; }`。

## 全局初始化与翻译

- `Form::resolving(callable $callback, bool $once = false)`：Form 实例化时（闭包执行前）；`Form::composing(callable, bool $once = false)`：渲染页面时（提交请求不触发）。常写在 `app/Admin/bootstrap.php` 统一 `disableEditingCheck()` 等，单个表单用 `disableEditingCheck(false)` 恢复。
- 字段标签翻译：未传 label 时读 `resources/lang/{locale}/{控制器名 kebab}.php` 的 `fields.{column}`，找不到再读 `global.php` 的 `fields`。控制器 `protected $translation = 'user1';` 或 `Admin::translation('user1')` 指定语言包名。
