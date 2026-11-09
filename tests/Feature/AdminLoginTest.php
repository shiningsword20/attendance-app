<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// ID3 ログイン認証機能（管理者）
class AdminLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. 管理者ユーザーを登録する
        User::factory()->create([
            'email' => 'admin@example.com',
            'password' => 'password123',
            'admin_status' => true,
        ]);
    }

    public function test_メールアドレスが未入力の場合_バリデーションメッセージが表示される(): void
    {
        $response = $this->from('/admin/login')->post('/admin/login', [
            'email' => '',
            'password' => 'password123',
        ]);

        $response->assertRedirect('/admin/login');
        $response->assertSessionHasErrors(['email' => 'メールアドレスを入力してください']);
        $this->assertGuest();
    }

    public function test_パスワードが未入力の場合_バリデーションメッセージが表示される(): void
    {
        $response = $this->from('/admin/login')->post('/admin/login', [
            'email' => 'admin@example.com',
            'password' => '',
        ]);

        $response->assertRedirect('/admin/login');
        $response->assertSessionHasErrors(['password' => 'パスワードを入力してください']);
        $this->assertGuest();
    }

    public function test_登録内容と一致しない場合_バリデーションメッセージが表示される(): void
    {
        $response = $this->from('/admin/login')->post('/admin/login', [
            'email' => 'wrong@example.com',
            'password' => 'password123',
        ]);

        $response->assertRedirect('/admin/login');
        $response->assertSessionHasErrors(['email' => 'ログイン情報が登録されていません']);
        $this->assertGuest();
    }
}
