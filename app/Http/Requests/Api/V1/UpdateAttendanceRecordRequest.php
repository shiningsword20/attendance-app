<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Validation\Rule;

class UpdateAttendanceRecordRequest extends StoreAttendanceRecordRequest
{
    /**
     * 更新する勤怠を検証する（送信された項目だけ。date の重複チェックは自分自身を除く）
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $attendanceRecord = $this->route('attendanceRecord');

        // clock_in を送らない場合は、登録済みの出勤時刻と比べる
        $clockInToCompare = $this->has('clock_in') ? 'clock_in' : $attendanceRecord->clock_in;

        return [
            'date' => [
                'sometimes',
                'required',
                'date_format:Y-m-d',
                Rule::unique('attendance_records')
                    ->where('user_id', $attendanceRecord->user_id)
                    ->ignore($attendanceRecord),
            ],
            'clock_in' => ['sometimes', 'required', 'date_format:H:i:s'],
            'clock_out' => ['nullable', 'date_format:H:i:s', 'after:' . $clockInToCompare],
            'comment' => ['nullable', 'string', 'max:255'],
        ];
    }
}
