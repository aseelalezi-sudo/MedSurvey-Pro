<?php

namespace Tests\Feature;

use App\Events\SurveySubmitted;
use App\Models\SurveyResponse;
use App\Models\Tenant;
use Illuminate\Support\Facades\Broadcast;
use Tests\Feature\Concerns\CreatesTestData;
use Tests\TestCase;

class SurveyBroadcastTest extends TestCase
{
    use CreatesTestData;

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
