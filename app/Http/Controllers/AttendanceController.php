<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AttendanceController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();

        $date = $request->filled('date')
            ? Carbon::createFromFormat('!Y-m', $request->input('date'))
            : Carbon::now()->startOfMonth();

        $previousMonth = $date->copy()->subMonth()->format('Y-m');
        $nextMonth = $date->copy()->addMonth()->format('Y-m');

        $attendanceRecords = $user->attendanceRecords()
            ->with('breaks')
            ->whereBetween('date', [$date->copy()->startOfMonth()->toDateString(), $date->copy()->endOfMonth()->toDateString()])
            ->get()
            ->keyBy(fn($record) => $record->date->toDateString());

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

        return view('user.user-attendance-list', compact('date', 'previousMonth', 'nextMonth', 'formattedAttendanceRecords'));
    }

    public function create()
    {
        $user = Auth::user();
        $now = Carbon::now()->locale('ja');

        $formattedDate = $now->isoFormat('YYYY年M月D日(ddd)');
        $formattedTime = $now->format('H:i');

        return view('user.attendance-register', compact('user', 'formattedDate', 'formattedTime'));
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        $now = Carbon::now();
        $status = $user->attendance_status;
        $action = $request->input('action');

        if ($action === 'clock_in' && $status === '勤務外') {
            $user->attendanceRecords()->create([
                'date' => $now->toDateString(),
                'clock_in' => $now->format('H:i:s'),
            ]);
        }

        if ($action === 'break_in' && $status === '出勤中') {
            $user->todayAttendanceRecord()->breaks()->create([
                'break_in' => $now->format('H:i:s'),
            ]);
        }

        if ($action === 'break_out' && $status === '休憩中') {
            $user->todayAttendanceRecord()->breaks()->whereNull('break_out')->update([
                'break_out' => $now->format('H:i:s'),
            ]);
        }

        if ($action === 'clock_out' && $status === '出勤中') {
            $user->todayAttendanceRecord()->update([
                'clock_out' => $now->format('H:i:s'),
            ]);
        }

        return redirect('/attendance');
    }
}
