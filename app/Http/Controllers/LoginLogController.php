<?php

namespace App\Http\Controllers;

use App\Models\LoginLog;
use App\Utils\AESEncryption;
use Illuminate\Http\JsonResponse;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;

class LoginLogController
{
    private $aes;

    public function __construct()
    {
        $this->aes = new AESEncryption();
    }

    /**
     * Get all login attempts for authenticated user
     *
     * Endpoint Privado
     *
     * Este método devuelve todos los intentos de inicio de sesión del usuario autenticado
     * sin paginación, ordenados por fecha más reciente primero.
     */
    public function myLoginAttempts(): JsonResponse
    {
        try {
            $user = auth('api')->user();

            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'Usuario no autenticado'
                ], 401);
            }

            $loginLogs = LoginLog::where('user_id', $user->id)
                ->orderBy('logged_in_at', 'desc')
                ->get();

            $formattedLogs = $loginLogs->map(function ($log) {
                return [
                    'id' => $log->id,
                    'ip_address' => $this->aes->decrypt($this->aes->decrypt($log->ip_address)),
                    'user_agent' => $log->user_agent,
                    'status' => $this->aes->decrypt($this->aes->decrypt($log->status)),
                    'logged_in_at' => $log->logged_in_at->format('Y-m-d H:i:s'),
                    'logged_in_at_human' => $log->logged_in_at->diffForHumans(),
                ];
            });

            return response()->json([
                'status' => true,
                'message' => 'Intentos de inicio de sesión obtenidos exitosamente',
                'total_attempts' => $loginLogs->count(),
                'login_attempts' => $formattedLogs
            ]);

        } catch (JWTException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error de autenticación'
            ], 401);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error obteniendo intentos de inicio de sesión',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
