# 上传与编辑器字段（file / image / multipleFile / multipleImage / editor / markdown）

> 覆盖基于 WebUploader 的上传字段与 TinyMCE（editor）、editor-md（markdown）编辑器字段；通用字段方法见 `form-fields.md`，事件见 `form.md`。

## 配置与机制

- `config/admin.php` 的 `upload.disk`（默认 `public`）与 `upload.directory.image`（`images`）/`.file`（`files`）。本地 public disk 需 `storage:link` 并设对 `APP_URL`（预览 URL 由 `Storage::disk()->url()` 生成）。
- file/image 提交、保存为路径字符串；multipleFile/multipleImage 为**路径数组**，**不会自动编码**（`$casts = ['photos' => 'array']` / `saveAsJson()` / `saving(fn ($v) => implode(',', $v))`）。读取时数组、JSON、逗号分隔都能识别。image 默认规则 `nullable|image`、mimeTypes `image/*`。
- 默认只存相对 disk 的路径（如 `images/a.png`），`saveFullUrl()` 才存完整 URL。
- 上传流程：选择文件后 WebUploader 先把文件 POST 到表单自身的 store/update 地址（带 `upload_column`、`_id`），返回路径写入隐藏域，表单提交时只提交路径。编辑页默认 `autoSave` 开启：上传/删除后立即更新该列。
- 编辑时替换或移除文件会删除服务器上的旧文件（除非 `retainable()`）。

> ⚠️ 不要在模型里为上传列写拼接域名的访问器/修改器，否则回显与删除都出错；需要完整 URL 另写普通方法，或用 `saveFullUrl()`。

## 存储与命名（trait UploadField，源码：src/Form/Field/UploadField.php）

- `disk(string)`、`dir($dir)`（可为闭包，`$this` 为模型，参数 `$form`）、`move($directory, $name = null)` = dir + name。
- `name($name)`：字符串或闭包 `function (UploadedFile $file)`。默认用原始文件名，**同名已存在时自动改为随机名**。`uniqueName()`：`md5(uniqid('', true)).扩展名`。`sequenceName()`：**总是**追加序号，从 `原名_1.ext` 开始。
- `override()`：上传前删除同路径文件。`retainable()`：替换/删除时不删服务器文件。`storagePermission($p)`：`putFileAs` 的 visibility/选项。

## 前端组件（trait WebUploader，源码：src/Form/Field/WebUploader.php）

- `accept(string $extensions, ?string $mimeTypes = null)`、`mimeTypes()`；`maxSize(int $kb)` 同时加 `max:` 规则（默认前端上限 10MB）。
- `chunked()`、`chunkSize(int $kb)`（调用即开启分块）、`threads()`；`autoUpload()`（不显示上传按钮）；`autoSave(bool)`；`downloadable()`。
- `removable(false)`：点删除时**只从表单移除、不请求服务器删除**；默认会请求删除。
- `compress(bool|array)`：前端压缩，数组为 WebUploader compress 配置。
- `url(string $server)`：自定义上传接口（**同时设为删除接口**，经 `admin_url()`）；`deleteUrl()` 单独设置。`withFormData(array)` / `withDeleteData(array)`：附加请求参数。
- `on(string $event, string $script, bool $once = false)` / `once()`：WebUploader 事件，`$script` 为 JS 函数字符串（如 `startUpload` 中改 `this.uploader.options.formData`）。`options(['fileNumLimit' => 5])` 合并配置。
- `disable()`：禁用上传组件（非 HTML disabled）。

## 图片专属（image/multipleImage）

- `dimensions(array)`：键 `width/height/min_width/min_height/max_width/max_height/ratio`，同时加 Laravel `dimensions:` 规则并前端校验；`ratio($r)` 快捷。
- `thumbnail($name, ?int $width = null, ?int $height = null)`（**字符串形式必须同时传宽高，否则静默无效**）或 `thumbnail(['small' => [100, 100]])`：文件名追加 `-small`。模型 `use Dcat\Admin\Traits\Resizable;` 后 `$model->thumbnail('small', 'cover')` 返回**路径**（非 URL）。
- Intervention 透传：image 上任何未定义方法（`resize`、`crop`、`fit`、`insert`、`filling`→`fill`）记录后在上传时执行（`ImageField::__call`）。

> ⚠️ 依赖 `intervention/image` **2.x**（`ImageManagerStatic`，3.x 已移除）；未安装时抛 `AdminException`，thumbnail 同样依赖。拼错方法名不报 BadMethodCall，上传时才失败。

## 多文件专属

`limit(int)`（默认 10，`< 2` 忽略；超出提交校验报错）、`sortable()`。

## 验证规则的执行时机

- `rules()` 中 `mimes`、`max`、`image`、`dimensions` 等**在上传请求时**对 `UploadedFile` 校验（`UploadField::getValidationErrors`）。
- 表单提交时 `File::getValidator` 只构造 `required` / `required_if*`（及多文件数量）规则；hasMany/embeds 子表单内通过 `File::deleteRules` 剔除 image/file/dimensions/size/max/min。

## 自定义上传接口（Dcat\Admin\Traits\HasUploadedFile）

```php
public function handle()   // 路由 $router->any('users/files', ...)；字段 ->url('users/files')
{
    $disk = $this->disk('public');
    if ($this->isDeleteRequest()) {
        return $this->deleteFileAndResponse($disk);
    }
    $path = $disk->putFile('my-images', $this->file());   // $this->uploader()->upload_column 为字段名
    return $path ? $this->responseUploaded($path, $disk->url($path)) : $this->responseErrorMessage('上传失败');
}
```

其他：`responseValidationMessage($msg)`、`responseDeleted()`、`responseDeleteFailed($msg = '')`、`deleteFile($disk = null, $path = null)`。

## 常见片段

- 路径存附件表、主表存 ID：`->saving(fn ($path) => $path ? Attachment::firstOrCreate(['path' => $path])->getKey() : null)->customFormat(fn ($id) => $id ? Attachment::whereKey($id)->value('path') : null)`。
- 监听文件变动：上传成功或删除后隐藏域 `{$field->getElementClassSelector()} .file-input` 触发 `change`。

## editor（TinyMCE，源码：src/Form/Field/Editor.php）

`options(array)`（合并，回调用 `Dcat\Admin\Support\JavaScript::make('function (editor) {...}')`）、`height(int = 400)`、`languageUrl()`、`disk()`（默认 `admin.upload.disk`）、`imageDirectory('tinymce/images')`、`imageUrl($url)`（响应须为 `{"location": "URL"}`）、`readOnly()`/`disable()` 均为只读。

## markdown（editor-md，源码：src/Form/Field/Markdown.php）

`options(array)`、`height(500)`、`languageUrl()`（内置 en、zh_TW 自动加载，zh_CN 默认）、`htmlDecode('style,script,iframe')`、`disk()`、`imageDirectory('markdown/images')`、`imageUrl($url)`（响应须为 `{"success": 1, "url": "URL"}`）、`readOnly()`/`disable()`。

全局默认：`Editor::resolving(fn (Editor $e) => $e->disk('oss'))`、`Markdown::resolving(...)`，写在 `app/Admin/bootstrap.php`。
