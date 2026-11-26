<?php

use App\Models\Survey;
use App\Models\SurveyResponse;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('e2e') || ! str_ends_with(DB::connection()->getDatabaseName(), '_e2e')) {
    throw new RuntimeException('Tenant browser fixtures require an isolated E2E database.');
}

DB::transaction(function (): void {
    app(RolesAndPermissionsSeeder::class)->run();
    foreach (['A', 'B'] as $label) {
        $tenant = Tenant::create(['name' => 'Browser clinic '.$label]);
        User::create([
            'username' => 'browser-admin-'.$label, 'name' => 'Browser fixture '.$label,
            'email' => 'fixture-'.$label.'@example.test', 'role' => 'admin', 'tenantId' => $tenant->id,
            'password' => Hash::make(env('TEST_ADMIN_PASSWORD', 'Password123!')), 'isActive' => true,
        ]);
        $survey = Survey::create(['tenantId' => $tenant->id, 'title' => 'Browser survey '.$label, 'description' => 'Fixture', 'isActive' => true]);
        SurveyResponse::create([
            'surveyId' => $survey->id, 'tenantId' => $tenant->id, 'department' => 'Browser department '.$label,
            'answers' => [], 'overallScore' => 90, 'submittedAt' => now(),
        ]);
    }
});
