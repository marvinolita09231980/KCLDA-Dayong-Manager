<?php

use App\Http\Controllers\DataToolsController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/admin');
});

Route::middleware('auth')->prefix('admin/data-tools')->name('tools.')->controller(DataToolsController::class)->group(function () {
    Route::get('/template', 'template')->name('template');
    Route::post('/export', 'export')->name('export');
    Route::post('/import-members', 'importMembers')->name('import-members');
    Route::post('/import-windows', 'importWindows')->name('import-windows');
    Route::post('/backup', 'backup')->name('backup');
});
