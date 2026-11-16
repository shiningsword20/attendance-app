<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'admin_status' => 'boolean',
    ];

    /**
     * ユーザーの勤怠
     *
     * @return HasMany<AttendanceRecord>
     */
    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    /**
     * 今日の勤怠（無ければ null）
     *
     * @return AttendanceRecord|null 今日の勤怠
     */
    public function todayAttendanceRecord(): ?AttendanceRecord
    {
        return $this->attendanceRecords()
            ->where('date', today()->toDateString())
            ->first();
    }

    /**
     * 今日の勤怠ステータス（勤務外・出勤中・休憩中・退勤済）
     *
     * @return string 勤怠ステータス
     */
    public function getAttendanceStatusAttribute(): string
    {
        $todayRecord = $this->todayAttendanceRecord();

        if (! $todayRecord) {
            return '勤務外';
        }

        if ($todayRecord->clock_out) {
            return '退勤済';
        }

        if ($todayRecord->breaks()->whereNull('break_out')->exists()) {
            return '休憩中';
        }

        return '出勤中';
    }
}
