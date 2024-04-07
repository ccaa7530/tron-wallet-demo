<?php

use App\Http\Controllers\LoginController;
use App\Http\Controllers\WalletController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('welcome');

Route::controller(LoginController::class)->group(function() {

    Route::get('/register', 'registerPage')->name('register');
    Route::get('/login', 'loginPage')->name('login');

    Route::post('/register', 'register');
    Route::post('/createTestUser', 'testUser')->name('createTestUser');
    Route::post('/authenticate', 'authenticate')->name('authenticate');
    Route::post('/logout', 'logout')->name('logout');

});


Route::controller(WalletController::class)->name('wallet.')->prefix('wallet')->group(function() {

    Route::get('/list', 'list')->name('list');

    Route::get('/detail/{address}', 'detail')->name('detail');

    Route::post('/create', 'createWallet')->name('create');

    Route::post('/activeAccount', 'activeAccount')->name('activeAccount');

    Route::post('/transfer', 'transfer')->name('transfer');

});
