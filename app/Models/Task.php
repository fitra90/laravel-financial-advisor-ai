<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Task extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'type',
        'status',
        'description',
        'context',
        'steps',
        'last_attempted_at',
        'attempt_count',
    ];

    protected $casts = [
        'context' => 'array',
        'steps' => 'array',
        'last_attempted_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
