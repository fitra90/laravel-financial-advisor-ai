<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Instruction extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'instruction',
        'is_active',
        'triggers',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'triggers' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
