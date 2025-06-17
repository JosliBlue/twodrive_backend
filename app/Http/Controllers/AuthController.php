<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\LoginLog;
use App\Mail\TwoFactorCodeMail;
use App\Mail\EmailVerificationMail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    /**
     * User registration
     *
     * Este método es público y permite a los nuevos usuarios registrarse
     */
    public function register(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Generar código de verificación de email
            $emailVerificationCode = rand(100000, 999999);

            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'email_verified' => false,
                'email_verification_code' => $emailVerificationCode,
                'email_verification_expires_at' => now()->addMinutes(30), // 30 minutos para verificar
            ]);

            // Enviar email de verificación
            try {
                Mail::to($user->email)->send(new EmailVerificationMail($emailVerificationCode, $user->name));
            } catch (\Exception $mailException) {
                // Log del error de email pero no fallar el registro
                Log::warning('Error sending verification email: ' . $mailException->getMessage());
            }

            return response()->json([
                'status' => true,
                'message' => 'Usuario registrado exitosamente. Revisa tu email para verificar tu cuenta.',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'email_verified' => $user->email_verified,
                ],
                'email_verification_required' => true
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error registering user',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * User Login
     *
     * Este método es público y permite a los usuarios iniciar sesión
     */
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $credentials = $request->only('email', 'password');

        // 1. Validamos las credenciales primero sin crear una sesión/token
        if (!Auth::validate($credentials)) {
            // Opcional: Log del intento de login fallido
            $user = User::where('email', $request->email)->first();
            if ($user) {
                LoginLog::create([
                    'user_id' => $user->id,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'status' => 'failed',
                    'logged_in_at' => now(),
                ]);
            }

            return response()->json([
                'status' => false,
                'message' => 'Las credenciales proporcionadas son incorrectas.'
            ], 401);
        }

        // Si las credenciales son correctas, obtenemos el usuario
        $user = User::where('email', $request->email)->first();

        // Verificar si el email está verificado
        if (!$user->email_verified) {
            return response()->json([
                'status' => false,
                'message' => 'Debes verificar tu email antes de iniciar sesión.',
                'email_verification_required' => true
            ], 403);
        }

        // 2. Verificamos si el 2FA está activado para este usuario
        if ($user->two_factor_enabled) {
            // 3. Si está activado, generamos y guardamos el código de un solo uso
            $user->two_factor_code = rand(100000, 999999); // Código aleatorio de 6 dígitos
            $user->two_factor_expires_at = now()->addMinutes(10); // El código expira en 10 minutos
            $user->save();

            // 4. Enviar el código por email al usuario
            try {
                Mail::to($user->email)->send(new TwoFactorCodeMail($user->two_factor_code, $user->name));
            } catch (\Exception $mailException) {
                Log::warning('Error sending 2FA email: ' . $mailException->getMessage());
                return response()->json([
                    'status' => false,
                    'message' => 'Error enviando código de verificación. Inténtalo de nuevo.'
                ], 500);
            }

            // 5. Devolvemos una respuesta indicando que se requiere el segundo factor
            return response()->json([
                'status' => true,
                'message' => 'Se ha enviado un código de verificación a tu email.',
                'two_factor_required' => true, // Esta bandera es clave para tu frontend
            ]);
        }

        // 6. Si 2FA no está activado, generamos el token JWT y procedemos como antes
        try {
            $token = JWTAuth::fromUser($user);

            // Log del inicio de sesión exitoso
            LoginLog::create([
                'user_id' => $user->id,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'status' => 'success',
                'logged_in_at' => now(),
            ]);
        } catch (JWTException $e) {
            return response()->json([
                'status' => false,
                'message' => 'No se pudo generar el token.'
            ], 500);
        }

        // Devolvemos la respuesta con el token de acceso final
        return response()->json([
            'status' => true,
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => JWTAuth::factory()->getTTL() * 60, // Expiración en segundos
            'two_factor_required' => false,
        ]);
    }

    /**
     * Get user profile
     *
     * Este método debe estar protegido por el middleware 'IsUserAuth' y devuelve la información del usuario autenticado.
     */
    public function profile(): JsonResponse
    {
        try {
            $user = auth('api')->user();

            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            return response()->json([
                'status' => true,
                'user' => $user
            ]);
        } catch (JWTException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error getting user'
            ], 500);
        }
    }

    /**
     * Change password
     *
     * Este método debe estar protegido por el middleware 'IsUserAuth' y permite a los usuarios cambiar su contraseña.
     */
    public function changePassword(Request $request): JsonResponse
    {
        try {
            $user = auth('api')->user();

            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

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

            if (!Hash::check($request->current_password, $user->password)) {
                return response()->json([
                    'status' => false,
                    'message' => 'Current password is incorrect'
                ], 422);
            }

            $user->update([
                'password' => Hash::make($request->new_password)
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Password changed successfully'
            ]);
        } catch (JWTException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error changing password'
            ], 500);
        }
    }

    /**
     * User Logout
     *
     * Este método debe estar protegido por el middleware 'IsUserAuth' y permite a los usuarios cerrar sesión.
     */
    public function logout(): JsonResponse
    {
        try {
            $token = JWTAuth::getToken();

            if (!$token) {
                return response()->json([
                    'status' => false,
                    'message' => 'Token not provided'
                ], 401);
            }

            JWTAuth::invalidate($token);

            return response()->json([
                'status' => true,
                'message' => 'Successfully logged out'
            ]);
        } catch (JWTException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error logging out'
            ], 500);
        }
    }

    /**
     * Verify email address
     *
     * Este método es público y permite a los usuarios verificar su dirección de email
     */
    public function verifyEmail(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'verification_code' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = User::where('email', $request->email)->first();

            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'Usuario no encontrado.'
                ], 404);
            }

            if ($user->email_verified) {
                return response()->json([
                    'status' => false,
                    'message' => 'El email ya está verificado.'
                ], 400);
            }

            if (!$user->email_verification_code || $user->email_verification_code !== $request->verification_code) {
                return response()->json([
                    'status' => false,
                    'message' => 'Código de verificación inválido.'
                ], 422);
            }

            if (now()->gt($user->email_verification_expires_at)) {
                return response()->json([
                    'status' => false,
                    'message' => 'El código de verificación ha expirado.'
                ], 422);
            }

            // Verificar el email
            $user->email_verified = true;
            $user->email_verification_code = null;
            $user->email_verification_expires_at = null;
            $user->save();

            return response()->json([
                'status' => true,
                'message' => 'Email verificado exitosamente. Ahora puedes iniciar sesión.',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'email_verified' => $user->email_verified,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error verificando email.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Resend email verification code
     *
     * Este método es público y permite reenviar el código de verificación
     */
    public function resendEmailVerification(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = User::where('email', $request->email)->first();

            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'Usuario no encontrado.'
                ], 404);
            }

            if ($user->email_verified) {
                return response()->json([
                    'status' => false,
                    'message' => 'El email ya está verificado.'
                ], 400);
            }

            // Generar nuevo código
            $emailVerificationCode = rand(100000, 999999);
            $user->email_verification_code = $emailVerificationCode;
            $user->email_verification_expires_at = now()->addMinutes(30);
            $user->save();

            // Enviar email
            try {
                Mail::to($user->email)->send(new EmailVerificationMail($emailVerificationCode, $user->name));
            } catch (\Exception $mailException) {
                Log::warning('Error sending verification email: ' . $mailException->getMessage());
                return response()->json([
                    'status' => false,
                    'message' => 'Error enviando email de verificación.'
                ], 500);
            }

            return response()->json([
                'status' => true,
                'message' => 'Código de verificación reenviado exitosamente.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error reenviando código de verificación.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
