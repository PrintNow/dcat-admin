<?php

namespace Dcat\Admin\Form\Field;

class Mobile extends Text
{
    protected $inputmaskNormalizePaste = true;

    protected $inputmaskKeepUnfitValue = true;

    /**
     * @see https://github.com/RobinHerbots/Inputmask#options
     *
     * @var array
     */
    protected $options = [
        'mask' => '99999999999',
    ];

    public function render()
    {
        $this->inputmask($this->options);

        $this->defaultAttribute('style', 'width: 160px;flex:none');

        $this->prepend('<i class="feather icon-smartphone"></i>');

        return parent::render();
    }
}
