<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Http\Requests\LoginRequest;
use App\Http\Responses\LoginResponse;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\Http\Requests\LoginRequest as FortifyLoginRequest;

class FortifyServiceProvider extends ServiceProvider
{
    public function register()
    {
        // Fortify標準のLoginRequestを、自作のFormRequestに差し替える
        $this->app->bind(FortifyLoginRequest::class, LoginRequest::class);

        // ログイン後の遷移先を、一般・管理者で出し分ける
        $this->app->singleton(LoginResponseContract::class, LoginResponse::class);
    }

    public function boot()
    {
        Fortify::createUsersUsing(CreateNewUser::class);

        Fortify::registerView(function () {
            return view('user.register');
        });

        Fortify::loginView(function (Request $request) {
            if ($request->is('admin/*')) {
                return view('admin.admin-login');
            }

            return view('user.user-login');
        });

        Fortify::authenticateUsing(function (Request $request) {
            $user = User::where('email', $request->email)->first();

            $isValidUser = $user && Hash::check($request->password, $user->password);
            $isAdminLogin = $request->is('admin/*');

            if (! $isValidUser || ($isAdminLogin && ! $user->admin_status)) {
                throw ValidationException::withMessages([
                    'email' => 'ログイン情報が登録されていません',
                ]);
            }

            return $user;
        });

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())) . '|' . $request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });
    }
}
