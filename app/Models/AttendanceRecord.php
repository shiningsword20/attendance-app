<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    /**
     * 勤怠の持ち主
     *
     * @return BelongsTo<User, AttendanceRecord>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * この勤怠の休憩
     *
     * @return HasMany<BreakTime>
     */
    public function breaks(): HasMany
    {
        return $this->hasMany(BreakTime::class);
    }

    /**
     * この勤怠に対する修正申請
     *
     * @return HasMany<Application>
     */
    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }

    /**
     * 休憩時間の合計（HH:MM）
     *
     * @return string 休憩時間の合計
     */
    public function getTotalBreakTimeAttribute(): string
    {
        return $this->formatMinutes($this->breakMinutes());
    }

    /**
     * 休憩を除いた労働時間（HH:MM）。退勤していない場合は null
     *
     * @return string|null 労働時間
     */
    public function getTotalTimeAttribute(): ?string
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

    /**
     * 終了済みの休憩時間の合計（分）
     *
     * @return int 休憩時間（分）
     */
    private function breakMinutes(): int
    {
        return $this->breaks
            ->filter(fn ($break) => $break->break_out)
            ->sum(fn ($break) => Carbon::parse($break->break_in)->diffInMinutes(Carbon::parse($break->break_out)));
    }

    /**
     * 分を HH:MM 形式にする
     *
     * @param  int  $minutes  分
     * @return string HH:MM 形式の時間
     */
    private function formatMinutes(int $minutes): string
    {
        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }
}
