<?php

namespace App\Policies;

use App\Models\AttendanceRecord;
use App\Models\User;

class AttendanceRecordPolicy
{
    /**
     * 管理者はすべての操作を許可する（一般ユーザーは各メソッドで判定）
     *
     * @param  User  $user  ログインユーザー
     * @param  string  $ability  判定する操作名
     * @return bool|null 管理者なら true、それ以外は null（各メソッドの判定へ進む）
     */
    public function before(User $user, string $ability): ?bool
    {
        return $user->admin_status ? true : null;
    }

    /**
     * 勤怠を更新できるのは本人のみ
     *
     * @param  User  $user  ログインユーザー
     * @param  AttendanceRecord  $attendanceRecord  更新対象の勤怠
     * @return bool 本人なら true
     */
    public function update(User $user, AttendanceRecord $attendanceRecord): bool
    {
        return $user->id === $attendanceRecord->user_id;
    }

    /**
     * 勤怠を削除できるのは本人のみ
     *
     * @param  User  $user  ログインユーザー
     * @param  AttendanceRecord  $attendanceRecord  削除対象の勤怠
     * @return bool 本人なら true
     */
    public function delete(User $user, AttendanceRecord $attendanceRecord): bool
    {
        return $user->id === $attendanceRecord->user_id;
    }
}
