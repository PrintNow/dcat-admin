<?php

namespace Dcat\Admin\Grid\Filter\Presenter;

use Dcat\Admin\Admin;
use Dcat\Admin\Form\Field\Text as FieldText;

class Text extends Presenter
{
    /**
     * @var string
     */
    protected $placeholder = '';

    /**
     * @var string
     */
    protected $icon = 'pencil';

    /**
     * @var string
     */
    protected $type = 'text';

    /**
     * @var bool
     */
    protected $normalizeMaskPaste = false;

    /**
     * Text constructor.
     *
     * @param  string  $placeholder
     */
    public function __construct($placeholder = '')
    {
        $this->placeholder($placeholder);
    }

    /**
     * Get variables for field template.
     *
     * @return array
     */
    public function defaultVariables(): array
    {
        return [
            'placeholder' => $this->placeholder,
            'icon'        => $this->icon,
            'type'        => $this->type,
            'group'       => $this->filter->group,
        ];
    }

    /**
     * Set input placeholder.
     *
     * @param  string  $placeholder
     * @return $this
     */
    public function placeholder($placeholder = '')
    {
        $this->placeholder = $placeholder;

        return $this;
    }

    /**
     * @return Text
     */
    public function url()
    {
        return $this->inputmask(['alias' => 'url'], 'internet-explorer');
    }

    /**
     * @return Text
     */
    public function email()
    {
        return $this->inputmask(['alias' => 'email'], 'envelope');
    }

    /**
     * @return Text
     */
    public function integer()
    {
        return $this->normalizedInputmask(['alias' => 'integer']);
    }

    /**
     * @param  array  $options
     *
     * @see https://github.com/RobinHerbots/Inputmask/blob/4.x/README_numeric.md
     *
     * @return Text
     */
    public function decimal($options = [])
    {
        return $this->normalizedInputmask(array_merge($options, ['alias' => 'decimal']));
    }

    /**
     * @param  array  $options
     *
     * @see https://github.com/RobinHerbots/Inputmask/blob/4.x/README_numeric.md
     *
     * @return Text
     */
    public function currency($options = [])
    {
        return $this->normalizedInputmask(array_merge($options, [
            'alias'              => 'currency',
            'prefix'             => '',
            'removeMaskOnSubmit' => true,
        ]));
    }

    /**
     * @param  array  $options
     *
     * @see https://github.com/RobinHerbots/Inputmask/blob/4.x/README_numeric.md
     *
     * @return Text
     */
    public function percentage($options = [])
    {
        $options = array_merge(['alias' => 'percentage'], $options);

        return $this->normalizedInputmask($options);
    }

    /**
     * @return Text
     */
    public function ip()
    {
        return $this->inputmask(['alias' => 'ip'], 'laptop');
    }

    /**
     * @return Text
     */
    public function mac()
    {
        return $this->inputmask(['alias' => 'mac'], 'laptop');
    }

    /**
     * @param  string  $mask
     * @return Text
     */
    public function mobile($mask = '19999999999')
    {
        return $this->normalizedInputmask(compact('mask'), 'phone');
    }

    /**
     * @param  array  $options
     * @param  string  $icon
     * @return $this
     */
    public function inputmask($options = [], $icon = 'pencil')
    {
        Admin::js('@jquery.inputmask');

        $options['rightAlign'] = false;

        $options = json_encode($options);

        if ($this->normalizeMaskPaste) {
            $options = '$.extend({onBeforePaste: '.FieldText::inputmaskPasteNormalizer().'}, '.$options.')';
        }

        Admin::script("$('#{$this->filter->parent()->filterID()} input.{$this->filter->getId()}').inputmask($options);");

        $this->icon = $icon;

        return $this;
    }

    /**
     * 调用 inputmask，并在粘贴时先将全角字符转为半角.
     *
     * @param  array  $options
     * @param  string  $icon
     * @return $this
     */
    protected function normalizedInputmask($options = [], $icon = 'pencil')
    {
        $this->normalizeMaskPaste = true;

        try {
            return $this->inputmask($options, $icon);
        } finally {
            $this->normalizeMaskPaste = false;
        }
    }
}
