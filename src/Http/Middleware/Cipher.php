<?php

namespace Dcat\Admin\Http\Middleware;

use Dcat\Admin\Support\UrlCipherManager;
use Illuminate\Http\Request;

/**
 * URL 主键解密中间件.
 *
 * 放在 admin 中间件组最前端，负责解密路由路径参数中的主键
 * （如 /users/{id}/edit 中的 {id}），覆盖 route parameters。
 *
 * 仅加密主键，其它查询参数（过滤条件等）保持明文，不做处理。
 */
class Cipher
{
    /**
     * @var UrlCipherManager
     */
    protected $manager;

    public function __construct(UrlCipherManager $manager)
    {
        $this->manager = $manager;
    }

    public function handle(Request $request, \Closure $next)
    {
        if (! $this->manager->enabled()) {
            return $next($request);
        }

        $this->decryptRouteParameters($request);

        return $next($request);
    }

    /**
     * 从密文提取作用域标签：优先 UuidCipher->peekScope()；CryptCipher 密文
     * 走 [scope]: 前缀正则；拿不到返回 null（视为非密文，跳过不处理）.
     *
     * @param  string  $value
     * @return string|null
     */
    protected function extractScope(string $value): ?string
    {
        // CryptCipher 格式：[scope]:payload
        if (preg_match('/^\[([^\]]+)\]:/', $value, $m)) {
            return $m[1] !== '' ? $m[1] : null;
        }

        // UuidCipher 格式：UUID 样式，标签内嵌密文中，需解密才能取
        $cipher = $this->manager->cipher();

        if (method_exists($cipher, 'peekScope')) {
            return $cipher->peekScope($value);
        }

        // 无 peekScope 的实现无法自动提标签；不传 key 会破坏必填约束，
        // 这里按「非密文」处理（不改动原参数）。
        return null;
    }

    /**
     * 解密路由路径参数（仅尝试，失败回退明文）.
     *
     * @return void
     */
    protected function decryptRouteParameters(Request $request)
    {
        $route = $request->route();

        if (! $route) {
            return;
        }

        $parameters = $route->parameters();

        // 解密必须用「期望作用域」而不是「密文声称的作用域」：
        // 控制器配置了 cipherScope 时，URL 加密也一律用它；解密也只用它，
        // 密文标签与它不一致 → 解密失败 → 参数保持密文 → 控制器 find 不到 → 404。
        // 未配置 cipherScope（或闭包路由 / 旧版内置 gi/fo 混合场景）才回退自动提取标签。
        $expected = $this->manager->expectedScope();

        foreach ($parameters as $key => $value) {
            if (! is_string($value) || $value === '') {
                continue;
            }

            // 解密作用域来源：
            //  - 有期望 scope（控制器 cipherScope）→ 直接用期望值，与密文标签比对不匹配即失败；
            //  - 无期望 scope（未配置 / 闭包路由）→ 手动从密文提取标签（UuidCipher 内嵌 2 字节 /
            //    CryptCipher [scope]: 前缀）自动解密。
            // 两种方式都满足「加解密都必传 scope」；前者更加严格（防跨场景重放）。
            $scope = $expected ?? $this->extractScope($value);

            if ($scope === null) {
                continue;  // 非密文（如手工输入 /users/42），不处理
            }

            $plain = $this->manager->decrypt($value, $scope);

            if ($plain !== null) {
                $route->setParameter($key, $plain);
            }
        }
    }
}