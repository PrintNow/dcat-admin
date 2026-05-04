<?php

namespace Dcat\Admin\Enums;

enum FormMode: string
{
    case Edit = 'edit';
    case Create = 'create';
    case Delete = 'delete';
}
