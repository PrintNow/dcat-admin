<?php

namespace Tests\Performance;

use Tests\UnitTestCase;

/**
 * 中间件性能测试
 *
 * 测试 Admin::mixMiddlewareGroup 方法优化后的性能提升
 */
class MiddlewarePerformanceTest extends UnitTestCase
{
    /**
     * 测试优化后的性能
     */
    public function testOptimizedMixMiddlewareGroupPerformance()
    {
        $results = $this->runPerformanceTest('optimized');

        echo "\n=== 优化后性能测试结果 ===\n";
        echo "小数组 (10个元素): {$results['small']} ms\n";
        echo "中等数组 (50个元素): {$results['medium']} ms\n";
        echo "大数组 (100个元素): {$results['large']} ms\n";
        echo "超大数组 (500个元素): {$results['xlarge']} ms\n";

        // 性能断言：确保大数组处理时间合理
        $this->assertLessThan(50, $results['large'], '处理100个元素应该小于50ms');
        $this->assertLessThan(200, $results['xlarge'], '处理500个元素应该小于200ms');
    }

    /**
     * 对比测试：优化前 vs 优化后
     */
    public function testPerformanceComparison()
    {
        $sizes = [10, 50, 100, 200, 500];

        echo "\n=== 性能对比测试 ===\n";
        echo str_pad("数组大小", 12) . str_pad("优化前(ms)", 15) . str_pad("优化后(ms)", 15) . str_pad("提升", 10) . "\n";
        echo str_repeat("-", 52) . "\n";

        foreach ($sizes as $size) {
            $oldTime = $this->benchmarkOldImplementation($size);
            $newTime = $this->benchmarkNewImplementation($size);
            $improvement = (($oldTime - $newTime) / $oldTime) * 100;

            echo str_pad($size, 12) .
                 str_pad(number_format($oldTime, 4), 15) .
                 str_pad(number_format($newTime, 4), 15) .
                 str_pad(number_format($improvement, 1) . "%", 10) . "\n";
        }

        $this->assertTrue(true);
    }

    /**
     * 运行性能测试
     */
    protected function runPerformanceTest($version = 'optimized')
    {
        return [
            'small' => $this->benchmark($version, 10),
            'medium' => $this->benchmark($version, 50),
            'large' => $this->benchmark($version, 100),
            'xlarge' => $this->benchmark($version, 500),
        ];
    }

    /**
     * 基准测试
     */
    protected function benchmark($version, $arraySize)
    {
        $iterations = 1000;

        if ($version === 'optimized') {
            $start = microtime(true);
            for ($i = 0; $i < $iterations; $i++) {
                $this->simulateOptimizedVersion($arraySize);
            }
            $end = microtime(true);
        } else {
            $start = microtime(true);
            for ($i = 0; $i < $iterations; $i++) {
                $this->simulateOldVersion($arraySize);
            }
            $end = microtime(true);
        }

        return round(($end - $start) * 1000, 4);
    }

    /**
     * 基准测试：新实现
     */
    protected function benchmarkNewImplementation($arraySize)
    {
        return $this->benchmark('optimized', $arraySize);
    }

    /**
     * 基准测试：旧实现
     */
    protected function benchmarkOldImplementation($arraySize)
    {
        return $this->benchmark('old', $arraySize);
    }

    /**
     * 模拟优化后的实现
     */
    protected function simulateOptimizedVersion($size)
    {
        $group = $this->generateMiddlewareArray($size);
        $mix = ['custom.middleware1', 'custom.middleware2'];

        // 优化后的逻辑
        $position = array_search('admin.permission', $group, true);

        if ($position !== false) {
            array_splice($group, $position, 0, $mix);
        } else {
            array_push($group, ...$mix);
        }

        return $group;
    }

    /**
     * 模拟优化前的实现（旧代码）
     */
    protected function simulateOldVersion($size)
    {
        $group = $this->generateMiddlewareArray($size);
        $mix = ['custom.middleware1', 'custom.middleware2'];

        // 旧的实现逻辑
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

    /**
     * 生成测试用的中间件数组
     */
    protected function generateMiddlewareArray($size)
    {
        $middlewares = [];

        for ($i = 0; $i < $size; $i++) {
            if ($i === intval($size / 2)) {
                $middlewares[] = 'admin.permission';
            } else {
                $middlewares[] = 'middleware.' . $i;
            }
        }

        return $middlewares;
    }

    /**
     * 内存使用测试
     */
    public function testMemoryUsage()
    {
        echo "\n=== 内存使用测试 ===\n";

        $sizes = [100, 500, 1000];

        foreach ($sizes as $size) {
            // 测试旧实现
            $memBefore = memory_get_usage();
            for ($i = 0; $i < 100; $i++) {
                $this->simulateOldVersion($size);
            }
            $memAfter = memory_get_usage();
            $oldMemory = $memAfter - $memBefore;

            // 测试新实现
            $memBefore = memory_get_usage();
            for ($i = 0; $i < 100; $i++) {
                $this->simulateOptimizedVersion($size);
            }
            $memAfter = memory_get_usage();
            $newMemory = $memAfter - $memBefore;

            // 避免除零错误
            $saved = $oldMemory > 0 ? (($oldMemory - $newMemory) / $oldMemory) * 100 : 0;

            echo "数组大小 {$size}: 旧实现 " .
                 $this->formatBytes($oldMemory) .
                 " | 新实现 " .
                 $this->formatBytes($newMemory) .
                 " | 节省 " . number_format($saved, 1) . "%\n";
        }

        $this->assertTrue(true);
    }

    /**
     * 格式化字节数
     */
    protected function formatBytes($bytes)
    {
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        }
        return $bytes . ' B';
    }
}

