<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

// ID16 メール認証機能
class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_会員登録後_認証メールが送信される(): void
    {
        Notification::fake();

        $this->post('/register', [
            'name' => 'テスト太郎',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $user = User::where('email', 'test@example.com')->first();

        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_メール未認証の場合_勤怠登録画面を開くとメール認証誘導画面に遷移する(): void
    {
        $user = User::factory()->unverified()->create();

        $response = $this->actingAs($user)->get('/attendance');

        $response->assertRedirect('/email/verify');
    }

    public function test_メール認証誘導画面の認証はこちらからボタンで_メール認証サイトに遷移する(): void
    {
        $user = User::factory()->unverified()->create();

        $response = $this->actingAs($user)->get('/email/verify');

        $response->assertStatus(200);
        $response->assertSee('<a class="verify__link" href="http://localhost:8025"', false);
        $response->assertSee('認証はこちらから');
    }

    public function test_メール認証を完了すると_勤怠登録画面に遷移する(): void
    {
        $user = User::factory()->unverified()->create();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        $response = $this->actingAs($user)->get($verificationUrl);

        $response->assertRedirect('/attendance?verified=1');
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $this->actingAs($user->fresh())->get('/attendance')->assertStatus(200);
    }

    public function test_認証メールを再送するボタンで_認証メールが再送される(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();

        $response = $this->actingAs($user)
            ->from('/email/verify')
            ->post('/email/verification-notification');

        $response->assertRedirect('/email/verify');
        $response->assertSessionHas('message', '認証メールを再送しました');
        Notification::assertSentTo($user, VerifyEmail::class);
    }
}
