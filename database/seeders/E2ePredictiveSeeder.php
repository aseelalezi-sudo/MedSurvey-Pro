<?php

namespace Database\Seeders;

use App\Models\Survey;
use App\Models\SurveyResponse;
use App\Models\User;
use App\Support\DashboardAnalyticsCache;
use App\Support\DashboardBadgeCache;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class E2ePredictiveSeeder extends Seeder
{
    public function run(): void
    {
        $adminUsername = env('TEST_ADMIN_USERNAME', 'super_admin');
        $adminPassword = env('TEST_ADMIN_PASSWORD', 'Password123!');

        $user = User::query()->updateOrCreate(
            ['username' => $adminUsername],
            [
                'password' => Hash::make($adminPassword),
                'name' => 'Super Admin',
                'email' => 'super_admin@medsurvey.local',
                'role' => 'super_admin',
                'department' => null,
                'isActive' => true,
                'tenantId' => null,
            ],
        );

        $survey = Survey::query()->updateOrCreate(
            ['id' => 'e2e-predictive-survey'],
            [
                'title' => 'E2E Predictive Survey',
                'description' => 'Browser test fixture for predictive alerts.',
                'isActive' => true,
                'requireName' => false,
                'requirePhone' => false,
                'assignedDepartments' => ['E2E Emergency'],
                'tips' => null,
                'tenantId' => null,
            ],
        );

        $rows = [
            ['id' => 'e2e-predictive-previous-1', 'score' => 92, 'submittedAt' => now()->subDays(10)],
            ['id' => 'e2e-predictive-previous-2', 'score' => 88, 'submittedAt' => now()->subDays(9)],
            ['id' => 'e2e-predictive-current-1', 'score' => 45, 'submittedAt' => now()->subDays(2)],
            ['id' => 'e2e-predictive-current-2', 'score' => 42, 'submittedAt' => now()->subDay()],
        ];

        foreach ($rows as $row) {
            SurveyResponse::query()->updateOrCreate(
                ['id' => $row['id']],
                [
                    'surveyId' => $survey->id,
                    'answers' => ['overall' => $row['score']],
                    'patientName' => null,
                    'patientPhone' => null,
                    'ageGroup' => null,
                    'gender' => null,
                    'visitType' => 'emergency',
                    'department' => 'E2E Emergency',
                    'overallScore' => $row['score'],
                    'submittedAt' => $row['submittedAt'],
                    'tenantId' => null,
                ],
            );
        }

        DashboardAnalyticsCache::bump();
        DashboardBadgeCache::forgetPredictive($user);
    }
}
