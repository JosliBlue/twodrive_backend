<?php

// app/Http/Controllers/AuthTwoFactorController.php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class AuthTwoFactorController extends Controller
{
    /**
     * Enable 2FA
     *
     * Endpoint Privado
     *
     * Este método permite a los usuarios activar la autenticación de dos factores.
     *
     * Una vez iniciado sesion en el frontend solo seria un boton para activar la autenticación de dos factores.
     */
    public function enable(): JsonResponse
    {
        $user = auth('api')->user();
        $user->two_factor_enabled = true;
        $user->save();

        return response()->json([
            'status' => true,
            'message' => 'Two-factor authentication has been enabled.'
        ]);
    }

    /**
     * Disable 2FA
     *
     * Endpoint Privado
     *
     * Este método permite a los usuarios desactivar la autenticación de dos factores.
     *
     * Una vez iniciado sesión en el frontend solo seria un boton para desactivar la autenticación de dos factores.
     */
    public function disable(): JsonResponse
    {
        $user = auth('api')->user();
        $user->two_factor_enabled = false;
        $user->two_factor_code = null;
        $user->two_factor_expires_at = null;
        $user->save();

        return response()->json([
            'status' => true,
            'message' => 'Two-factor authentication has been disabled.'
        ]);
    }

    /**
     * Verify 2FA code and emit jwt token.
     *
     * Endpoint Publico
     *
     * Este método se utiliza para verificar el código de autenticación de dos factores ingresado por el usuario.
     *
     * Este seria el paso 2 despues de querer iniciar sesion con usuario y contraseña si ya ejecuto "Enable 2FA" en una sesion anterior.
     */
    public function verify(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'two_factor_code' => 'required|string',
        ]);

        try {
            $encryptedEmail = $request->email;

            $user = User::where('email', $encryptedEmail)->first();

            if (!$user || is_null($user->two_factor_code) || $request->two_factor_code !== $user->two_factor_code) {
                return response()->json(['status' => false, 'message' => 'Invalid two-factor code.'], 422);
            }

            if (now()->gt($user->two_factor_expires_at)) {
                return response()->json(['status' => false, 'message' => 'Two-factor code has expired.'], 422);
            }

            // Limpiamos los campos 2FA después de un uso exitoso
            $user->two_factor_code = null;
            $user->two_factor_expires_at = null;
            $user->save();

            // Emitimos el token de acceso final
            $token = JWTAuth::fromUser($user);

            return response()->json([
                'status' => true,
                'access_token' => $token,
                'token_type' => 'bearer',
                'expires_in' => JWTAuth::factory()->getTTL() * 60,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error verifying two-factor code.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
