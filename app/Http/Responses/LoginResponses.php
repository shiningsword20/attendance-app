<?php

namespace App\Http\Responses;

use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

class LoginResponse implements LoginResponseContract
{
    public function toResponse($request)
    {
        if ($request->user()->admin_status) {
            return redirect('/admin/attendance/list');
        }

        return redirect('/attendance');
    }
}
