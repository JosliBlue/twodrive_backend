<?php

use App\Http\Middleware\IsUserAuth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


Route::middleware([IsUserAuth::class])->group(function () {
    
});
