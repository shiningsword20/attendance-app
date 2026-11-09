<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// ID8 退勤機能
class ClockOutTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    // 指定した時刻に、打刻ボタンを押す
    private function stamp(string $action, int $hour, int $minute): void
    {
        $this->travelTo(Carbon::create(2026, 11, 4, $hour, $minute, 0));

        $this->actingAs($this->user)
            ->post('/attendance', ['action' => $action])
            ->assertRedirect('/attendance');
    }

    public function test_退勤ボタンが正しく機能する(): void
    {
        // 1. ステータスが勤務中のユーザーにログインする（09:00に出勤済み、今は18:00）
        $this->travelTo(Carbon::create(2026, 11, 4, 18, 0, 0));

        AttendanceRecord::create([
            'user_id' => $this->user->id,
            'date' => '2026-11-04',
            'clock_in' => '09:00:00',
        ]);

        // 2. 画面に「退勤」ボタンが表示されていることを確認する
        $response = $this->actingAs($this->user)->get('/attendance');
        $response->assertSee('<button class="attendance__button--submit--clock-out" type="submit" name="action" value="clock_out">退勤</button>', false);

        // 3. 退勤の処理を行う
        $this->stamp('clock_out', 18, 0);

        // 処理後のステータスが「退勤済」になる
        $response = $this->actingAs($this->user)->get('/attendance');
        $response->assertSee('<p class="attendance__status--item">退勤済</p>', false);
    }

    public function test_退勤時刻が勤怠一覧画面で確認できる(): void
    {
        // 1. ステータスが勤務外のユーザーにログインする
        // 2. 出勤と退勤の処理を行う
        $this->stamp('clock_in', 9, 0);
        $this->stamp('clock_out', 18, 0);

        $this->assertDatabaseHas('attendance_records', [
            'user_id' => $this->user->id,
            'date' => '2026-11-04',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        // 3. 勤怠一覧画面から退勤の日付を確認する
        $response = $this->actingAs($this->user)->get('/attendance/list');
        $response->assertSeeInOrder(['11/04(水)', '09:00', '18:00']);
    }
}
