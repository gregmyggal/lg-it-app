<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShareCode extends Model
{
    protected $fillable = ['code', 'created_by', 'expires_at', 'max_uses'];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public function shareable()
    {
        return $this->morphTo();
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public static function generateCode(): string
    {
        $code = strtoupper(substr(bin2hex(random_bytes(6)), 0, 12));

        while (self::where('code', $code)->exists()) {
            $code = strtoupper(substr(bin2hex(random_bytes(6)), 0, 12));
        }

        return $code;
    }

    public function isValid(): bool
    {
        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        if ($this->max_uses && $this->used_count >= $this->max_uses) {
            return false;
        }

        return true;
    }

    public function incrementUsage(): void
    {
        $this->increment('used_count');
    }
}
