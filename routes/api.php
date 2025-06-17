<?php

use App\Http\Controllers\Api;
use App\Http\Middleware\IsUserAuth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', [Api::class, 'version']);
Route::get('/routes', [Api::class, 'routes']);


Route::middleware([IsUserAuth::class])->group(function () {
    
});
