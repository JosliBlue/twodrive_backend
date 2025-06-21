<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Utils\AESEncryption;
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Facades\Validator;
use PHPOpenSourceSaver\JWTAuth\{Exceptions\JWTException, Facades\JWTAuth};

class UserController
{
    private $aes;
    public function __construct()
    {
        $this->aes = new AESEncryption();
    }
    /**
     * User profile
     *
     * Endpoint Privado
     *
     * Este método devuelve la información del usuario autenticado.
     */
    public function profile(): JsonResponse
    {
        try {
            $user = auth('api')->user();
            return response()->json([
                'status' => true,
                'token' => JWTAuth::getToken()->get(),
                'user' => $this->userResponse($user)
            ]);
        } catch (JWTException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error obteniendo usuario',
            ], 500);
        }
    }

    /**
     * Change password
     *
     * Endpoint Privado
     *
     * Este método permite al usuario cambiar su contraseña(metodo unico)
     */
    public function changePassword(Request $request): JsonResponse
    {
        try {
            $user = auth('api')->user();

            $validator = Validator::make($request->all(), [
                'current_password' => 'required|string',
                'new_password' => 'required|string|min:8|confirmed',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            if ($this->aes->encrypt($this->aes->encrypt($request->current_password)) !== $user->password) {
                return response()->json([
                    'status' => false,
                    'message' => 'Contraseña actual ingresada incorrecta'
                ], 422);
            }

            $user->update(['password' => $this->aes->encrypt($this->aes->encrypt($request->new_password))]);

            return response()->json([
                'status' => true,
                'message' => 'Contraseña cambiada exitosamente'
            ]);
        } catch (JWTException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error cambiando contraseña'
            ], 500);
        }
    }
    /**
     * ####################   METODOS PRIVADOS DE MI CONTROLADOR SIN RUTAS   ####################
     */
    private function userResponse(User $user): array
    {
        $response = [
            'id' => $user->id,
            'email' => $this->aes->decrypt($this->aes->decrypt($user->email)),
            'email_verified' => (bool) $user->email_verified,
            'two_factor_enabled' => (bool) $user->two_factor_enabled,
        ];
        return $response;
    }
}
