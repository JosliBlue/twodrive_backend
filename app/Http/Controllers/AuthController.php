<?php

namespace App\Http\Controllers;

use App\Models\{User, LoginLog, PdfUserPermission};
use App\Mail\{TwoFactorCodeMail, EmailVerificationMail, AccountDeletionConfirmationMail};
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
        // solo se encripta el email para validacion de email unico ya encriotado
        $request->merge(['email' => $this->aes->encrypt($request->email)]);

        $validator = Validator::make($request->all(), [
            'email' => 'required|max:255|unique:users',
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
                'email' => $request->email,
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
            $code = $this->generateRandomCode();
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
     * Get user profile
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

            if ($this->aes->encrypt($request->current_password) !== $user->password) {
                return response()->json([
                    'status' => false,
                    'message' => 'Contraseña actual ingresada incorrecta'
                ], 422);
            }

            $user->update(['password' => $this->aes->encrypt($request->new_password)]);

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
     * User Logout
     *
     * Endpoint Privado
     *
     * Este método permite a los usuarios cerrar sesión.
     */
    public function logout(): JsonResponse
    {
        try {
            if (!$token = JWTAuth::getToken()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Token no proporcionado'
                ], 401);
            }

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

            $code = $this->generateRandomCode();
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
     * Endpoint Publico
     *
     * Antes ejecutar "Request Email Verification" para enviar el codigo de verificacion al email del usuario
     *
     * Despues ejecutar "Verify Email Address" para verificar el email con el codigo enviado
     *
     * Este método permite reenviar el código de verificación
     *
     * Este método es útil si el usuario no recibió el email de verificación o si el código ha expirado
     *
     * El usuario debe proporcionar su email para recibir el código de verificación
     */
    public function resendEmailVerification(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = User::where('email', $this->aes->encrypt($request->email))->first();

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

            $code = $this->generateRandomCode();
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

            $code = $this->generateRandomCode();
            $user->update([
                'account_deletion_code' => $this->aes->encrypt($code),
                'account_deletion_expires_at' => now()->addMinutes(30)
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

    /**
     * ####################   METODOS PRIVADOS DE MI CONTROLADOR SIN RUTAS   ####################
     */
    private function generateRandomCode(): int
    {
        return rand(100000, 999999);
    }
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
