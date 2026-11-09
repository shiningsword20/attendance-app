<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// ID1 認証機能（一般ユーザー）
class RegisterTest extends TestCase
{
    use RefreshDatabase;

    // 正しい入力値。各テストで一部だけ上書きして使う
    private function formData(array $overrides = []): array
    {
        return array_merge([
            'name' => 'テスト太郎',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ], $overrides);
    }

    public function test_名前が未入力の場合_バリデーションメッセージが表示される(): void
    {
        $response = $this->from('/register')->post('/register', $this->formData(['name' => '']));

        $response->assertRedirect('/register');
        $response->assertSessionHasErrors(['name' => 'お名前を入力してください']);
    }

    public function test_メールアドレスが未入力の場合_バリデーションメッセージが表示される(): void
    {
        $response = $this->from('/register')->post('/register', $this->formData(['email' => '']));

        $response->assertRedirect('/register');
        $response->assertSessionHasErrors(['email' => 'メールアドレスを入力してください']);
    }

    public function test_パスワードが8文字未満の場合_バリデーションメッセージが表示される(): void
    {
        $response = $this->from('/register')->post('/register', $this->formData([
            'password' => 'pass123',
            'password_confirmation' => 'pass123',
        ]));

        $response->assertRedirect('/register');
        $response->assertSessionHasErrors(['password' => 'パスワードは8文字以上で入力してください']);
    }

    public function test_パスワードが一致しない場合_バリデーションメッセージが表示される(): void
    {
        $response = $this->from('/register')->post('/register', $this->formData([
            'password_confirmation' => 'different123',
        ]));

        $response->assertRedirect('/register');
        $response->assertSessionHasErrors(['password_confirmation' => 'パスワードと一致しません']);
    }

    public function test_パスワードが未入力の場合_バリデーションメッセージが表示される(): void
    {
        $response = $this->from('/register')->post('/register', $this->formData([
            'password' => '',
            'password_confirmation' => '',
        ]));

        $response->assertRedirect('/register');
        $response->assertSessionHasErrors(['password' => 'パスワードを入力してください']);
    }

    public function test_フォームに内容が入力されていた場合_データが正常に保存される(): void
    {
        $response = $this->post('/register', $this->formData());

        // 会員登録後は打刻画面へ遷移する（FN005）
        $response->assertRedirect('/attendance');
        $this->assertDatabaseHas('users', [
            'name' => 'テスト太郎',
            'email' => 'test@example.com',
        ]);
        $this->assertAuthenticated();
    }
}
