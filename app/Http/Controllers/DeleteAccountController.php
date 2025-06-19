<?php

namespace App\Http\Controllers;

use App\Models\PdfUserPermission;
use App\Mail\AccountDeletionConfirmationMail;
use App\Models\User;
use App\Utils\AESEncryption;
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Facades\{Log, Mail, Validator};
use PHPOpenSourceSaver\JWTAuth\{Exceptions\JWTException, Facades\JWTAuth};

class DeleteAccountController
{
    private $aes;
    public function __construct()
    {
        $this->aes = new AESEncryption();
    }
    //
    /**
     * Request account deletion
     *
     * Endpoint Privado
     *
     * Este método permite al usuario autenticado solicitar la eliminación de su cuenta mediante un código de confirmación al email
     *
     * El usuario debe tener su email verificado para poder solicitar la eliminación.
     */
    public function requestAccountDeletion(): JsonResponse
    {
        try {
            $user = auth('api')->user();

            if (!$user->email_verified) {
                return response()->json([
                    'status' => false,
                    'message' => 'Email no verificado'
                ], 403);
            }

            $code = rand(100000, 999999);
            $user->update([
                'account_deletion_code' => $this->aes->encrypt($code),
                'account_deletion_expires_at' => now()->addMinutes(10)
            ]);

            try {
                Mail::to($this->aes->decrypt($user->email))->send(new AccountDeletionConfirmationMail($code, $this->aes->decrypt($user->email)));
            } catch (\Exception $e) {
                Log::warning('Error enviando email de confirmación de eliminación: ' . $e->getMessage());
                return response()->json([
                    'status' => false,
                    'message' => 'Error enviando email de confirmación'
                ], 500);
            }

            return response()->json([
                'status' => true,
                'message' => 'Código de confirmación de eliminación enviado exitosamente'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error solicitando eliminación de cuenta: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Error solicitando eliminación de cuenta'
            ], 500);
        }
    }

    /**
     * Confirm and execute account deletion
     *
     * Endpoint Privado
     *
     * Antes ejecutar "Request Account Deletion" para enviar el codigo de confirmacion al email del usuario
     */
    public function confirmAccountDeletion(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'deletion_code' => 'required|string|size:6'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = auth('api')->user();

            if (!$user->email_verified) {
                return response()->json([
                    'status' => false,
                    'message' => 'Email no verificado'
                ], 403);
            }

            if (!$user->account_deletion_code || $user->account_deletion_code !== $this->aes->encrypt($request->deletion_code)) {
                return response()->json([
                    'status' => false,
                    'message' => 'Código de eliminación inválido'
                ], 422);
            }

            if (now()->gt($user->account_deletion_expires_at)) {
                return response()->json([
                    'status' => false,
                    'message' => 'Código de eliminación expirado'
                ], 422);
            }

            // Eliminar relaciones en la base de datos
            PdfUserPermission::where('shared_with_user_id', $user->id)
                ->orWhereHas('pdf', fn($q) => $q->where('user_id', $user->id))
                ->delete();

            $user->pdfs()->delete();
            $user->loginLogs()->delete();

            // Invalidar token JWT
            try {
                if ($token = JWTAuth::getToken()) {
                    JWTAuth::invalidate($token);
                }
            } catch (JWTException $e) {
                Log::warning('Error invalidando token JWT', ['user_id' => $user->id]);
            }

            // Eliminar usuario
            if ($user instanceof User) {
                $user->delete();
            }

            Log::info('Usuario eliminado de la base de datos', []);

            return response()->json([
                'status' => true,
                'message' => 'Cuenta eliminada exitosamente',
                'details' => [
                    'deleted_at' => now()->toDateTimeString()
                ]
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error eliminando cuenta: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Error eliminando cuenta'
            ], 500);
        }
    }
}
