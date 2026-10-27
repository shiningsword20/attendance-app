<?php

namespace App\Http\Controllers;

use App\Models\AttendanceRecord;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminAttendanceController extends Controller
{
    public function index(Request $request)
    {
        // 管理者以外は見られないようにする
        abort_unless(Auth::user()->admin_status, 403);

        // ?date=Y-m-d があればその日、無ければ今日を表示する
        $date = $request->filled('date')
            ? Carbon::createFromFormat('!Y-m-d', $request->input('date'))
            : Carbon::today();

        $previousDay = $date->copy()->subDay()->format('Y-m-d');
        $nextDay = $date->copy()->addDay()->format('Y-m-d');

        // 一般ユーザー全員と、その日の勤怠（休憩つき）を取得する
        $users = User::where('admin_status', false)->orderBy('id')->get();

        $attendanceRecords = AttendanceRecord::with('breaks')
            ->where('date', $date->toDateString())
            ->get();

        return view('admin.admin-attendance-list', compact('date', 'previousDay', 'nextDay', 'users', 'attendanceRecords'));
    }

    public function show($id)
    {
        // 管理者以外は見られないようにする
        abort_unless(Auth::user()->admin_status, 403);

        $record = AttendanceRecord::with(['user', 'breaks'])->findOrFail($id);

        // 名前欄には、勤怠の持ち主（一般ユーザー）の名前を表示する
        $user = $record->user;

        // Bladeで使う形に整える
        $attendanceRecord = [
            'id' => $record->id,
            'year' => $record->date->format('Y年'),
            'date' => $record->date->format('n月j日'),
            'clock_in' => $this->formatTime($record->clock_in),
            'clock_out' => $this->formatTime($record->clock_out),
            'breaks' => $record->breaks->map(fn ($break) => [
                'break_in' => $this->formatTime($break->break_in),
                'break_out' => $this->formatTime($break->break_out),
            ])->values()->all(),
            'comment' => $record->comment,
        ];

        return view('admin.admin-detail', compact('user', 'attendanceRecord'));
    }

    private function formatTime($time)
    {
        return $time ? Carbon::parse($time)->format('H:i') : '';
    }
}
