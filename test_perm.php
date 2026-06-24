<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$user = \App\Models\User::where('role', 'staff')->first();
$request = \Illuminate\Http\Request::create('/dashboard/surveys', 'GET');
$request->setUserResolver(function() use ($user) { return $user; });

$httpKernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$response = $httpKernel->handle($request);

echo "Status: " . $response->getStatusCode() . "\n";
if ($response->getStatusCode() === 200) {
    echo substr($response->getContent(), 0, 500);
}
