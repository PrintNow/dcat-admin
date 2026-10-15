<?php

namespace Dcat\Admin\Support;

use Dcat\Admin\Contracts\UrlCipher;

/**
 * URL 主键加解密管理器.
 *
 * - 作为包内统一入口：Grid/Form 的主键加密、中间件解密都通过它干活；
 * - 仅面向「主键」场景：加密路径参数中的记录 ID（grid.id / form.id），
 *   其它查询参数一律保持明文，不做打包 / 分组。
 *
 * 作用域（scope）设计（方案 D：cipherScope 即最终作用域，不合成前缀）：
 * - 内置调用点仍传显式 scope 短标签（'gi' = grid.id / 'fo' = form.id），
 *   用于「未配置 cipherScope 的控制器」；
 * - encrypt 时，若当前控制器显式配置了 cipherScope，其属性值 **直接作为最终作用域**
 *   传给底层 cipher（不做任何字符串拼接 / 前缀合成）；
 * - 底层加密不再强制校验作用域注册，但严格校验长度：作用域必须是
 *   1~2 个可打印 ASCII 字符（'gi' / 'fo' / 'bo'），超长或中文会抛异常；
 * - 无 cipherScope 时保持调用点 scope 不变（旧行为）；不用 title / 类名兜底；
 * - 显式启停：config('admin.route.encrypt') 为 false 时整个链路不加密。
 *
 * 用法：
 *   $manager = app('admin.cipher');
 *   $enc = $manager->encrypt(42, 'gi');             // 无 cipherScope → gi
 *                                                    // cipherScope='bo' → bo（覆盖为控制器标签）
 *   $dec = $manager->decrypt($enc, 'bo');           // 解密必传 scope，需与加密时一致
 */
class UrlCipherManager
{
    /**
     * @var UrlCipher
     */
    protected $cipher;

    public function __construct(UrlCipher $cipher)
    {
        $this->cipher = $cipher;
    }

    /**
     * 获取底层 cipher 实现（供中间件等读取作用域标签）.
     *
     * @return \Dcat\Admin\Contracts\UrlCipher
     */
    public function cipher(): UrlCipher
    {
        return $this->cipher;
    }

    /**
     * 加密主键值（仅支持 int；scope 可空）.
     *
     * 若当前控制器显式配置了 cipherScope，其属性值 **直接作为最终作用域**
     * （不再做 {前缀}:{scope} 拼接）；否则使用传入的 $key 原样（如 gi / fo）。
     * 底层 UuidCipher 要求作用域为 1~2 个可打印 ASCII 字符（短标签），
     * 超长（如完整作用域 grid.id / book:grid.id）加密时会抛异常；CryptCipher 另按其规则。
     *
     * @param  int  $plain
     * @param  string  $key  调用点作用域短标签（如 gi / fo），cipherScope 未配置时使用
     * @return string
     */
    public function encrypt(int $plain, string $key): string
    {
        $identity = $this->resolveDefaultScope();

        // 方案 D：cipherScope 就是最终作用域，不合成前缀
        if ($identity !== null) {
            $key = $identity;
        }

        return $this->cipher->encrypt($plain, $key);
    }

    /**
     * 解密主键值；失败返回 null.
     *
     * 作用域 $key 必填（类型 string）：解密时校验与密文内嵌标签一致，
     * 不一致返回 null（防跨场景重放）。页面访问由中间件提供服务：
     * 控制器配置 cipherScope 时以它为唯一可接受作用域，否则自动提取密文标签。
     *
     * @param  string  $cipher
     * @param  string  $key  作用域标识，需与加密时一致
     * @return string|null
     */
    public function decrypt(string $cipher, string $key): ?string
    {
        return $this->cipher->decrypt($cipher, $key);
    }

    /**
     * 是否启用主键加密（配置开关）.
     *
     * @return bool
     */
    public function enabled(): bool
    {
        return (bool) config('admin.route.encrypt', false);
    }

    /**
     * 当前控制器期望的作用域（供中间件解密校验使用）.
     *
     * 加密时若控制器配置了 cipherScope，URL 一律用它加密；
     * 解密时中间件也应**只用它**去解密（标签不一致 → 解密失败 → 404），
     * 而不是用密文自己声称的标签（否则"解密校验作用域"形同虚设）。
     * 未配置 cipherScope / 闭包路由 → 返回 null，中间件回退自动提取标签。
     *
     * @return string|null
     */
    public function expectedScope(): ?string
    {
        return $this->resolveDefaultScope();
    }

    /**
     * 控制器身份解析：
     *   仅使用 $controller->cipherScope（显式配置），无则返回 null（不叠加身份）；
     *   title / 类名均不做兜底.
     * 属性为 protected 时通过公开 getter / 反射读取；
     * 空串属性视为未配置；任一环节失败 / 无控制器返回 null（不抛异常）.
     *
     * @return string|null
     */
    protected function resolveDefaultScope(): ?string
    {
        $controller = $this->currentController();

        if (! $controller) {
            return null;
        }

        // 仅使用显式配置的 cipherScope（优先公开 getter，其次反射读 protected）
        $scope = $this->readControllerProperty($controller, 'cipherScope');

        if ($scope !== null && $scope !== '') {
            return (string) $scope;
        }

        return null;
    }

    /**
     * 安全读取控制器属性（兼容 protected/private）：
     * - 优先调用公开 getter（如 cipherScope()）；
     * - 无 getter 时用反射读取（不抛异常，读不到返回 null）.
     *
     * @param  object  $controller
     * @param  string  $property
     * @return mixed
     */
    protected function readControllerProperty($controller, string $property)
    {
        if (method_exists($controller, $property)) {
            try {
                $value = $controller->{$property}();

                return $value;
            } catch (\Throwable $e) {
                // 方法抛异常则继续尝试反射
            }
        }

        if (! property_exists($controller, $property)) {
            return null;
        }

        try {
            $ref = new \ReflectionProperty($controller, $property);
            $ref->setAccessible(true);

            return $ref->getValue($controller);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * 获取当前请求路由绑定的控制器实例；拿不到返回 null.
     *
     * @return object|null
     */
    protected function currentController(): ?object
    {
        if (! function_exists('request')) {
            return null;
        }

        $route = request()->route();

        if (! $route) {
            return null;
        }

        // getController() 可能抛异常（路由尚未绑定 controller / 闭包路由），
        // 一律按"无控制器"处理，绝不把异常抛给调用方
        try {
            return $route->getController();
        } catch (\Throwable $e) {
            return null;
        }
    }
}