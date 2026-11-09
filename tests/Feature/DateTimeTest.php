<?php

namespace Tests\Feature;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// ID4 日時取得機能
class DateTimeTest extends TestCase
{
    use RefreshDatabase;

    public function test_現在の日時情報がUIと同じ形式で出力されている(): void
    {
        // 現在日時を固定する（2026年11月4日(水) 09:30）
        $this->travelTo(Carbon::create(2026, 11, 4, 9, 30, 0));

        $user = User::factory()->create();

        // 1. 勤怠打刻画面を開く
        $response = $this->actingAs($user)->get('/attendance');

        // 2. 画面に表示されている日時情報を確認する
        $response->assertStatus(200);
        $response->assertSee('2026年11月4日(水)');
        $response->assertSee('09:30');
    }
}
