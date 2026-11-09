<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\BreakTime;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// ID12 勤怠一覧情報取得機能（管理者）
class AdminAttendanceListTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $userA;

    private User $userB;

    protected function setUp(): void
    {
        parent::setUp();

        // 「今日」を固定する（2026年11月4日(水)）
        $this->travelTo(Carbon::create(2026, 11, 4, 20, 0, 0));

        // 1. 管理者ユーザーにログインする
        $this->admin = User::factory()->create(['admin_status' => true]);

        $this->userA = User::factory()->create(['name' => 'ユーザーA']);
        $this->userB = User::factory()->create(['name' => 'ユーザーB']);
    }

    // 勤怠（休憩1回つき）を1日分登録する
    private function createRecord(User $user, string $date, string $clockIn, string $clockOut, string $breakIn, string $breakOut): void
    {
        $record = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => $date,
            'clock_in' => $clockIn,
            'clock_out' => $clockOut,
        ]);

        BreakTime::create([
            'attendance_record_id' => $record->id,
            'break_in' => $breakIn,
            'break_out' => $breakOut,
        ]);
    }

    public function test_その日になされた全ユーザーの勤怠情報が正確に確認できる(): void
    {
        // 今日の勤怠（ユーザーA：9時間勤務・休憩1時間、ユーザーB：8時間勤務・休憩30分）
        $this->createRecord($this->userA, '2026-11-04', '09:00:00', '18:00:00', '12:00:00', '13:00:00');
        $this->createRecord($this->userB, '2026-11-04', '10:00:00', '18:00:00', '13:00:00', '13:30:00');

        // 別の日の勤怠は表示されないことも確認する
        $this->createRecord($this->userA, '2026-11-03', '07:15:00', '16:45:00', '12:00:00', '12:45:00');

        // 2. 勤怠一覧画面を開く
        $response = $this->actingAs($this->admin)->get('/admin/attendance/list');

        // 名前・出勤・退勤・休憩合計・勤務合計が正しい
        $response->assertStatus(200);
        $response->assertSeeInOrder([
            'ユーザーA', '09:00', '18:00', '1:00', '8:00',
            'ユーザーB', '10:00', '18:00', '0:30', '7:30',
        ]);
        $response->assertDontSee('07:15');
        $response->assertDontSee('16:45');
    }

    public function test_遷移した際に現在の日付が表示される(): void
    {
        // 2. 勤怠一覧画面を開く
        $response = $this->actingAs($this->admin)->get('/admin/attendance/list');

        $response->assertStatus(200);
        $response->assertSee('2026年11月04日の勤怠');
        $response->assertSee('<p class="current-day">2026/11/04</p>', false);
    }

    public function test_前日を押下した時に前の日の勤怠情報が表示される(): void
    {
        $this->createRecord($this->userA, '2026-11-03', '08:00:00', '17:00:00', '12:00:00', '13:00:00');

        // 2. 勤怠一覧画面を開く（「前日」ボタンのリンク先を確認）
        $response = $this->actingAs($this->admin)->get('/admin/attendance/list');
        $response->assertSee('href="?date=2026-11-03"', false);

        // 3. 「前日」ボタンを押す
        $response = $this->actingAs($this->admin)->get('/admin/attendance/list?date=2026-11-03');

        $response->assertStatus(200);
        $response->assertSee('<p class="current-day">2026/11/03</p>', false);
        $response->assertSeeInOrder(['ユーザーA', '08:00', '17:00']);
    }

    public function test_翌日を押下した時に次の日の勤怠情報が表示される(): void
    {
        $this->createRecord($this->userB, '2026-11-05', '11:00:00', '20:00:00', '15:00:00', '16:00:00');

        // 2. 勤怠一覧画面を開く（「翌日」ボタンのリンク先を確認）
        $response = $this->actingAs($this->admin)->get('/admin/attendance/list');
        $response->assertSee('href="?date=2026-11-05"', false);

        // 3. 「翌日」ボタンを押す
        $response = $this->actingAs($this->admin)->get('/admin/attendance/list?date=2026-11-05');

        $response->assertStatus(200);
        $response->assertSee('<p class="current-day">2026/11/05</p>', false);
        $response->assertSeeInOrder(['ユーザーB', '11:00', '20:00']);
    }
}
