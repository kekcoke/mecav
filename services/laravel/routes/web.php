<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DiagramController;
use Illuminate\Support\Facades\Route;

// Temporary auth routes for development
Route::get('/login', fn() => view('auth.login'))->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::get('/', function () {
    return auth()->check() ? redirect('/diagrams') : redirect('/login');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/diagrams',           [DiagramController::class, 'index'])->name('diagrams.index');
    Route::get('/diagrams/create',    fn() => view('diagrams.create'))->name('diagrams.create');
    Route::get('/diagrams/{diagram}', fn($d) => view('diagrams.editor', ['diagram' => $d]))->name('diagrams.show');
});
