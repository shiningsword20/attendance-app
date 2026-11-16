<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class IndexAttendanceRecordRequest extends FormRequest
{
    /**
     * 一覧取得は認証不要なので、誰でも許可する
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * 絞り込み条件とページ指定を検証する（すべて任意）
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['nullable', 'integer'],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'month' => ['nullable', 'date_format:Y-m'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * バリデーションエラーのメッセージ
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'user_id.integer' => 'ユーザーIDは整数で指定してください。',
            'date.date_format' => '日付は YYYY-MM-DD 形式で指定してください。',
            'month.date_format' => '年月は YYYY-MM 形式で指定してください。',
            'page.integer' => 'ページ番号は整数で指定してください。',
            'page.min' => 'ページ番号は1以上で指定してください。',
            'per_page.integer' => '1ページあたりの件数は整数で指定してください。',
            'per_page.min' => '1ページあたりの件数は1以上で指定してください。',
            'per_page.max' => '1ページあたりの件数は100以下で指定してください。',
        ];
    }
}
