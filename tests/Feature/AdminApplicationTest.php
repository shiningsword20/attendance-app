<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\ApplicationBreak;
use App\Models\AttendanceRecord;
use App\Models\BreakTime;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// ID15 勤怠情報修正機能（管理者）
class AdminApplicationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $userA;

    private User $userB;

    // 状態の欄に表示されるタグ（提供Bladeのタグそのまま）
    private const PENDING = '<p class="table__description--item">承認待ち</p>';

    private const APPROVED = '<p class="table__description--item">承認済み</p>';

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::create(2026, 11, 9, 12, 0, 0));

        // 1. 管理者ユーザーにログインをする
        $this->admin = User::factory()->create(['admin_status' => true]);

        $this->userA = User::factory()->create(['name' => 'ユーザーA']);
        $this->userB = User::factory()->create(['name' => 'ユーザーB']);
    }

    // 勤怠（09:00〜18:00、休憩12:00〜13:00）と、その修正申請（09:30〜18:30、休憩12:15〜13:15）を作る
    // ※ createApplication() は Laravel のテスト基盤（CreatesApplication）で使われているので別名にする
    private function createCorrectionRequest(User $user, string $date, string $comment, bool $isApproved = false): Application
    {
        $record = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => $date,
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        BreakTime::create([
            'attendance_record_id' => $record->id,
            'break_in' => '12:00:00',
            'break_out' => '13:00:00',
        ]);

        $application = Application::create([
            'attendance_record_id' => $record->id,
            'clock_in' => '09:30:00',
            'clock_out' => '18:30:00',
            'comment' => $comment,
        ]);

        ApplicationBreak::create([
            'application_id' => $application->id,
            'break_in' => '12:15:00',
            'break_out' => '13:15:00',
        ]);

        // is_approved は $fillable に無いので、プロパティに直接入れて保存する
        if ($isApproved) {
            $application->is_approved = true;
            $application->save();
        }

        return $application;
    }

    public function test_承認待ちの修正申請が全て表示されている(): void
    {
        $this->createCorrectionRequest($this->userA, '2026-11-04', '電車遅延のため');
        $this->createCorrectionRequest($this->userB, '2026-11-05', '打刻忘れのため');

        // 2. 修正申請一覧ページを開き、承認待ちのタブを開く
        $response = $this->actingAs($this->admin)->get('/stamp_correction_request/list');

        // 全ユーザーの未承認の修正申請が表示される
        $response->assertStatus(200);
        $this->assertSame(2, substr_count($response->getContent(), self::PENDING));
        $response->assertSee('ユーザーA');
        $response->assertSee('2026/11/04');
        $response->assertSee('電車遅延のため');
        $response->assertSee('ユーザーB');
        $response->assertSee('2026/11/05');
        $response->assertSee('打刻忘れのため');
    }

    public function test_承認済みの修正申請が全て表示されている(): void
    {
        $this->createCorrectionRequest($this->userA, '2026-11-04', '電車遅延のため', true);
        $this->createCorrectionRequest($this->userB, '2026-11-05', '打刻忘れのため', true);

        // 2. 修正申請一覧ページを開き、承認済みのタブを開く
        $response = $this->actingAs($this->admin)->get('/stamp_correction_request/list');

        // 全ユーザーの承認済みの修正申請が表示される
        $response->assertStatus(200);
        $this->assertSame(2, substr_count($response->getContent(), self::APPROVED));
        $this->assertSame(0, substr_count($response->getContent(), self::PENDING));
        $response->assertSee('ユーザーA');
        $response->assertSee('電車遅延のため');
        $response->assertSee('ユーザーB');
        $response->assertSee('打刻忘れのため');
    }

    public function test_修正申請の詳細内容が正しく表示されている(): void
    {
        $application = $this->createCorrectionRequest($this->userA, '2026-11-04', '電車遅延のため');

        // 2. 修正申請の詳細画面を開く
        $response = $this->actingAs($this->admin)->get('/stamp_correction_request/approve/'.$application->id);

        // 申請内容が正しく表示されている
        $response->assertStatus(200);
        $response->assertSee('value="ユーザーA"', false);
        $response->assertSee('value="2026年"', false);
        $response->assertSee('value="11月4日"', false);
        $response->assertSeeInOrder(['value="09:30"', 'value="18:30"', 'value="12:15"', 'value="13:15"'], false);
        $response->assertSee('電車遅延のため');
    }

    public function test_修正申請の承認処理が正しく行われる(): void
    {
        $application = $this->createCorrectionRequest($this->userA, '2026-11-04', '電車遅延のため');

        // 2. 修正申請の詳細画面で「承認」ボタンを押す
        $response = $this->actingAs($this->admin)->post('/stamp_correction_request/approve/'.$application->id);
        $response->assertRedirect('/stamp_correction_request/approve/'.$application->id);

        // 修正申請が承認される
        $this->assertDatabaseHas('applications', [
            'id' => $application->id,
            'is_approved' => true,
        ]);

        // 勤怠情報が申請内容で更新される
        $this->assertDatabaseHas('attendance_records', [
            'id' => $application->attendance_record_id,
            'clock_in' => '09:30:00',
            'clock_out' => '18:30:00',
            'comment' => '電車遅延のため',
        ]);

        // 休憩も申請内容に入れ替わる
        $this->assertDatabaseHas('break_times', [
            'attendance_record_id' => $application->attendance_record_id,
            'break_in' => '12:15:00',
            'break_out' => '13:15:00',
        ]);
        $this->assertDatabaseMissing('break_times', [
            'attendance_record_id' => $application->attendance_record_id,
            'break_in' => '12:00:00',
        ]);
    }
}
