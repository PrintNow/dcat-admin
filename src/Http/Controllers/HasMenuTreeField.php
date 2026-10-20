<?php

namespace Dcat\Admin\Http\Controllers;

use Dcat\Admin\Form;

trait HasMenuTreeField
{
    /**
     * Add menu tree field to form.
     */
    protected function addMenuTreeField(Form $form)
    {
        $form->tree('menus', trans('admin.menu'))
            ->treeState(false)
            ->setTitleColumn('title')
            ->nodes(function () {
                $model = config('admin.database.menu_model');

                return (new $model())->allNodes();
            })
            ->customFormat(function ($v) {
                if (! $v) {
                    return [];
                }

                return array_column($v, 'id');
            });
    }

    /**
     * Flush menu cache after form saved.
     *
     * 注意：Form 的事件回调会被重新绑定到模型实例（见 HasEvents::makeListener），
     * 回调内的 $this 不再是控制器，因此这里不能依赖 $this，统一通过本方法注册。
     */
    protected function flushMenuCacheOnSaved(Form $form)
    {
        $form->saved(function () {
            $model = config('admin.database.menu_model');

            (new $model())->flushCache();
        });
    }
}
