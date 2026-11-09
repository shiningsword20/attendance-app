<?php

namespace App\Http\Responses;

use Illuminate\Http\RedirectResponse;
use Laravel\Fortify\Contracts\EmailVerificationNotificationSentResponse as EmailVerificationNotificationSentResponseContract;

class EmailVerificationNotificationSentResponse implements EmailVerificationNotificationSentResponseContract
{
    /**
     * 認証メールを再送したあと、メール認証誘導画面に戻してメッセージを表示する
     *
     * @param  \Illuminate\Http\Request  $request
     */
    public function toResponse($request): RedirectResponse
    {
        return back()->with('message', '認証メールを再送しました');
    }
}
