<?php

namespace Tests\Feature;

use App\Models\Survey;
use App\Models\SurveyResponse;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ArchiveOldDataCommandTest extends TestCase
{
    use DatabaseTransactions;

    public function test_archive_old_data_preserves_survey_response_tenant_id(): void
    {
        $tenant = Tenant::query()->create([
            'id' => 'archive-tenant-a',
            'name' => 'Archive Tenant A',
        ]);

        $survey = Survey::query()->create([
            'id' => 'archive-survey-a',
            'title' => 'Archive Survey A',
            'description' => 'Archive test',
            'isActive' => true,
            'tenantId' => $tenant->id,
        ]);

        $response = SurveyResponse::query()->create([
            'id' => 'archive-response-a',
            'surveyId' => $survey->id,
            'tenantId' => $tenant->id,
            'answers' => [],
            'patientName' => 'Archive Patient',
            'patientPhone' => '777777777',
            'ageGroup' => '30-39',
            'gender' => 'male',
            'visitType' => 'outpatient',
            'department' => 'Emergency',
            'overallScore' => 80,
            'submittedAt' => now()->subYears(4),
        ]);

        $this->artisan('archive:old-data', ['--years' => 3])
            ->expectsOutput('Archived 1 survey response(s), 0 ticket(s), and 0 audit log(s).')
            ->assertExitCode(0);

        $this->assertDatabaseHas('archived_survey_responses', [
            'id' => $response->id,
            'surveyId' => $survey->id,
            'tenantId' => $tenant->id,
            'department' => 'Emergency',
            'overallScore' => 80,
        ]);

        $this->assertDatabaseMissing('survey_responses', [
            'id' => $response->id,
        ]);
    }
}
