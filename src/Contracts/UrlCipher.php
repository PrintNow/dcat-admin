<?php

namespace Dcat\Admin\Contracts;

/**
 * URL 参数加解密接口.
 *
 * 包内所有路由参数的加密/解密都经由该接口完成。
 * 默认实现为 {@see \Dcat\Admin\Support\UuidCipher}
 * （AES 伪 UUID 版：16 字节块 AES-256-ECB，PBKDF2 派生密钥，密文 36 位 UUID 样式；
 * 仅支持正整数主键，范围 1 ~ 1099511627775（uint40）；作用域内嵌密文）。
 * 你也可以通过配置项 `admin.route.cipher` 切换为 CryptCipher 等自定义实现。
 *
 * 注意：加密仅支持 int 主键。
 */
interface UrlCipher
{
    /**
     * 加密一个明文主键（仅支持 int）.
     *
     * @param  int  $plain  明文主键
     * @param  string|null  $key  作用域标识，同一个明文在不同作用域下可用不同密文，防止跨场景重放
     * @return string 密文（URL 安全字符集）
     */
    public function encrypt(int $plain, string $key): string;

    /**
     * 解密一个密文.
     *
     * @param  string  $cipher  密文
     * @param  string|null  $key  作用域标识（必填）；解密时必须与加密时一致，不匹配返回 null
     * @return string|null 解密后的明文；解密失败时返回 null（由调用方决定是否回退明文）
     */
    public function decrypt(string $cipher, string $key): ?string;
}