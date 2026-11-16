<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IndexAttendanceRecordRequest;
use App\Http\Requests\Api\V1\StoreAttendanceRecordRequest;
use App\Http\Requests\Api\V1\UpdateAttendanceRecordRequest;
use App\Http\Resources\AttendanceRecordResource;
use App\Models\AttendanceRecord;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class AttendanceRecordController extends Controller
{
    /**
     * 勤怠一覧を取得する（user_id・date・month で絞り込み、ページネーション付き）
     *
     * @param  IndexAttendanceRecordRequest  $request  絞り込み条件とページ指定
     * @return AnonymousResourceCollection data・links・meta を含む勤怠一覧
     */
    public function index(IndexAttendanceRecordRequest $request): AnonymousResourceCollection
    {
        $attendanceRecords = AttendanceRecord::with(['user', 'breaks'])
            ->when($request->validated('user_id'), fn ($query, $userId) => $query->where('user_id', $userId))
            ->when($request->validated('date'), fn ($query, $date) => $query->whereDate('date', $date))
            ->when($request->validated('month'), function ($query, $month) {
                $startOfMonth = Carbon::createFromFormat('!Y-m', $month);

                return $query->whereBetween('date', [
                    $startOfMonth->toDateString(),
                    $startOfMonth->copy()->endOfMonth()->toDateString(),
                ]);
            })
            ->latest('date')
            ->paginate($request->validated('per_page') ?? 20)
            ->withQueryString();

        return AttendanceRecordResource::collection($attendanceRecords);
    }

    /**
     * 指定IDの勤怠詳細（ユーザー・休憩・修正申請を含む）を取得する
     *
     * @param  AttendanceRecord  $attendanceRecord  ルートモデルバインディングで取得した勤怠
     * @return AttendanceRecordResource 勤怠詳細
     */
    public function show(AttendanceRecord $attendanceRecord): AttendanceRecordResource
    {
        $attendanceRecord->load(['user', 'breaks', 'applications']);

        return new AttendanceRecordResource($attendanceRecord);
    }

    /**
     * ログインユーザーの勤怠を新規登録する
     *
     * @param  StoreAttendanceRecordRequest  $request  勤怠日・出勤・退勤・備考
     * @return JsonResponse 登録した勤怠（201 Created）
     */
    public function store(StoreAttendanceRecordRequest $request): JsonResponse
    {
        $attendanceRecord = $request->user()->attendanceRecords()->create($request->validated());
        $attendanceRecord->load(['user', 'breaks']);

        return (new AttendanceRecordResource($attendanceRecord))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * 指定IDの勤怠を更新する（送信された項目のみ。本人または管理者のみ）
     *
     * @param  UpdateAttendanceRecordRequest  $request  更新する項目
     * @param  AttendanceRecord  $attendanceRecord  更新対象の勤怠
     * @return AttendanceRecordResource 更新後の勤怠
     */
    public function update(UpdateAttendanceRecordRequest $request, AttendanceRecord $attendanceRecord): AttendanceRecordResource
    {
        $this->authorize('update', $attendanceRecord);

        $attendanceRecord->update($request->validated());
        $attendanceRecord->load(['user', 'breaks']);

        return new AttendanceRecordResource($attendanceRecord);
    }

    /**
     * 指定IDの勤怠を削除する（休憩・修正申請も一緒に削除される。本人または管理者のみ）
     *
     * @param  AttendanceRecord  $attendanceRecord  削除対象の勤怠
     * @return Response 204 No Content
     */
    public function destroy(AttendanceRecord $attendanceRecord): Response
    {
        $this->authorize('delete', $attendanceRecord);

        $attendanceRecord->delete();

        return response()->noContent();
    }
}
