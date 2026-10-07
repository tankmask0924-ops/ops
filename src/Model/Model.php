<?php

declare(strict_types=1);

namespace App\Model;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model as EloquentModel;

abstract class Model extends EloquentModel
{
    /** 时间统一按本地时区输出 Y-m-d H:i:s（Eloquent 默认会转成 UTC 的 ISO 格式） */
    protected function serializeDate(DateTimeInterface $date): string
    {
        return $date->format('Y-m-d H:i:s');
    }
}
