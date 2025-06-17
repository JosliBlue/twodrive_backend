<?php

// app/Http/Controllers/AuthTwoFactorController.php

namespace App\Http\Controllers;

use App\Models\User;
use App\Utils\AESEncryption;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class AuthTwoFactorController extends Controller
{
    /**
     * Activa el 2FA
     *
     * Este método debe estar protegido por el middleware 'IsUserAuth' y permite a los usuarios activar la autenticación de dos factores.
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
     * Desactiva el 2FA
     *
     * Este método también debe estar protegido por el middleware 'IsUserAuth' y permite a los usuarios desactivar la autenticación de dos factores.
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
     * Verifica el código 2FA y emite el token de acceso final.
     *
     * Este método es público y se utiliza para verificar el código de autenticación de dos factores ingresado por el usuario.
     */
    public function verify(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'two_factor_code' => 'required|string',
        ]);

        try {
            $aesEncryption = new AESEncryption();
            $encryptedEmail = $aesEncryption->encrypt($request->email);

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
                    'name' => $aesEncryption->decrypt($user->name),
                    'email' => $aesEncryption->decrypt($user->email),
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
