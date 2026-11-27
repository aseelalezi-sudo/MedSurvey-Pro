<?php

namespace Tests\Feature;

use App\Events\SurveySubmitted;
use App\Models\SurveyResponse;
use App\Models\Tenant;
use Illuminate\Broadcasting\BroadcastEvent;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Concerns\CreatesTestData;
use Tests\TestCase;

class SurveyBroadcastTest extends TestCase
{
    use CreatesTestData;

    public function test_logout_and_account_switch_reject_the_previous_private_channel(): void
    {
        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'fixture-key',
            'broadcasting.connections.reverb.secret' => 'fixture-secret',
            'broadcasting.connections.reverb.app_id' => 'fixture',
            'broadcasting.connections.reverb.options.host' => 'localhost',
        ]);
        // Test config normally registers channels with the null broadcaster.
        require base_path('routes/channels.php');
        $first = Tenant::create(['name' => 'First']);
        $second = Tenant::create(['name' => 'Second']);
        $a = $this->userWithRole('admin', ['tenantId' => $first->id]);
        $b = $this->userWithRole('admin', ['tenantId' => $second->id]);
        $subscribe = fn (Tenant $tenant) => $this->postJson('/broadcasting/auth', [
            'socket_id' => '123.456',
            'channel_name' => 'private-surveys.tenant.'.$tenant->id,
        ]);

        $this->actingAs($a);
        $subscribe($first)->assertOk()->assertJsonStructure(['auth']);
        $this->post('/logout')->assertRedirect(route('login'));
        $subscribe($first)->assertForbidden();
        $this->actingAs($b);
        $subscribe($first)->assertForbidden();
        $subscribe($second)->assertOk()->assertJsonStructure(['auth']);
    }

    public function test_only_the_selected_database_queue_drains_and_disabled_broadcasts_do_not_accumulate(): void
    {
        $survey = $this->createTestSurvey();
        $response = SurveyResponse::create(['surveyId' => $survey->id, 'department' => 'A', 'answers' => [], 'overallScore' => 5, 'submittedAt' => now()]);
        config(['queue.default' => 'database', 'broadcasting.default' => 'null']);
        event(new SurveySubmitted($response));
        $this->assertDatabaseCount('jobs', 0);

        config(['broadcasting.default' => 'log', 'queue.connections.database.queue' => 'medsurvey-live']);
        app('queue')->connection('database')->pushOn('legacy', new BroadcastEvent(new SurveySubmitted($response)));
        $this->assertDatabaseHas('jobs', ['queue' => 'legacy', 'attempts' => 0]);
        event(new SurveySubmitted($response));
        $this->assertDatabaseHas('jobs', ['queue' => 'medsurvey-live']);

        $this->artisan('queue:work', ['connection' => 'database', '--queue' => 'medsurvey-live', '--once' => true, '--tries' => 1])->assertSuccessful();
        $this->assertDatabaseMissing('jobs', ['queue' => 'medsurvey-live']);
        $this->assertSame(1, DB::table('jobs')->where('queue', 'legacy')->where('attempts', 0)->count());
        $this->assertDatabaseCount('failed_jobs', 0);
    }

    public function test_notifications_respect_facility_department_and_user_scope(): void
    {
        $first = Tenant::create(['name' => 'First']);
        $second = Tenant::create(['name' => 'Second']);
        $head = $this->userWithRole('head_of_department', ['tenantId' => $first->id, 'department' => 'A']);
        $admin = $this->userWithRole('admin', ['tenantId' => $second->id]);
        $event = new SurveySubmitted(new SurveyResponse(['id' => 'r1', 'tenantId' => $first->id, 'department' => 'A', 'patientName' => 'Sample']));
        $department = hash('sha256', 'A');
        $this->assertSame(['id' => 'r1'], $event->broadcastWith());
        $this->assertSame([
            'private-surveys.system',
            'private-surveys.tenant.'.$first->id,
            'private-surveys.tenant.'.$first->id.'.department.'.$department,
        ], array_map(fn ($channel) => $channel->name, $event->broadcastOn()));

        $channels = Broadcast::getChannels();
        $this->assertFalse($channels['surveys.system']($admin));
        $this->assertTrue($channels['surveys.system']($this->superAdminUser()));
        $this->assertFalse($channels['App.Models.User.{id}']($head, $admin->id));
        $this->assertTrue($channels['App.Models.User.{id}']($head, $head->id));
        $facility = $channels['surveys.tenant.{tenantId}'];
        $this->assertTrue($facility($admin, $second->id));
        $this->assertFalse($facility($admin, $first->id));
        $this->assertFalse($facility($head, $first->id));
        $byDepartment = $channels['surveys.tenant.{tenantId}.department.{department}'];
        $this->assertTrue($byDepartment($head, $first->id, $department));
        $this->assertFalse($byDepartment($head, $second->id, $department));
        $this->assertFalse($byDepartment($head, $first->id, hash('sha256', 'B')));
    }
}
