<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// ID14 ユーザー情報取得機能（管理者）
class AdminStaffTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // 「今日」を固定する（2026年11月4日(水)）
        $this->travelTo(Carbon::create(2026, 11, 4, 20, 0, 0));

        // 1. 管理者ユーザーでログインする
        $this->admin = User::factory()->create([
            'name' => '管理者',
            'email' => 'admin@example.com',
            'admin_status' => true,
        ]);

        $this->user = User::factory()->create([
            'name' => 'テスト太郎',
            'email' => 'taro@example.com',
        ]);
    }

    // 勤怠を1日分登録する
    private function createRecord(string $date, string $clockIn, string $clockOut): AttendanceRecord
    {
        return AttendanceRecord::create([
            'user_id' => $this->user->id,
            'date' => $date,
            'clock_in' => $clockIn,
            'clock_out' => $clockOut,
        ]);
    }

    public function test_管理者ユーザーが全一般ユーザーの氏名とメールアドレスを確認できる(): void
    {
        User::factory()->create([
            'name' => 'テスト花子',
            'email' => 'hanako@example.com',
        ]);

        // 2. スタッフ一覧ページを開く
        $response = $this->actingAs($this->admin)->get('/admin/staff/list');

        $response->assertStatus(200);
        $response->assertSeeInOrder([
            'テスト太郎', 'taro@example.com',
            'テスト花子', 'hanako@example.com',
        ]);

        // 管理者自身は一般ユーザーではないので表示されない
        $response->assertDontSee('admin@example.com');
    }

    public function test_ユーザーの勤怠情報が正しく表示される(): void
    {
        $this->createRecord('2026-11-02', '09:00:00', '18:00:00');
        $this->createRecord('2026-11-03', '10:00:00', '19:00:00');

        // 他のユーザーの勤怠は表示されないことも確認する
        $otherUser = User::factory()->create();
        AttendanceRecord::create([
            'user_id' => $otherUser->id,
            'date' => '2026-11-04',
            'clock_in' => '07:15:00',
            'clock_out' => '16:45:00',
        ]);

        // 2. 選択したユーザーの勤怠一覧ページを開く
        $response = $this->actingAs($this->admin)->get('/admin/attendance/staff/'.$this->user->id);

        $response->assertStatus(200);
        $response->assertSee('テスト太郎さんの勤怠');
        $response->assertSeeInOrder([
            '11/02(月)', '09:00', '18:00', '9:00',
            '11/03(火)', '10:00', '19:00', '9:00',
        ]);
        $response->assertDontSee('07:15');
    }

    public function test_前月を押下した時に表示月の前月の情報が表示される(): void
    {
        $this->createRecord('2026-10-15', '09:00:00', '18:00:00');

        // 2. 勤怠一覧ページを開く（「前月」ボタンのリンク先を確認）
        $response = $this->actingAs($this->admin)->get('/admin/attendance/staff/'.$this->user->id);
        $response->assertSee('href="?date=2026-10"', false);

        // 3. 「前月」ボタンを押す
        $response = $this->actingAs($this->admin)->get('/admin/attendance/staff/'.$this->user->id.'?date=2026-10');

        $response->assertStatus(200);
        $response->assertSee('<p class="current-month">2026/10</p>', false);
        $response->assertSeeInOrder(['10/15(木)', '09:00', '18:00']);
    }

    public function test_翌月を押下した時に表示月の翌月の情報が表示される(): void
    {
        $this->createRecord('2026-12-10', '09:00:00', '18:00:00');

        // 2. 勤怠一覧ページを開く（「翌月」ボタンのリンク先を確認）
        $response = $this->actingAs($this->admin)->get('/admin/attendance/staff/'.$this->user->id);
        $response->assertSee('href="?date=2026-12"', false);

        // 3. 「翌月」ボタンを押す
        $response = $this->actingAs($this->admin)->get('/admin/attendance/staff/'.$this->user->id.'?date=2026-12');

        $response->assertStatus(200);
        $response->assertSee('<p class="current-month">2026/12</p>', false);
        $response->assertSeeInOrder(['12/10(木)', '09:00', '18:00']);
    }

    public function test_詳細を押下するとその日の勤怠詳細画面に遷移する(): void
    {
        $record = $this->createRecord('2026-11-04', '09:00:00', '18:00:00');

        // 2. 勤怠一覧ページを開く（「詳細」ボタンのリンク先を確認）
        $response = $this->actingAs($this->admin)->get('/admin/attendance/staff/'.$this->user->id);
        $response->assertSee(url('/attendance/'.$record->id), false);

        // 3. 「詳細」ボタンを押下する（提供Bladeのリンク先から管理者の勤怠詳細へ転送される）
        $response = $this->actingAs($this->admin)->get('/attendance/'.$record->id);
        $response->assertRedirect('/admin/attendance/'.$record->id);

        // その日の勤怠詳細画面が表示される
        $response = $this->actingAs($this->admin)->get('/admin/attendance/'.$record->id);
        $response->assertStatus(200);
        $response->assertSee('value="テスト太郎"', false);
        $response->assertSee('value="11月4日"', false);
    }
}
