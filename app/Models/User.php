<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, Notifiable;

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

    public function attendanceRecords()
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    public function todayAttendanceRecord()
    {
        return $this->attendanceRecords()
            ->where('date', today()->toDateString())
            ->first();
    }

    public function getAttendanceStatusAttribute()
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
