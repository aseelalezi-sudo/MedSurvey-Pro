<?php

// Compile the reviewed application's scope without booting Laravel or loading .env.
use App\Models\SurveyResponse;
use App\Models\User;
use Illuminate\Database\Capsule\Manager;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$database = new Manager;
$database->addConnection(['driver' => 'mysql', 'database' => 'drill', 'unix_socket' => '/nonexistent']);
$database->bootEloquent();
$user = new User;
$user->setRawAttributes(['tenantId' => 'drill-binding', 'role' => 'admin']);
$query = SurveyResponse::query()->forUserAccess($user)->select('tenantId');
if ($query->getBindings() !== ['drill-binding']) {
    exit(1);
}
echo $query->toSql();
