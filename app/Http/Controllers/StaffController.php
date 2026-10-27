<?php

namespace App\Http\Controllers;

use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
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

    public function show(Request $request, $id)
    {
        // 管理者以外は見られないようにする
        abort_unless(Auth::user()->admin_status, 403);

        // 表示するスタッフ（一般ユーザー）
        $user = User::where('admin_status', false)->findOrFail($id);

        // ?date=Y-m があればその月、無ければ今月を表示する
        $date = $request->filled('date')
            ? Carbon::createFromFormat('!Y-m', $request->input('date'))
            : Carbon::now()->startOfMonth();

        $previousMonth = $date->copy()->subMonth()->format('Y-m');
        $nextMonth = $date->copy()->addMonth()->format('Y-m');

        // その月の勤怠を日付で引けるようにしておく
        $attendanceRecords = $user->attendanceRecords()
            ->with('breaks')
            ->whereBetween('date', [$date->copy()->startOfMonth()->toDateString(), $date->copy()->endOfMonth()->toDateString()])
            ->get()
            ->keyBy(fn ($record) => $record->date->toDateString());

        // 月の全日を並べ、勤怠が無い日は空欄にする
        $formattedAttendanceRecords = collect(CarbonPeriod::create($date->copy()->startOfMonth(), $date->copy()->endOfMonth()))
            ->map(function ($day) use ($attendanceRecords) {
                $record = $attendanceRecords->get($day->toDateString());

                return [
                    'id' => $record?->id,
                    'date' => $day->locale('ja')->isoFormat('MM/DD(ddd)'),
                    'clock_in' => $record ? Carbon::parse($record->clock_in)->format('H:i') : '',
                    'clock_out' => $record && $record->clock_out ? Carbon::parse($record->clock_out)->format('H:i') : '',
                    'total_break_time' => $record?->total_break_time,
                    'total_time' => $record?->total_time,
                ];
            });

        return view('admin.staff-attendance-list', compact('user', 'date', 'previousMonth', 'nextMonth', 'formattedAttendanceRecords'));
    }
}
