<?php

use App\Http\Controllers\_Api;
use App\Http\Controllers\_CifradoAES;
use App\Http\Middleware\IsUserAuth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', [_Api::class, 'version']);
Route::get('/routes', [_Api::class, 'routes']);

// Rutas de cifrado AES (públicas para facilitar las pruebas)
Route::prefix('cifrado')->group(function () {
    Route::post('/cifrar', [_CifradoAES::class, 'cifrar']);
    Route::post('/descifrar', [_CifradoAES::class, 'descifrar']);
    Route::get('/info', [_CifradoAES::class, 'info']);
});

Route::middleware([IsUserAuth::class])->group(function () {});
