<?php

namespace App\Http\Controllers;

use App\Models\Application;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ApplicationController extends Controller
{
    /**
     * 申請一覧を表示する（管理者は全ユーザー分、一般ユーザーは自分の分）
     *
     * @return View 申請一覧画面
     */
    public function index(): View
    {
        $user = Auth::user();

        // 管理者：全ユーザーの申請を、新しい順に取得する
        // ★提供Bladeが $applications（モデルのまま）を使うので、配列に整えずに渡す
        if ($user->admin_status) {
            $applications = Application::with(['attendanceRecord', 'user'])
                ->latest()
                ->get();

            return view('admin.admin-application-list', compact('applications'));
        }

        // 一般ユーザー：自分の勤怠に対する申請を、新しい順に取得する
        $applications = Application::with('attendanceRecord')
            ->whereHas('attendanceRecord', fn ($query) => $query->where('user_id', $user->id))
            ->latest()
            ->get();

        $formattedApplications = $applications->map(fn ($application) => [
            'id' => $application->id, // ★提供Bladeの詳細リンク（/application/{id}）で使う
            'attendance_record_id' => $application->attendance_record_id,
            'approval_status' => $application->is_approved ? '承認済み' : '承認待ち',
            'date' => $application->attendanceRecord->date->format('Y/m/d'),
            'comment' => $application->comment,
            'application_date' => $application->created_at->format('Y/m/d'),
        ]);

        return view('user.user-application-list', compact('user', 'formattedApplications'));
    }

    /**
     * 修正申請の承認画面（管理者）を表示する
     *
     * @param  string  $attendance_correct_request_id  修正申請ID
     * @return View 修正申請承認画面
     */
    public function show(string $attendance_correct_request_id): View
    {
        // 管理者以外は見られないようにする
        abort_unless(Auth::user()->admin_status, 403);

        $application = Application::with(['attendanceRecord.user', 'proposalBreaks'])
            ->findOrFail($attendance_correct_request_id);

        // 名前欄には、申請したスタッフの名前を表示する
        $user = $application->attendanceRecord->user;

        return view('admin.admin-application-detail', compact('user', 'application'));
    }

    /**
     * 修正申請を承認し、勤怠と休憩を申請の内容で更新する（管理者）
     *
     * @param  string  $attendance_correct_request_id  修正申請ID
     * @return RedirectResponse 修正申請承認画面へのリダイレクト
     */
    public function approve(string $attendance_correct_request_id): RedirectResponse
    {
        // 管理者以外は承認できないようにする
        abort_unless(Auth::user()->admin_status, 403);

        $application = Application::with(['attendanceRecord', 'applicationBreaks'])
            ->findOrFail($attendance_correct_request_id);

        // 承認済みなら何もしない
        if ($application->is_approved) {
            return redirect("/stamp_correction_request/approve/{$application->id}");
        }

        DB::transaction(function () use ($application) {
            $attendanceRecord = $application->attendanceRecord;

            // 勤怠を申請の内容で上書きする
            $attendanceRecord->update([
                'clock_in' => $application->clock_in,
                'clock_out' => $application->clock_out,
                'comment' => $application->comment,
            ]);

            // 休憩は入れ替える
            $attendanceRecord->breaks()->delete();
            $attendanceRecord->breaks()->createMany(
                $application->applicationBreaks->map(fn ($break) => [
                    'break_in' => $break->break_in,
                    'break_out' => $break->break_out,
                ])->all()
            );

            // 申請を承認済みにする
            $application->is_approved = true;
            $application->save();
        });

        return redirect("/stamp_correction_request/approve/{$application->id}");
    }

    /**
     * 申請一覧の「詳細」から、申請対象の勤怠詳細画面へ転送する
     *
     * @param  string  $id  修正申請ID
     * @return RedirectResponse 勤怠詳細画面へのリダイレクト
     */
    public function redirectToAttendance(string $id): RedirectResponse
    {
        // 自分の申請だけ開けるようにする
        $application = Application::whereHas('attendanceRecord', fn ($query) => $query->where('user_id', Auth::id()))
            ->findOrFail($id);

        return redirect("/attendance/detail/{$application->attendance_record_id}");
    }
}
