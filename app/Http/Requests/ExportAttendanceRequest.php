<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ExportAttendanceRequest extends FormRequest
{
    /**
     * CSV出力は管理者のみ許可する
     */
    public function authorize(): bool
    {
        return (bool) $this->user()->admin_status;
    }

    /**
     * 出力対象のスタッフと年月を検証する
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'year_month' => ['required', 'date_format:Y-m'],
        ];
    }
}
