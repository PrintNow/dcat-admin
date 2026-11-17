<?php

namespace Tests\Unit;

use Dcat\Admin\Layout\Asset;
use PHPUnit\Framework\TestCase;

class AssetAliasPathTest extends TestCase
{
    private function normalize(array $files, array $params): array
    {
        $asset = new class extends Asset
        {
            public function callNormalizeAliasPaths(array $files, array $params): array
            {
                return $this->normalizeAliasPaths($files, $params);
            }
        };

        return array_values($asset->callNormalizeAliasPaths($files, $params));
    }

    public function test_placeholder_is_replaced_when_param_given(): void
    {
        $this->assertSame(
            ['@admin/dcat/plugins/select/select2.full.min.js', '@admin/dcat/plugins/select/i18n/zh-CN.js'],
            $this->normalize(
                ['@admin/dcat/plugins/select/select2.full.min.js', '@admin/dcat/plugins/select/i18n/{lang}.js'],
                ['lang' => 'zh-CN']
            )
        );
    }

    public function test_path_with_unresolved_placeholder_is_dropped(): void
    {
        // app.locale = en 时 select-script 传入 lang=''，不应再请求 i18n/{lang}.js
        $this->assertSame(
            ['@admin/dcat/plugins/select/select2.full.min.js'],
            $this->normalize(
                ['@admin/dcat/plugins/select/select2.full.min.js', '@admin/dcat/plugins/select/i18n/{lang}.js'],
                ['lang' => '']
            )
        );

        $this->assertSame(
            ['@admin/dcat/plugins/select/select2.full.min.js'],
            $this->normalize(
                ['@admin/dcat/plugins/select/select2.full.min.js', '@admin/dcat/plugins/select/i18n/{lang}.js'],
                []
            )
        );
    }

    public function test_paths_without_placeholder_are_kept(): void
    {
        $this->assertSame(
            ['@admin/a.js', 'https://cdn.example.com/b.js'],
            $this->normalize(['@admin/a.js', 'https://cdn.example.com/b.js'], ['lang' => 'en'])
        );
    }
}
