<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AttendanceCorrectionRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'new_clock_in' => ['required', 'date_format:H:i'],
            'new_clock_out' => ['required', 'date_format:H:i'],
            'new_break_in.*' => ['nullable', 'required_with:new_break_out.*', 'date_format:H:i'],
            'new_break_out.*' => ['nullable', 'required_with:new_break_in.*', 'date_format:H:i'],
            'comment' => ['required', 'max:255'],
        ];
    }

    public function messages()
    {
        return [
            'new_clock_in.required' => '出勤時間を入力してください',
            'new_clock_in.date_format' => '出勤時間は00:00の形式で入力してください',
            'new_clock_out.required' => '退勤時間を入力してください',
            'new_clock_out.date_format' => '退勤時間は00:00の形式で入力してください',
            'new_break_in.*.required_with' => '休憩の開始時間を入力してください',
            'new_break_in.*.date_format' => '休憩時間は00:00の形式で入力してください',
            'new_break_out.*.required_with' => '休憩の終了時間を入力してください',
            'new_break_out.*.date_format' => '休憩時間は00:00の形式で入力してください',
            'comment.required' => '備考を記入してください',
            'comment.max' => '備考は255文字以内で入力してください',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->hasAny(['new_clock_in', 'new_clock_out'])) {
                return;
            }

            $clockIn = $this->input('new_clock_in');
            $clockOut = $this->input('new_clock_out');

            if ($clockIn >= $clockOut) {
                $validator->errors()->add('new_clock_in', '出勤時間もしくは退勤時間が不適切な値です');

                return;
            }

            foreach ($this->input('new_break_in', []) as $index => $breakIn) {
                $breakOut = $this->input("new_break_out.{$index}");

                if ($breakIn && ($breakIn < $clockIn || $breakIn > $clockOut)) {
                    $validator->errors()->add("new_break_in.{$index}", '休憩時間が不適切な値です');
                }

                if ($breakOut && $breakOut > $clockOut) {
                    $validator->errors()->add("new_break_out.{$index}", '休憩時間もしくは退勤時間が不適切な値です');
                }
            }
        });
    }
}
