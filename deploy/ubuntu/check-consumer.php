<?php

// Called as the deployment account. Never print adapter exceptions or signed URLs.
use Illuminate\Contracts\Broadcasting\Factory;
use Illuminate\Contracts\Console\Kernel;

try {
    if (! chdir($argv[1])) {
        exit(1);
    }
    require getcwd().'/vendor/autoload.php';
    $app = require getcwd().'/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();

    if ($argv[2] === 'medsurvey-queue') {
        exit(extension_loaded('pcntl') && config('queue.default') === 'database'
            && config('queue.connections.database.queue') === 'medsurvey-live'
            && (int) config('queue.connections.database.retry_after') > 85 ? 0 : 1);
    }

    if ($argv[2] !== 'medsurvey-reverb' || config('broadcasting.default') !== 'reverb'
        || config('broadcasting.connections.reverb.options.host') !== '127.0.0.1') {
        exit(1);
    }
    $origins = config('reverb.apps.apps.0.allowed_origins', []);
    if (! $origins || str_contains(implode(',', $origins), '*')) {
        exit(1);
    }
    $pusher = $app->make(Factory::class)->connection('reverb')->getPusher();
    $result = $pusher->getChannelInfo('private-medsurvey-readiness');
    exit(is_bool($result->occupied ?? null) ? 0 : 1);
} catch (Throwable) {
    fwrite(STDERR, "Consumer readiness failed. Inspect private site configuration.\n");
    exit(1);
}
