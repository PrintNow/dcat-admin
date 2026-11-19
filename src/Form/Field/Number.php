<?php

namespace Dcat\Admin\Form\Field;

class Number extends Text
{
    protected $view = 'admin::form.number';

    protected $options = [
        'upClass'   => 'primary shadow-0',
        'downClass' => 'light shadow-0',
        'center'    => true,
        'disabled'  => false,
    ];

    /**
     * @var bool
     */
    protected $integer = false;

    /**
     * Set min value of number field.
     *
     * @param  int  $value
     * @return $this
     */
    public function min($value)
    {
        $this->attribute('min', $value);

        return $this;
    }

    /**
     * Set max value of number field.
     *
     * @param  int  $value
     * @return $this
     */
    public function max($value)
    {
        $this->attribute('max', $value);

        return $this;
    }

    /**
     * Set increment and decrement button to disabled.
     *
     * @param  bool  $value
     * @return $this
     */
    public function disable(bool $value = true)
    {
        parent::disable($value);

        $this->options['disabled'] = $value;

        return $this;
    }

    /**
     * Only allow integer values.
     *
     * 开启后失焦或回车时按 PHP (int) 的规则去掉小数部分（向零取整），
     * 并在提交时自动校验为整数；空值的处理与未开启时一致。
     *
     * @param  bool  $value
     * @return $this
     */
    public function integer(bool $value = true)
    {
        $this->integer = $value;

        if ($value) {
            $this->options['integer'] = true;
        } else {
            unset($this->options['integer']);
        }

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function rules($rules = null, $messages = [])
    {
        // rules() 内部会读取 getRules() 并写回，这里临时关闭，避免整数规则被写入字段规则中
        $integer = $this->integer;
        $this->integer = false;

        try {
            return parent::rules($rules, $messages);
        } finally {
            $this->integer = $integer;
        }
    }

    /**
     * {@inheritDoc}
     */
    protected function getRules()
    {
        $rules = parent::getRules();

        if (! $this->integer) {
            return $rules;
        }

        if (! is_array($rules)) {
            $rules = $rules ? [$rules] : [];
        }

        if (in_array('integer', $rules, true)) {
            return $rules;
        }

        // 与 Laravel 的 integer 规则判断一致，但空值不参与校验，
        // 避免为了放行空值而追加 nullable，从而改变其它规则对空值的行为
        $rules[] = function ($attribute, $value, $fail) {
            if ($value === null || $value === '') {
                return;
            }

            if (filter_var($value, FILTER_VALIDATE_INT) === false) {
                $fail('validation.integer')->translate();
            }
        };

        return $rules;
    }

    /**
     * {@inheritDoc}
     */
    protected function prepareInputValue($value)
    {
        return empty($value) ? 0 : $value;
    }

    /**
     * {@inheritDoc}
     */
    public function value($value = null)
    {
        if (is_null($value)) {
            return (int) parent::value();
        }

        return parent::value($value);
    }

    public function render()
    {
        $this->defaultAttribute('style', 'width: 140px;flex:none');

        $this->prepend('');

        return parent::render();
    }
}
