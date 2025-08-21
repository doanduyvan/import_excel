<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\SettingsController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
Route::get('/settings', [SettingsController::class, 'index'])->name('settings');


Route::get('/info', function () {
    $info = phpinfo();
    return $info;
});


Route::prefix('ajax')->group(function () {
    Route::get('checkmail', [SettingsController::class, 'checkmail']);
});
