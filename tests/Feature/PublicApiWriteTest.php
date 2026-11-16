<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

// ID18 公開API 書き込み系
class PublicApiWriteTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user, ['*']);
    }

    private function createRecord(): AttendanceRecord
    {
        return AttendanceRecord::create([
            'user_id' => $this->user->id,
            'date' => '2026-11-02',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);
    }

    public function test_勤怠が登録される(): void
    {
        $response = $this->postJson('/api/v1/attendance-records', [
            'date' => '2026-11-02',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
            'comment' => '通常勤務',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('attendance_records', [
            'user_id' => $this->user->id,
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
            'comment' => '通常勤務',
        ]);
    }

    public function test_必須項目が無い場合_422と日本語のエラーメッセージが返る(): void
    {
        $response = $this->postJson('/api/v1/attendance-records', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors([
            'date' => '勤怠日は必須です。',
            'clock_in' => '出勤時刻は必須です。',
        ]);
    }

    public function test_勤怠が更新される(): void
    {
        $record = $this->createRecord();

        $response = $this->putJson('/api/v1/attendance-records/'.$record->id, [
            'clock_out' => '19:00:00',
            'comment' => '残業',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('attendance_records', [
            'id' => $record->id,
            'clock_in' => '09:00:00',
            'clock_out' => '19:00:00',
            'comment' => '残業',
        ]);
    }

    public function test_存在しない勤怠を更新すると404が返る(): void
    {
        $response = $this->putJson('/api/v1/attendance-records/99999', ['comment' => '残業']);

        $response->assertStatus(404);
    }

    public function test_勤怠が削除される(): void
    {
        $record = $this->createRecord();

        $response = $this->deleteJson('/api/v1/attendance-records/'.$record->id);

        $response->assertStatus(204);
        $this->assertDatabaseMissing('attendance_records', ['id' => $record->id]);
    }

    public function test_存在しない勤怠を削除すると404が返る(): void
    {
        $response = $this->deleteJson('/api/v1/attendance-records/99999');

        $response->assertStatus(404);
    }
}
