<?php

namespace App\Http\Controllers;

use App\Models\Pdf;
use App\Models\PdfUserPermission;
use App\Utils\AESEncryption;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use setasign\Fpdi\Tcpdf\Fpdi;

class PdfController
{
    private $aesEncryption;

    public function __construct()
    {
        $this->aesEncryption = new AESEncryption();
    }

    /**
     * My PDFs
     *
     * Este método obtiene todos los PDFs propios del usuario autenticado.
     * Retorna una lista con los documentos que el usuario ha subido.
     */
    public function myPdfs(): JsonResponse
    {
        try {
            $user = auth('api')->user();

            $ownPdfs = $user->pdfs()->get()->map(function ($pdf) {
                return [
                    'id' => $pdf->id,
                    'filename' => $this->aesEncryption->decrypt($pdf->filename), // Desencriptar para mostrar
                    'deleted_at' => $pdf->deleted_at ? $pdf->deleted_at : false,
                    'updated_at' => $pdf->updated_at
                ];
            });
            if ($ownPdfs->isEmpty()) {
                return response()->json([
                    'status' => true,
                    'message' => 'Sin documentos aun',
                    'pdfs' => []
                ], 200);
            }
            return response()->json([
                'status' => true,
                'count' => $ownPdfs->count(),
                'pdfs' => $ownPdfs
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error al obtener mis PDFs',
                'error' => config('app.debug') ? $e->getMessage() : 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Trash PDFs
     *
     * Este método obtiene todos los PDFs eliminados (papelera) del usuario autenticado.
     * Muestra únicamente los archivos que han sido eliminados con soft delete y pueden ser restaurados.
     */
    public function trashPdfs(): JsonResponse
    {
        try {
            $user = auth('api')->user();

            // Obtener solo los PDFs eliminados (soft deleted) del usuario
            $trashedPdfs = $user->pdfs()->onlyTrashed()->get()->map(function ($pdf) {
                return [
                    'id' => $pdf->id,
                    'filename' => $this->aesEncryption->decrypt($pdf->filename),
                    'deleted_at' => $pdf->deleted_at,
                    'updated_at' => $pdf->updated_at
                ];
            });

            if ($trashedPdfs->isEmpty()) {
                return response()->json([
                    'status' => true,
                    'message' => 'La papelera está vacía',
                    'pdfs' => []
                ], 200);
            }

            return response()->json([
                'status' => true,
                'count' => $trashedPdfs->count(),
                'pdfs' => $trashedPdfs
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error al obtener PDFs de la papelera',
                'error' => config('app.debug') ? $e->getMessage() : 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Upload PDF
     *
     * Este método permite subir un archivo PDF y aplicarle protección por contraseña.
     * El archivo se procesa, encripta y almacena de forma segura en el sistema.
     */
    public function uploadPdf(Request $request): JsonResponse
    {
        // Validación
        $validator = Validator::make($request->all(), [
            'pdf_file' => 'required|file|mimes:pdf|max:10240', // Máximo 10MB
            'pdf_password' => 'required|string|min:6|max:50'
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
            $uploadedFile = $request->file('pdf_file');
            $password = $request->input('pdf_password');
            $originalName = pathinfo($uploadedFile->getClientOriginalName(), PATHINFO_FILENAME);

            // Procesar y guardar archivo
            $encryptedFilename = $this->aesEncryption->encrypt($originalName) . '.pdf';
            $protectedContent = $this->applyPdfPassword(
                file_get_contents($uploadedFile->getPathname()),
                $password
            );

            Storage::put($encryptedFilename, $protectedContent);

            // Crear registro en BD
            $pdf = Pdf::create([
                'filename' => $this->aesEncryption->encrypt($originalName),
                'user_id' => $user->id,
                'pdf_password' => $this->aesEncryption->encrypt($password),
            ]);

            return response()->json([
                'status' => true,
                'message' => 'PDF subido exitosamente',
                'data' => [
                    'id' => $pdf->id,
                    'filename' => $originalName,
                    'has_password' => true,
                    'uploaded_at' => $pdf->created_at
                ]
            ], 201);
        } catch (\Exception $e) {
            // Cleanup si existe el archivo
            if (isset($encryptedFilename)) {
                Storage::delete($encryptedFilename);
            }

            return response()->json([
                'status' => false,
                'message' => 'Error al subir el PDF',
                'error' => config('app.debug') ? $e->getMessage() : 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Delete PDF (Soft Delete)
     *
     * Este método elimina lógicamente un PDF usando soft delete.
     * Solo el propietario del PDF puede eliminarlo.
     * El archivo físico permanece en storage para posible restauración.
     */
    public function deletePdf($id): JsonResponse
    {
        try {
            $user = auth('api')->user();

            $pdf = Pdf::withTrashed()
                ->where('id', $id)
                ->where('user_id', $user->id)
                ->first();

            if (!$pdf) {
                return response()->json([
                    'status' => false,
                    'message' => 'PDF no encontrado o no tienes permisos para eliminarlo'
                ], 404);
            }

            // Verificar si ya está eliminado (soft deleted)
            if ($pdf->trashed()) {
                return response()->json([
                    'status' => false,
                    'message' => 'El PDF ya está eliminado'
                ], 400);
            }

            // Realizar soft delete
            $pdf->delete();

            return response()->json([
                'status' => true,
                'message' => 'PDF eliminado exitosamente',
                'data' => [
                    'id' => $pdf->id,
                    'filename' => $this->aesEncryption->decrypt($pdf->filename),
                    'deleted_at' => $pdf->deleted_at
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error al eliminar el PDF',
                'error' => config('app.debug') ? $e->getMessage() : 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Restore PDF
     *
     * Este método permite restaurar un PDF que fue eliminado con soft delete.
     * Solo el propietario puede restaurar sus PDFs eliminados.
     */
    public function restorePdf($id): JsonResponse
    {
        try {
            $user = auth('api')->user();

            // Buscar el PDF eliminado (withTrashed incluye los soft deleted)
            $pdf = Pdf::withTrashed()
                ->where('id', $id)
                ->where('user_id', $user->id)
                ->first();

            if (!$pdf) {
                return response()->json([
                    'status' => false,
                    'message' => 'PDF no encontrado o no tienes permisos para restaurarlo'
                ], 404);
            }

            if (!$pdf->trashed()) {
                return response()->json([
                    'status' => false,
                    'message' => 'El PDF no está eliminado'
                ], 400);
            }

            // Restaurar el PDF
            $pdf->restore();

            return response()->json([
                'status' => true,
                'message' => 'PDF restaurado exitosamente',
                'data' => [
                    'id' => $pdf->id,
                    'filename' => $this->aesEncryption->decrypt($pdf->filename),
                    'restored_at' => now()
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error al restaurar el PDF',
                'error' => config('app.debug') ? $e->getMessage() : 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Delete PDF Force
     *
     * Este método elimina completamente el PDF de la base de datos y el archivo físico.
     * Esta acción es irreversible y solo puede ser ejecutada por el propietario.
     */
    public function deletePdfForce($id): JsonResponse
    {
        try {
            $user = auth('api')->user();

            // Buscar el PDF (incluyendo eliminados)
            $pdf = Pdf::withTrashed()
                ->where('id', $id)
                ->where('user_id', $user->id)
                ->first();

            if (!$pdf) {
                return response()->json([
                    'status' => false,
                    'message' => 'PDF no encontrado o no tienes permisos para eliminarlo'
                ], 404);
            }

            // Obtener información antes de eliminar
            $filename = $this->aesEncryption->decrypt($pdf->filename);
            $encryptedFilename = $this->aesEncryption->encrypt($filename) . '.pdf';

            // Eliminar archivo físico del storage
            if (Storage::exists($encryptedFilename)) {
                Storage::delete($encryptedFilename);
            }

            // Eliminar permanentemente de la base de datos
            $pdf->forceDelete();

            return response()->json([
                'status' => true,
                'message' => 'PDF eliminado permanentemente',
                'data' => [
                    'id' => $id,
                    'filename' => $filename
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error al eliminar permanentemente el PDF',
                'error' => config('app.debug') ? $e->getMessage() : 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * View PDF
     *
     * Este método permite visualizar un PDF propio o compartido contigo.
     * Valida permisos de visualización antes de retornar el contenido.
     */
    public function viewPdf($id): JsonResponse
    {
        try {
            $user = auth('api')->user();

            // Verificar acceso al PDF
            $accessInfo = $this->checkPdfAccess($id, $user->id, 'view');

            if (!$accessInfo['has_access']) {
                return response()->json([
                    'status' => false,
                    'message' => $accessInfo['message']
                ], $accessInfo['status_code']);
            }

            $pdf = $accessInfo['pdf'];
            $filename = $this->aesEncryption->decrypt($pdf->filename);
            $encryptedFilename = $this->aesEncryption->encrypt($filename) . '.pdf';

            if (!Storage::exists($encryptedFilename)) {
                return response()->json([
                    'status' => false,
                    'message' => 'Archivo no encontrado en el servidor'
                ], 404);
            }

            $fileContent = Storage::get($encryptedFilename);
            $base64Content = base64_encode($fileContent);

            return response()->json([
                'status' => true,
                'message' => 'PDF obtenido exitosamente',
                'data' => [
                    'id' => $pdf->id,
                    'filename' => $filename,
                    'content' => $base64Content,
                    'content_type' => 'application/pdf',
                    'size' => strlen($fileContent)
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error al obtener el PDF',
                'error' => config('app.debug') ? $e->getMessage() : 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Download PDF
     *
     * Este método permite descargar un PDF propio o compartido contigo.
     * Valida permisos de descarga antes de retornar el archivo.
     */
    public function downloadPdf($id)
    {
        try {
            $user = auth('api')->user();

            // Verificar acceso al PDF
            $accessInfo = $this->checkPdfAccess($id, $user->id, 'download');

            if (!$accessInfo['has_access']) {
                return response()->json([
                    'status' => false,
                    'message' => $accessInfo['message']
                ], $accessInfo['status_code']);
            }

            $pdf = $accessInfo['pdf'];
            $filename = $this->aesEncryption->decrypt($pdf->filename);
            $encryptedFilename = $this->aesEncryption->encrypt($filename) . '.pdf';

            if (!Storage::exists($encryptedFilename)) {
                return response()->json([
                    'status' => false,
                    'message' => 'Archivo no encontrado en el servidor'
                ], 404);
            }

            // Retornar el archivo directamente para descarga
            return Storage::download($encryptedFilename, $filename . '.pdf', [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $filename . '.pdf"'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error al descargar el PDF',
                'error' => config('app.debug') ? $e->getMessage() : 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * ####################   METODOS PRIVADOS DE MI CONTROLADOR SIN RUTAS   ####################
     */

    /**
     * Método privado para verificar acceso a PDFs
     * Centraliza la lógica de validación de permisos
     */
    private function checkPdfAccess($pdfId, $userId, $action = 'view'): array
    {
        // Buscar el PDF
        $pdf = Pdf::find($pdfId);

        if (!$pdf) {
            return [
                'has_access' => false,
                'message' => 'PDF no encontrado',
                'status_code' => 404,
                'pdf' => null
            ];
        }

        // Si es el propietario, tiene acceso completo
        if ($pdf->user_id === $userId) {
            return [
                'has_access' => true,
                'message' => 'Acceso como propietario',
                'status_code' => 200,
                'pdf' => $pdf
            ];
        }

        // Verificar si tiene permisos compartidos
        $permission = PdfUserPermission::where('pdf_id', $pdfId)
            ->where('shared_with_user_id', $userId)
            ->first();

        if (!$permission) {
            return [
                'has_access' => false,
                'message' => 'No tienes permisos para acceder a este PDF',
                'status_code' => 403,
                'pdf' => null
            ];
        }

        // Verificar permisos específicos según la acción
        if ($action === 'view' && !$permission->can_view) {
            return [
                'has_access' => false,
                'message' => 'No tienes permisos para visualizar este PDF',
                'status_code' => 403,
                'pdf' => null
            ];
        }

        if ($action === 'download' && !$permission->can_download) {
            return [
                'has_access' => false,
                'message' => 'No tienes permisos para descargar este PDF',
                'status_code' => 403,
                'pdf' => null
            ];
        }

        return [
            'has_access' => true,
            'message' => 'Acceso permitido',
            'status_code' => 200,
            'pdf' => $pdf
        ];
    }

    private function applyPdfPassword($pdfContent, $password): string
    {
        // Variable para rastrear el archivo temporal creado
        $tempFile = null;

        try {
            // PASO 1: Crear archivo temporal en el sistema
            // Se necesita un archivo físico porque FPDI requiere una ruta de archivo para leer PDFs
            $tempFile = tempnam(sys_get_temp_dir(), 'pdf_') . '.pdf';

            // PASO 2: Escribir el contenido PDF al archivo temporal
            // Convertimos el contenido binario en un archivo físico temporal
            file_put_contents($tempFile, $pdfContent);

            // PASO 3: Inicializar FPDI (extensión de TCPDF para importar PDFs)
            // FPDI permite importar páginas de PDFs existentes
            $pdf = new Fpdi();

            // PASO 4: Establecer el archivo fuente y obtener número de páginas
            // setSourceFile() lee el PDF y retorna la cantidad total de páginas
            $pageCount = $pdf->setSourceFile($tempFile);

            // PASO 5: Importar todas las páginas del PDF original
            // Loop que recorre cada página del PDF original
            for ($i = 1; $i <= $pageCount; $i++) {
                // Crear una nueva página en el PDF de salida
                $pdf->AddPage();

                // Importar la página actual y aplicarla como template
                // importPage($i) obtiene la página i como template
                // useTemplate() aplica ese template a la página actual
                $pdf->useTemplate($pdf->importPage($i));
            }

            // PASO 6: Aplicar protección con contraseña al PDF
            // SetProtection() configura la seguridad del PDF:
            // - ['print', 'copy']: Permisos permitidos (imprimir y copiar texto)
            // - $password: Contraseña de usuario (para abrir el PDF)
            // - $password . '_owner': Contraseña de propietario (para editar permisos)
            $pdf->SetProtection(['print', 'copy'], $password, $password . '_owner');

            // PASO 7: Generar y retornar el PDF protegido como string
            // Output('', 'S') retorna el PDF como string binario en lugar de enviarlo al navegador
            return $pdf->Output('', 'S');
        } catch (\Exception $e) {
            // Si algo falla, lanzamos una excepción más descriptiva
            throw new \Exception('Error al proteger PDF: ' . $e->getMessage());
        } finally {
            // LIMPIEZA GARANTIZADA: Eliminar archivo temporal
            // Esto garantiza que no dejemos archivos temporales en el sistema
            if ($tempFile && file_exists($tempFile)) {
                unlink($tempFile); // Eliminar el archivo temporal del sistema
            }
        }
    }
}
