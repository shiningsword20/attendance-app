<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// ID6 出勤機能
class ClockInTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // 「今」を固定する（2026年11月4日(水) 09:00）
        $this->travelTo(Carbon::create(2026, 11, 4, 9, 0, 0));

        $this->user = User::factory()->create();
    }

    public function test_出勤ボタンが正しく機能する(): void
    {
        // 1. ステータスが勤務外のユーザーにログインする
        // 2. 画面に「出勤」ボタンが表示されていることを確認する
        $response = $this->actingAs($this->user)->get('/attendance');
        $response->assertSee('<button class="attendance__button--submit--clock-in" type="submit" name="action" value="clock_in">出勤</button>', false);

        // 3. 出勤の処理を行う
        $response = $this->actingAs($this->user)->post('/attendance', ['action' => 'clock_in']);
        $response->assertRedirect('/attendance');

        // 処理後のステータスが「出勤中」になる
        $response = $this->actingAs($this->user)->get('/attendance');
        $response->assertSee('<p class="attendance__status--item">出勤中</p>', false);
    }

    public function test_出勤は一日一回のみできる(): void
    {
        // 1. ステータスが退勤済であるユーザーにログインする
        AttendanceRecord::create([
            'user_id' => $this->user->id,
            'date' => '2026-11-04',
            'clock_in' => '08:00:00',
            'clock_out' => '08:30:00',
        ]);

        // 2. 出勤ボタンが表示されないことを確認する
        $response = $this->actingAs($this->user)->get('/attendance');
        $response->assertSee('<p class="attendance__status--item">退勤済</p>', false);
        $response->assertDontSee('value="clock_in"', false);
    }

    public function test_出勤時刻が勤怠一覧画面で確認できる(): void
    {
        // 1. ステータスが勤務外のユーザーにログインする
        // 2. 出勤の処理を行う
        $this->actingAs($this->user)->post('/attendance', ['action' => 'clock_in']);

        $this->assertDatabaseHas('attendance_records', [
            'user_id' => $this->user->id,
            'date' => '2026-11-04',
            'clock_in' => '09:00:00',
        ]);

        // 3. 勤怠一覧画面から出勤の日付を確認する
        $response = $this->actingAs($this->user)->get('/attendance/list');
        $response->assertSeeInOrder(['11/04(水)', '09:00']);
    }
}
