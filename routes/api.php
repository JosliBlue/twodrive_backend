<?php

use App\Http\Controllers\_Api;
use App\Http\Controllers\_CifradoAES;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AuthTwoFactorController;
use App\Http\Middleware\IsUserAuth;
use Illuminate\Support\Facades\Route;

// Rutas de autenticación (públicas)
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/resend-email-verification', [AuthController::class, 'resendEmailVerification']);
    Route::post('/2fa/verify-two-factor', [AuthTwoFactorController::class, 'verify']);
});

Route::middleware([IsUserAuth::class])->group(function () {
    // Rutas de autenticación protegidas
    Route::prefix('auth')->group(function () {
        Route::get('/profile', [AuthController::class, 'profile']);
        Route::post('/request-email-verification', [AuthController::class, 'requestEmailVerification']);
        Route::post('/verify-email-authenticated', [AuthController::class, 'verifyEmail']);
        Route::put('/change-password', [AuthController::class, 'changePassword']);
        Route::post('/logout', [AuthController::class, 'logout']);

        // Rutas de eliminación de cuenta
        Route::post('/request-account-deletion', [AuthController::class, 'requestAccountDeletion']);
        Route::post('/confirm-account-deletion', [AuthController::class, 'confirmAccountDeletion']);

        // Rutas de Two Factor Authentication
        Route::post('/2fa/enable', [AuthTwoFactorController::class, 'enable']);
        Route::post('/2fa/disable', [AuthTwoFactorController::class, 'disable']);
    });
});

// RUTAS API DE INFORMACION
Route::get('/', [_Api::class, 'version']);
Route::get('/routes', [_Api::class, 'routes']);

// RUTAS API DE TESTING (solo disponibles en entorno de desarrollo)
if (app()->environment('local')) {
    Route::prefix('cifrado')->group(function () {
        Route::post('/cifrar', [_CifradoAES::class, 'cifrar']);
        Route::post('/descifrar', [_CifradoAES::class, 'descifrar']);
        Route::get('/info', [_CifradoAES::class, 'info']);
    });
}
