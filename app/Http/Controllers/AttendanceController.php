<?php

namespace App\Http\Controllers;

use App\Http\Requests\AttendanceCorrectionRequest;
use App\Models\AttendanceRecord;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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

    public function show($id)
    {
        $user = Auth::user();

        $attendanceRecord = $user->attendanceRecords()
            ->with(['breaks', 'applications.applicationBreaks'])
            ->findOrFail($id);

        $pendingApplication = $attendanceRecord->applications->firstWhere('is_approved', false);

        // 承認待ちの申請があれば申請内容を、無ければ勤怠の内容を表示する
        $source = $pendingApplication ?? $attendanceRecord;
        $breaks = $pendingApplication ? $pendingApplication->applicationBreaks : $attendanceRecord->breaks;

        $data = [
            'id' => $attendanceRecord->id,
            'application' => $pendingApplication,
            'year' => $attendanceRecord->date->format('Y年'),
            'date' => $attendanceRecord->date->format('n月j日'),
            'clock_in' => $this->formatTime($source->clock_in),
            'clock_out' => $this->formatTime($source->clock_out),
            'breaks' => $breaks->map(fn($break) => [
                'break_in' => $this->formatTime($break->break_in),
                'break_out' => $this->formatTime($break->break_out),
            ])->values()->all(),
            'comment' => $source->comment,
        ];

        return view('user.user-detail', compact('user', 'data'));
    }

    public function update(AttendanceCorrectionRequest $request, $id)
    {
        $user = Auth::user();

        // 入力された休憩のうち、開始・終了がそろっているものだけを使う
        $breaks = collect($request->input('new_break_in', []))
            ->map(fn($breakIn, $index) => [
                'break_in' => $breakIn,
                'break_out' => $request->input("new_break_out.{$index}"),
            ])
            ->filter(fn($break) => $break['break_in'] && $break['break_out'])
            ->values();

        // 管理者：勤怠を直接修正
        if ($user->admin_status) {
            $attendanceRecord = AttendanceRecord::findOrFail($id);

            DB::transaction(function () use ($attendanceRecord, $request, $breaks) {
                $attendanceRecord->update([
                    'clock_in' => $request->input('new_clock_in'),
                    'clock_out' => $request->input('new_clock_out'),
                    'comment' => $request->input('comment'),
                ]);

                $attendanceRecord->breaks()->delete();
                $attendanceRecord->breaks()->createMany($breaks->all());
            });

            return redirect("/admin/attendance/{$attendanceRecord->id}");
        }

        // 一般ユーザー：修正申請を作成
        $attendanceRecord = $user->attendanceRecords()->findOrFail($id);

        if ($attendanceRecord->applications()->where('is_approved', false)->exists()) {
            return redirect("/attendance/detail/{$attendanceRecord->id}");
        }

        DB::transaction(function () use ($attendanceRecord, $request, $breaks) {
            $application = $attendanceRecord->applications()->create([
                'clock_in' => $request->input('new_clock_in'),
                'clock_out' => $request->input('new_clock_out'),
                'comment' => $request->input('comment'),
            ]);

            $application->applicationBreaks()->createMany($breaks->all());
        });

        return redirect("/attendance/detail/{$attendanceRecord->id}");
    }

    private function formatTime($time)
    {
        return $time ? Carbon::parse($time)->format('H:i') : '';
    }
}
