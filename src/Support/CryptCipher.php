<?php

namespace Dcat\Admin\Support;

use Dcat\Admin\Contracts\UrlCipher;

/**
 * 极简 URL 主键加解密实现（配置盐混淆版）.
 *
 * 密文结构：[scope]:<payload>
 *
 * - scope 前缀同时充当「密文标识」：只有带 [xxx]: 前缀的值才会被尝试解密，
 *   纯明文 URL（如手工输入 /users/42）不会落入解密逻辑，避免误伤；
 * - cipher_salt 为必填项：未配置或为空时抛异常（防误用）；
 * - payload = hex(明文逐字节 XOR 派生密钥)，URL 安全、无 base64。
 *
 * 派生密钥：sha256(cipher_salt . '|' . scope)
 *   - 同一 cipher_salt 下不同 scope 使用不同字节流，隔离使用场景；
 *   - 切换 cipher_salt 后，旧链接将无法解密（属预期）。
 *
 * 注意：本实现不设主键上限（非负 int 即可）；未做防篡改校验（无完整性位），
 * 适合追求简单轻量的场景；默认实现是 {@see UuidCipher}，本类需在
 * config('admin.route.cipher') 显式指定。
 */
class CryptCipher implements UrlCipher
{
    /**
     * {@inheritdoc}
     */
    public function encrypt(int $plain, string $key): string
    {
        // 作用域由类型系统强制必填（string $key）
        $salt = $this->salt();

        $payload = $this->obfuscate((string) $plain, $salt, $key);

        return '['.$key.']:'.$payload;
    }

    /**
     * {@inheritdoc}
     *
     * - 无 [scope]: 前缀的值视为非密文，直接返回 null（不尝试解密）；
     * - 作用域 `$key` **必填**（类型声明 `string`）：缺参 / 传 `null` → PHP `TypeError`；
     * - 严格校验作用域前缀，与传入 `$key` 不匹配返回 null。
     */
    public function decrypt(string $cipher, string $key): ?string
    {
        // 作用域由类型系统强制必填（string $key）；随后与密文前缀比对，不匹配返回 null

        // 提取作用域前缀；无前缀视为非密文
        if (! preg_match('/^\[([^\]]+)\]:/', $cipher, $m)) {
            return null;
        }

        if ($key !== $m[1]) {
            return null;
        }

        $key = $m[1];
        $payload = substr($cipher, strlen($m[0]));

        return $this->deobfuscate($payload, $this->salt(), $key);
    }

    /**
     * 读取配置盐；必须配置，为空抛异常.
     *
     * @return string
     *
     * @throws \RuntimeException
     */
    protected function salt(): string
    {
        $salt = (string) config('admin.route.cipher_salt', '');

        if ($salt === '') {
            throw new \RuntimeException('admin.route.cipher_salt 未配置或为空，请设置 cipher_salt（如 ADMIN_ROUTE_CIPHER_SALT=xxx）');
        }

        return $salt;
    }

    /**
     * 混淆：明文逐字节 XOR 派生密钥，再转 hex.
     *
     * @param  string  $plain
     * @param  string  $salt
     * @param  string  $key
     * @return string
     */
    protected function obfuscate(string $plain, string $salt, string $key): string
    {
        $derived = hash('sha256', $salt.'|'.$key, true);

        $out = '';
        $len = strlen($plain);

        for ($i = 0; $i < $len; $i++) {
            $out .= $plain[$i] ^ $derived[$i % 32];
        }

        return bin2hex($out);
    }

    /**
     * 反混淆：hex 还原后逐字节 XOR 派生密钥；格式非法返回 null.
     *
     * @param  string  $payload
     * @param  string  $salt
     * @param  string  $key
     * @return string|null
     */
    protected function deobfuscate(string $payload, string $salt, string $key): ?string
    {
        if ($payload === '' || strlen($payload) % 2 !== 0 || ! ctype_xdigit($payload)) {
            return null;
        }

        $raw = hex2bin($payload);

        if ($raw === false) {
            return null;
        }

        $derived = hash('sha256', $salt.'|'.$key, true);

        $out = '';
        $len = strlen($raw);

        for ($i = 0; $i < $len; $i++) {
            $out .= $raw[$i] ^ $derived[$i % 32];
        }

        return $out;
    }
}