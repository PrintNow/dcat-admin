<?php

namespace Dcat\Admin\Tree\Actions;

use Dcat\Admin\Tree\RowAction;

class Edit extends RowAction
{
    public function html()
    {
        $key = $this->getKey();

        if (config('admin.route.encrypt', false)) {
            $key = admin_cipher_encrypt($key, 'gi');
        }

        return <<<HTML
<a href="{$this->resource()}/{$key}/edit"><i class="feather icon-edit-1"></i>&nbsp;</a>
HTML;
    }
}
