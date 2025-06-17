<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\LoginLog;
use App\Models\Pdf;
use App\Models\PdfUserPermission;
use App\Mail\TwoFactorCodeMail;
use App\Mail\EmailVerificationMail;
use App\Mail\AccountDeletionConfirmationMail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    /**
     * User Registration
     *
     * Endpoint Publico
     *
     * Este método permite a los nuevos usuarios registrarse
     *
     * Despues de esto podria ya iniciar sesion directamente sin necesidad de verificar el email -> "User Login".
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
                'password' => Hash::make($request->password),
                'email_verified' => false,
                'email_verification_code' => null,
                'email_verification_expires_at' => null,
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Usuario registrado exitosamente.',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'email_verified' => $user->email_verified,
                ],
                'email_verification_required' => false
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
     * Endpoint Publico
     *
     * Este método permite a los usuarios iniciar sesión
     *
     * Si el usuario tiene activada la autenticación de dos factores, se le enviará un código de verificación por email -> siguiente paso es "Verify 2FA code and emit jwt token"
     *
     * De lo contrario se generará un token JWT y se devolverá al usuario.
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

        // Nota: La verificación de email ahora es opcional, el usuario puede iniciar sesión sin verificar

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
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'email_verified' => $user->email_verified,
            ],
            'email_verification_available' => !$user->email_verified
        ]);
    }

    /**
     * Get user profile
     *
     * Endpoint Privado
     *
     * Este método devuelve la información del usuario autenticado.
     *
     * Incluye el token JWT actual, información del usuario y disponibilidad de verificación de email.
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

            // Obtener el token actual
            $token = JWTAuth::getToken();

            return response()->json([
                'status' => true,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'email_verified' => $user->email_verified,
                    'two_factor_enabled' => $user->two_factor_enabled,
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at,
                ],
                'token' => $token ? $token->get() : null,
                'email_verification_available' => !$user->email_verified
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
     * Endpoint Privado
     *
     * Este método permite a los usuarios cambiar su contraseña.
     *
     * Requiere la contraseña actual para confirmar la identidad antes de establecer la nueva contraseña.
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
     * Endpoint Privado
     *
     * Este método permite a los usuarios cerrar sesión.
     *
     * Invalida el token JWT actual haciendo que ya no sea válido para futuras peticiones autenticadas.
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
     * Endpoint Privado
     *
     * Este método permite a los usuarios verificar su email.
     *
     * Para este paso primero se requiere ejecutar "Request email verification" para enviar un código de verificación al email del usuario.
     */
    public function verifyEmail(Request $request): JsonResponse
    {
        // Verificar si el usuario está autenticado
        $authenticatedUser = null;
        try {
            $authenticatedUser = auth('api')->user();
        } catch (\Exception $e) {
            // El usuario no está autenticado, continuar con validación normal
        }

        // Si el usuario está autenticado, solo requerir el código
        if ($authenticatedUser) {
            $validator = Validator::make($request->all(), [
                'verification_code' => 'required|string',
            ]);
            $email = $authenticatedUser->email;
        } else {
            // Si no está autenticado, requerir email y código
            $validator = Validator::make($request->all(), [
                'email' => 'required|email',
                'verification_code' => 'required|string',
            ]);
            $email = $request->email;
        }

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = User::where('email', $email)->first();

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
                'message' => 'Email verificado exitosamente.',
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
     * Endpoint Publico
     *
     * Este método permite reenviar el código de verificación.
     *
     * Genera un nuevo código de verificación y lo envía por email -> el usuario puede usar "Verify email address" con el nuevo código.
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

    /**
     * Request account deletion
     *
     * Endpoint Privado
     *
     * Este método permite a los usuarios solicitar la eliminación de su cuenta. Requiere que el email esté verificado.
     *
     * Genera un código de confirmación de eliminación y lo envía por email -> siguiente paso es "Confirm and execute account deletion".
     */
    public function requestAccountDeletion(): JsonResponse
    {
        try {
            $user = auth('api')->user();

            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'Usuario no autenticado'
                ], 401);
            }

            // Verificar que el email esté verificado
            if (!$user->email_verified) {
                return response()->json([
                    'status' => false,
                    'message' => 'Debes verificar tu email antes de poder eliminar tu cuenta'
                ], 403);
            }

            // Generar código de confirmación de eliminación
            $deletionCode = rand(100000, 999999);

            // Actualizar usuario con código y expiración
            $user->account_deletion_code = $deletionCode;
            $user->account_deletion_expires_at = now()->addMinutes(30);
            $user->save();

            // Enviar email de confirmación
            try {
                Mail::to($user->email)->send(new AccountDeletionConfirmationMail($deletionCode, $user->name));
            } catch (\Exception $mailException) {
                Log::warning('Error sending account deletion email: ' . $mailException->getMessage());
                return response()->json([
                    'status' => false,
                    'message' => 'Error enviando email de confirmación'
                ], 500);
            }

            return response()->json([
                'status' => true,
                'message' => 'Se ha enviado un código de confirmación a tu email para proceder con la eliminación de tu cuenta'
            ]);
        } catch (\Exception $e) {
            Log::error('Error requesting account deletion: ' . $e->getMessage());
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
     * Este método permite confirmar la eliminación de la cuenta usando el código enviado por email.
     *
     * Verifica el código de eliminación recibido por email y procede a eliminar permanentemente la cuenta y todos los datos relacionados.
     */
    public function confirmAccountDeletion(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'deletion_code' => 'required|string|size:6',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = auth('api')->user();

            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'Usuario no autenticado'
                ], 401);
            }

            // Verificar que el email esté verificado
            if (!$user->email_verified) {
                return response()->json([
                    'status' => false,
                    'message' => 'Debes verificar tu email antes de poder eliminar tu cuenta'
                ], 403);
            }

            // Verificar código de eliminación
            if (!$user->account_deletion_code || $user->account_deletion_code !== $request->deletion_code) {
                return response()->json([
                    'status' => false,
                    'message' => 'Código de confirmación inválido'
                ], 422);
            }

            // Verificar que el código no haya expirado
            if (now()->gt($user->account_deletion_expires_at)) {
                return response()->json([
                    'status' => false,
                    'message' => 'El código de confirmación ha expirado'
                ], 422);
            }

            // Iniciar proceso de eliminación completa
            $this->deleteUserCompletely($user);

            return response()->json([
                'status' => true,
                'message' => 'Tu cuenta ha sido eliminada permanentemente junto con todos tus archivos'
            ]);
        } catch (\Exception $e) {
            Log::error('Error confirming account deletion: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Error confirmando eliminación de cuenta'
            ], 500);
        }
    }

    /**
     * Request email verification
     *
     * Endpoint Privado
     *
     * Este método permite al usuario autenticado solicitar la verificación de su email enviando un código de verificación.
     *
     * Genera un código de verificación y lo envía por email al usuario autenticado -> siguiente paso es "Verify email address" con el código recibido.
     */
    public function requestEmailVerification(): JsonResponse
    {
        try {
            $user = auth('api')->user();

            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'Usuario no autenticado'
                ], 401);
            }

            if ($user->email_verified) {
                return response()->json([
                    'status' => false,
                    'message' => 'Tu email ya está verificado.'
                ], 400);
            }

            // Generar código de verificación de email
            $emailVerificationCode = rand(100000, 999999);
            $user->email_verification_code = $emailVerificationCode;
            $user->email_verification_expires_at = now()->addMinutes(30); // 30 minutos para verificar
            $user->save();

            // Enviar email de verificación
            try {
                Mail::to($user->email)->send(new EmailVerificationMail($emailVerificationCode, $user->name));
            } catch (\Exception $mailException) {
                // Log del error de email pero no fallar la operación
                Log::warning('Error sending verification email: ' . $mailException->getMessage());
                return response()->json([
                    'status' => false,
                    'message' => 'Error enviando email de verificación. Inténtalo de nuevo.'
                ], 500);
            }

            return response()->json([
                'status' => true,
                'message' => 'Se ha enviado un código de verificación a tu email.',
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
                'message' => 'Error solicitando verificación de email',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete user completely including all related data and files
     *
     * Método privado que elimina completamente el usuario y todos sus datos relacionados.
     *
     * Elimina PDFs físicos del storage, permisos de PDFs, logs de login, invalida tokens JWT y finalmente elimina el registro del usuario.
     */
    private function deleteUserCompletely(User $user): void
    {
        try {
            // 1. Obtener todos los PDFs del usuario
            $userPdfs = Pdf::where('user_id', $user->id)->get();

            // 2. Eliminar archivos físicos del storage
            foreach ($userPdfs as $pdf) {
                if ($pdf->path && Storage::exists($pdf->path)) {
                    Storage::delete($pdf->path);
                }

                // También intentar eliminar de la carpeta pública si existe
                $publicPath = public_path('storage/' . $pdf->path);
                if (File::exists($publicPath)) {
                    File::delete($publicPath);
                }
            }

            // 3. Eliminar permisos de PDFs donde el usuario es el receptor
            PdfUserPermission::where('shared_with_user_id', $user->id)->delete();

            // 4. Eliminar permisos de PDFs que pertenecen al usuario (compartidos por él)
            PdfUserPermission::whereHas('pdf', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })->delete();

            // 5. Eliminar PDFs del usuario de la base de datos
            Pdf::where('user_id', $user->id)->delete();

            // 6. Eliminar logs de login del usuario
            $user->loginLogs()->delete();

            // 7. Invalidar token JWT actual si existe
            try {
                $token = JWTAuth::getToken();
                if ($token) {
                    JWTAuth::invalidate($token);
                }
            } catch (JWTException $e) {
                Log::warning('Error invalidating JWT token during account deletion: ' . $e->getMessage());
            }

            // 8. Finalmente eliminar el usuario
            $user->delete();

            Log::info('User account deleted completely', [
                'user_id' => $user->id,
                'email' => $user->email,
                'deleted_at' => now()
            ]);
        } catch (\Exception $e) {
            Log::error('Error in deleteUserCompletely: ' . $e->getMessage(), [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}
