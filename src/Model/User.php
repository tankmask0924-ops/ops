<?php

declare(strict_types=1);

namespace App\Model;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property string $username
 * @property string $password_hash
 * @property string $name
 * @property bool $is_admin
 * @property int $status
 * @property int $login_failures
 * @property ?\Carbon\Carbon $locked_until
 * @property ?\Carbon\Carbon $password_changed_at
 * @property ?\Carbon\Carbon $last_login_at
 */
class User extends Model
{
    public const STATUS_ENABLED = 1;

    public const STATUS_DISABLED = 0;

    protected $table = 'users';

    protected $fillable = ['username', 'name', 'is_admin', 'status'];

    protected $hidden = ['password_hash', 'login_failures', 'locked_until', 'password_changed_at'];

    protected $casts = [
        'is_admin' => 'boolean',
        'status' => 'integer',
        'login_failures' => 'integer',
        'locked_until' => 'datetime',
        'password_changed_at' => 'datetime',
        'last_login_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /** 分配给该账号的平台；管理员不看这个，能看到全部 */
    public function platforms(): BelongsToMany
    {
        return $this->belongsToMany(Platform::class, 'user_platforms', 'user_id', 'platform_id');
    }

    public function setPassword(string $password): void
    {
        $this->password_hash = password_hash($password, PASSWORD_DEFAULT);
    }

    public function canSee(Platform $platform): bool
    {
        return $this->is_admin || $this->platforms()->whereKey($platform->id)->exists();
    }
}
