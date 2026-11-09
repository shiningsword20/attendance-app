<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// ID9 勤怠一覧情報取得機能（一般ユーザー）
class AttendanceListTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // 「今日」を固定する（2026年11月4日(水)）
        $this->travelTo(Carbon::create(2026, 11, 4, 20, 0, 0));

        $this->user = User::factory()->create();
    }

    // 勤怠を1日分登録する
    private function createRecord(User $user, string $date, string $clockIn, string $clockOut): AttendanceRecord
    {
        return AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => $date,
            'clock_in' => $clockIn,
            'clock_out' => $clockOut,
        ]);
    }

    public function test_自分が行った勤怠情報が全て表示されている(): void
    {
        // 1. 勤怠情報が登録されたユーザーにログインする
        $this->createRecord($this->user, '2026-11-02', '09:00:00', '18:00:00');
        $this->createRecord($this->user, '2026-11-03', '10:00:00', '19:00:00');
        $this->createRecord($this->user, '2026-11-04', '08:30:00', '17:30:00');

        // 他のユーザーの勤怠は表示されないことも確認する
        $otherUser = User::factory()->create();
        $this->createRecord($otherUser, '2026-11-05', '07:15:00', '16:45:00');

        // 2. 勤怠一覧ページを開く
        $response = $this->actingAs($this->user)->get('/attendance/list');

        // 3. 自分の勤怠情報がすべて表示されていることを確認する
        $response->assertStatus(200);
        $response->assertSeeInOrder([
            '11/02(月)', '09:00', '18:00',
            '11/03(火)', '10:00', '19:00',
            '11/04(水)', '08:30', '17:30',
        ]);
        $response->assertDontSee('07:15');
        $response->assertDontSee('16:45');
    }

    public function test_勤怠一覧画面に遷移した際に現在の月が表示される(): void
    {
        // 2. 勤怠一覧ページを開く
        $response = $this->actingAs($this->user)->get('/attendance/list');

        $response->assertStatus(200);
        $response->assertSee('<p class="current-month">2026/11</p>', false);
    }

    public function test_前月を押下した時に表示月の前月の情報が表示される(): void
    {
        // 1. 勤怠情報が登録されたユーザーにログインをする（前月に勤怠あり）
        $this->createRecord($this->user, '2026-10-15', '09:00:00', '18:00:00');

        // 2. 勤怠一覧ページを開く（「前月」ボタンのリンク先を確認）
        $response = $this->actingAs($this->user)->get('/attendance/list');
        $response->assertSee('href="?date=2026-10"', false);

        // 3. 「前月」ボタンを押す
        $response = $this->actingAs($this->user)->get('/attendance/list?date=2026-10');

        $response->assertStatus(200);
        $response->assertSee('<p class="current-month">2026/10</p>', false);
        $response->assertSeeInOrder(['10/15(木)', '09:00', '18:00']);
    }

    public function test_翌月を押下した時に表示月の翌月の情報が表示される(): void
    {
        // 1. 勤怠情報が登録されたユーザーにログインをする（翌月に勤怠あり）
        $this->createRecord($this->user, '2026-12-10', '09:00:00', '18:00:00');

        // 2. 勤怠一覧ページを開く（「翌月」ボタンのリンク先を確認）
        $response = $this->actingAs($this->user)->get('/attendance/list');
        $response->assertSee('href="?date=2026-12"', false);

        // 3. 「翌月」ボタンを押す
        $response = $this->actingAs($this->user)->get('/attendance/list?date=2026-12');

        $response->assertStatus(200);
        $response->assertSee('<p class="current-month">2026/12</p>', false);
        $response->assertSeeInOrder(['12/10(木)', '09:00', '18:00']);
    }

    public function test_詳細を押下するとその日の勤怠詳細画面に遷移する(): void
    {
        // 1. 勤怠情報が登録されたユーザーにログインをする
        $record = $this->createRecord($this->user, '2026-11-04', '09:00:00', '18:00:00');

        // 2. 勤怠一覧ページを開く（「詳細」ボタンのリンク先を確認）
        $response = $this->actingAs($this->user)->get('/attendance/list');
        $response->assertSee(url('/attendance/'.$record->id), false);

        // 3. 「詳細」ボタンを押下する（提供Bladeのリンク先から勤怠詳細へ転送される）
        $response = $this->actingAs($this->user)->get('/attendance/'.$record->id);
        $response->assertRedirect('/attendance/detail/'.$record->id);

        // その日の勤怠詳細画面が表示される
        $response = $this->actingAs($this->user)->get('/attendance/detail/'.$record->id);
        $response->assertStatus(200);
        $response->assertSee('11月4日');
    }
}
