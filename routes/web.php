<?php

use Illuminate\Support\Facades\Route;

$rootStatus = static function () {
    return response()->json([
        'success' => true,
        'message' => 'Reserva Escolar API V2 online.',
        'data' => [
            'service' => 'reserva_escolar_api',
            'status' => 'ok',
        ],
    ]);
};

Route::get('/', $rootStatus)->name('api.root');
Route::get('/index.php', $rootStatus)->name('legacy.index');
