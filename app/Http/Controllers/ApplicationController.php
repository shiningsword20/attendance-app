<?php

namespace App\Http\Controllers;

use App\Models\Application;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ApplicationController extends Controller
{
    public function index()
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
            ->whereHas('attendanceRecord', fn($query) => $query->where('user_id', $user->id))
            ->latest()
            ->get();

        $formattedApplications = $applications->map(fn($application) => [
            'id' => $application->id, // ★提供Bladeの詳細リンク（/application/{id}）で使う
            'attendance_record_id' => $application->attendance_record_id,
            'approval_status' => $application->is_approved ? '承認済み' : '承認待ち',
            'date' => $application->attendanceRecord->date->format('Y/m/d'),
            'comment' => $application->comment,
            'application_date' => $application->created_at->format('Y/m/d'),
        ]);

        return view('user.user-application-list', compact('user', 'formattedApplications'));
    }

    public function show($attendance_correct_request_id)
    {
        // 管理者以外は見られないようにする
        abort_unless(Auth::user()->admin_status, 403);

        $application = Application::with(['attendanceRecord.user', 'proposalBreaks'])
            ->findOrFail($attendance_correct_request_id);

        // 名前欄には、申請したスタッフの名前を表示する
        $user = $application->attendanceRecord->user;

        return view('admin.admin-application-detail', compact('user', 'application'));
    }

    public function approve($attendance_correct_request_id)
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
                $application->applicationBreaks->map(fn($break) => [
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

    // ★提供Bladeの申請一覧の「詳細」（/application/{id}）から、申請対象の勤怠詳細へ転送する
    public function redirectToAttendance($id)
    {
        // 自分の申請だけ開けるようにする
        $application = Application::whereHas('attendanceRecord', fn($query) => $query->where('user_id', Auth::id()))
            ->findOrFail($id);

        return redirect("/attendance/detail/{$application->attendance_record_id}");
    }
}
