<?php

use App\Http\Controllers\TestController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:api');

Route::get('frank', function() {
    $time = microtime(true);
    Log::debug("Request $time");

    return response()->json([
        'data' => 'ok',
        'time' => $time
    ]);
});

Route::get('test', [TestController::class, 'test']);