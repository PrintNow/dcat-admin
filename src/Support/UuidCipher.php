<?php

namespace Dcat\Admin\Support;

use Dcat\Admin\Contracts\UrlCipher;

/**
 * UUID 伪装版 URL 主键加解密实现.
 *
 * 把主键包装成「看起来像随机 UUID」的密文，输出 36 字符 UUID 格式。
 *
 * 字节布局（16 字节明文 = 一个 AES 块，无需填充）：
 *   ┌─────────┬───────────────┬───────────────┬────────────────┐
 *   │  魔数 1B │ 作用域标签 2B  │ 填充 8B        │ 主键 5B（大端）  │
 *   │ 可配置  │ 短标签 ASCII  │ 0x00 全 0     │ uint40 BE      │
 *   └─────────┴───────────────┴───────────────┴────────────────┘
 *   offset : 0              1..2           3..10          11..15
 *
 * ① 魔数第一字节：默认 0x02，可通过 `admin.route.cipher_magic` 配置化
 *    （详见 {@see UuidCipher::magic()}）；加解密共用同一值，
 *    解密时第一字节必须等于配置的魔数，否则拒绝（防误伤明文/垃圾数据）；
 * ② 作用域标签（2 字节）：作用域本身必须是 1~2 个可打印 ASCII 字符的短标签
 *    （如 'gi' / 'fo' / 'bo'），整体写入第 2-3 字节（右补 \x00）。
 *    不再强制校验注册（不要求 SCOPES / cipher_scopes）——任何非空作用域都可加密；
 *    解密时读出 2 字节 → 去掉尾部 \x00，仅要求非空，且必须与传入的 $key 一致
 *    （不匹配返回 null，防跨场景重放）。
 * ③ 主键（5 字节）：主键自身的 5 字节大端表示（uint40，字节序 高→低），
 *    仅支持正整数 1 ~ 0xFFFFFFFFFF（约 1.1 万亿）；
 * ④ 16 字节整体走 AES-256-ECB 加密（OPENSSL_ZERO_PADDING，数据恰好一块）；
 * ⑤ bin2hex 后统一转大写，再按 8-4-4-4-12 拼成标准 UUID 字符串。
 *
 * 解密为逆过程：AES 解密 → 校验魔数 == 配置值 → 校验标签非空 → 还原主键。
 * 任何一步失败/格式非法 → 返回 null（由调用方决定是否回退明文）。
 *
 * 作用域语义：标签不同则密文不同，防止跨场景 KV 重放（同一明文在不同 scope 下密文不同），
 * 且加密结果确定（同 scope 同明文的密文幂等），URL 稳定可收藏。
 *
 * 密钥派生（PBKDF2 + 缓存）：
 * - AES-256 密钥 = PBKDF2-HMAC-SHA256(cipher_salt, domain 盐, 100000 迭代, 32 字节)；
 * - 强 KDF 拉伸低熵配置盐，离线暴力破解成本显著上升；
 * - 密钥在首次使用时派生并缓存（实例是 singleton，渲染 Grid 时不会反复计算）。
 *
 * 注意事项：
 * - 仅支持正整数主键，范围 1 ~ 1099511627775（uint40，0xFFFFFFFFFF），超界加密时抛异常；
 *   如需更大的主键请改用 {@see CryptCipher}（内部不设此上限）；
 * - 作用域不再强制校验注册，但必须是 1~2 个可打印 ASCII 字符的短标签（如 gi / fo / bo），
 *   超长或中文加密时抛异常（不做静默截断）；无需配置 cipher_scopes；
 *   解密时标签非空且必须与显式传入的 scope 一致，否则返回 null；
 * - 输出的「UUID」只是密文形式，不是 RFC4122 合法 UUID（版本/变体位是密文随机比特），
 *   不要作为真实 UUID 传给需要严格校验 UUID 的第三方系统；
 * - AES-ECB 确定性：同一明文（同 scope）产出同一密文，可据此观察「哪些 URL 指向同一条
 *   记录」；对「隐藏主键 + 防枚举」足够，不是机密性/防重放方案；
 * - 本版本无独立完整性校验位：篡改检测依赖 AES 解密失败 + 魔数/作用域标签/范围粗校验；
 * - 中间件解密优先用控制器 cipherScope（expectedScope）作为唯一可接受作用域，
 *   标签不一致 → 解密失败 → 404；未配置 cipherScope 时才回退自动提取密文标签
 *   （peekScope）解密；对外手动解密必须传 scope;
 * - 切换 cipher_salt 后旧链接无法解密（属预期）。
 *
 * 配置：admin.route.cipher 指定本类即可（当前默认）。
 */
class UuidCipher implements UrlCipher
{
    /**
     * 主键最大值：uint40（0xFFFFFFFFFF = 1099511627775）.
     */
    protected const MAX_PRIMARY_KEY = 0xFFFFFFFFFF;

    /**
     * PBKDF2 迭代次数：每次请求仅首次派生，之后走缓存，成本可控.
     */
    protected const PBKDF2_ITERATIONS = 100000;

    /**
     * PBKDF2 域分隔盐，避免与其它用途的派生撞车.
     */
    protected const PBKDF2_SALT = 'dcat-admin.url-cipher.v1';

    /**
     * @var string|null 派生的 AES-256 密钥（32 字节），首次使用时缓存
     */
    protected ?string $secretKey = null;

    /**
     * {@inheritdoc}
     *
     * @throws \InvalidArgumentException 主键非法 / 作用域超长或非可打印 ASCII
     * @throws \RuntimeException         cipher_salt 未配置或 AES 加密失败
     * @throws \InvalidArgumentException admin.route.cipher_magic 配置非法
     */
    public function encrypt(int $plain, string $key): string
    {
        if ($plain <= 0) {
            throw new \InvalidArgumentException('UuidCipher 仅支持正整数主键（1 ~ 1099511627775），传入了：'.$plain);
        }

        if ($plain > static::MAX_PRIMARY_KEY) {
            throw new \InvalidArgumentException('UuidCipher 仅支持 uint40 主键（最大 1099511627775），传入了：'.$plain);
        }

        $scopeLabel = $this->scopeLabel((string) $key);

        // 1. 拼 16 字节明文（一个 AES 块）
        $plaintext =
            $this->magic()                     // [0]    魔数（默认 0x02，可配置）
            .str_pad($scopeLabel, 2, "\x00", STR_PAD_RIGHT) // [1..2] 作用域短标签（右补 0）
            ."\x00\x00\x00\x00\x00\x00\x00\x00"           // [3..10] 填充 8 字节
            .chr(($plain >> 32) & 0xFF)   // [11] 主键第 1 字节（最高）
            .chr(($plain >> 24) & 0xFF)   // [12] 主键第 2 字节
            .chr(($plain >> 16) & 0xFF)   // [13] 主键第 3 字节
            .chr(($plain >> 8) & 0xFF)    // [14] 主键第 4 字节
            .chr($plain & 0xFF);          // [15] 主键第 5 字节（最低）

        // 2. AES-256-ECB 加密（数据恰好 16 字节，零填充无副作用）
        $cipherRaw = openssl_encrypt($plaintext, 'aes-256-ecb', $this->secret(), OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING);

        if ($cipherRaw === false) {
            throw new \RuntimeException('UuidCipher AES 加密失败，请检查 admin.route.cipher_salt 配置');
        }

        // 3. 32 位 hex 统一转大写后按 8-4-4-4-12 拼成 UUID 字符串
        //    （bin2hex 原生输出小写；转大写后 URL 与页面显示一致；
        //     解密侧 ctype_xdigit / hex2bin 兼容大小写，新旧密文均可解）
        $hex = strtoupper(bin2hex($cipherRaw));

        return implode('-', [
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20),
        ]);
    }

    /**
     * {@inheritdoc}
     *
     * - 作用域 $key **必填**（类型声明 `string`）：缺参 / 传 `null` → PHP `TypeError`；
     * - 长度非法 / 非 32 hex / 无连字符结构 → 视为非密文，返回 null（明文不误伤）；
     * - AES 解密失败 → 返回 null（篡改拒绝）；
     * - 第一个字节必须为配置的魔数（默认 0x02），否则返回 null；
     * - 第 2-3 字节读出作用域标签，必须与传入的 $key 一致，否则返回 null；
     * - 主键必须为正整数（> 0），否则返回 null；
     * - 成功返回明文主键字符串。
     */
    public function decrypt(string $cipher, string $key): ?string
    {
        // 作用域由类型系统强制必填（string $key），空串在标签比对时自然不匹配返回 null

        // 36 字符是合法 UUID 密文的最大长度，超长值直接视为非密文
        if (strlen($cipher) > 36) {
            return null;
        }

        $hex = str_replace('-', '', $cipher);

        if ($hex === '' || strlen($hex) !== 32 || ! ctype_xdigit($hex)) {
            return null;
        }

        $cipherRaw = hex2bin($hex);

        if ($cipherRaw === false || strlen($cipherRaw) !== 16) {
            return null;
        }

        $decrypted = openssl_decrypt($cipherRaw, 'aes-256-ecb', $this->secret(), OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING);

        if ($decrypted === false || strlen($decrypted) !== 16) {
            return null;
        }

        // 1. 核心校验：第一个字节必须为配置的魔数（默认 0x02）
        if ($decrypted[0] !== $this->magic()) {
            return null;
        }

        // 2. 校验：第 2-3 字节 = 作用域标签（去掉尾部 \x00）
        //    必须与传入的 $key 一致，不匹配直接拒绝（防跨场景重放）
        $scopeLabel = rtrim(substr($decrypted, 1, 2), "\x00");

        if ($scopeLabel === '' || $scopeLabel !== $key) {
            return null;
        }

        // 3. 主键范围校验：第 11-15 字节就是主键自身的 5 字节大端（uint40）
        //    读回后必须为正整数（> 0）
        $pkBytes = substr($decrypted, 11, 5);          // 最后 5 字节 = 主键 5B BE
        $byte = array_values(unpack('C*', $pkBytes));  // 5 个字节，各 8 位
        $plain = ($byte[0] << 32) | ($byte[1] << 24) | ($byte[2] << 16) | ($byte[3] << 8) | $byte[4];

        if ($plain <= 0) {
            return null;
        }

        return (string) $plain;
    }

    /**
     * 作用域标签：作用域本身必须就是 1~2 个【可打印 ASCII 字符】.
     *
     * 密文第 2-3 字节只放得下 2 字节（1~2 个单字节字符）。
     * 超出 / 多字节（如中文 UTF-8）会静默截断或切半，造成标签碰撞与非法字节，
     * 因此这里**严格校验**：不是 1~2 个可打印 ASCII 字符直接抛异常，
     * 而不是偷偷截断（如 'grid.id' 7 字符必须改成 'gi' 这类短标签才能加密）。
     *
     * 不校验是否「注册」——只要符合长度/字符集约束即可加密；
     * 解密侧必须传入与加密一致的 scope，标签不匹配返回 null.
     *
     * @param  string  $scope
     * @return string 1~2 个可打印 ASCII 字符
     *
     * @throws \InvalidArgumentException
     */
    protected function scopeLabel(string $scope): string
    {
        if ($scope === '') {
            throw new \InvalidArgumentException('UuidCipher 作用域必填，传入为空');
        }

        if (strlen($scope) > 2 || ! preg_match('/^[\x21-\x7E]+$/', $scope)) {
            throw new \InvalidArgumentException(
                'UuidCipher 作用域必须是 1~2 个可打印 ASCII 字符，传入了「'.$scope.'」（'.strlen($scope).' 字节）。'.'请使用短标签（如 gi / fo / bo），不要传完整作用域名（如 grid.id / book:grid.id）'
            );
        }

        return $scope;
    }

    /**
     * 提取密文内嵌的作用域标签（供中间件/调用方在解密前确定正确 scope）.
     *
     * 与 decrypt 的区别：不校验传入 scope（其目的就是拿 scope），
     * 只在密文合法时返回标签；格式非法 / AES 失败 / 魔数错（非配置值） / 标签空 → 返回 null.
     *
     * @param  string  $cipher
     * @return string|null  密文内嵌的 1~2 字符标签；非法密文返回 null
     */
    public function peekScope(string $cipher): ?string
    {
        if (strlen($cipher) > 36) {
            return null;
        }

        $hex = str_replace('-', '', $cipher);

        if ($hex === '' || strlen($hex) !== 32 || ! ctype_xdigit($hex)) {
            return null;
        }

        $cipherRaw = hex2bin($hex);

        if ($cipherRaw === false || strlen($cipherRaw) !== 16) {
            return null;
        }

        $decrypted = openssl_decrypt($cipherRaw, 'aes-256-ecb', $this->secret(), OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING);

        if ($decrypted === false || strlen($decrypted) !== 16 || $decrypted[0] !== $this->magic()) {
            return null;
        }

        $scopeLabel = rtrim(substr($decrypted, 1, 2), "\x00");

        if ($scopeLabel === '') {
            return null;
        }

        return $scopeLabel;
    }

    /**
     * @var string|null 解析后的魔数单字节（首次使用时缓存）
     */
    protected ?string $magicKey = null;

    /**
     * 解析配置的魔数并缓存（默认 0x02）.
     *
     * 取值 `admin.route.cipher_magic`，支持以下写法（最终归一为单字节字符串）：
     * - `"\x02"`（PHP 转义字符串，默认写法，作为字面单字节直接使用）
     * - `2`（int 十进制）、`0x02`（int 十六进制）
     * - `'0x02'` / `'2'`（多字节数字字符串：0x 前缀按十六进制，否则十进制）
     * - `"\x01"` 等任意单字节转义字符串（字面使用）
     *
     * 加解密两侧共用该方法读取同一个魔数：加密写入、解密/peekScope 校验。
     *
     * 注意：魔数参与解密校验，改动配置会让旧密文全部无法解密（与新 salt 同理），
     * 如无必要请保持默认 `"\x02"`。
     *
     * @return string 单字节魔数
     *
     * @throws \InvalidArgumentException 配置值无法归一为单个字节（0 ~ 255）时
     */
    protected function magic(): string
    {
        if ($this->magicKey !== null) {
            return $this->magicKey;
        }

        $value = config('admin.route.cipher_magic', "\x02");

        if (is_string($value) && strlen($value) === 1) {
            // 单字节字符串（如 "\x02" / 'a'）：当作字面字节直接使用
            $this->magicKey = $value;

            return $this->magicKey;
        }

        if (is_int($value)) {
            $num = $value;
        } elseif (is_string($value)) {
            $str = trim($value);
            if (preg_match('/^0x[0-9a-fA-F]+$/', $str)) {
                $num = hexdec(substr($str, 2));
            } elseif (preg_match('/^-?\d+$/', $str)) {
                $num = (int) $str;
            } else {
                throw new \InvalidArgumentException('admin.route.cipher_magic 无法解析：传入了「'.var_export($value, true).'」（支持 int / 0x 十六进制 / 单字节字符串）');
            }
        } else {
            throw new \InvalidArgumentException('admin.route.cipher_magic 必须是 int 或字符串，传入了：'.gettype($value));
        }

        if ($num < 0 || $num > 255) {
            throw new \InvalidArgumentException('admin.route.cipher_magic 必须在 0 ~ 255 之间（单字节），传入了：'.var_export($value, true));
        }

        $this->magicKey = chr($num);

        return $this->magicKey;
    }

    /**
     * 从配置派生 AES-256 密钥并缓存（PBKDF2-HMAC-SHA256）；盐为空抛异常.
     *
     * @return string 32 字节密钥
     *
     * @throws \RuntimeException
     */
    protected function secret(): string
    {
        if ($this->secretKey !== null) {
            return $this->secretKey;
        }

        $salt = (string) config('admin.route.cipher_salt', '');

        if ($salt === '') {
            throw new \RuntimeException('admin.route.cipher_salt 未配置或为空，请设置 cipher_salt（如 ADMIN_ROUTE_CIPHER_SALT=xxx）');
        }

        $this->secretKey = hash_pbkdf2('sha256', $salt, static::PBKDF2_SALT, static::PBKDF2_ITERATIONS, 32, true);

        return $this->secretKey;
    }
}