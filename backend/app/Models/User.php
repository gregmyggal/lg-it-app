<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'statut',
        'date_sortie',
        'must_change_password',
        'invitation_envoyee_le',
        'mot_de_passe_defini_le',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'must_change_password' => 'boolean',
            'date_sortie' => 'date:Y-m-d',
            'invitation_envoyee_le' => 'datetime',
            'mot_de_passe_defini_le' => 'datetime',
        ];
    }

    public function accesTokens(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(AccesToken::class);
    }

    public function professeur(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Professeur::class);
    }

    // Bypasse toutes les policies, équivalent de manage_options côté WP.
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isDirecteur(): bool
    {
        return $this->role === 'directeur';
    }

    public function isProfesseur(): bool
    {
        return $this->role === 'professeur';
    }

    public function isEleve(): bool
    {
        return $this->role === 'eleve';
    }

    // Admin + directeur ont un accès de gestion transverse (pas de notion d'isolation pour eux).
    public function isStaff(): bool
    {
        return $this->isAdmin() || $this->isDirecteur();
    }
}
