<?php

use App\Http\Controllers\_Api;
use App\Http\Controllers\_CifradoAES;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AuthTwoFactorController;
use App\Http\Middleware\IsUserAuth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', [_Api::class, 'version']);
Route::get('/routes', [_Api::class, 'routes']);

// Rutas de autenticación (públicas)
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/2fa/verify-two-factor', [AuthTwoFactorController::class, 'verify']);
});

// Rutas de cifrado AES (públicas para facilitar las pruebas)
Route::prefix('cifrado')->group(function () {
    Route::post('/cifrar', [_CifradoAES::class, 'cifrar']);
    Route::post('/descifrar', [_CifradoAES::class, 'descifrar']);
    Route::get('/info', [_CifradoAES::class, 'info']);
});

Route::middleware([IsUserAuth::class])->group(function () {
    // Rutas de autenticación protegidas
    Route::prefix('auth')->group(function () {
        Route::get('/profile', [AuthController::class, 'profile']);
        Route::put('/change-password', [AuthController::class, 'changePassword']);
        Route::post('/logout', [AuthController::class, 'logout']);

        // Rutas de Two Factor Authentication
        Route::post('/2fa/enable', [AuthTwoFactorController::class, 'enable']);
        Route::post('/2fa/disable', [AuthTwoFactorController::class, 'disable']);
    });
});
