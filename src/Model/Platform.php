<?php

declare(strict_types=1);

namespace App\Model;

use App\Service\Crypto;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property string $title
 * @property string $category
 * @property string $url
 * @property string $account
 * @property string $password_encrypted
 * @property string $description
 * @property int $created_by
 * @property int $updated_by
 */
class Platform extends Model
{
    protected $table = 'platforms';

    protected $fillable = ['title', 'category', 'url', 'account', 'description'];

    protected $hidden = ['password_encrypted', 'pivot'];

    protected $casts = [
        'created_by' => 'integer',
        'updated_by' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_platforms', 'platform_id', 'user_id');
    }

    public function setPassword(string $password): void
    {
        $this->password_encrypted = $password === '' ? '' : Crypto::encrypt($password);
    }

    public function password(): string
    {
        return $this->password_encrypted === '' ? '' : Crypto::decrypt($this->password_encrypted);
    }
}
