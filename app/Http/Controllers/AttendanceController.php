<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AttendanceController extends Controller
{
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
