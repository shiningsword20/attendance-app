<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\BreakTime;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

// ID10 勤怠詳細情報取得機能（一般ユーザー）
class AttendanceDetailTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private AttendanceRecord $record;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::create(2026, 11, 9, 12, 0, 0));

        // 1. 勤怠情報が登録されたユーザーにログインをする
        $this->user = User::factory()->create(['name' => 'テスト太郎']);

        $this->record = AttendanceRecord::create([
            'user_id' => $this->user->id,
            'date' => '2026-11-04',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        BreakTime::create([
            'attendance_record_id' => $this->record->id,
            'break_in' => '12:00:00',
            'break_out' => '13:00:00',
        ]);
    }

    // 2. 勤怠詳細ページを開く
    private function openDetail(): TestResponse
    {
        $response = $this->actingAs($this->user)->get('/attendance/detail/'.$this->record->id);
        $response->assertStatus(200);

        return $response;
    }

    public function test_勤怠詳細画面の名前がログインユーザーの氏名になっている(): void
    {
        // 3. 名前欄を確認する
        $this->openDetail()->assertSee('name="name" value="テスト太郎"', false);
    }

    public function test_勤怠詳細画面の日付が選択した日付になっている(): void
    {
        // 3. 日付欄を確認する
        $response = $this->openDetail();
        $response->assertSee('value="2026年"', false);
        $response->assertSee('name="new_date" value="11月4日"', false);
    }

    public function test_出勤退勤にて記されている時間がログインユーザーの打刻と一致している(): void
    {
        // 3. 出勤・退勤欄を確認する
        $response = $this->openDetail();
        $response->assertSee('name="new_clock_in" value="09:00"', false);
        $response->assertSee('name="new_clock_out" value="18:00"', false);
    }

    public function test_休憩にて記されている時間がログインユーザーの打刻と一致している(): void
    {
        // 3. 休憩欄を確認する
        $response = $this->openDetail();
        $response->assertSee('name="new_break_in[0]" value="12:00"', false);
        $response->assertSee('name="new_break_out[0]" value="13:00"', false);
    }
}
