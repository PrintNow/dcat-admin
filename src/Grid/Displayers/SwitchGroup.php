<?php

namespace Dcat\Admin\Grid\Displayers;

use Dcat\Admin\Admin;
use Illuminate\Support\Arr;

class SwitchGroup extends SwitchDisplay
{
    public function display($columns = [], $color = '', $refresh = false)
    {
        if ($columns instanceof \Closure) {
            $columns = $columns->call($this->row, $this);
        }

        if ($color) {
            $this->color($color);
        }

        if (! Arr::isAssoc($columns)) {
            $labels = array_map('admin_trans_field', $columns);
            $columns = array_combine($columns, $labels);
        }

        $color = $this->color ?: Admin::color()->primary();

        $key = $this->getKey();

        if ($key === null || $key === '') {
            $key = (string) $key;
        } elseif (config('admin.route.encrypt', false)) {
            $key = admin_cipher_encrypt($key, 'gi');
        }

        return Admin::view('admin::grid.displayer.switchgroup', [
            'row'      => $this->row->toArray(),
            'key'      => $key,
            'columns'  => $columns,
            'resource' => $this->resource(),
            'color'    => $color,
            'refresh'  => $refresh,
        ]);
    }
}
