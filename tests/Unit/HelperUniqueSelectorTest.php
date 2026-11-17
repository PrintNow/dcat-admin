<?php

namespace Tests\Unit;

use Dcat\Admin\Support\Helper;
use PHPUnit\Framework\TestCase;

class HelperUniqueSelectorTest extends TestCase
{
    public function test_result_is_a_valid_css_identifier_with_prefix(): void
    {
        $id = Helper::uniqueSelector('column-filter-all-');

        $this->assertStringStartsWith('column-filter-all-', $id);
        $this->assertStringNotContainsString('.', $id);
        $this->assertMatchesRegularExpression('/^[A-Za-z][A-Za-z0-9_-]*$/', $id);
    }

    public function test_prefix_is_added_when_missing_or_not_starting_with_letter(): void
    {
        foreach (['', '1-', '-x'] as $prefix) {
            $id = Helper::uniqueSelector($prefix);

            $this->assertStringStartsWith('dcat-'.$prefix, $id);
            $this->assertMatchesRegularExpression('/^[A-Za-z][A-Za-z0-9_-]*$/', $id);
        }
    }

    public function test_results_are_unique(): void
    {
        $ids = [];

        for ($i = 0; $i < 1000; $i++) {
            $ids[] = Helper::uniqueSelector('fieldset-');
        }

        $this->assertCount(1000, array_unique($ids));
    }
}
