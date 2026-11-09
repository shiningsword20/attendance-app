<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\AttendanceRecord;
use App\Models\BreakTime;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

// ID11 勤怠詳細情報修正機能（一般ユーザー）
class AttendanceCorrectionTest extends TestCase
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
        $this->record = $this->createRecord('2026-11-04');
    }

    // 勤怠（09:00〜18:00、休憩12:00〜13:00）を1日分登録する
    private function createRecord(string $date): AttendanceRecord
    {
        $record = AttendanceRecord::create([
            'user_id' => $this->user->id,
            'date' => $date,
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        BreakTime::create([
            'attendance_record_id' => $record->id,
            'break_in' => '12:00:00',
            'break_out' => '13:00:00',
        ]);

        return $record;
    }

    // 正しい修正内容。各テストで一部だけ上書きして使う
    private function formData(array $overrides = []): array
    {
        return array_merge([
            'new_clock_in' => '09:30',
            'new_clock_out' => '18:30',
            'new_break_in' => ['12:00'],
            'new_break_out' => ['13:00'],
            'comment' => '電車遅延のため',
        ], $overrides);
    }

    // 2. 勤怠詳細ページを開き、保存処理をする
    private function submitCorrection(AttendanceRecord $record, array $overrides = []): TestResponse
    {
        $detailUrl = '/attendance/detail/'.$record->id;

        return $this->actingAs($this->user)
            ->from($detailUrl)
            ->post('/attendance/'.$record->id, $this->formData($overrides));
    }

    public function test_出勤時間が退勤時間より後になっている場合_エラーメッセージが表示される(): void
    {
        // 3. 出勤時間を退勤時間より後に設定する
        $response = $this->submitCorrection($this->record, [
            'new_clock_in' => '19:00',
            'new_clock_out' => '18:00',
        ]);

        // テストケースは「出勤時間が不適切な値です」だが、機能要件FN029の文言に合わせている
        $response->assertRedirect('/attendance/detail/'.$this->record->id);
        $response->assertSessionHasErrors(['new_clock_in' => '出勤時間もしくは退勤時間が不適切な値です']);
    }

    public function test_休憩開始時間が退勤時間より後になっている場合_エラーメッセージが表示される(): void
    {
        // 3. 休憩開始時間を退勤時間より後に設定する
        $response = $this->submitCorrection($this->record, [
            'new_break_in' => ['19:00'],
            'new_break_out' => ['19:30'],
        ]);

        $response->assertSessionHasErrors(['new_break_in.0' => '休憩時間が不適切な値です']);
    }

    public function test_休憩終了時間が退勤時間より後になっている場合_エラーメッセージが表示される(): void
    {
        // 3. 休憩終了時間を退勤時間より後に設定する
        $response = $this->submitCorrection($this->record, [
            'new_break_in' => ['17:00'],
            'new_break_out' => ['19:00'],
        ]);

        $response->assertSessionHasErrors(['new_break_out.0' => '休憩時間もしくは退勤時間が不適切な値です']);
    }

    public function test_備考欄が未入力の場合のエラーメッセージが表示される(): void
    {
        // 3. 備考欄を未入力のまま保存処理をする
        $response = $this->submitCorrection($this->record, ['comment' => '']);

        $response->assertSessionHasErrors(['comment' => '備考を記入してください']);
    }

    public function test_修正申請処理が実行される(): void
    {
        // 2. 勤怠詳細を修正し保存処理をする
        $response = $this->submitCorrection($this->record);
        $response->assertSessionHasNoErrors();

        $this->assertDatabaseHas('applications', [
            'attendance_record_id' => $this->record->id,
            'clock_in' => '09:30:00',
            'clock_out' => '18:30:00',
            'comment' => '電車遅延のため',
            'is_approved' => false,
        ]);

        $application = Application::first();

        // 3. 管理者ユーザーで承認画面と申請一覧画面を確認する
        $admin = User::factory()->create(['admin_status' => true]);

        $response = $this->actingAs($admin)->get('/stamp_correction_request/list');
        $response->assertStatus(200);
        $response->assertSeeInOrder(['承認待ち', 'テスト太郎', '2026/11/04', '電車遅延のため']);

        $response = $this->actingAs($admin)->get('/stamp_correction_request/approve/'.$application->id);
        $response->assertStatus(200);
        $response->assertSee('value="テスト太郎"', false);
        $response->assertSee('value="09:30"', false);
        $response->assertSee('value="18:30"', false);
        $response->assertSee('電車遅延のため');
    }

    public function test_承認待ちにログインユーザーが行った申請が全て表示されている(): void
    {
        // 2. 勤怠詳細を修正し保存処理をする（2日分）
        $secondRecord = $this->createRecord('2026-11-05');

        $this->submitCorrection($this->record, ['comment' => '電車遅延のため']);
        $this->submitCorrection($secondRecord, ['comment' => '打刻忘れのため']);

        // 3. 申請一覧画面を確認する
        $response = $this->actingAs($this->user)->get('/stamp_correction_request/list');

        $response->assertStatus(200);
        $response->assertSee('2026/11/04');
        $response->assertSee('電車遅延のため');
        $response->assertSee('2026/11/05');
        $response->assertSee('打刻忘れのため');
        $this->assertSame(2, substr_count($response->getContent(), '<p class="table__description--item">承認待ち</p>'));
    }

    public function test_承認済みに管理者が承認した修正申請が全て表示されている(): void
    {
        // 2. 勤怠詳細を修正し保存処理をする
        $this->submitCorrection($this->record);
        $application = Application::first();

        // 管理者が承認する
        $admin = User::factory()->create(['admin_status' => true]);
        $this->actingAs($admin)->post('/stamp_correction_request/approve/'.$application->id);

        // 3. 申請一覧画面を開く
        $response = $this->actingAs($this->user)->get('/stamp_correction_request/list');

        // 4. 管理者が承認した修正申請が全て表示されていることを確認
        $response->assertStatus(200);
        $response->assertSee('<p class="table__description--item">承認済み</p>', false);
        $response->assertDontSee('<p class="table__description--item">承認待ち</p>', false);
        $response->assertSee('2026/11/04');
        $response->assertSee('電車遅延のため');
    }

    public function test_各申請の詳細を押下すると勤怠詳細画面に遷移する(): void
    {
        // 2. 勤怠詳細を修正し保存処理をする
        $this->submitCorrection($this->record);
        $application = Application::first();

        // 3. 申請一覧画面を開く（「詳細」ボタンのリンク先を確認）
        $response = $this->actingAs($this->user)->get('/stamp_correction_request/list');
        $response->assertSee(url('/application/'.$application->id), false);

        // 4. 「詳細」ボタンを押す（提供Bladeのリンク先から勤怠詳細へ転送される）
        $response = $this->actingAs($this->user)->get('/application/'.$application->id);
        $response->assertRedirect('/attendance/detail/'.$this->record->id);

        $response = $this->actingAs($this->user)->get('/attendance/detail/'.$this->record->id);
        $response->assertStatus(200);
        $response->assertSee('11月4日');
    }
}
