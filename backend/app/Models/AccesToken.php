<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** ADMIN-02 : lien à usage unique (seul le hash du token est stocké). */
class AccesToken extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'type', 'token_hash', 'expire_le'];

    protected function casts(): array
    {
        return ['expire_le' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
