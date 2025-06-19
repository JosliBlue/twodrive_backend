<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use Notifiable;

    protected $table = 'users';

    protected $fillable = [
        'email',
        'password',
        'two_factor_enabled',
        'two_factor_code',
        'two_factor_expires_at',
        'email_verified',
        'email_verification_code',
        'email_verification_expires_at',
        'account_deletion_code',
        'account_deletion_expires_at',
    ];

    protected $hidden = [
        'password',
        'two_factor_code',
        'email_verification_code',
        'account_deletion_code',
    ];

    protected $casts = [
        'two_factor_enabled' => 'boolean',
        'two_factor_expires_at' => 'datetime',
        'email_verified' => 'boolean',
        'email_verification_expires_at' => 'datetime',
        'account_deletion_expires_at' => 'datetime',
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

    /**
     * Get the identifier that will be stored in the subject claim of the JWT.
     *
     * @return mixed
     */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     *
     * @return array
     */
    public function getJWTCustomClaims()
    {
        return [];
    }
}
