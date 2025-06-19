<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Mail\EmailVerificationMail;
use App\Utils\AESEncryption;
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Facades\{Log, Mail, Validator};

class VerifyAccountController
{
    private $aes;
    public function __construct()
    {
        $this->aes = new AESEncryption();
    }
    /**
     * Request email verification
     *
     * Endpoint Privado
     *
     * Este método permite al usuario autenticado solicitar la verificación de su email mediante un código de verificación enviado al email
     *
     * Despues ejecutar "Verify Email Address" para verificar el email con el codigo enviado
     */
    public function requestEmailVerification(): JsonResponse
    {
        try {
            $user = auth('api')->user();
            if ($user->email_verified) {
                return response()->json([
                    'status' => false,
                    'message' => 'Email ya verificado'
                ], 400);
            }

            $code = rand(100000, 999999);
            $user->update([
                'email_verification_code' => $this->aes->encrypt($code),
                'email_verification_expires_at' => now()->addMinutes(30)
            ]);

            try {
                Mail::to($this->aes->decrypt($user->email))->send(new EmailVerificationMail($code, $this->aes->decrypt($user->email)));

                return response()->json([
                    'status' => true,
                    'message' => 'Código de verificación enviado exitosamente',
                    'user' => $this->userResponse($user)
                ], 200);
            } catch (\Exception $e) {
                Log::warning('Error enviando email de verificación: ' . $e->getMessage());
                return response()->json([
                    'status' => false,
                    'message' => 'Error enviando email de verificación'
                ], 500);
            }
        } catch (\Exception $e) {
            Log::error('Error solicitando verificación de email: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Error solicitando verificación de email'
            ], 500);
        }
    }

    /**
     * Resend email verification code
     *
     * Endpoint Privado
     *
     * Antes ejecutar "Request Email Verification" para enviar el codigo de verificacion al email del usuario
     *
     * Despues ejecutar "Verify Email Address" para verificar el email con el codigo enviado
     *
     * Este método permite reenviar el código de verificación
     *
     * Este método es útil si el usuario no recibió el email de verificación o si el código ha expirado
     */
    public function resendEmailVerification(): JsonResponse
    {
        try {
            $user = auth('api')->user();
            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'Usuario no encontrado'
                ], 404);
            }

            if ($user->email_verified) {
                return response()->json([
                    'status' => false,
                    'message' => 'Email ya verificado'
                ], 400);
            }

            $code = rand(100000, 999999);
            $user->update([
                'email_verification_code' => $this->aes->encrypt($code),
                'email_verification_expires_at' => now()->addMinutes(30)
            ]);

            try {
                Mail::to($this->aes->decrypt($user->email))->send(new EmailVerificationMail($code, $this->aes->decrypt($user->email)));
            } catch (\Exception $e) {
                Log::warning('Error enviando email de verificación: ' . $e->getMessage());
                return response()->json([
                    'status' => false,
                    'message' => 'Error enviando email de verificación'
                ], 500);
            }

            return response()->json([
                'status' => true,
                'message' => 'Código de verificación reenviado exitosamente'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error reenviando código de verificación'
            ], 500);
        }
    }

    /**
     * Verify email address
     *
     * Endpoint Privado
     *
     * Antes ejecutar "Request Email Verification" para enviar el codigo de verificacion al email del usuario
     *
     * Este método permite a los usuarios verificar su email
     */
    public function verifyEmail(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'verification_code' => 'required|string|size:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Obtener el usuario autenticado
        $user = auth('api')->user();

        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'Usuario no autenticado'
            ], 401);
        }

        try {
            if ($user->email_verified) {
                return response()->json([
                    'status' => false,
                    'message' => 'Email ya verificado'
                ], 400);
            }

            if (!$user->email_verification_code || $user->email_verification_code !== $this->aes->encrypt($request->verification_code)) {
                return response()->json([
                    'status' => false,
                    'message' => 'Código inválido'
                ], 422);
            }

            if (now()->gt($user->email_verification_expires_at)) {
                return response()->json([
                    'status' => false,
                    'message' => 'Código expirado'
                ], 422);
            }

            $user->update([
                'email_verified' => true,
                'email_verification_code' => null,
                'email_verification_expires_at' => null
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Email verificado exitosamente',
                'user' => $this->userResponse($user)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error verificando email'
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
            'email' => $user->email,
            'email_verified' => (bool) $user->email_verified,
            'two_factor_enabled' => (bool) $user->two_factor_enabled,
        ];
        return $response;
    }
}
