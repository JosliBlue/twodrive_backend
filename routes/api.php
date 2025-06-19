<?php

use App\Http\Controllers\_Api;
use App\Http\Controllers\_CifradoAES;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AuthTwoFactorController;
use App\Http\Controllers\DeleteAccountController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VerifyAccountController;
use App\Http\Middleware\IsUserAuth;
use Illuminate\Support\Facades\Route;

// =============================================================================
// RUTAS PÚBLICAS
// =============================================================================

// Rutas de autenticación (públicas)
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
});
Route::post('/2fa/verify-two-factor', [AuthTwoFactorController::class, 'verify']);

// =============================================================================
// RUTAS PROTEGIDAS (REQUIEREN AUTENTICACIÓN)
// =============================================================================

Route::middleware([IsUserAuth::class])->group(function () {

    Route::get('auth/logout', [AuthController::class, 'logout']);

    // Rutas de usuario
    Route::prefix('user')->group(function () {
        Route::get('/profile', [UserController::class, 'profile']);
        Route::put('/change-password', [UserController::class, 'changePassword']);
    });

    // Rutas de verificación de cuenta
    Route::prefix('account')->group(function () {
        Route::get('/request-email-verification', [VerifyAccountController::class, 'requestEmailVerification']);
        Route::post('/verify-email', [VerifyAccountController::class, 'verifyEmail']);

        // Rutas de eliminación de cuenta
        Route::get('/request-deletion', [DeleteAccountController::class, 'requestAccountDeletion']);
        Route::post('/confirm-deletion', [DeleteAccountController::class, 'confirmAccountDeletion']);
    });

    // Rutas de Two Factor Authentication
    Route::prefix('2fa')->group(function () {
        Route::get('/enable', [AuthTwoFactorController::class, 'enable']);
        Route::get('/disable', [AuthTwoFactorController::class, 'disable']);
    });
});

// =============================================================================
// RUTAS DE INFORMACIÓN
// =============================================================================
Route::get('/', [_Api::class, 'version']);
Route::get('/routes', [_Api::class, 'routes']);

// =============================================================================
// RUTAS DE TESTING Y UTILIDADES (SOLO ENTORNO DE DESARROLLO)
// =============================================================================
if (app()->environment('local')) {
    Route::prefix('cifrado')->group(function () {
        Route::post('/cifrar', [_CifradoAES::class, 'cifrar']);
        Route::post('/descifrar', [_CifradoAES::class, 'descifrar']);
        Route::get('/info', [_CifradoAES::class, 'info']);
    });
}
