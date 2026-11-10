<?php

namespace App\Http\Controllers;

use App\Models\AttendanceRecord;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class AttendanceReportController extends Controller
{
    // 集計する期間（当月を含む過去6ヶ月）
    private const REPORT_MONTHS = 6;

    // 1日の所定労働時間（これを超えた分が残業）
    private const STANDARD_WORK_MINUTES = 8 * 60;

    // 長時間労働とみなす1日の労働時間
    private const LONG_WORK_MINUTES = 10 * 60;

    private const WORK_START_TIME = '09:00:00';

    private const WORK_END_TIME = '18:00:00';

    /**
     * ログインユーザーの過去6ヶ月の勤怠統計（基本サマリー・月次推移・異常検知）を表示する
     *
     * @return View マイ勤怠レポート画面
     */
    public function index(): View
    {
        $currentMonth = Carbon::now()->startOfMonth();
        $startMonth = $currentMonth->copy()->subMonths(self::REPORT_MONTHS - 1);

        $attendanceRecords = Auth::user()->attendanceRecords()
            ->with('breaks')
            ->whereBetween('date', [$startMonth->toDateString(), $currentMonth->copy()->endOfMonth()->toDateString()])
            ->get();

        // 労働時間を出せるのは退勤済みの日だけ
        $completedRecords = $attendanceRecords->filter(fn(AttendanceRecord $record) => $record->clock_out);

        $summary = $this->summary($completedRecords);
        $monthlyTrend = $this->monthlyTrend($completedRecords, $startMonth);
        $anomalies = $this->anomalies(
            $attendanceRecords->filter(fn(AttendanceRecord $record) => $record->date->isSameMonth($currentMonth))
        );

        return view('reports.index', compact('summary', 'monthlyTrend', 'anomalies'));
    }

    /**
     * 基本サマリー（総労働時間・総残業時間・1日あたりの平均労働時間）を集計する
     *
     * @param  Collection<int, AttendanceRecord>  $records  退勤済みの勤怠
     * @return array{total_work_minutes: int, total_overtime_minutes: int, avg_work_minutes: int}
     */
    private function summary(Collection $records): array
    {
        $workMinutes = $records->map(fn(AttendanceRecord $record) => $record->workMinutes());

        return [
            'total_work_minutes' => $workMinutes->sum(),
            'total_overtime_minutes' => $workMinutes->sum(fn(int $minutes) => $this->overtimeMinutes($minutes)),
            'avg_work_minutes' => (int) round($workMinutes->avg() ?? 0),
        ];
    }

    /**
     * 月ごとの労働時間と残業時間を集計する（データが無い月は0）
     *
     * @param  Collection<int, AttendanceRecord>  $records  退勤済みの勤怠
     * @param  Carbon  $startMonth  集計を始める月（月初）
     * @return array<int, array{month: string, work_minutes: int, overtime_minutes: int}>
     */
    private function monthlyTrend(Collection $records, Carbon $startMonth): array
    {
        $recordsByMonth = $records->groupBy(fn(AttendanceRecord $record) => $record->date->format('Y-m'));

        return collect(range(0, self::REPORT_MONTHS - 1))
            ->map(fn(int $offset) => $startMonth->copy()->addMonths($offset))
            ->map(function (Carbon $month) use ($recordsByMonth) {
                $workMinutes = $recordsByMonth->get($month->format('Y-m'), collect())
                    ->map(fn(AttendanceRecord $record) => $record->workMinutes());

                return [
                    'month' => $month->format('Y/m'),
                    'work_minutes' => $workMinutes->sum(),
                    'overtime_minutes' => $workMinutes->sum(fn(int $minutes) => $this->overtimeMinutes($minutes)),
                ];
            })
            ->all();
    }

    /**
     * 当月の遅刻・早退・長時間労働の回数を数える
     *
     * @param  Collection<int, AttendanceRecord>  $records  当月の勤怠
     * @return array{late_count: int, early_leave_count: int, long_work_count: int}
     */
    private function anomalies(Collection $records): array
    {
        return [
            'late_count' => $records
                ->filter(fn(AttendanceRecord $record) => $record->clock_in > self::WORK_START_TIME)
                ->count(),
            'early_leave_count' => $records
                ->filter(fn(AttendanceRecord $record) => $record->clock_out && $record->clock_out < self::WORK_END_TIME)
                ->count(),
            'long_work_count' => $records
                ->filter(fn(AttendanceRecord $record) => $record->workMinutes() > self::LONG_WORK_MINUTES)
                ->count(),
        ];
    }

    /**
     * 1日の労働時間から残業時間（8時間を超えた分）を求める
     *
     * @param  int  $workMinutes  1日の労働時間（分）
     * @return int 残業時間（分）
     */
    private function overtimeMinutes(int $workMinutes): int
    {
        return max(0, $workMinutes - self::STANDARD_WORK_MINUTES);
    }
}
