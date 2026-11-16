<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttendanceBreakResource extends JsonResource
{
    /**
     * 休憩をAPIのレスポンス用の配列にする
     *
     * @param  Request  $request  リクエスト
     * @return array<string, mixed> 休憩のJSON
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'break_in' => $this->break_in,
            'break_out' => $this->break_out,
        ];
    }
}
