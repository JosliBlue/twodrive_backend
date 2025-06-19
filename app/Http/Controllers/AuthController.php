<?php

namespace App\Http\Controllers;

use App\Models\{User, LoginLog};
use App\Mail\{TwoFactorCodeMail, EmailVerificationMail};
use App\Utils\AESEncryption;
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Facades\{Log, Mail, Validator};
use PHPOpenSourceSaver\JWTAuth\{Exceptions\JWTException, Facades\JWTAuth};

class AuthController extends Controller
{
    private $aes;
    public function __construct()
    {
        $this->aes = new AESEncryption();
    }

    /**
     * User Registration
     *
     * Endpoint Publico
     *
     * Este método permite a los nuevos usuarios registrarse
     * Después de esto podría ya puedes ejecutar el metodo "Login" para iniciar sesión
     */
    public function register(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email|max:255',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Encriptamos el email solo después de validar su formato
        $encryptedEmail = $this->aes->encrypt($request->email);

        // Validamos unicidad del email encriptado
        $uniqueValidator = Validator::make(['email' => $encryptedEmail], [
            'email' => 'unique:users',
        ]);

        if ($uniqueValidator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => ['email' => ['El email ya está registrado']]
            ], 422);
        }

        try {
            $user = User::create([
                'email' => $encryptedEmail,
                'password' => $this->aes->encrypt($request->password)
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Usuario registrado exitosamente',
                'user' => $this->userResponse($user)
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error registrando usuario',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * User Login
     *
     * Endpoint Publico
     *
     * Este método permite a los usuarios iniciar sesión
     *
     * Si en una sesion previa se ejecuto "Enable 2FA" entonces se enviara un codigo de verificacion al email del usuario, y despues ejecutar "Verify 2FA code and emit kwt token"
     *
     * Si no se activo previamente el 2FA, entonces se emitira un token JWT directamente
     */
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::where('email', $this->aes->encrypt($request->email))->first();

        // Verifica si el usuario existe y si la contraseña coincide
        if (!$user || $user->password !== $this->aes->encrypt($request->password)) {
            // Log del intento fallido
            if ($attemptUser = User::where('email', $this->aes->encrypt($request->email))->first()) {
                $this->logLoginAttempt($attemptUser, $request, 'failed');
            }
            return response()->json([
                'status' => false,
                'message' => 'Credenciales incorrectas'
            ], 401);
        }

        if ($user->two_factor_enabled) {
            $code = rand(100000, 999999); // Genera un código aleatorio de 6 dígitos
            $user->update([
                'two_factor_code' => $this->aes->encrypt($code),
                'two_factor_expires_at' => now()->addMinutes(10)
            ]);

            try {
                Mail::to($this->aes->decrypt($user->email))->send(new TwoFactorCodeMail($code, $this->aes->decrypt($user->email)));
            } catch (\Exception $e) {
                Log::warning('Error enviando código de verificación 2FA: ' . $e->getMessage());
                return response()->json([
                    'status' => false,
                    'message' => 'Error enviando código de verificación'
                ], 500);
            }

            return response()->json([
                'status' => true,
                'message' => 'Código de verificación enviado',
                'two_factor_required' => true,
            ]);
        }

        try {
            $token = JWTAuth::fromUser($user);
            $this->logLoginAttempt($user, $request, 'success');

            return response()->json([
                'status' => true,
                'token' => $token,
                'user' => $this->userResponse($user)
            ]);
        } catch (JWTException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error generando token'
            ], 500);
        }
    }

    /**
     * User Logout
     *
     * Endpoint Privado
     *
     * Este método permite a los usuarios cerrar sesión.
     */
    public function logout(): JsonResponse
    {
        try {
            $token = JWTAuth::getToken();
            JWTAuth::invalidate($token);
            return response()->json([
                'status' => true,
                'message' => 'Sesión cerrada exitosamente'
            ]);
        } catch (JWTException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error cerrando sesión'
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
    private function logLoginAttempt(User $user, Request $request, string $status): void
    {
        LoginLog::create([
            'user_id' => $user->id,
            'ip_address' => $this->aes->encrypt($request->ip()),
            'user_agent' => $request->userAgent(),
            'status' => $this->aes->encrypt($status),
            'logged_in_at' => now(),
        ]);
    }
}
