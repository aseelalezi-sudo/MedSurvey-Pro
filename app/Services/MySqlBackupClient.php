<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class MySqlBackupClient
{
    public function executable(string $tool): ?string
    {
        $configured = config('medsurvey.backup.'.$tool.'_path');

        return $configured ? (is_file($configured) ? $configured : null) : (new ExecutableFinder)->find($tool);
    }

    public function command(string $executable, array $options): Process
    {
        $connection = DB::connection();
        $server = (string) $connection->selectOne('SELECT VERSION() AS version', [], false)->version;

        $probe = new Process([$executable, '--no-defaults', '--version'], timeout: 10);
        $probe->run();
        $client = $probe->getOutput();

        if (! $probe->isSuccessful() || stripos($client, 'mariadb') !== false || stripos($server, 'mariadb') !== false
            || ! preg_match('/\bVer\s+(8\.(?:0|4))\.\d+\b/i', $client, $clientVersion)
            || ! preg_match('/^(8\.(?:0|4))\.\d+\b/', $server, $serverVersion)
            || $clientVersion[1] !== $serverVersion[1]) {
            throw new RuntimeException('Use a MySQL client from the same MySQL 8.0 or 8.4 series as the server.');
        }

        $config = $connection->getConfig();
        $socket = $config['unix_socket'] ?? null;
        $endpoint = $socket
            ? ['--protocol=socket', '--socket='.$socket]
            : ['--protocol=tcp', '--host='.$config['host'], '--port='.$config['port']];
        $ca = $config['options'][\PDO::MYSQL_ATTR_SSL_CA] ?? null;
        if ($ca && ! $socket) {
            $endpoint[] = '--ssl-mode=VERIFY_IDENTITY';
            $endpoint[] = '--ssl-ca='.$ca;
        }

        return new Process([
            $executable, '--no-defaults', ...$endpoint, '--user='.$config['username'],
            ...$options, '--', $connection->getDatabaseName(),
        ], env: [
            'MYSQL_PWD' => (string) ($config['password'] ?? ''),
            // MySQL reads login paths even with --no-defaults. 8.0 mysqldump
            // does not accept --no-login-paths, so point it at a nonexistent file.
            'MYSQL_TEST_LOGIN_FILE' => sys_get_temp_dir().DIRECTORY_SEPARATOR.'medsurvey-'.bin2hex(random_bytes(16)),
        ]);
    }
}
