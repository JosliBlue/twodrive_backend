<?php

namespace App\Http\Controllers;

use App\Models\PdfUserPermission;
use App\Models\Pdf;
use App\Models\User;
use App\Utils\AESEncryption;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SharedPdfController
{
    private $aes;

    public function __construct()
    {
        $this->aes = new AESEncryption();
    }

    /**
     * PDFs shared with me
     *
     * Este método obtiene todos los PDFs que han sido compartidos con el usuario autenticado.
     * Incluye información sobre los permisos que tiene sobre cada documento.
     */
    public function sharedWithMe(): JsonResponse
    {
        try {
            $user = auth('api')->user();

            $sharedPermissions = PdfUserPermission::where('shared_with_user_id', $user->id)->get();

            $sharedPdfs = $sharedPermissions->map(function ($permission) {
                $pdf = $permission->pdf;
                if (!$pdf) {
                    return null;
                }
                return [
                    'id' => $pdf->id,
                    'filename' => $this->aes->decrypt($this->aes->decrypt($pdf->filename)),
                    'updated_at' => $pdf->updated_at,
                    'can_view' => $permission->can_view,
                    'can_download' => $permission->can_download
                ];
            })->filter();

            if ($sharedPdfs->isEmpty()) {
                return response()->json([
                    'status' => true,
                    'message' => 'Sin documentos compartidos conmigo aun',
                    'pdfs' => []
                ], 200);
            }

            return response()->json([
                'status' => true,
                'count' => $sharedPdfs->count(),
                'pdfs' => $sharedPdfs->values()
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error al obtener los PDFs compartidos conmigo',
                'error' => config('app.debug') ? $e->getMessage() : 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * PDFs shared by me
     *
     * Este método obtiene todos los PDFs que el usuario ha compartido con otros usuarios.
     * Muestra una lista de documentos propios que están siendo compartidos.
     */
    public function sharedByMe(): JsonResponse
    {
        try {
            $user = auth('api')->user();

            // Obtener PDFs propios que tienen permisos compartidos
            $sharedPdfs = Pdf::where('user_id', $user->id)
                ->whereHas('permissions')
                ->with(['permissions.sharedWith'])
                ->get();

            if ($sharedPdfs->isEmpty()) {
                return response()->json([
                    'status' => true,
                    'message' => 'No has compartido documentos aún',
                    'pdfs' => []
                ], 200);
            }

            $result = $sharedPdfs->map(function ($pdf) {
                $sharedWith = $pdf->permissions->map(function ($permission) {
                    return [
                        'permission_id' => $permission->id,
                        'shared_with_email' => $this->aes->decrypt($this->aes->decrypt($permission->sharedWith->email)),
                        'can_view' => $permission->can_view,
                        'can_download' => $permission->can_download,
                        'shared_at' => $permission->created_at
                    ];
                });

                return [
                    'id' => $pdf->id,
                    'filename' => $this->aes->decrypt($this->aes->decrypt($pdf->filename)),
                    'updated_at' => $pdf->updated_at,
                    'total_shares' => $sharedWith->count(),
                    'shared_with' => $sharedWith
                ];
            });

            return response()->json([
                'status' => true,
                'count' => $result->count(),
                'pdfs' => $result
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error al obtener los PDFs compartidos',
                'error' => config('app.debug') ? $e->getMessage() : 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Share PDF
     *
     * Este método permite compartir un PDF propio con otro usuario del sistema.
     * Solo el propietario del PDF puede compartirlo y configurar los permisos.
     */
    public function sharePdf(Request $request): JsonResponse
    {
        // Validación
        $validator = Validator::make($request->all(), [
            'pdf_id' => 'required|integer|exists:pdfs,id',
            'share_with_email' => 'required|email|max:255',
            'can_view' => 'boolean',
            'can_download' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Datos de entrada inválidos',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = auth('api')->user();
            $pdfId = $request->input('pdf_id');
            $shareWithEmail = $request->input('share_with_email');
            $canView = $request->input('can_view', true); // Por defecto puede ver
            $canDownload = $request->input('can_download', false); // Por defecto no puede descargar

            // Verificar que el PDF pertenece al usuario autenticado
            $pdf = Pdf::where('id', $pdfId)
                ->where('user_id', $user->id)
                ->first();

            if (!$pdf) {
                return response()->json([
                    'status' => false,
                    'message' => 'PDF no encontrado o no tienes permisos para compartirlo'
                ], 404);
            }

            // Buscar el usuario con quien se quiere compartir
            $shareWithUser = User::where('email', $this->aes->encrypt($this->aes->encrypt($shareWithEmail)))->first();

            if (!$shareWithUser) {
                return response()->json([
                    'status' => false,
                    'message' => 'Usuario no encontrado en el sistema'
                ], 404);
            }

            // Verificar que no se está compartiendo consigo mismo
            if ($shareWithUser->id === $user->id) {
                return response()->json([
                    'status' => false,
                    'message' => 'No puedes compartir un PDF contigo mismo'
                ], 400);
            }

            // Verificar si ya está compartido con esta persona
            $alreadyShared = PdfUserPermission::where('pdf_id', $pdfId)
                ->where('shared_with_user_id', $shareWithUser->id)
                ->exists();

            if ($alreadyShared) {
                return response()->json([
                    'status' => false,
                    'message' => 'El archivo ya está compartido con esta persona'
                ], 409);
            }

            // Crear nuevo permiso de compartir
            $permission = PdfUserPermission::create([
                'pdf_id' => $pdfId,
                'shared_with_user_id' => $shareWithUser->id,
                'can_view' => $canView,
                'can_download' => $canDownload
            ]);

            return response()->json([
                'status' => true,
                'message' => 'PDF compartido exitosamente',
                'data' => [
                    'permission_id' => $permission->id,
                    'pdf_id' => $pdfId,
                    'pdf_filename' => $this->aes->decrypt($this->aes->decrypt($pdf->filename)),
                    'shared_with_email' => $shareWithEmail,
                    'can_view' => $canView,
                    'can_download' => $canDownload,
                    'shared_at' => $permission->updated_at
                ]
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error al compartir el PDF',
                'error' => config('app.debug') ? $e->getMessage() : 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Update PDF Share Permissions
     *
     * Este método permite actualizar los permisos de un PDF ya compartido.
     * Solo el propietario puede modificar los permisos.
     */
    public function updatePdfSharePermissions(Request $request): JsonResponse
    {
        // Validación
        $validator = Validator::make($request->all(), [
            'permission_id' => 'required|integer|exists:pdf_user_permissions,id',
            'can_view' => 'boolean',
            'can_download' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Datos de entrada inválidos',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = auth('api')->user();
            $permissionId = $request->input('permission_id');
            $canView = $request->input('can_view', true);
            $canDownload = $request->input('can_download', false);

            // Buscar el permiso y verificar que el PDF pertenece al usuario
            $permission = PdfUserPermission::whereHas('pdf', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })->where('id', $permissionId)->with(['pdf', 'sharedWith'])->first();

            if (!$permission) {
                return response()->json([
                    'status' => false,
                    'message' => 'Permiso no encontrado o no tienes autorización para modificarlo'
                ], 404);
            }

            // Actualizar permisos
            $permission->update([
                'can_view' => $canView,
                'can_download' => $canDownload
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Permisos actualizados exitosamente',
                'data' => [
                    'permission_id' => $permission->id,
                    'pdf_filename' => $this->aes->decrypt($this->aes->decrypt($permission->pdf->filename)),
                    'shared_with_email' => $this->aes->decrypt($this->aes->decrypt($permission->sharedWith->email)),
                    'can_view' => $canView,
                    'can_download' => $canDownload,
                    'updated_at' => $permission->updated_at
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error al actualizar permisos',
                'error' => config('app.debug') ? $e->getMessage() : 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Revoke PDF Share
     *
     * Este método permite revocar el acceso compartido de un PDF.
     * Solo el propietario del PDF puede revocar permisos.
     */
    public function revokePdfShare(Request $request): JsonResponse
    {
        // Validación
        $validator = Validator::make($request->all(), [
            'pdf_id' => 'required|integer|exists:pdfs,id',
            'share_with_email' => 'required|email|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Datos de entrada inválidos',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = auth('api')->user();
            $pdfId = $request->input('pdf_id');
            $shareWithEmail = $request->input('share_with_email');

            // Verificar que el PDF pertenece al usuario autenticado
            $pdf = Pdf::where('id', $pdfId)
                ->where('user_id', $user->id)
                ->first();

            if (!$pdf) {
                return response()->json([
                    'status' => false,
                    'message' => 'PDF no encontrado o no tienes permisos para administrarlo'
                ], 404);
            }

            // Buscar el usuario con quien está compartido
            $shareWithUser = User::where('email', $this->aes->encrypt($this->aes->encrypt($shareWithEmail)))->first();

            if (!$shareWithUser) {
                return response()->json([
                    'status' => false,
                    'message' => 'Usuario no encontrado en el sistema'
                ], 404);
            }

            // Buscar y eliminar el permiso
            $permission = PdfUserPermission::where('pdf_id', $pdfId)
                ->where('shared_with_user_id', $shareWithUser->id)
                ->first();

            if (!$permission) {
                return response()->json([
                    'status' => false,
                    'message' => 'El PDF no está compartido con este usuario'
                ], 404);
            }

            $permission->delete();

            return response()->json([
                'status' => true,
                'message' => 'Acceso al PDF revocado exitosamente',
                'data' => [
                    'pdf_id' => $pdfId,
                    'pdf_filename' => $this->aes->decrypt($this->aes->decrypt($pdf->filename)),
                    'revoked_from_email' => $shareWithEmail,
                    'revoked_at' => now()
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error al revocar el acceso al PDF',
                'error' => config('app.debug') ? $e->getMessage() : 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Revoke All PDF Shares
     *
     * Este método revoca todos los accesos compartidos de un PDF específico.
     * Solo el propietario puede ejecutar esta acción.
     */
    public function revokeAllPdfShares($pdfId): JsonResponse
    {
        try {
            $user = auth('api')->user();

            // Verificar que el PDF pertenece al usuario
            $pdf = Pdf::where('id', $pdfId)
                ->where('user_id', $user->id)
                ->first();

            if (!$pdf) {
                return response()->json([
                    'status' => false,
                    'message' => 'PDF no encontrado o no tienes permisos para administrarlo'
                ], 404);
            }

            // Contar permisos antes de eliminar
            $sharesCount = PdfUserPermission::where('pdf_id', $pdfId)->count();

            if ($sharesCount === 0) {
                return response()->json([
                    'status' => false,
                    'message' => 'Este PDF no está compartido con nadie'
                ], 400);
            }

            // Eliminar todos los permisos
            PdfUserPermission::where('pdf_id', $pdfId)->delete();

            return response()->json([
                'status' => true,
                'message' => 'Todos los accesos al PDF han sido revocados exitosamente',
                'data' => [
                    'pdf_id' => $pdfId,
                    'pdf_filename' => $this->aes->decrypt($this->aes->decrypt($pdf->filename)),
                    'revoked_shares' => $sharesCount,
                    'revoked_at' => now()
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error al revocar todos los accesos',
                'error' => config('app.debug') ? $e->getMessage() : 'Error interno del servidor'
            ], 500);
        }
    }
}
