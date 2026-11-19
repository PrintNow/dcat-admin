<?php

namespace Dcat\Admin\Grid\Displayers;

use Dcat\Admin\Admin;

class Input extends Editable
{
    protected $type = 'input';

    protected $view = 'admin::grid.displayer.editinline.input';

    public function display($options = [])
    {
        if (! empty($options['mask'])) {
            Admin::requireAssets('@jquery.inputmask');
        }

        return parent::display($options);
    }

    protected function defaultOptions()
    {
        return [
            'unmask' => $this->shouldUnmask($this->options['mask'] ?? null),
        ];
    }

    /**
     * 行内编辑不是表单 submit，inputmask 的 removeMaskOnSubmit 不会生效.
     * 数值类别名（integer/decimal/currency/percentage/numeric）或显式开启了 removeMaskOnSubmit 时，
     * 提交去掉掩码后的值（如 "1234.00" 而不是 "1,234.00"、"50" 而不是 "50 %"）；
     * 显式传入 'removeMaskOnSubmit' => false 或其它掩码（如 '9999-99-99'）保持提交显示值的旧行为.
     *
     * @param  mixed  $mask
     * @return bool
     */
    protected function shouldUnmask($mask)
    {
        if (! is_array($mask)) {
            return false;
        }

        if (array_key_exists('removeMaskOnSubmit', $mask)) {
            return $mask['removeMaskOnSubmit'] === true;
        }

        return in_array($mask['alias'] ?? null, ['integer', 'decimal', 'currency', 'percentage', 'numeric'], true);
    }
}
