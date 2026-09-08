<?php

namespace Dcat\Admin\Http\Controllers;

use Dcat\Admin\Admin;
use Dcat\Admin\Form;
use Dcat\Admin\Http\Repositories\Permission;
use Dcat\Admin\Layout\Content;
use Dcat\Admin\Tree;
use Illuminate\Support\Str;

class PermissionController extends AdminController
{
    use HasMenuTreeField;
    protected function title()
    {
        return trans('admin.permissions');
    }

    public function index(Content $content)
    {
        return $content
            ->title($this->title())
            ->description(trans('admin.list'))
            ->body($this->treeView());
    }

    protected function treeView()
    {
        $model = config('admin.database.permissions_model');

        return new Tree(new $model(), function (Tree $tree) {
            $tree->disableCreateButton();
            $tree->disableEditButton();

            $tree->branch(function ($branch) {
                $branchName = htmlspecialchars($branch['name'], ENT_QUOTES | ENT_HTML5);
                $branchSlug = htmlspecialchars($branch['slug'], ENT_QUOTES | ENT_HTML5);
                $payload = "<div class='pull-left' style='min-width:310px'><b>{$branchName}</b>&nbsp;&nbsp;[<span class='text-primary'>{$branchSlug}</span>]";

                $path = array_filter($branch['http_path']);

                if (! $path) {
                    return $payload.'</div>&nbsp;';
                }

                $max = 3;
                if (count($path) > $max) {
                    $path = array_slice($path, 0, $max);
                    $path[] = '...';
                }

                $method = $branch['http_method'] ?: [];

                $path = collect($path)->map(function ($path) use (&$method) {
                    if (Str::contains($path, ':')) {
                        [$me, $path] = explode(':', $path);

                        $method = array_merge($method, explode(',', $me));
                    }
                    if ($path !== '...' && ! empty(config('admin.route.prefix')) && ! Str::contains($path, '.')) {
                        $path = trim(admin_base_path($path), '/');
                    }

                    $color = Admin::color()->primaryDarker();

                    return "<code style='color:{$color}'>$path</code>";
                })->implode('&nbsp;&nbsp;');

                $method = collect($method ?: ['ANY'])->unique()->map(function ($name) {
                    return strtoupper($name);
                })->map(function ($name) {
                    return "<span class='label bg-primary'>{$name}</span>";
                })->implode('&nbsp;').'&nbsp;';

                $payload .= "</div>&nbsp; $method<a class=\"dd-nodrag\">$path</a>";

                return $payload;
            });
        });
    }

    public function form()
    {
        // 捕获控制器引用：表单事件（saved/saving 等）闭包会被 bind 到模型，
        // 直接写 $this 会变成 Permission::xxx() 导致 undefined method；
        // 通过 use 捕获的 $controller 始终指向控制器实例。
        $controller = $this;

        $with = [];

        if ($bindMenu = config('admin.menu.permission_bind_menu', true)) {
            $with[] = 'menus';
        }

        return Form::make(Permission::with($with), function (Form $form) use ($bindMenu, $controller) {
            $permissionTable = config('admin.database.permissions_table');
            $connection = config('admin.database.connection');
            $permissionModel = config('admin.database.permissions_model');

            $id = $form->getKey();

            $form->display('id', 'ID');

            $form->select('parent_id', trans('admin.parent_id'))
                ->options($permissionModel::selectOptions())
                ->saving(function ($v) {
                    return (int) $v;
                });

            $form->text('slug', trans('admin.slug'))
                ->required()
                ->creationRules(['required', "unique:{$connection}.{$permissionTable}"])
                ->updateRules(['required', "unique:{$connection}.{$permissionTable},slug,$id"]);
            $form->text('name', trans('admin.name'))->required();

            $form->multipleSelect('http_method', trans('admin.http.method'))
                ->options($this->getHttpMethodsOptions())
                ->help(trans('admin.all_methods_if_empty'));

            $form->tags('http_path', trans('admin.http.path'))
                ->options($this->getRoutes());

            if ($bindMenu) {
                $this->addMenuTreeField($form);
            }

            $form->display('created_at', trans('admin.created_at'));
            $form->display('updated_at', trans('admin.updated_at'));

            $form->disableViewButton();
            $form->disableViewCheck();
        })->saved(function () use ($controller) {
            $controller->flushMenuCache();
        });
    }

    public function getRoutes()
    {
        $prefix = (string) config('admin.route.prefix');

        $container = collect();

        $routes = collect(app('router')->getRoutes())->map(function ($route) use ($prefix, $container) {
            if (! Str::startsWith($uri = $route->uri(), $prefix) && $prefix && $prefix !== '/') {
                return;
            }

            if (! Str::contains($uri, '{')) {
                if ($prefix !== '/') {
                    $route = Str::replaceFirst($prefix, '', $uri.'*');
                } else {
                    $route = $uri.'*';
                }

                if ($route !== '*') {
                    $container->push($route);
                }
            }

            $path = preg_replace('/{.*}+/', '*', $uri);

            if ($prefix !== '/') {
                return Str::replaceFirst($prefix, '', $path);
            }

            return $path;
        });

        return $container->merge($routes)->filter()->all();
    }

    /**
     * Get options of HTTP methods select field.
     *
     * @return array
     */
    protected function getHttpMethodsOptions()
    {
        $permissionModel = config('admin.database.permissions_model');

        return array_combine($permissionModel::$httpMethods, $permissionModel::$httpMethods);
    }
}
