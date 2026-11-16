<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// ID17 公開API 読み取り系
class PublicApiReadTest extends TestCase
{
    use RefreshDatabase;

    public function test_勤怠一覧が取得できる(): void
    {
        $this->seed();

        $response = $this->getJson('/api/v1/attendance-records');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [['id', 'user_id', 'date', 'clock_in', 'clock_out', 'total_time', 'total_break_time', 'comment']],
            'meta' => ['current_page', 'last_page', 'per_page', 'total'],
        ]);
        $response->assertJsonPath('meta.per_page', 20);
    }

    public function test_勤怠詳細が取得できる(): void
    {
        $user = User::factory()->create(['name' => 'テスト太郎']);
        $record = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => '2026-11-02',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);
        $record->breaks()->create(['break_in' => '12:00:00', 'break_out' => '13:00:00']);
        $record->applications()->create(['clock_in' => '09:30:00', 'clock_out' => '18:00:00', 'comment' => '電車遅延のため']);

        $response = $this->getJson('/api/v1/attendance-records/'.$record->id);

        $response->assertStatus(200);
        $response->assertJsonPath('data.id', $record->id);
        $response->assertJsonPath('data.user.name', 'テスト太郎');
        $response->assertJsonPath('data.date', '2026-11-02');
        $response->assertJsonPath('data.breaks.0.break_in', '12:00:00');
        $response->assertJsonPath('data.applications.0.comment', '電車遅延のため');
    }

    public function test_存在しない勤怠では404とエラーが返る(): void
    {
        $response = $this->getJson('/api/v1/attendance-records/99999');

        $response->assertStatus(404);
        $response->assertExactJson(['error' => '勤怠情報が見つかりませんでした。']);
    }
}
