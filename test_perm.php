<?php

use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$user = User::where('role', 'staff')->first();
$request = Request::create('/dashboard/surveys', 'GET');
$request->setUserResolver(function () use ($user) {
    return $user;
});

$httpKernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$response = $httpKernel->handle($request);

echo 'Status: '.$response->getStatusCode()."\n";
if ($response->getStatusCode() === 200) {
    echo substr($response->getContent(), 0, 500);
}
