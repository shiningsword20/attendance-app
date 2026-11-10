<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AttendanceRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'date',
        'clock_in',
        'clock_out',
        'comment',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function breaks()
    {
        return $this->hasMany(BreakTime::class);
    }

    public function applications()
    {
        return $this->hasMany(Application::class);
    }

    public function getTotalBreakTimeAttribute()
    {
        return $this->formatMinutes($this->breakMinutes());
    }

    public function getTotalTimeAttribute()
    {
        $workMinutes = $this->workMinutes();

        return $workMinutes === null ? null : $this->formatMinutes($workMinutes);
    }

    /**
     * 休憩を除いた労働時間（分）を返す。退勤していない場合は null
     *
     * @return int|null 労働時間（分）
     */
    public function workMinutes(): ?int
    {
        if (! $this->clock_out) {
            return null;
        }

        $stayMinutes = Carbon::parse($this->clock_in)->diffInMinutes(Carbon::parse($this->clock_out));

        return $stayMinutes - $this->breakMinutes();
    }

    private function breakMinutes()
    {
        return $this->breaks
            ->filter(fn ($break) => $break->break_out)
            ->sum(fn ($break) => Carbon::parse($break->break_in)->diffInMinutes(Carbon::parse($break->break_out)));
    }

    private function formatMinutes($minutes)
    {
        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }
}
