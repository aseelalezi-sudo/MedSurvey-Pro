<?php

namespace Database\Seeders;

use App\Models\Survey;
use App\Models\SurveyQuestion;
use App\Models\SurveyResponse;
use App\Models\SurveySection;
use App\Models\Ticket;
use App\Models\User;
use Carbon\Carbon;
use Faker\Factory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoDataSeeder extends Seeder
{
    private const PASSWORD = 'Password123!';

    private const DEPARTMENTS = [
        'Emergency',
        'Pharmacy',
        'Laboratory',
        'Radiology',
        'Outpatient',
        'Inpatient',
        'Surgery',
        'Pediatrics',
    ];

    public function run(): void
    {
        // ── 1. Users ─────────────────────────────────────────────────────
        $this->seedUsers();

        // ── 2. Surveys ───────────────────────────────────────────────────
        $emergencySurvey = $this->seedEmergencySurvey();
        $outpatientSurvey = $this->seedOutpatientSurvey();
        $generalSurvey = $this->seedGeneralSurvey();
        $surveys = [$emergencySurvey, $outpatientSurvey, $generalSurvey];

        // ── 3. Responses ─────────────────────────────────────────────────
        $createdResponses = [];
        $lowScoreResponses = [];

        foreach ($surveys as $survey) {
            $responsesForSurvey = $this->seedResponses($survey);
            $createdResponses = array_merge($createdResponses, $responsesForSurvey);

            // Track low-score responses (overallScore < 70) for ticket creation
            foreach ($responsesForSurvey as $resp) {
                if ($resp->overallScore < 70) {
                    $lowScoreResponses[] = $resp;
                }
            }
        }

        // ── 4. Tickets ───────────────────────────────────────────────────
        $this->seedTickets($lowScoreResponses);
    }

    // ───────────────────────────────────────────────────────────────────
    //  USERS
    // ───────────────────────────────────────────────────────────────────
    private function seedUsers(): void
    {
        $users = [
            [
                'username' => 'super_admin',
                'name' => 'Super Admin',
                'email' => 'super_admin@medsurvey.local',
                'role' => 'super_admin',
                'department' => null,
            ],
            [
                'username' => 'admin',
                'name' => 'Admin User',
                'email' => 'admin@medsurvey.local',
                'role' => 'admin',
                'department' => null,
            ],
            [
                'username' => 'unit_manager',
                'name' => 'Unit Manager',
                'email' => 'unit_manager@medsurvey.local',
                'role' => 'unit_manager',
                'department' => null,
            ],
            [
                'username' => 'head_emergency',
                'name' => 'HOD Emergency',
                'email' => 'head_emergency@medsurvey.local',
                'role' => 'head_of_department',
                'department' => 'Emergency',
            ],
            [
                'username' => 'head_pharmacy',
                'name' => 'HOD Pharmacy',
                'email' => 'head_pharmacy@medsurvey.local',
                'role' => 'head_of_department',
                'department' => 'Pharmacy',
            ],
            [
                'username' => 'staff',
                'name' => 'Staff Member',
                'email' => 'staff@medsurvey.local',
                'role' => 'staff',
                'department' => null,
            ],
        ];

        $password = Hash::make(self::PASSWORD);

        foreach ($users as $data) {
            User::query()->updateOrCreate(
                ['username' => $data['username']],
                [
                    'password' => $password,
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'role' => $data['role'],
                    'department' => $data['department'],
                    'isActive' => true,
                ],
            );
        }
    }

    // ───────────────────────────────────────────────────────────────────
    //  SURVEYS
    // ───────────────────────────────────────────────────────────────────
    private function seedEmergencySurvey(): Survey
    {
        $survey = Survey::query()->updateOrCreate(
            ['id' => 'demo-survey-emergency'],
            [
                'title' => 'Emergency Patient Satisfaction Survey',
                'description' => 'قياس رضا المرضى عن خدمات الطوارئ / Measure patient satisfaction with Emergency Department services',
                'isActive' => true,
                'requireName' => true,
                'requirePhone' => true,
                'assignedDepartments' => ['Emergency'],
                'tips' => [
                    'يرجى الإجابة بصراحة لمساعدتنا في تحسين الخدمات',
                    'Please answer honestly to help us improve',
                ],
            ],
        );

        $section1 = SurveySection::query()->create([
            'id' => 'demo-sec-emerg-1',
            'surveyId' => $survey->id,
            'title' => 'سرعة الخدمة / Service Speed',
            'description' => 'قياس وقت الانتظار وسرعة الخدمة',
            'sortOrder' => 1,
        ]);

        SurveyQuestion::query()->create([
            'id' => 'demo-q-emerg-1',
            'sectionId' => $section1->id,
            'type' => 'stars',
            'title' => 'معدل سرعة استقبال الحالات في الطوارئ',
            'description' => 'مدى سرعة التعامل مع حالتك منذ الوصول',
            'required' => true,
            'category' => 'speed',
            'sortOrder' => 1,
        ]);

        SurveyQuestion::query()->create([
            'id' => 'demo-q-emerg-2',
            'sectionId' => $section1->id,
            'type' => 'stars',
            'title' => 'وقت الانتظار حتى مقابلة الطبيب',
            'description' => 'كم استغرق وقت الانتظار حتى الكشف',
            'required' => true,
            'category' => 'waiting',
            'sortOrder' => 2,
        ]);

        $section2 = SurveySection::query()->create([
            'id' => 'demo-sec-emerg-2',
            'surveyId' => $survey->id,
            'title' => 'جودة الرعاية / Care Quality',
            'description' => 'تقييم جودة الرعاية المقدمة',
            'sortOrder' => 2,
        ]);

        SurveyQuestion::query()->create([
            'id' => 'demo-q-emerg-3',
            'sectionId' => $section2->id,
            'type' => 'nps',
            'title' => 'مدى احتمالية توصيتك بقسم الطوارئ للآخرين',
            'description' => 'على مقياس من 0 إلى 10',
            'required' => true,
            'category' => 'nps',
            'sortOrder' => 1,
        ]);

        SurveyQuestion::query()->create([
            'id' => 'demo-q-emerg-4',
            'sectionId' => $section2->id,
            'type' => 'yes_no',
            'title' => 'هل تم شرح حالتك الصحية بوضوح؟',
            'description' => '',
            'required' => true,
            'category' => 'treatment',
            'sortOrder' => 2,
        ]);

        return $survey;
    }

    private function seedOutpatientSurvey(): Survey
    {
        $survey = Survey::query()->updateOrCreate(
            ['id' => 'demo-survey-outpatient'],
            [
                'title' => 'Outpatient Clinic Satisfaction Survey',
                'description' => 'قياس رضا المرضى عن العيادات الخارجية',
                'isActive' => true,
                'requireName' => false,
                'requirePhone' => false,
                'assignedDepartments' => ['Outpatient', 'Pharmacy', 'Laboratory', 'Radiology'],
                'tips' => [
                    'نقدر وقتك وملاحظاتك',
                    'We value your time and feedback',
                ],
            ],
        );

        $section1 = SurveySection::query()->create([
            'id' => 'demo-sec-out-1',
            'surveyId' => $survey->id,
            'title' => 'الخدمة والمواعيد / Service & Appointments',
            'description' => 'تقييم عملية حجز المواعيد والخدمة',
            'sortOrder' => 1,
        ]);

        SurveyQuestion::query()->create([
            'id' => 'demo-q-out-1',
            'sectionId' => $section1->id,
            'type' => 'stars',
            'title' => 'سهولة حجز الموعد',
            'description' => '',
            'required' => true,
            'category' => 'reception',
            'sortOrder' => 1,
        ]);

        SurveyQuestion::query()->create([
            'id' => 'demo-q-out-2',
            'sectionId' => $section1->id,
            'type' => 'stars',
            'title' => 'التزام العيادة بمواعيد الكشف',
            'description' => '',
            'required' => true,
            'category' => 'waiting',
            'sortOrder' => 2,
        ]);

        $section2 = SurveySection::query()->create([
            'id' => 'demo-sec-out-2',
            'surveyId' => $survey->id,
            'title' => 'التعامل / Staff Interaction',
            'description' => 'مدى احترافية ولطف الكادر الطبي',
            'sortOrder' => 2,
        ]);

        SurveyQuestion::query()->create([
            'id' => 'demo-q-out-3',
            'sectionId' => $section2->id,
            'type' => 'stars',
            'title' => 'مدى احترام الكادر الطبي للمريض',
            'description' => '',
            'required' => true,
            'category' => 'staff',
            'sortOrder' => 1,
        ]);

        return $survey;
    }

    private function seedGeneralSurvey(): Survey
    {
        $survey = Survey::query()->updateOrCreate(
            ['id' => 'demo-survey-general'],
            [
                'title' => 'General Hospital Services Survey',
                'description' => 'استبيان عام عن خدمات المستشفى / General feedback on hospital services',
                'isActive' => true,
                'requireName' => false,
                'requirePhone' => false,
                'assignedDepartments' => self::DEPARTMENTS,
                'tips' => null,
            ],
        );

        $section1 = SurveySection::query()->create([
            'id' => 'demo-sec-gen-1',
            'surveyId' => $survey->id,
            'title' => 'المرافق / Facilities',
            'description' => 'النظافة والمرافق',
            'sortOrder' => 1,
        ]);

        SurveyQuestion::query()->create([
            'id' => 'demo-q-gen-1',
            'sectionId' => $section1->id,
            'type' => 'stars',
            'title' => 'نظافة المنشأة',
            'description' => '',
            'required' => true,
            'category' => 'cleanliness',
            'sortOrder' => 1,
        ]);

        SurveyQuestion::query()->create([
            'id' => 'demo-q-gen-2',
            'sectionId' => $section1->id,
            'type' => 'yes_no',
            'title' => 'هل كانت اللوحات الإرشادية واضحة؟',
            'description' => '',
            'required' => true,
            'category' => 'signs',
            'sortOrder' => 2,
        ]);

        $section2 = SurveySection::query()->create([
            'id' => 'demo-sec-gen-2',
            'surveyId' => $survey->id,
            'title' => 'الرضا العام / Overall Satisfaction',
            'description' => 'التقييم النهائي',
            'sortOrder' => 2,
        ]);

        SurveyQuestion::query()->create([
            'id' => 'demo-q-gen-3',
            'sectionId' => $section2->id,
            'type' => 'nps',
            'title' => 'إلى أي مدى توصي بالمستشفى لأقاربك؟',
            'description' => '',
            'required' => true,
            'category' => 'nps',
            'sortOrder' => 1,
        ]);

        return $survey;
    }

    // ───────────────────────────────────────────────────────────────────
    //  RESPONSES
    // ───────────────────────────────────────────────────────────────────
    private function seedResponses(Survey $survey): array
    {
        $survey->load('sections.questions');
        $responses = [];
        $faker = Factory::create('ar_SA');

        $visitTypes = ['inpatient', 'outpatient', 'emergency'];
        $ageGroups = ['طفل', 'شاب', 'بالغ', 'كبير'];
        $genders = ['male', 'female'];
        $patientNames = [
            'محمد أحمد', 'سارة خالد', 'أحمد علي', 'فاطمة عمر',
            'عبدالله حسن', 'نورة سعيد', 'خالد إبراهيم', 'مريم عبدالعزيز',
            'يوسف محمد', 'هدى صالح', 'عمر عبدالله', 'لينا فهد',
        ];

        // Distribute 300+ responses across surveys: ~100 each
        $count = $survey->id === 'demo-survey-emergency' ? 120
            : ($survey->id === 'demo-survey-outpatient' ? 100 : 100);

        for ($i = 0; $i < $count; $i++) {
            $department = self::DEPARTMENTS[array_rand(self::DEPARTMENTS)];

            // 25% chance anonymous
            $hasName = $i % 4 !== 0;
            $hasPhone = $i % 3 !== 0;

            $patientName = $hasName ? $patientNames[array_rand($patientNames)] : null;
            $patientPhone = $hasPhone ? '05'.str_pad((string) random_int(10000000, 99999999), 8, '0', STR_PAD_LEFT) : null;

            // Score distribution: 40% excellent, 25% good, 20% average, 15% poor
            $scoreRand = $i % 100;
            if ($scoreRand < 40) {
                $overallScore = random_int(85, 100);
            } elseif ($scoreRand < 65) {
                $overallScore = random_int(70, 84);
            } elseif ($scoreRand < 85) {
                $overallScore = random_int(50, 69);
            } else {
                $overallScore = random_int(10, 49);
            }

            // Date distribution
            $submittedAt = $this->randomDate();

            $answers = [];
            foreach ($survey->sections as $section) {
                foreach ($section->questions as $question) {
                     if (in_array($question->type, ['stars', 'emoji', 'rating'])) {
                         $answers[$question->id] = $this->scoreToAnswer($overallScore);
                     } elseif ($question->type === 'nps') {
                         $answers[$question->id] = max(0, min(10, (int) round($overallScore / 10)));
                     } elseif ($question->type === 'yes_no') {
                         $answers[$question->id] = $overallScore >= 50 ? 'yes' : 'no';
                     }
                }
            }

            $id = 'demo-resp-'.$survey->id.'-'.str_pad((string) $i, 4, '0', STR_PAD_LEFT);

            $resp = SurveyResponse::query()->create([
                'id' => $id,
                'surveyId' => $survey->id,
                'answers' => $answers,
                'patientName' => $patientName,
                'patientPhone' => $patientPhone,
                'ageGroup' => $ageGroups[array_rand($ageGroups)],
                'gender' => $genders[array_rand($genders)],
                'visitType' => $visitTypes[array_rand($visitTypes)],
                'department' => $department,
                'overallScore' => $overallScore,
                'submittedAt' => $submittedAt,
            ]);

            $answerRows = [];
            foreach ($answers as $qId => $val) {
                $answerRows[] = [
                    'id' => \App\Support\Cuid::make(),
                    'responseId' => $resp->id,
                    'questionId' => $qId,
                    'value' => is_array($val) || is_object($val) ? json_encode($val) : (string) $val,
                ];
            }
            if ($answerRows !== []) {
                \App\Models\SurveyAnswer::query()->insert($answerRows);
            }

            $responses[] = $resp;
        }

        return $responses;
    }

    // ───────────────────────────────────────────────────────────────────
    //  TICKETS
    // ───────────────────────────────────────────────────────────────────
    private function seedTickets(array $lowScoreResponses): void
    {
        $statuses = ['open', 'in_progress', 'resolved'];
        $priorities = ['high', 'medium', 'low'];

        $samples = [];
        foreach ($lowScoreResponses as $resp) {
            $samples[] = $resp;
        }

        shuffle($samples);

        // Create tickets for about 60% of low-score responses
        $ticketCount = (int) (count($samples) * 0.6);
        $ticketCount = min($ticketCount, 40);

        for ($i = 0; $i < $ticketCount; $i++) {
            $resp = $samples[$i];
            $status = $statuses[array_rand($statuses)];
            $priority = $priorities[array_rand($priorities)];

            $resolutionNotes = null;
            $resolvedAt = null;
            if ($status === 'resolved') {
                $resolutionNotes = 'تم حل البلاغ بعد التواصل مع القسم المختص ومتابعة الحالة.';
                $resolvedAt = now()->subDays(random_int(0, 10));
            } elseif ($status === 'in_progress') {
                $resolutionNotes = 'جارٍ متابعة الحالة مع قسم '.$resp->department;
            }

            $ticketData = [
                'id' => 'demo-ticket-'.str_pad((string) $i, 3, '0', STR_PAD_LEFT),
                'responseId' => $resp->id,
                'department' => $resp->department,
                'patientName' => $resp->patientName ?? 'مريض غير معروف',
                'patientPhone' => $resp->patientPhone,
                'priority' => $priority,
                'status' => $status,
                'description' => 'انخفاض معدل الرضا ('.$resp->overallScore.'%) لقسم '.$resp->department
                    .' - تاريخ الزيارة: '.$resp->submittedAt->format('Y-m-d'),
                'createdAt' => $resp->submittedAt,
                'resolvedAt' => $resolvedAt,
                'resolutionNotes' => $resolutionNotes,
            ];

            Ticket::query()->create($ticketData);
        }
    }

    // ───────────────────────────────────────────────────────────────────
    //  HELPERS
    // ───────────────────────────────────────────────────────────────────
    private function randomDate(): Carbon
    {
        $now = now();
        $rand = random_int(0, 100);

        return match (true) {
            $rand < 10 => $now,                                               // today
            $rand < 30 => $now->copy()->subDays(random_int(1, 7)),           // last 7 days
            $rand < 55 => $now->copy()->subDays(random_int(8, 30)),          // last 30 days
            $rand < 80 => $now->copy()->subDays(random_int(31, 90)),         // last 3 months
            default => $now->copy()->subDays(random_int(91, 180)),           // older
        };
    }

    private function scoreToAnswer(int $score): int|string
    {
        if ($score >= 85) {
            return 5;
        }
        if ($score >= 70) {
            return 4;
        }
        if ($score >= 50) {
            return 3;
        }
        if ($score >= 30) {
            return 2;
        }

        return 1;
    }
}
