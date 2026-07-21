<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class TypeFormation extends Model
{
    protected $table = 'types_formation';

    protected $fillable = ['nom', 'slug'];

    public function formations(): BelongsToMany
    {
        return $this->belongsToMany(Formation::class, 'formation_type_formation');
    }
}
