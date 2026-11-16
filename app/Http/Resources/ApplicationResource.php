<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApplicationResource extends JsonResource
{
    /**
     * 修正申請をAPIのレスポンス用の配列にする
     *
     * @param  Request  $request  リクエスト
     * @return array<string, mixed> 修正申請のJSON
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'clock_in' => $this->clock_in,
            'clock_out' => $this->clock_out,
            'comment' => $this->comment,
            'is_approved' => $this->is_approved,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}
