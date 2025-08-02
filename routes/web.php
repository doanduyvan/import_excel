<?php

use Illuminate\Support\Facades\Route;
use App\Services\handleExcel\ImportExcel;
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

Route::get('/', function () {
    $service = new ImportExcel();
    $content = $service->import();
    return $content;
});


Route::get('/info', function(){
$info = phpinfo();
return $info;
});