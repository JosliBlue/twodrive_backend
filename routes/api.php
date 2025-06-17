<?php

use App\Http\Controllers\Api;
use App\Http\Controllers\CifradoAES;
use App\Http\Middleware\IsUserAuth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', [Api::class, 'version']);
Route::get('/routes', [Api::class, 'routes']);

// Rutas de cifrado AES (públicas para facilitar las pruebas)
Route::prefix('cifrado')->group(function () {
    Route::post('/cifrar', [CifradoAES::class, 'cifrar']);
    Route::post('/descifrar', [CifradoAES::class, 'descifrar']);
    Route::get('/info', [CifradoAES::class, 'info']);
});

Route::middleware([IsUserAuth::class])->group(function () {});
