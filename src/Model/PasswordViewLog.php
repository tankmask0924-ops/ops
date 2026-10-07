<?php

declare(strict_types=1);

namespace App\Model;

/**
 * @property int $id
 * @property int $user_id
 * @property int $platform_id
 * @property string $ip
 */
class PasswordViewLog extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'password_view_logs';

    protected $fillable = ['user_id', 'platform_id', 'ip'];

    protected $casts = [
        'user_id' => 'integer',
        'platform_id' => 'integer',
        'created_at' => 'datetime',
    ];
}
