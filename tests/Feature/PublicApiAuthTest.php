<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

// ID19 Sanctum 認証
class PublicApiAuthTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private AttendanceRecord $record;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->record = AttendanceRecord::create([
            'user_id' => $this->owner->id,
            'date' => '2026-11-02',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);
    }

    public function test_未認証の場合_書き込み系で401が返る(): void
    {
        $url = '/api/v1/attendance-records/' . $this->record->id;

        $this->postJson('/api/v1/attendance-records', [])
            ->assertStatus(401)
            ->assertExactJson(['message' => 'Unauthenticated.']);
        $this->putJson($url, [])
            ->assertStatus(401)
            ->assertExactJson(['message' => 'Unauthenticated.']);
        $this->deleteJson($url)
            ->assertStatus(401)
            ->assertExactJson(['message' => 'Unauthenticated.']);
    }

    public function test_認証済みユーザーは自分の勤怠を更新_削除できる(): void
    {
        Sanctum::actingAs($this->owner, ['*']);
        $url = '/api/v1/attendance-records/' . $this->record->id;

        $this->putJson($url, ['comment' => '修正'])->assertStatus(200);
        $this->deleteJson($url)->assertStatus(204);
    }

    public function test_他ユーザーの勤怠を更新_削除しようとすると403が返る(): void
    {
        Sanctum::actingAs(User::factory()->create(), ['*']);
        $url = '/api/v1/attendance-records/' . $this->record->id;

        $this->putJson($url, ['comment' => '修正'])
            ->assertStatus(403)
            ->assertExactJson(['error' => 'この操作を実行する権限がありません。']);
        $this->deleteJson($url)
            ->assertStatus(403)
            ->assertExactJson(['error' => 'この操作を実行する権限がありません。']);
    }
}
