<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable;

    protected $table = 'users';

    protected $fillable = [
        'name',
        'email',
        'password',
        'encryption_key',
        'two_factor_enabled',
        'two_factor_code',
        'two_factor_expires_at',
    ];

    protected $hidden = [
        'password',
        'encryption_key',
        'two_factor_code',
    ];

    protected $casts = [
        'two_factor_enabled' => 'boolean',
        'two_factor_expires_at' => 'datetime',
    ];

    // Relaciones
    public function pdfs()
    {
        return $this->hasMany(Pdf::class);
    }

    public function sharedPdfs()
    {
        return $this->hasMany(PdfUserPermission::class, 'shared_with_user_id');
    }

    public function loginLogs()
    {
        return $this->hasMany(LoginLog::class);
    }
}
