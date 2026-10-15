# dcat-admin 路由主键加密功能使用说明

> 适用于 `zgy-dcat/laravel-admin`（dcat-admin）的路由主键加密能力。
> 页面 URL 中的主键（如 `/admin/books/1/edit` 里的 `1`）会被加密成不可读的密文（默认
> **36 位 UUID 样式**），防止直接暴露数据库主键。

---

## 1. 快速开始

### 1.1 启用加密

在项目根目录 `.env` 中配置：

```dotenv
# 是否启用 URL 主键加密
ADMIN_ROUTE_ENCRYPT=true

# 加解密盐（字符串，必须有值；改盐会让旧密文全部失效）
ADMIN_ROUTE_CIPHER_SALT=111
```

> **注意**：`ADMIN_ROUTE_CIPHER_SALT` 必填且不能为空，否则加解密会抛异常。
> 修改盐之前生成的密文将在修改后无法解密，请勿随意变更。
>
> 魔数 `cipher_magic` 为可选（默认 `\x02`，一般无需配置），
> 同样参与解密校验，改动会让旧密文失效，详见 [§5 配置项详解](#5-配置项详解)。

启用后 `php artisan config:clear` 刷新配置缓存。

### 1.2 验证是否生效

打开任意后台列表页（如 `/admin/books`），查看「编辑 / 查看 / 删除」按钮链接：

- 启用前：`/admin/books/1/edit`
- 启用后：`/admin/books/f5a24e32-xxxx-xxxx-xxxx-xxxxxxxxxxxx/edit`（UUID 样式密文）

登录与权限等原有功能不受影响，控制器里拿到的主键**仍是明文**（由中间件自动解密）。

---

## 2. 加密范围

启用后，以下场景的 URL 主键会被加密（`encrypt=true` 时才生效，默认 `false` 完全不影响现有功能）：

| 场景 | 组件 | 说明 |
|---|---|---|
| Grid 行操作 | 编辑 / 查看 / 删除按钮 | 自动生效 |
| Delete 弹窗 | 单行 / 批量删除确认弹窗 | 不再展示明文主键 |
| Form 表单 | 编辑页提交、查看、删除 | 自动生效 |
| Show 详情页 | 编辑 / 删除按钮 | 自动生效 |
| Tree 树形列表 | 编辑 / 快速编辑 / 删除 | 自动生效 |
| 行内编辑 | `editable()`、`select()`、`switch()`、`switchGroup()` | 自动生效 |
| 行内排序 | `orderable()` 的 ↑↓ 排序请求 | 启用后自动生效 |

**不加密**：

- Query 字符串参数（`?filter=xx` 等）不受影响
- **批量删除选中主键**（`data-id` / `keys`）为明文：批量删除按钮的请求 URL 是
  `resource/{id1,id2,...}`，其中 `keys` 来自 Grid 行选择器（RowSelector）的 `data-id`。
  该通道同时被「导出选中行」复用（`ExportButton` 直接拼 `__rows__`），
  因此保持明文以兼容导出；确认弹窗已置空，不展示这些明文主键。
- **单行删除**（Grid/Tree/Form 的删除按钮）是**加密的**：`data-url` 里的主键经
  `admin_cipher_encrypt($key, 'gi' / 'fo')` 加密，只有确认弹窗文案被置空
  （不展示明文），URL 本身是密文。

---

## 3. 作用域（Scope）

加密时有一个「作用域」概念，用于区分同一个主键在不同上下文下的密文。

`UuidCipher` 把**作用域短标签本身**（1~2 个可打印 ASCII 字符）写入密文第 2-3 字节
（右补 `\x00`）。**作用域必须是 1~2 个可打印 ASCII 字符**（如 `gi`/`fo`/`bo`），
超长（如 `grid.id`、`book:grid.id`）或中文会直接抛异常，不会静默截断：
**不再强制校验作用域注册**：只要符合长度/字符集约束即可加密；解密时读出 2 字节标签，
必须与传入的 scope 一致（不匹配返回 null，防跨场景重放）——不再校验是否
在某个作用域数组（即不要求注册），但**标签比对是必须的**。
> CryptCipher 无 1~2 字节限制（`[scope]:` 前缀可任意长度），但仍需与加密时一致。

内置约定（仅供参考，不强制）：

| 作用域 | 用途 |
|---|---|
| `gi` | Grid 行操作（编辑/查看/删除按钮） |
| `fo` | Form 表单（提交/删除/查看） |

**`cipher_scopes` 配置为历史兼容保留**：旧版 `UuidCipher` 用它强制校验作用域注册，
当前实现不再读取做强制校验，可保留可删除：

```php
'route' => [
    ...
    // 历史兼容，可省略
    'cipher_scopes' => [
        // 历史兼容示例（旧版用）

    ],
],
```

**控制器指定最终作用域（可选）**：控制器可定义 `cipherScope` 属性，其属性值**直接作为
该控制器 URL 加密的最终作用域**（不再拼接前缀/合成）。这样同一控制器的 grid / form
URL 都使用同一个作用域隔离：

```php
class BookController extends AdminController
{
    // cipherScope 直接是最终作用域短标签（1~2 可打印 ASCII 字符）
    // 该控制器所有加密 URL 统一使用此作用域（不再用调用点的 gi/fo）
    protected $cipherScope = 'bo';
}
```

> **不设置 `cipherScope`** 时行为等同旧版，使用调用点的 `gi` / `fo`。
> **解密必须传入作用域** —— 密文自带 2 字节标签/前缀，解密时需传与加密一致的 scope，
> 不匹配返回 null（防跨场景重放）。
> **中间件解密的作用域来源**：
> - 控制器配置了 `cipherScope` → 中间件**只用它**解密；密文标签与它不一致 → 解密失败 → 404
>   （这正是"用 b1 解 bo 密文应 404"的期望行为）；
> - 未配置 `cipherScope`（或闭包路由）→ 回退自动提取密文标签解密（内置 gi/fo 兼容场景）。

---

## 4. 手动加解密（公开 Helper）

包提供两个公开全局函数，`$key`（作用域）**必填**，主键**仅支持正整数**：

```php
// helpers.php 无 namespace，函数是全局的，直接调用即可
$cipher = admin_cipher_encrypt(42, 'bo');
```

### 4.1 加密

```php
$cipher = admin_cipher_encrypt(42, 'bo');
// 返回：f5a24e32-xxxx-xxxx-xxxx-xxxxxxxxxxxx（UUID 样式密文）
// 参数：$plain 正整数主键（int，弱类型下数字字符串也能传）；$key 作用域必填
```

- 非正整数（`0` / `负数` / 非数字）→ 抛 `InvalidArgumentException`
- `$key` 通过类型声明 `string` 强制必填：缺参 / 传 `null` → PHP `TypeError`（不满足类型即被引擎拦截，不进入业务逻辑）
- 传空串 `''` → 抛 `InvalidArgumentException`（防产出无 scope 的密文）

### 4.2 解密

```php
$plain = admin_cipher_decrypt($cipher, 'bo');
// 返回：'42'（明文主键字符串）；失败返回 null
// 参数：$cipher 密文；$key 作用域必填，需与加密时一致
```

- `$key` 通过类型声明 `string` 强制必填：缺参 / 传 `null` → PHP `TypeError`；传空串 `''` → 抛 `InvalidArgumentException`（不进入解密流程）
- 传入的 `$key` 与密文内嵌 scope 不一致 → 返回 `null`（防跨场景重放）
- 解密结果非正整数 → 返回 `null`
- 页面访问走中间件：控制器配了 `cipherScope` 时用它校验（不匹配 → 404），未配置时自动提取标签解密

### 4.3 完整示例

```php
// 加密
$cipher = admin_cipher_encrypt($book->id, 'bo');   // 'f5a24e32-...'
$url    = "/admin/books/{$cipher}/edit";

// 解密
$id = admin_cipher_decrypt($cipher, 'bo');          // '42'
if ($id === null) {
    abort(404);
}
```

---

## 5. 配置项详解

`config/admin.php` 的 `route` 段：

```php
'route' => [
    // 是否启用 URL 主键加密（默认 false）
    'encrypt' => env('ADMIN_ROUTE_ENCRYPT', false),

    // 加解密实现类，必须实现 \Dcat\Admin\Contracts\UrlCipher
    // 当前默认：UuidCipher（AES 伪 UUID 版，密文 36 位 UUID 样式）
    // 可选：    CryptCipher（配置盐混淆版，密文 [scope]:hex）
    'cipher' => \Dcat\Admin\Support\UuidCipher::class,

    // 加解密盐（必须有值）
    'cipher_salt' => env('ADMIN_ROUTE_CIPHER_SALT', 'dcat_c'),

    // UuidCipher 明文块第一字节魔数（默认 "\x02"）
    // 支持：int（如 2）/ "0x02" / "\x02" 单字节字符串，最终归一为单字节。
    // 注意：此键不读 env()，只能在这里改 PHP 字面量；魔数参与解密校验，
    // 改动后旧密文将无法解密（与新 salt 同理），如无必要请保持默认。
    'cipher_magic' => "\x02",
],
```

### 两种内置实现对比

| | **UuidCipher**（默认） | **CryptCipher**（可选） |
|---|---|---|
| 密文样式 | 36 位 UUID：`f5a24e32-xxxx-...` | `[gi]:a1b2c3...`（hex） |
| 密钥派生 | AES-256 + PBKDF2（100k 迭代） | 配置盐 XOR 混淆（sha256 派生） |
| 支持主键 | 正整数 1 ~ 1099511627775（uint40） | 非负 int（无长度上限） |
| 作用域内嵌 | 2 字节字符串标签（如 gi/fo/bo） | `[scope]:` 前缀 |
| 完整性校验 | 第一字节必须为配置魔数（默认 0x02）+ 标签与传入 scope 一致 | 前缀与传入 scope 一致（靠前缀 + XOR 还原） |
| 适用场景 | 追求密文形式隐蔽、更安全 | 简单轻量、可读性略高 |

切换实现只需改 `cipher` 配置（需要同时保证 `cipher_salt` 存在）：

```php
'cipher' => \Dcat\Admin\Support\CryptCipher::class,
```

> 切换后旧密文无法解密（加密算法不同），请合理安排切换时机。

### UuidCipher 字节布局

`UuidCipher` 加密前会把主键拼成一个 **16 字节明文块**（恰好一个 AES 块，无需填充），
然后整体走 AES-256-ECB 加密，hex 后拼成 36 位 UUID 样式：

```
┌─────────┬───────────────┬───────────────┬─────────────────┐
│ 魔数 1B  │ 作用域标签 2B  │ 填充 8B        │ 主键 5B（大端）   │
│ 可配置   │ 短标签 ASCII  │ 0x00 全 0     │ uint40 BE       │
└─────────┴───────────────┴───────────────┴─────────────────┘
offset:   0              1..2           3..10          11..15
```

- **魔数**：默认 `0x02`，可通过 `admin.route.cipher_magic` 配置化（详见 §5），
  解密时**第一个字节必须等于配置的魔数**，否则拒绝；
- **作用域标签（2 字节）**：作用域短标签本身（1~2 个可打印 ASCII 字符）
  （右补 `\x00` 到 2 字节）。**作用域必须本身是 1~2 个可打印 ASCII 字符**，
  超长/中文在加密时抛异常（不做静默截断，避免标签碰撞）。
  **不再强制校验注册**：解密时读出 2 字节 → 去掉尾部 `\x00` → 标签必须与传入 scope 一致
  （不匹配返回 null，防跨场景重放）；不再校验是否在作用域数组（即不要求注册）。
- **填充**：8 字节全 0；
- **主键（5 字节）**：主键自身的 5 字节大端表示（uint40，字节序 高→低），仅支持正整数 `1 ~ 1099511627775`。

> **主键上限**：`UuidCipher` 支持 uint40 主键（最大约 1.1 万亿）。如果表主键是
> bigint / 超过 1.1 万亿，请改用 `CryptCipher`（不设此上限），或在分表后使用。

---

## 6. 自定义加密实现

如果内置两种都不满足，可实现 `UrlCipher` 接口：

```php
<?php

namespace App\Support;

use Dcat\Admin\Contracts\UrlCipher;

class MyCipher implements UrlCipher
{
    public function encrypt(int $plain, string $key): string
    {
        // 返回 URL 安全字符集的密文（必须避免 / + = 等路径特殊字符）
        return rtrim(strtr(base64_encode("{$plain}|{$key}"), '+/', '-_'), '=');
    }

    public function decrypt(string $cipher, string $key): ?string
    {
        $raw = base64_decode(strtr($cipher, '-_', '+/'), true);
        if ($raw === false) {
            return null;   // 非法 base64 → 非本实现密文
        }
        [$plain, $scope] = array_pad(explode('|', $raw, 2), 2, '');
        if ($key !== $scope) {
            return null;   // scope 不匹配拒绝
        }
        return $plain;
    }
}
```

然后在 `config/admin.php` 指定：

```php
'cipher' => \App\Support\MyCipher::class,
```

接口签名（`Contracts/UrlCipher.php`）：

```php
public function encrypt(int $plain, string $key): string;
public function decrypt(string $cipher, string $key): ?string;
```

---

## 7. 常见问题

### 7.1 页面 URL 还是明文？

确认 `.env` 里 `ADMIN_ROUTE_ENCRYPT=true` 且执行了 `php artisan config:clear`。
默认值是 `false`，不改就完全不加密（未显式配置时走 `config/admin.php` 的默认值）。

### 7.2 改了 salt 后所有链接打不开了？

对，改了 `ADMIN_ROUTE_CIPHER_SALT` 会让旧密文全部失效。中间件解密失败会回退明文尝试，
但基本都会 404。**salt 定下来就别改**，或者在无存量密文时再改。

### 7.3 控制器里拿到的还是明文吗？

是的。中间件负责把 URL 里的密文主键解密回明文，控制器 `$id` 收到的仍是正常的主键值，
业务代码无需改动。

### 7.4 Orderable 排序按钮没有加密？

`orderable()` 是可选 displayer，需要你在 Grid 里启用才有：

```php
$grid->column('sort')->orderable();
```

启用后 ↑↓ 图标的 `data-id` 和排序 PUT 请求 URL 会自动携带密文。

### 7.5 手动解密失败？

`admin_cipher_decrypt` 要求传入与加密时**一致的 scope**。**UuidCipher 和 CryptCipher 解密时
都会校验 scope**：UuidCipher 读取内嵌的 2 字节标签与传入 `$key` 比对，不匹配返回 `null`；
CryptCipher 比对密文 `[scope]:` 前缀，不匹配返回 `null`。因此传错 scope 会返回 `null`
（防跨场景重放）——这是手动解密失败的常见原因。

### 7.6 改了 cipher_magic 后所有链接打不开了？

同样会。`cipher_magic` 参与解密校验（第一字节必须等于配置值），改动后旧密文第一字节
与新的魔数不匹配 → 解密失败。**cipher_magic 和 salt 一样，定下来就别改**；
它没有 `env()` 包裹，只能改 `config/admin.php` 里的 PHP 字面量。

---

## 8. 文件清单（涉及本次功能）

```
src/Contracts/UrlCipher.php                 # 加解密接口契约
src/Support/UrlCipherManager.php            # 统一入口（encrypt/decrypt/开关判断/expectedScope）
src/Support/CryptCipher.php                 # 可选实现 B：配置盐混淆版
src/Support/UuidCipher.php                  # 默认实现 A：AES 伪 UUID 版（cipher_magic 配置化）
src/Http/Middleware/Cipher.php              # 中间件（解密路由主键）
src/Http/Controllers/AdminController.php    # cipherScope getter
src/Support/helpers.php                     # admin_cipher_encrypt / admin_cipher_decrypt
config/admin.php                            # 配置项（encrypt / cipher / cipher_salt / cipher_magic / cipher_scopes）
```

---

*文档版本：2026-08-15，对应 `UuidCipher` 默认实现。*