<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TransportCookieTest extends TestCase
{
    public static function transports(): array
    {
        return [
            'tunnel' => ['127.0.0.1', 'https', true],
            'direct LAN' => ['192.0.2.20', 'http', false],
            'untrusted forwarding header' => ['192.0.2.20', 'https', false],
        ];
    }

    public function test_production_hostname_without_scheme_keeps_secure_cookies(): void
    {
        $this->app->instance('env', 'production');
        config(['app.url' => 'survey.example.test', 'session.secure' => false]);
        $response = $this->get('http://survey.example.test/login');
        $cookie = $response->getCookie(config('session.cookie'), false);
        $this->assertNotNull($cookie);
        $this->assertTrue($cookie->isSecure());
    }

    #[DataProvider('transports')]
    public function test_session_cookie_uses_effective_transport(string $peer, string $proto, bool $secure): void
    {
        config(['session.secure' => false]);
        $response = $this->withServerVariables(['REMOTE_ADDR' => $peer])
            ->withHeader('X-Forwarded-Proto', $proto)->get('/login');
        $cookie = $response->getCookie(config('session.cookie'), false);
        $this->assertNotNull($cookie);
        $this->assertSame($secure, $cookie->isSecure());
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertSame('lax', $cookie->getSameSite());
    }
}
