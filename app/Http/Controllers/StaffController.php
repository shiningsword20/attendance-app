<?php

namespace App\Http\Controllers;

use App\Http\Requests\ExportAttendanceRequest;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

        $formattedAttendanceRecords = $this->monthlyAttendanceRows($user, $date);

        return view('admin.staff-attendance-list', compact('user', 'date', 'previousMonth', 'nextMonth', 'formattedAttendanceRecords'));
    }

    /**
     * スタッフの指定月の勤怠一覧をCSVでダウンロードさせる
     *
     * @param  ExportAttendanceRequest  $request  出力対象のスタッフID（user_id）と年月（year_month: Y-m）
     * @return StreamedResponse CSVファイルのダウンロードレスポンス
     */
    public function export(ExportAttendanceRequest $request): StreamedResponse
    {
        $user = User::where('admin_status', false)->findOrFail($request->input('user_id'));
        $date = Carbon::createFromFormat('!Y-m', $request->input('year_month'));

        $csvRows = $this->monthlyAttendanceRows($user, $date)
            ->map(fn($row) => [
                $row['date'],
                $row['clock_in'],
                $row['clock_out'],
                $row['total_break_time'] ? Carbon::parse($row['total_break_time'])->format('G:i') : '',
                $row['total_time'] ? Carbon::parse($row['total_time'])->format('G:i') : '',
            ])
            ->prepend(['日付', '出勤', '退勤', '休憩', '合計']);

        $fileName = $user->name . '_' . $date->format('Y-m') . '_勤怠.csv';

        return response()->streamDownload(function () use ($csvRows) {
            $stream = fopen('php://output', 'w');

            // Excelで開いたときに文字化けしないよう、UTF-8のBOMを付ける
            fwrite($stream, "\xEF\xBB\xBF");

            $csvRows->each(fn($row) => fputcsv($stream, $row));

            fclose($stream);
        }, $fileName, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * 指定月の全日を並べた勤怠一覧を作る（勤怠が無い日は空欄）
     *
     * @param  User  $user  対象のスタッフ
     * @param  Carbon  $date  対象月（月初）
     * @return Collection<int, array<string, mixed>> 1日1行の勤怠データ
     */
    private function monthlyAttendanceRows(User $user, Carbon $date): Collection
    {
        // その月の勤怠を日付で引けるようにしておく
        $attendanceRecords = $user->attendanceRecords()
            ->with('breaks')
            ->whereBetween('date', [$date->copy()->startOfMonth()->toDateString(), $date->copy()->endOfMonth()->toDateString()])
            ->get()
            ->keyBy(fn($record) => $record->date->toDateString());

        return collect(CarbonPeriod::create($date->copy()->startOfMonth(), $date->copy()->endOfMonth()))
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
    }
}
