<?php

namespace Dcat\Admin\Grid\Actions;

use Dcat\Admin\Grid\RowAction;

class Show extends RowAction
{
    /**
     * @return array|null|string
     */
    public function title()
    {
        if ($this->title) {
            return $this->title;
        }

        return '<i class="feather icon-eye"></i> '.__('admin.show').' &nbsp;&nbsp;';
    }

    /**
     * @return string
     */
    public function href()
    {
        $key = $this->getKey();

        if (config('admin.route.encrypt', false)) {
            $key = admin_cipher_encrypt($key, 'gi');
        }

        return $this->parent->urlWithConstraints("{$this->resource()}/{$key}");
    }
}
