<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Pdf extends Model
{
    /**
     * Implementa la funcionalidad de "soft delete" en el modelo.
     * 
     * En lugar de eliminar físicamente los registros de la base de datos,
     * el soft delete marca los registros como eliminados estableciendo un valor
     * en el campo 'deleted_at'. Esto permite restaurar los registros si es necesario
     * y mantener un historial de los datos eliminados.
     * 
     * Para realizar un soft delete, se actualiza la columna 'deleted_at' con la fecha y hora actual.
     * Las consultas estándar excluyen automáticamente los registros marcados como eliminados,
     * pero es posible incluirlos o restaurarlos según sea necesario.
     */
    use SoftDeletes;
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
