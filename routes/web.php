<?php

use App\Http\Controllers\AdminAttendanceController;
use App\Http\Controllers\ApplicationController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\StaffController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Http\Controllers\AuthenticatedSessionController;

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
    // 管理者：スタッフ別月次勤怠
    Route::get('/admin/attendance/staff/{id}', [StaffController::class, 'show']);
    // 管理者：修正申請の詳細・承認
    Route::get('/stamp_correction_request/approve/{attendance_correct_request_id}', [ApplicationController::class, 'show']);
    Route::post('/stamp_correction_request/approve/{attendance_correct_request_id}', [ApplicationController::class, 'approve']);
    // 提供Bladeのリンク先から、画面設計の勤怠詳細へ転送する
    Route::get('/attendance/{id}', [AttendanceController::class, 'redirectToDetail'])->whereNumber('id');
    // 提供Bladeのリンク先から、申請対象の勤怠詳細へ転送する
    Route::get('/application/{id}', [ApplicationController::class, 'redirectToAttendance'])->whereNumber('id');
});
