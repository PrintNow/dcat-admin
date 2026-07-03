<?php

namespace Dcat\Admin\Traits;

use Dcat\Admin\Exception\RuntimeException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

trait HasModelOptions
{
    /**
     * Load options from current selected resource(s).
     *
     * @param  string  $model
     * @param  string|null  $idField 默认取模型主键
     * @param  string  $textField
     * @return $this
     */
    public function model($model, ?string $idField = null, string $textField = 'name')
    {
        if (! class_exists($model)
            || ! in_array(Model::class, class_parents($model), true)
        ) {
            throw new RuntimeException("[$model] must be a valid model class");
        }

        $idField ??= (new $model())->getKeyName();

        $this->options = function ($value) use ($model, $idField, $textField) {
            if (empty($value)) {
                return [];
            }

            $resources = [];

            if (is_array($value)) {
                if (Arr::isAssoc($value)) {
                    $resources[] = Arr::get($value, $idField);
                } else {
                    $resources = array_column($value, $idField);
                }
            } else {
                $resources[] = $value;
            }

            // 使用 whereIn 而非 find，以支持非主键字段（如 uuid、slug 等）
            return $model::whereIn($idField, $resources)->pluck($textField, $idField)->toArray();
        };

        return $this;
    }
}
