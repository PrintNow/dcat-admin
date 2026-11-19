<?php

namespace Dcat\Admin\Form\Field;

use Illuminate\Support\Arr;

class Currency extends Text
{
    /**
     * inputmask currency 别名默认的小数位数.
     */
    const DEFAULT_DIGITS = 2;

    protected $symbol = '$';

    protected $inputmaskNormalizePaste = true;

    /**
     * @see https://github.com/RobinHerbots/Inputmask#options
     *
     * @var array
     */
    protected $options = [
        'alias'              => 'currency',
        'radixPoint'         => '.',
        'prefix'             => '',
        'removeMaskOnSubmit' => true,
        'rightAlign'         => false,
    ];

    /**
     * Set symbol for currency field.
     *
     * @param  string  $symbol
     * @return $this
     */
    public function symbol($symbol)
    {
        $this->symbol = $symbol;

        return $this;
    }

    /**
     * Set digits for input number.
     *
     * @param  int  $digits
     * @return $this
     */
    public function digits($digits)
    {
        return $this->mergeOptions(compact('digits'));
    }

    /**
     * @param  mixed  $value
     * @return mixed
     */
    protected function prepareInputValue($value)
    {
        return is_string($value) ? str_replace(',', '', $value) : $value;
    }

    /**
     * 校验前去掉千分位分隔符，使校验规则（如 numeric）看到的值与最终保存的值一致.
     *
     * 前端表单通过 ajax 提交，inputmask 的 removeMaskOnSubmit 不会生效，提交的值形如 "1,234.50".
     *
     * @param  array  $input
     * @param  string  $column
     * @return array
     */
    protected function sanitizeInput($input, $column)
    {
        $input = parent::sanitizeInput($input, $column);

        $value = Arr::get($input, $column);

        if (is_string($value)) {
            Arr::set($input, $column, str_replace(',', '', $value));
        }

        return $input;
    }

    /**
     * 未显式设置 digits 时，如果已有值的有效小数位多于默认位数，则按已有值的位数显示，
     * 避免 inputmask 将已有值四舍五入后在保存时覆盖原数据.
     *
     * @param  array  $options
     * @return array
     */
    protected function formatDigitsOptions(array $options)
    {
        if (array_key_exists('digits', $options)) {
            return $options;
        }

        $value = $this->value();

        if (
            (is_string($value) || is_float($value))
            && preg_match('/^\s*[-+]?\d*\.(\d+)\s*$/', (string) $value, $matches)
        ) {
            $decimals = strlen(rtrim($matches[1], '0'));

            if ($decimals > static::DEFAULT_DIGITS) {
                $options['digits'] = $decimals;
            }
        }

        return $options;
    }

    /**
     * {@inheritdoc}
     */
    public function render()
    {
        $this->inputmask($this->formatDigitsOptions($this->options));

        $this->prepend($this->symbol)
            ->defaultAttribute('style', 'width: 200px');

        return parent::render();
    }
}
