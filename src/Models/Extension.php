<?php

namespace Dcat\Admin\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class Extension.
 *
 * @property int $id
 * @property string $name
 * @property string $version
 * @property int $is_enabled
 * @property array|null $options
 *
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class Extension extends Model
{
    protected $fillable = ['name', 'is_enabled', 'version', 'options'];

    protected $casts = [
        'options' => 'json',
    ];

    /**
     * {@inheritDoc}
     */
    public function __construct(array $attributes = [])
    {
        $this->init();

        parent::__construct($attributes);
    }

    protected function init()
    {
        $connection = config('admin.database.connection') ?: config('database.default');

        $this->setConnection($connection);

        $this->setTable(config('admin.database.extensions_table') ?: 'admin_extensions');
    }
}
