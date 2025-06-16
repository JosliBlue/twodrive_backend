<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoginLog extends Model
{
    protected $table = 'login_logs';

    protected $fillable = [
        'user_id',
        'ip_address',
        'user_agent',
        'status',
        'logged_in_at',
    ];

    protected $casts = [
        'logged_in_at' => 'datetime',
    ];

    // Relaciones
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
