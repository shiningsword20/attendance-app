<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\BreakTime;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

// ID13 勤怠詳細情報取得・修正機能（管理者）
class AdminAttendanceDetailTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private AttendanceRecord $record;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::create(2026, 11, 9, 12, 0, 0));

        // 1. 管理者ユーザーにログインをする
        $this->admin = User::factory()->create(['admin_status' => true]);

        // 一般ユーザーの勤怠（09:00〜18:00、休憩12:00〜13:00、備考あり）
        $user = User::factory()->create(['name' => 'テスト太郎']);

        $this->record = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => '2026-11-04',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
            'comment' => '通常勤務',
        ]);

        BreakTime::create([
            'attendance_record_id' => $this->record->id,
            'break_in' => '12:00:00',
            'break_out' => '13:00:00',
        ]);
    }

    // 正しい修正内容。各テストで一部だけ上書きして使う
    private function formData(array $overrides = []): array
    {
        return array_merge([
            'new_clock_in' => '09:30',
            'new_clock_out' => '18:30',
            'new_break_in' => ['12:00'],
            'new_break_out' => ['13:00'],
            'comment' => '管理者による修正',
        ], $overrides);
    }

    // 2. 勤怠詳細ページを開き、保存処理をする
    private function submitCorrection(array $overrides = []): TestResponse
    {
        return $this->actingAs($this->admin)
            ->from('/admin/attendance/'.$this->record->id)
            ->post('/attendance/'.$this->record->id, $this->formData($overrides));
    }

    public function test_勤怠詳細画面に表示されるデータが選択したものになっている(): void
    {
        // 2. 勤怠詳細ページを開く
        $response = $this->actingAs($this->admin)->get('/admin/attendance/'.$this->record->id);

        $response->assertStatus(200);
        $response->assertSee('value="テスト太郎"', false);
        $response->assertSee('value="2026年"', false);
        $response->assertSee('value="11月4日"', false);
        $response->assertSeeInOrder(['value="09:00"', 'value="18:00"', 'value="12:00"', 'value="13:00"'], false);
        $response->assertSee('通常勤務');
    }

    public function test_出勤時間が退勤時間より後になっている場合_エラーメッセージが表示される(): void
    {
        // 3. 出勤時間を退勤時間より後に設定する
        $response = $this->submitCorrection([
            'new_clock_in' => '19:00',
            'new_clock_out' => '18:00',
        ]);

        $response->assertRedirect('/admin/attendance/'.$this->record->id);
        $response->assertSessionHasErrors(['new_clock_in' => '出勤時間もしくは退勤時間が不適切な値です']);
    }

    public function test_休憩開始時間が退勤時間より後になっている場合_エラーメッセージが表示される(): void
    {
        // 3. 休憩開始時間を退勤時間より後に設定する
        $response = $this->submitCorrection([
            'new_break_in' => ['19:00'],
            'new_break_out' => ['19:30'],
        ]);

        $response->assertSessionHasErrors(['new_break_in.0' => '休憩時間が不適切な値です']);
    }

    public function test_休憩終了時間が退勤時間より後になっている場合_エラーメッセージが表示される(): void
    {
        // 3. 休憩終了時間を退勤時間より後に設定する
        $response = $this->submitCorrection([
            'new_break_in' => ['17:00'],
            'new_break_out' => ['19:00'],
        ]);

        $response->assertSessionHasErrors(['new_break_out.0' => '休憩時間もしくは退勤時間が不適切な値です']);
    }

    public function test_備考欄が未入力の場合のエラーメッセージが表示される(): void
    {
        // 3. 備考欄を未入力のまま保存処理をする
        $response = $this->submitCorrection(['comment' => '']);

        $response->assertSessionHasErrors(['comment' => '備考を記入してください']);
    }
}
