<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PdfUserPermission extends Model
{
    protected $table = 'pdf_user_permissions';

    protected $fillable = [
        'pdf_id',
        'shared_with_user_id',
        'can_view',
        'can_download',
    ];

    protected $casts = [
        'can_view' => 'boolean',
        'can_download' => 'boolean',
    ];

    // Relaciones
    public function pdf()
    {
        return $this->belongsTo(Pdf::class);
    }

    public function sharedWith()
    {
        return $this->belongsTo(User::class, 'shared_with_user_id');
    }
}
