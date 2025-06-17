<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\LoginLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Auth;
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
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password)
            ]);

            return response()->json([
                'status' => true,
                'message' => 'User registered successfully',
                'user' => $user
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

        // 2. Verificamos si el 2FA está activado para este usuario
        if ($user->two_factor_enabled) {
            // 3. Si está activado, generamos y guardamos el código de un solo uso
            $user->two_factor_code = rand(100000, 999999); // Código aleatorio de 6 dígitos
            $user->two_factor_expires_at = now()->addMinutes(10); // El código expira en 10 minutos
            $user->save();

            // 4. (Opcional pero recomendado) Enviar el código por email al usuario
            // Mail::to($user->email)->send(new TuClaseDeMailDe2FA($user->two_factor_code));

            // 5. Devolvemos una respuesta indicando que se requiere el segundo factor
            return response()->json([
                'status' => true,
                'message' => 'Se requiere autenticación de dos factores.',
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
}
