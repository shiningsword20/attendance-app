<?php

namespace Database\Seeders;

use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Seeder;

class AttendanceRecordSeeder extends Seeder
{
    public function run()
    {
        $user1 = User::where('email', 'user1@example.com')->first();
        $this->seedUser1($user1);

        User::where('email', '!=', 'user1@example.com')
            ->get()
            ->each(fn ($user) => $this->seedRegularUser($user));
    }

    private function seedUser1($user)
    {
        // 過去5か月：各月の平日から15日を、月全体に均等にバラけさせて通常勤務
        collect(range(5, 1))->each(function ($monthsAgo) use ($user) {
            $month = Carbon::now()->startOfMonth()->subMonths($monthsAgo);
            $weekdays = $this->weekdaysOf($month)->values();
            $step = $weekdays->count() / 15;

            collect(range(0, 14))
                ->map(fn ($index) => $weekdays[(int) floor($index * $step)])
                ->each(fn ($day) => $this->createAttendance($user, $day, '09:00', '18:00'));
        });

        // 当月：17日分（今日は打刻確認用に空けておく）
        $patterns = array_merge(
            array_fill(0, 10, ['09:00', '18:00']), // 通常
            array_fill(0, 3, ['09:00', '20:00']),  // 残業
            array_fill(0, 2, ['09:30', '18:00']),  // 遅刻
            [['09:00', '17:00']],                  // 早退
            [['08:00', '21:00']],                  // 長時間労働
        );

        $this->weekdaysOf(Carbon::now()->startOfMonth())
            ->filter(fn ($day) => $day->lt(Carbon::today()))
            ->take(17)
            ->values()
            ->each(fn ($day, $index) => $this->createAttendance($user, $day, $patterns[$index][0], $patterns[$index][1]));
    }

    private function seedRegularUser($user)
    {
        // 2か月前の1日〜昨日までの平日を通常勤務
        $period = CarbonPeriod::create(Carbon::now()->startOfMonth()->subMonths(2), Carbon::yesterday());

        collect($period->toArray())
            ->filter(fn ($day) => $day->isWeekday())
            ->each(fn ($day) => $this->createAttendance($user, $day, '09:00', '18:00'));
    }

    private function weekdaysOf($month)
    {
        $period = CarbonPeriod::create($month->copy()->startOfMonth(), $month->copy()->endOfMonth());

        return collect($period->toArray())->filter(fn ($day) => $day->isWeekday());
    }

    private function createAttendance($user, $day, $clockIn, $clockOut)
    {
        $attendanceRecord = $user->attendanceRecords()->create([
            'date' => $day->toDateString(),
            'clock_in' => $clockIn.':00',
            'clock_out' => $clockOut.':00',
        ]);

        $attendanceRecord->breaks()->create([
            'break_in' => '12:00:00',
            'break_out' => '13:00:00',
        ]);
    }
}
