<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\BreakTime;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// ID5 ステータス確認機能
class AttendanceStatusTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // 「今日」を固定する（2026年11月4日 12:00）
        $this->travelTo(Carbon::create(2026, 11, 4, 12, 0, 0));

        $this->user = User::factory()->create();
    }

    // 画面上のステータス表示部分（<p class="attendance__status--item">）を確認する
    private function assertAttendanceStatus(string $status): void
    {
        $response = $this->actingAs($this->user)->get('/attendance');

        $response->assertStatus(200);
        $response->assertSee('<p class="attendance__status--item">' . $status . '</p>', false);
    }

    public function test_勤務外の場合_勤怠ステータスが正しく表示される(): void
    {
        // 今日の勤怠が無い = 勤務外
        $this->assertAttendanceStatus('勤務外');
    }

    public function test_出勤中の場合_勤怠ステータスが正しく表示される(): void
    {
        AttendanceRecord::create([
            'user_id' => $this->user->id,
            'date' => '2026-11-04',
            'clock_in' => '09:00:00',
        ]);

        $this->assertAttendanceStatus('出勤中');
    }

    public function test_休憩中の場合_勤怠ステータスが正しく表示される(): void
    {
        $attendanceRecord = AttendanceRecord::create([
            'user_id' => $this->user->id,
            'date' => '2026-11-04',
            'clock_in' => '09:00:00',
        ]);

        // 休憩戻が未入力の休憩がある = 休憩中
        BreakTime::create([
            'attendance_record_id' => $attendanceRecord->id,
            'break_in' => '11:30:00',
        ]);

        $this->assertAttendanceStatus('休憩中');
    }

    public function test_退勤済の場合_勤怠ステータスが正しく表示される(): void
    {
        AttendanceRecord::create([
            'user_id' => $this->user->id,
            'date' => '2026-11-04',
            'clock_in' => '09:00:00',
            'clock_out' => '11:00:00',
        ]);

        $this->assertAttendanceStatus('退勤済');
    }
}
