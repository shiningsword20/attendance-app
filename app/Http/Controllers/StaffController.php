<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

class StaffController extends Controller
{
    public function index()
    {
        // 管理者以外は見られないようにする
        abort_unless(Auth::user()->admin_status, 403);

        // 一般ユーザー全員の名前とメールアドレスを表示する
        $users = User::where('admin_status', false)->orderBy('id')->get();

        return view('admin.staff-list', compact('users'));
    }
}
