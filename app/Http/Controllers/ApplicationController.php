<?php

namespace App\Http\Controllers;

use App\Models\Application;
use Illuminate\Support\Facades\Auth;

class ApplicationController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        // 管理者：全ユーザーの申請を、新しい順に取得する
        if ($user->admin_status) {
            $applications = Application::with('attendanceRecord.user')
                ->latest()
                ->get();

            $formattedApplications = $applications->map(fn($application) => [
                'id' => $application->id,
                'approval_status' => $application->is_approved ? '承認済み' : '承認待ち',
                'name' => $application->attendanceRecord->user->name,
                'date' => $application->attendanceRecord->date->format('Y/m/d'),
                'comment' => $application->comment,
                'application_date' => $application->created_at->format('Y/m/d'),
            ]);

            return view('admin.admin-application-list', compact('formattedApplications'));
        }

        // 一般ユーザー：自分の勤怠に対する申請を、新しい順に取得する
        $applications = Application::with('attendanceRecord')
            ->whereHas('attendanceRecord', fn($query) => $query->where('user_id', $user->id))
            ->latest()
            ->get();

        $formattedApplications = $applications->map(fn($application) => [
            'attendance_record_id' => $application->attendance_record_id,
            'approval_status' => $application->is_approved ? '承認済み' : '承認待ち',
            'date' => $application->attendanceRecord->date->format('Y/m/d'),
            'comment' => $application->comment,
            'application_date' => $application->created_at->format('Y/m/d'),
        ]);

        return view('user.user-application-list', compact('user', 'formattedApplications'));
    }
}
