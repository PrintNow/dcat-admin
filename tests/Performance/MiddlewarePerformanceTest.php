<?php

namespace Tests\Performance;

use Tests\UnitTestCase;

/**
 * Admin::mixMiddlewareGroup 算法测试
 *
 * 该方法依赖 app('router')，无法在纯 PHPUnit 中直接调用。
 * 此处提取核心算法进行等价测试，确保行为正确。
 */
class MiddlewarePerformanceTest extends UnitTestCase
{
    /**
     * 等价算法：与 Admin::mixMiddlewareGroup 逻辑一致
     */
    protected static function mixMiddleware(array $group, array $mix): array
    {
        if (! $mix) {
            return $group;
        }

        $position = array_search('admin.permission', $group, true);

        if ($position !== false) {
            array_splice($group, $position, 0, $mix);
        } else {
            array_push($group, ...$mix);
        }

        return $group;
    }

    // ────────────────────── 功能测试 ──────────────────────

    public function testInsertsBeforeAdminPermission(): void
    {
        $group = ['admin.auth', 'admin.pjax', 'admin.permission', 'admin.session'];
        $mix = ['custom.a', 'custom.b'];

        $result = static::mixMiddleware($group, $mix);

        $this->assertSame(
            ['admin.auth', 'admin.pjax', 'custom.a', 'custom.b', 'admin.permission', 'admin.session'],
            $result,
        );
    }

    public function testAppendsWhenNoAdminPermission(): void
    {
        $group = ['admin.auth', 'admin.pjax', 'admin.session'];
        $mix = ['custom.a'];

        $result = static::mixMiddleware($group, $mix);

        $this->assertSame(
            ['admin.auth', 'admin.pjax', 'admin.session', 'custom.a'],
            $result,
        );
    }

    public function testEmptyMixDoesNotModifyGroup(): void
    {
        $group = ['admin.auth', 'admin.permission'];

        $this->assertSame($group, static::mixMiddleware($group, []));
    }

    public function testEmptyGroupWithMixReturnsMix(): void
    {
        $result = static::mixMiddleware([], ['custom.a']);

        $this->assertSame(['custom.a'], $result);
    }

    public function testAdminPermissionAtStart(): void
    {
        $group = ['admin.permission', 'admin.session'];
        $mix = ['custom.a'];

        $result = static::mixMiddleware($group, $mix);

        $this->assertSame(['custom.a', 'admin.permission', 'admin.session'], $result);
    }

    public function testAdminPermissionAtEnd(): void
    {
        $group = ['admin.auth', 'admin.permission'];
        $mix = ['custom.a'];

        $result = static::mixMiddleware($group, $mix);

        $this->assertSame(['admin.auth', 'custom.a', 'admin.permission'], $result);
    }

    public function testSingleElementGroup(): void
    {
        $result = static::mixMiddleware(['admin.permission'], ['custom.a']);

        $this->assertSame(['custom.a', 'admin.permission'], $result);
    }

    public function testOnlyFirstAdminPermissionUsed(): void
    {
        // array_search 返回第一个匹配的索引，所以只在第一个之前插入
        $group = ['admin.auth', 'admin.permission', 'admin.session', 'admin.permission'];
        $mix = ['custom.a'];

        $result = static::mixMiddleware($group, $mix);

        $this->assertSame(
            ['admin.auth', 'custom.a', 'admin.permission', 'admin.session', 'admin.permission'],
            $result,
        );
    }

    public function testMultipleMixMiddleware(): void
    {
        $group = ['admin.auth', 'admin.permission'];
        $mix = ['custom.a', 'custom.b', 'custom.c'];

        $result = static::mixMiddleware($group, $mix);

        $this->assertSame(
            ['admin.auth', 'custom.a', 'custom.b', 'custom.c', 'admin.permission'],
            $result,
        );
    }

    public function testOriginalGroupNotMutatedWhenMixEmpty(): void
    {
        $group = ['admin.auth', 'admin.permission'];
        $original = $group;

        static::mixMiddleware($group, []);

        $this->assertSame($original, $group);
    }

    // ────────────────────── 性能对比 ──────────────────────

    public function testPerformanceComparison(): void
    {
        $sizes = [10, 50, 100, 200];

        foreach ($sizes as $size) {
            $group = $this->generateGroup($size);
            $mix = ['custom.a', 'custom.b'];

            $oldTime = $this->benchmarkOld($group, $mix);
            $newTime = $this->benchmarkNew($group, $mix);

            // 新实现不应比旧实现慢超过 5 倍（容差）
            $this->assertLessThan(
                $oldTime * 5,
                $newTime,
                "size={$size}: new ({$newTime}ms) is too slow compared to old ({$oldTime}ms)",
            );
        }
    }

    private function benchmarkOld(array $group, array $mix): float
    {
        $iterations = 5000;
        $start = microtime(true);

        for ($i = 0; $i < $iterations; $i++) {
            $this->oldImplementation($group, $mix);
        }

        return (microtime(true) - $start) * 1000;
    }

    private function benchmarkNew(array $group, array $mix): float
    {
        $iterations = 5000;
        $start = microtime(true);

        for ($i = 0; $i < $iterations; $i++) {
            static::mixMiddleware($group, $mix);
        }

        return (microtime(true) - $start) * 1000;
    }

    private function oldImplementation(array $group, array $mix): array
    {
        $finalGroup = [];

        foreach ($group as $i => $mid) {
            $next = $i + 1;
            $finalGroup[] = $mid;

            if (! isset($group[$next]) || $group[$next] !== 'admin.permission') {
                continue;
            }

            $finalGroup = array_merge($finalGroup, $mix);
            $mix = [];
        }

        if ($mix) {
            $finalGroup = array_merge($finalGroup, $mix);
        }

        return $finalGroup;
    }

    private function generateGroup(int $size): array
    {
        $middlewares = [];

        for ($i = 0; $i < $size; $i++) {
            $middlewares[] = $i === intdiv($size, 2)
                ? 'admin.permission'
                : 'middleware.' . $i;
        }

        return $middlewares;
    }
}
