<?php

use App\Http\Controllers\ResultsController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::view('/courier', 'dashboard.courier')->name('courier');
Route::get('/results/{run}', [ResultsController::class, 'show'])->name('results.show');
Route::get('/demo/control', function () {
    $configuredToken = trim((string) config('courier.demo.access_token', ''));
    $providedToken = (string) request()->header('X-Demo-Token', request()->query('token', ''));
    $authorized = app()->environment(['local', 'testing'])
        || ($configuredToken !== '' && hash_equals($configuredToken, $providedToken));
    abort_unless($authorized, 404);

    return view('demo.control');
})->name('demo.control');
