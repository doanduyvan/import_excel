<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SaleController;

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
Route::get('/detailbyinvoice', [SaleController::class, 'DetailbyinvoiceView'])->name('detailbyinvoice');

Route::get('/info', function () {
    $info = phpinfo();
    return $info;
});


Route::prefix('ajax')->group(function () {
    Route::get('checkmail', [SettingsController::class, 'checkmail']);
    Route::get('detailbyinvoice', [SaleController::class, 'Detailbyinvoice']);
});


// Route::get('/check-imap', function () {
//     $hostname = '{mail.odinn.site:143/imap/notls}INBOX';
//     $username = "mail@odinn.site";
//     $password = "o^yer9]KsD61V@oB";
//     $inbox = @imap_open($hostname, $username, $password);
//     if (!$inbox) {
//         dd(imap_last_error());
//     } else {
//         echo "Connected successfully to IMAP server.";
//         imap_close($inbox);
//     }
// });
