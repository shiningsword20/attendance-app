<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Application extends Model
{
    use HasFactory;

    protected $fillable = [
        'attendance_record_id',
        'clock_in',
        'clock_out',
        'comment',
    ];

    protected $casts = [
        'is_approved' => 'boolean',
    ];

    public function attendanceRecord()
    {
        return $this->belongsTo(AttendanceRecord::class);
    }

    public function applicationBreaks()
    {
        return $this->hasMany(ApplicationBreak::class);
    }

    // 承認画面のBladeで使う名前の休憩リレーション
    public function proposalBreaks()
    {
        return $this->hasMany(ApplicationBreak::class);
    }

    // 申請対象の日付
    public function getNewDateAttribute()
    {
        return $this->attendanceRecord->date;
    }

    // 申請した出勤・退勤時間（H:i で表示）
    public function getNewClockInAttribute()
    {
        return Carbon::parse($this->clock_in)->format('H:i');
    }

    public function getNewClockOutAttribute()
    {
        return Carbon::parse($this->clock_out)->format('H:i');
    }

    // 承認状態の表示用
    public function getApprovalStatusAttribute()
    {
        return $this->is_approved ? '承認済み' : '承認待ち';
    }

    // 申請者（勤怠の持ち主）を、勤怠レコード経由で取得する
    public function user()
    {
        return $this->hasOneThrough(User::class, AttendanceRecord::class, 'id', 'id', 'attendance_record_id', 'user_id');
    }

    // 申請日時（作成日時）
    public function getApplicationDateAttribute()
    {
        return $this->created_at;
    }
}
