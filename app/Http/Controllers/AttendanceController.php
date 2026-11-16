<?php

namespace App\Http\Controllers;

use App\Http\Requests\AttendanceCorrectionRequest;
use App\Models\AttendanceRecord;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AttendanceController extends Controller
{
    /**
     * 勤怠一覧（一般ユーザー）を表示する。月の全日を並べ、勤怠が無い日は空欄にする
     *
     * @param  Request  $request  表示する月（?date=Y-m、無ければ今月）
     * @return View 勤怠一覧画面
     */
    public function index(Request $request): View
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
            ->keyBy(fn ($record) => $record->date->toDateString());

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

    /**
     * 勤怠登録（打刻）画面を表示する
     *
     * @return View 勤怠登録画面
     */
    public function create(): View
    {
        $user = Auth::user();
        $now = Carbon::now()->locale('ja');

        $formattedDate = $now->isoFormat('YYYY年M月D日(ddd)');
        $formattedTime = $now->format('H:i');

        return view('user.attendance-register', compact('user', 'formattedDate', 'formattedTime'));
    }

    /**
     * 出勤・休憩入・休憩戻・退勤を打刻する（今のステータスで押せるものだけ）
     *
     * @param  Request  $request  押したボタン（action）
     * @return RedirectResponse 勤怠登録画面へのリダイレクト
     */
    public function store(Request $request): RedirectResponse
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

    /**
     * 勤怠詳細（一般ユーザー）を表示する。承認待ちの申請があれば申請内容を表示する
     *
     * @param  string  $id  勤怠ID
     * @return View 勤怠詳細画面
     */
    public function show(string $id): View
    {
        $user = Auth::user();

        $attendanceRecord = $user->attendanceRecords()
            ->with(['breaks', 'applications.applicationBreaks'])
            ->findOrFail($id);

        // 承認待ちの申請（承認済みの申請は修正の妨げにしない）
        $pendingApplication = $attendanceRecord->applications->firstWhere('is_approved', false);

        // 承認待ちの申請があれば申請内容を、無ければ勤怠の内容を表示する
        $source = $pendingApplication ?? $attendanceRecord;
        $breaks = $pendingApplication ? $pendingApplication->applicationBreaks : $attendanceRecord->breaks;

        $data = [
            'id' => $attendanceRecord->id,
            // 承認待ちの申請があるときだけ、閲覧のみにする
            'application' => $pendingApplication,
            'year' => $attendanceRecord->date->format('Y年'),
            'date' => $attendanceRecord->date->format('n月j日'),
            'clock_in' => $this->formatTime($source->clock_in),
            'clock_out' => $this->formatTime($source->clock_out),
            'breaks' => $breaks->map(fn ($break) => [
                'break_in' => $this->formatTime($break->break_in),
                'break_out' => $this->formatTime($break->break_out),
            ])->values()->all(),
            'comment' => $source->comment,
        ];

        return view('user.user-detail', compact('user', 'data'));
    }

    /**
     * 勤怠を修正する（管理者は直接修正、一般ユーザーは修正申請を作成）
     *
     * @param  AttendanceCorrectionRequest  $request  修正後の出勤・退勤・休憩・備考
     * @param  string  $id  勤怠ID
     * @return RedirectResponse 日次勤怠一覧または勤怠詳細画面へのリダイレクト
     */
    public function update(AttendanceCorrectionRequest $request, string $id): RedirectResponse
    {
        $user = Auth::user();

        // 入力された休憩のうち、開始・終了がそろっているものだけを使う
        $breaks = collect($request->input('new_break_in', []))
            ->map(fn ($breakIn, $index) => [
                'break_in' => $breakIn,
                'break_out' => $request->input("new_break_out.{$index}"),
            ])
            ->filter(fn ($break) => $break['break_in'] && $break['break_out'])
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

            // 修正した勤怠の日付の、日次勤怠一覧へ戻る
            return redirect('/admin/attendance/list?date='.$attendanceRecord->date->format('Y-m-d'));
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

    /**
     * 時刻を H:i 形式にする（空なら空文字）
     *
     * @param  string|null  $time  時刻（H:i:s）
     * @return string H:i 形式の時刻
     */
    private function formatTime(?string $time): string
    {
        return $time ? Carbon::parse($time)->format('H:i') : '';
    }

    /**
     * 提供Bladeのリンク（/attendance/{id}）から、管理者・一般ユーザーそれぞれの勤怠詳細へ転送する
     *
     * @param  string  $id  勤怠ID
     * @return RedirectResponse 勤怠詳細画面へのリダイレクト
     */
    public function redirectToDetail(string $id): RedirectResponse
    {
        // 管理者は管理者の勤怠詳細へ、一般ユーザーは自分の勤怠詳細へ
        if (Auth::user()->admin_status) {
            return redirect("/admin/attendance/{$id}");
        }

        return redirect("/attendance/detail/{$id}");
    }
}
