<?php

namespace Dcat\Admin\Grid\Column\Filter;

use Dcat\Admin\Grid\Column\Filter;
use Dcat\Admin\Grid\Model;
use Dcat\Admin\Support\Helper;

class In extends Filter
{
    use Checkbox;

    /**
     * @var array
     */
    protected $options = [];

    /**
     * CheckFilter constructor.
     *
     * @param  array  $options
     */
    public function __construct(array $options)
    {
        $this->options = $options;

        $this->class = [
            'all'  => Helper::uniqueSelector('column-filter-all-'),
            'item' => Helper::uniqueSelector('column-filter-item-'),
        ];
    }

    /**
     * Add a binding to the query.
     *
     * @param  array  $value
     * @param  Model  $model
     */
    public function addBinding($value, Model $model)
    {
        if (empty($value)) {
            return;
        }

        $this->withQuery($model, 'whereIn', [$value]);
    }

    /**
     * Render this filter.
     *
     * @return string
     */
    public function render()
    {
        return $this->renderCheckbox();
    }
}
