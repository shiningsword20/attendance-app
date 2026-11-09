<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// ID7 休憩機能
class BreakTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    // 画面のボタン（提供Bladeのタグそのまま）
    private const BREAK_IN_BUTTON = '<button class="attendance__button--submit--break-in" type="submit" name="action" value="break_in">休憩入</button>';

    private const BREAK_OUT_BUTTON = '<button class="attendance__button--submit--break-out" type="submit" name="action" value="break_out">休憩戻</button>';

    protected function setUp(): void
    {
        parent::setUp();

        // 「今」を固定する（2026年11月4日(水) 12:00）
        $this->travelTo(Carbon::create(2026, 11, 4, 12, 0, 0));

        // ステータスが出勤中のユーザー（09:00に出勤済み）
        $this->user = User::factory()->create();

        AttendanceRecord::create([
            'user_id' => $this->user->id,
            'date' => '2026-11-04',
            'clock_in' => '09:00:00',
        ]);
    }

    // 指定した時刻に、打刻ボタンを押す
    private function stamp(string $action, int $hour, int $minute): void
    {
        $this->travelTo(Carbon::create(2026, 11, 4, $hour, $minute, 0));

        $this->actingAs($this->user)
            ->post('/attendance', ['action' => $action])
            ->assertRedirect('/attendance');
    }

    public function test_休憩ボタンが正しく機能する(): void
    {
        // 2. 画面に「休憩入」ボタンが表示されていることを確認する
        $response = $this->actingAs($this->user)->get('/attendance');
        $response->assertSee(self::BREAK_IN_BUTTON, false);

        // 3. 休憩の処理を行う
        $this->stamp('break_in', 12, 0);

        // 処理後のステータスが「休憩中」になる
        $response = $this->actingAs($this->user)->get('/attendance');
        $response->assertSee('<p class="attendance__status--item">休憩中</p>', false);
    }

    public function test_休憩は一日に何回でもできる(): void
    {
        // 2. 休憩入と休憩戻の処理を行う
        $this->stamp('break_in', 12, 0);
        $this->stamp('break_out', 12, 30);

        // 3. 「休憩入」ボタンが表示されることを確認する
        $response = $this->actingAs($this->user)->get('/attendance');
        $response->assertSee(self::BREAK_IN_BUTTON, false);
    }

    public function test_休憩戻ボタンが正しく機能する(): void
    {
        // 2. 休憩入の処理を行う
        $this->stamp('break_in', 12, 0);

        // 「休憩戻」ボタンが表示される
        $response = $this->actingAs($this->user)->get('/attendance');
        $response->assertSee(self::BREAK_OUT_BUTTON, false);

        // 3. 休憩戻の処理を行う
        $this->stamp('break_out', 12, 30);

        // 処理後のステータスが「出勤中」になる
        $response = $this->actingAs($this->user)->get('/attendance');
        $response->assertSee('<p class="attendance__status--item">出勤中</p>', false);
    }

    public function test_休憩戻は一日に何回でもできる(): void
    {
        // 2. 休憩入と休憩戻の処理を行い、再度休憩入の処理を行う
        $this->stamp('break_in', 12, 0);
        $this->stamp('break_out', 12, 30);
        $this->stamp('break_in', 15, 0);

        // 3. 「休憩戻」ボタンが表示されることを確認する
        $response = $this->actingAs($this->user)->get('/attendance');
        $response->assertSee(self::BREAK_OUT_BUTTON, false);
    }

    public function test_休憩時刻が勤怠一覧画面で確認できる(): void
    {
        // 2. 休憩入と休憩戻の処理を行う（12:00〜13:00 の1時間）
        $this->stamp('break_in', 12, 0);
        $this->stamp('break_out', 13, 0);

        $this->assertDatabaseHas('break_times', [
            'break_in' => '12:00:00',
            'break_out' => '13:00:00',
        ]);

        // 3. 勤怠一覧画面から休憩の日付を確認する（一覧の休憩欄は合計時間を表示）
        $response = $this->actingAs($this->user)->get('/attendance/list');
        $response->assertSeeInOrder(['11/04(水)', '09:00', '1:00']);
    }
}
