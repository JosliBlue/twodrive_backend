<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pdf extends Model
{
    protected $table = 'pdfs';

    protected $fillable = [
        'user_id',
        'original_filename',
        'encrypted_filename',
        'path',
        'uploaded_at',
        'pdf_password',
    ];

    protected $hidden = [
        'pdf_password',
    ];

    protected $casts = [
        'uploaded_at' => 'datetime',
    ];

    // Relaciones
    public function owner()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function permissions()
    {
        return $this->hasMany(PdfUserPermission::class);
    }
}
