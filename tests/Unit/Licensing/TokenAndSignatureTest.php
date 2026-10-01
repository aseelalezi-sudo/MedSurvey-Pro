<?php

namespace Tests\Unit\Licensing;

use App\Licensing\Services\SignatureVerifier;
use App\Licensing\Support\Base64Url;
use App\Licensing\Support\Token;
use PHPUnit\Framework\TestCase;
use Tests\Licensing\Support\JwtFactory;

class TokenAndSignatureTest extends TestCase
{
    public function test_base64url_round_trips_with_and_without_padding(): void
    {
        $raw = bin2hex(random_bytes(32));

        $this->assertSame($raw, Base64Url::decode(Base64Url::encode($raw)));
        $this->assertStringNotContainsString('=', Base64Url::encode($raw));
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]+$/', Base64Url::encode($raw));
    }

    public function test_token_parser_extracts_header_payload_and_signature(): void
    {
        $factory = JwtFactory::make();
        $token = $factory->sign([
            'sub' => 'lic-1', 'prd' => 'medsurvey-pro', 'exp' => time() + 3600, 'fea' => ['advanced-reports'],
        ], 'kid-abc');

        $parsed = Token::parse($token);

        $this->assertSame('kid-abc', $parsed->kid());
        $this->assertSame('EdDSA', $parsed->algorithm());
        $this->assertSame('medsurvey-pro', $parsed->claim('prd'));
        $this->assertSame(['advanced-reports'], $parsed->claim('fea'));
        $this->assertNotEmpty($parsed->signature);
    }

    public function test_token_parser_rejects_malformed_tokens(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Token::parse('only.two.segments.somewhere');
    }

    public function test_verify_accepts_a_correctly_signed_token(): void
    {
        $factory = JwtFactory::make();
        $token = $factory->sign(['sub' => 'lic-1', 'exp' => time() + 900], 'kid-abc');

        $verified = (new SignatureVerifier)->verify(Token::parse($token), $factory->publicKeyB64());

        $this->assertTrue($verified);
    }

    public function test_verify_rejects_tampered_payload(): void
    {
        $factory = JwtFactory::make();
        $good = $factory->sign(['sub' => 'lic-1', 'exp' => time() + 900], 'kid-abc');

        $parts = explode('.', $good);
        $payload = json_decode(Base64Url::decode($parts[1]), true);
        $payload['fee'] = true;
        $parts[1] = Base64Url::encode((string) json_encode($payload));
        $tampered = implode('.', $parts);

        $this->assertFalse((new SignatureVerifier)->verify(Token::parse($tampered), $factory->publicKeyB64()));
    }

    public function test_verify_rejects_wrong_signing_key(): void
    {
        $factoryA = JwtFactory::make();
        $factoryB = JwtFactory::make();

        $token = $factoryA->sign(['sub' => 'lic-1', 'exp' => time() + 900], 'kid-abc');

        $this->assertFalse((new SignatureVerifier)->verify(Token::parse($token), $factoryB->publicKeyB64()));
    }

    public function test_verify_rejects_non_eddsa_algorithm(): void
    {
        $factory = JwtFactory::make();
        $header = Base64Url::encode((string) json_encode(['typ' => 'JWT', 'alg' => 'HS256']));
        $payload = Base64Url::encode((string) json_encode(['sub' => 'lic-1']));
        $token = $header.'.'.$payload.'.'.Base64Url::encode(hash('sha256', $header.'.'.$payload));

        $this->expectException(\InvalidArgumentException::class);

        (new SignatureVerifier)->verify(Token::parse($token), $factory->publicKeyB64());
    }
}
