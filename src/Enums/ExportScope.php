<?php

namespace Dcat\Admin\Enums;

enum ExportScope: string
{
    case All = 'all';
    case CurrentPage = 'page';
    case SelectedRows = 'selected';
}
