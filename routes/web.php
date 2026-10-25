<?php

use App\Enums\UserRole;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\BatchController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DueDiligenceController;
use App\Http\Controllers\FarmerController;
use App\Http\Controllers\FieldAppController;
use App\Http\Controllers\MillIntakeController;
use App\Http\Controllers\OfficerController;
use App\Http\Controllers\PlotController;
use App\Http\Controllers\SupervisionController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route(auth()->user()->homeRoute())
        : redirect()->route('login');
});

Route::get('/login', [LoginController::class, 'choose'])->name('login');
Route::get('/login/{desk}', [LoginController::class, 'create'])->name('login.desk')->whereIn('desk', ['tcda', 'officer', 'aggregator', 'mill', 'exporter']);
Route::post('/login', [LoginController::class, 'store']);
Route::post('/login/{desk}', [LoginController::class, 'store'])->name('login.desk.store')->whereIn('desk', ['tcda', 'officer', 'aggregator', 'mill', 'exporter']);
Route::get('/field', FieldAppController::class)->name('field');

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::redirect('/dashboard', '/tcda');
    Route::redirect('/dds', '/export');
    Route::get('/farmers', function () {
        return match (auth()->user()->role) {
            UserRole::FieldOfficer => redirect()->route('officer.home'),
            UserRole::Aggregator => redirect()->route('buying.farmers'),
            UserRole::Tcda => redirect()->route('farmers.index'),
            default => abort(403),
        };
    });
    Route::get('/batches', function () {
        return match (auth()->user()->role) {
            UserRole::Aggregator => redirect()->route('buying.home'),
            UserRole::Tcda => redirect()->route('batches.index'),
            default => abort(403),
        };
    });

    Route::middleware('role:tcda')->group(function () {
        Route::get('/tcda', DashboardController::class)->name('dashboard');
        Route::get('/tcda/exceptions', SupervisionController::class)->name('supervision');
        Route::get('/tcda/officers', [OfficerController::class, 'index'])->name('officers.index');
        Route::post('/tcda/officers', [OfficerController::class, 'store'])->name('officers.store');
        Route::put('/tcda/officers/{officer}', [OfficerController::class, 'update'])->name('officers.update');
        Route::delete('/tcda/officers/{officer}', [OfficerController::class, 'destroy'])->name('officers.destroy');
        Route::get('/tcda/farmers', [FarmerController::class, 'index'])->name('farmers.index');
        Route::get('/tcda/farm-sets', [BatchController::class, 'index'])->name('batches.index');
    });

    Route::middleware('role:field_officer')->group(function () {
        Route::get('/officer', [FarmerController::class, 'index'])->name('officer.home');
    });

    Route::middleware('role:aggregator')->group(function () {
        Route::get('/buying', [BatchController::class, 'index'])->name('buying.home');
        Route::get('/buying/farmers', [FarmerController::class, 'index'])->name('buying.farmers');
    });

    Route::middleware('role:tcda,field_officer,aggregator')->group(function () {
        Route::get('/farmers/create', [FarmerController::class, 'create'])->name('farmers.create');
        Route::post('/farmers', [FarmerController::class, 'store'])->name('farmers.store');
        Route::get('/farmers/{farmer}', [FarmerController::class, 'show'])->name('farmers.show');
        Route::post('/farmers/{farmer}/plots', [PlotController::class, 'store'])->name('plots.store');
    });

    Route::middleware('role:tcda,aggregator')->group(function () {
        Route::get('/batches/create', [BatchController::class, 'create'])->name('batches.create');
        Route::post('/batches', [BatchController::class, 'store'])->name('batches.store');
        Route::post('/batches/{batch}/deliveries', [BatchController::class, 'addDelivery'])->name('batches.deliveries');
        Route::post('/batches/{batch}/close', [BatchController::class, 'close'])->name('batches.close');
    });

    Route::middleware('role:tcda,aggregator,mill,exporter')->get('/batches/{batch}', [BatchController::class, 'show'])->name('batches.show');

    Route::middleware('role:mill')->group(function () {
        Route::get('/mill', [MillIntakeController::class, 'index'])->name('mill.index');
        Route::post('/mill/{batch}', [MillIntakeController::class, 'store'])->name('mill.store');
    });

    Route::middleware('role:exporter')->group(function () {
        Route::get('/export', [DueDiligenceController::class, 'index'])->name('dds.index');
        Route::get('/export/{batch}', [DueDiligenceController::class, 'show'])->name('dds.show');
        Route::post('/export/{batch}/export', [DueDiligenceController::class, 'export'])->name('dds.export');
    });
});
