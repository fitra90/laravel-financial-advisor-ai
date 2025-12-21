<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Email extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'gmail_id',
        'thread_id',
        'from_email',
        'from_name',
        'to_email',
        'subject',
        'body_text',
        'body_html',
        'email_date',
        'labels',
        'embedding',
        'processed_at',
    ];

    protected $casts = [
        'email_date' => 'datetime',
        'labels' => 'array',
        'processed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
