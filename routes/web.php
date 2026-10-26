<?php

use App\Http\Controllers\AttendanceController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Http\Controllers\AuthenticatedSessionController;
use App\Http\Controllers\ApplicationController;
use App\Http\Controllers\AdminAttendanceController;
use App\Http\Controllers\StaffController;

// HOMEにアクセスしたらログイン画面表示
Route::redirect('/', '/login');

// 管理者ログイン
Route::middleware('guest')->group(function () {
    Route::get('/admin/login', [AuthenticatedSessionController::class, 'create']);
    Route::post('/admin/login', [AuthenticatedSessionController::class, 'store']);
});

// ログイン済みユーザー処理
Route::middleware('auth')->group(function () {
    Route::post('/admin/logout', [AuthenticatedSessionController::class, 'destroy']);
    Route::get('/attendance', [AttendanceController::class, 'create']);
    Route::post('/attendance', [AttendanceController::class, 'store']);
    Route::get('/attendance/list', [AttendanceController::class, 'index']);
    Route::get('/attendance/detail/{id}', [AttendanceController::class, 'show']);
    Route::post('/attendance/{id}', [AttendanceController::class, 'update']);
    Route::get('/stamp_correction_request/list', [ApplicationController::class, 'index']);
    // 管理者：日次勤怠一覧
    Route::get('/admin/attendance/list', [AdminAttendanceController::class, 'index']);
    // 管理者：勤怠詳細
    Route::get('/admin/attendance/{id}', [AdminAttendanceController::class, 'show']);
    // 管理者：スタッフ一覧
    Route::get('/admin/staff/list', [StaffController::class, 'index']);
});
