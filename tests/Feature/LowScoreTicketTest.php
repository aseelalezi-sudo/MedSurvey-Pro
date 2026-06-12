<?php

namespace Tests\Feature;

use App\Models\Ticket;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\Concerns\CreatesTestData;
use Tests\TestCase;

class LowScoreTicketTest extends TestCase
{
    use CreatesTestData;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_low_score_survey_response_creates_automatic_ticket(): void
    {
        $survey = $this->createActiveSurveyWithQuestion();
        $payload = $this->validStorePayload($survey, 'Emergency', [
            'answers' => [
                [
                    'questionId' => $survey->sections->first()->questions->first()->id,
                    'value' => 1,
                ],
            ],
        ]);

        $response = $this->postJson(route('survey.responses'), $payload);

        $response->assertCreated();
        $responseId = $response->json('id');

        $this->assertDatabaseHas('survey_responses', [
            'id' => $responseId,
            'surveyId' => $survey->id,
            'department' => 'Emergency',
        ]);

        $ticket = Ticket::query()->where('responseId', $responseId)->first();
        $this->assertNotNull($ticket);
        $this->assertEquals($responseId, $ticket->responseId);
        $this->assertEquals('Emergency', $ticket->department);
        $this->assertEquals('Test Patient', $ticket->patientName);
        $this->assertEquals('0555123456', $ticket->patientPhone);
        $this->assertEquals('open', $ticket->status);
        $this->assertEquals('high', $ticket->priority);
        $this->assertNotNull($ticket->description);
        $this->assertNotEmpty($ticket->description);
    }

    public function test_medium_low_score_creates_medium_priority_ticket(): void
    {
        $survey = $this->createActiveSurveyWithQuestion();
        $payload = $this->validStorePayload($survey, 'Emergency', [
            'answers' => [
                [
                    'questionId' => $survey->sections->first()->questions->first()->id,
                    'value' => 2,
                ],
            ],
        ]);

        $response = $this->postJson(route('survey.responses'), $payload);

        $response->assertCreated();
        $responseId = $response->json('id');

        $ticket = Ticket::query()->where('responseId', $responseId)->first();
        $this->assertNotNull($ticket);
        $this->assertEquals('medium', $ticket->priority);
    }

    public function test_high_score_survey_response_does_not_create_ticket(): void
    {
        $survey = $this->createActiveSurveyWithQuestion();
        $payload = $this->validStorePayload($survey, 'Emergency', [
            'answers' => [
                [
                    'questionId' => $survey->sections->first()->questions->first()->id,
                    'value' => 5,
                ],
            ],
        ]);

        $response = $this->postJson(route('survey.responses'), $payload);

        $response->assertCreated();
        $responseId = $response->json('id');

        $this->assertDatabaseHas('survey_responses', [
            'id' => $responseId,
            'surveyId' => $survey->id,
        ]);

        $ticket = Ticket::query()->where('responseId', $responseId)->first();
        $this->assertNull($ticket);
    }

    public function test_low_score_anonymous_response_safely_creates_ticket(): void
    {
        $survey = $this->createActiveSurveyWithQuestion();
        $questionId = $survey->sections->first()->questions->first()->id;
        $payload = [
            '_startedAt' => (int) ((microtime(true) - 10) * 1000),
            'surveyId' => $survey->id,
            'answers' => [
                [
                    'questionId' => $questionId,
                    'value' => 1,
                ],
            ],
            'department' => 'Emergency',
            'patientInfo' => null,
        ];

        $response = $this->postJson(route('survey.responses'), $payload);

        $response->assertCreated();
        $responseId = $response->json('id');

        $this->assertDatabaseHas('survey_responses', [
            'id' => $responseId,
        ]);

        $ticket = Ticket::query()->where('responseId', $responseId)->first();
        $this->assertNotNull($ticket);
        $this->assertEquals('Emergency', $ticket->department);
        $this->assertEquals(__('default_patient_name'), $ticket->patientName);
        $this->assertNull($ticket->patientPhone);
        $this->assertEquals('open', $ticket->status);
        $this->assertNotNull($ticket->description);
    }

    public function test_low_score_ticket_contains_correct_department(): void
    {
        $survey = $this->createActiveSurveyWithQuestion();
        $questionId = $survey->sections->first()->questions->first()->id;
        $payload = [
            '_startedAt' => (int) ((microtime(true) - 10) * 1000),
            'surveyId' => $survey->id,
            'answers' => [
                [
                    'questionId' => $questionId,
                    'value' => 1,
                ],
            ],
            'department' => 'Cardiology',
            'patientInfo' => [
                'name' => 'Cardio Patient',
                'phone' => '0555987654',
            ],
        ];

        $response = $this->postJson(route('survey.responses'), $payload);

        $response->assertCreated();
        $responseId = $response->json('id');

        $ticket = Ticket::query()->where('responseId', $responseId)->first();
        $this->assertNotNull($ticket);
        $this->assertEquals('Cardiology', $ticket->department);
        $this->assertEquals('Cardio Patient', $ticket->patientName);
    }
}
