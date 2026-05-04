<?php

namespace Dcat\Admin\Enums;

enum MapProvider: string
{
    case Tencent = 'tencent';
    case Google = 'google';
    case Yandex = 'yandex';
    case Amap = 'amap';
    case Baidu = 'baidu';
}
