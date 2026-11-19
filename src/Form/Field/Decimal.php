<?php

namespace Dcat\Admin\Form\Field;

class Decimal extends Text
{
    protected $inputmaskNormalizePaste = true;

    /**
     * @see https://github.com/RobinHerbots/Inputmask#options
     *
     * @var array
     */
    protected $options = [
        'alias'      => 'decimal',
        'rightAlign' => false,
    ];

    public function render()
    {
        $this->inputmask($this->options);

        $this->prepend('<i class="fa fa-terminal fa-fw"></i>');

        return parent::render();
    }
}
