<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// ID20 マイ勤怠レポート機能
class AttendanceReportTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // 当月を2026年11月に固定する
        $this->travelTo(Carbon::create(2026, 11, 20, 12, 0, 0));

        $this->user = User::factory()->create();
    }

    // 指定日の勤怠を、12:00〜13:00の休憩付きで作る
    private function createRecord(string $date, string $clockIn, string $clockOut): void
    {
        $record = AttendanceRecord::create([
            'user_id' => $this->user->id,
            'date' => $date,
            'clock_in' => $clockIn,
            'clock_out' => $clockOut,
        ]);

        $record->breaks()->create(['break_in' => '12:00:00', 'break_out' => '13:00:00']);
    }

    public function test_ゲストはレポートページにアクセスできない(): void
    {
        $response = $this->get('/attendance/report');

        $response->assertRedirect('/login');
    }

    public function test_認証ユーザーの統計情報が正しく計算される(): void
    {
        // 6ヶ月より前（集計対象外）
        $this->createRecord('2026-05-29', '09:00:00', '18:00:00');
        // 先月：通常勤務2日（8時間×2）
        $this->createRecord('2026-10-01', '09:00:00', '18:00:00');
        $this->createRecord('2026-10-02', '09:00:00', '18:00:00');
        // 当月：遅刻（7.5時間）・早退（7時間）・残業（10時間）・長時間労働（12時間）
        $this->createRecord('2026-11-02', '09:30:00', '18:00:00');
        $this->createRecord('2026-11-03', '09:00:00', '17:00:00');
        $this->createRecord('2026-11-04', '09:00:00', '20:00:00');
        $this->createRecord('2026-11-05', '08:00:00', '21:00:00');

        $response = $this->actingAs($this->user)->get('/attendance/report');

        $response->assertStatus(200);
        // 総労働 52.5時間、残業 2時間＋4時間、平均 52.5時間÷6日＝8時間45分
        $response->assertViewHas('summary', [
            'total_work_minutes' => 3150,
            'total_overtime_minutes' => 360,
            'avg_work_minutes' => 525,
        ]);
        $response->assertViewHas('monthlyTrend', [
            ['month' => '2026/06', 'work_minutes' => 0, 'overtime_minutes' => 0],
            ['month' => '2026/07', 'work_minutes' => 0, 'overtime_minutes' => 0],
            ['month' => '2026/08', 'work_minutes' => 0, 'overtime_minutes' => 0],
            ['month' => '2026/09', 'work_minutes' => 0, 'overtime_minutes' => 0],
            ['month' => '2026/10', 'work_minutes' => 960, 'overtime_minutes' => 0],
            ['month' => '2026/11', 'work_minutes' => 2190, 'overtime_minutes' => 360],
        ]);
        $response->assertViewHas('anomalies', [
            'late_count' => 1,
            'early_leave_count' => 1,
            'long_work_count' => 1,
        ]);
    }

    public function test_勤怠記録がないユーザーで安全に処理される(): void
    {
        $response = $this->actingAs($this->user)->get('/attendance/report');

        $response->assertStatus(200);
        $response->assertViewHas('summary', [
            'total_work_minutes' => 0,
            'total_overtime_minutes' => 0,
            'avg_work_minutes' => 0,
        ]);
        $response->assertViewHas('monthlyTrend', fn (array $monthlyTrend) => count($monthlyTrend) === 6
            && collect($monthlyTrend)->every(fn (array $row) => $row['work_minutes'] === 0 && $row['overtime_minutes'] === 0));
        $response->assertViewHas('anomalies', [
            'late_count' => 0,
            'early_leave_count' => 0,
            'long_work_count' => 0,
        ]);
    }
}
