<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'google_id',
        'google_token',
        'google_refresh_token',
        'google_token_expires_at',
        'hubspot_id',
        'hubspot_token',
        'hubspot_refresh_token',
        'hubspot_token_expires_at',
    ];

    protected $guarded = [];
    
    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'google_token',
        'google_refresh_token',
        'hubspot_token',
        'hubspot_refresh_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'google_token_expires_at' => 'datetime',
        'hubspot_token_expires_at' => 'datetime',
        'password' => 'hashed',
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
        ];
    }

    // Relationships
    public function messages()
    {
        return $this->hasMany(Message::class);
    }

    public function tasks()
    {
        return $this->hasMany(Task::class);
    }

    public function instructions()
    {
        return $this->hasMany(Instruction::class);
    }

    public function emails()
    {
        return $this->hasMany(Email::class);
    }

    public function contacts()
    {
        return $this->hasMany(Contact::class);
    }
}
