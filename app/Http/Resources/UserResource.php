<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * ユーザーをAPIのレスポンス用の配列にする（IDと名前のみ）
     *
     * @param  Request  $request  リクエスト
     * @return array<string, mixed> ユーザーのJSON
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
        ];
    }
}
