<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

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

    /**
     * 申請対象の勤怠
     *
     * @return BelongsTo<AttendanceRecord, Application>
     */
    public function attendanceRecord(): BelongsTo
    {
        return $this->belongsTo(AttendanceRecord::class);
    }

    /**
     * 申請した休憩
     *
     * @return HasMany<ApplicationBreak>
     */
    public function applicationBreaks(): HasMany
    {
        return $this->hasMany(ApplicationBreak::class);
    }

    /**
     * 承認画面のBladeで使う名前の休憩リレーション（applicationBreaks と同じ）
     *
     * @return HasMany<ApplicationBreak>
     */
    public function proposalBreaks(): HasMany
    {
        return $this->hasMany(ApplicationBreak::class);
    }

    /**
     * 申請対象の日付
     *
     * @return Carbon 勤怠の日付
     */
    public function getNewDateAttribute(): Carbon
    {
        return $this->attendanceRecord->date;
    }

    /**
     * 申請した出勤時間（H:i）
     *
     * @return string 出勤時間
     */
    public function getNewClockInAttribute(): string
    {
        return Carbon::parse($this->clock_in)->format('H:i');
    }

    /**
     * 申請した退勤時間（H:i）
     *
     * @return string 退勤時間
     */
    public function getNewClockOutAttribute(): string
    {
        return Carbon::parse($this->clock_out)->format('H:i');
    }

    /**
     * 承認状態の表示用の文字列
     *
     * @return string 承認済み／承認待ち
     */
    public function getApprovalStatusAttribute(): string
    {
        return $this->is_approved ? '承認済み' : '承認待ち';
    }

    /**
     * 申請者（勤怠の持ち主）を、勤怠レコード経由で取得する
     *
     * @return HasOneThrough<User>
     */
    public function user(): HasOneThrough
    {
        return $this->hasOneThrough(User::class, AttendanceRecord::class, 'id', 'id', 'attendance_record_id', 'user_id');
    }

    /**
     * 申請日時（作成日時）
     *
     * @return Carbon|null 申請日時
     */
    public function getApplicationDateAttribute(): ?Carbon
    {
        return $this->created_at;
    }
}
