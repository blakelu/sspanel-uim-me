<?php

declare(strict_types=1);

namespace Tests\Integration\Services\MFA;

use App\Models\MFADevice;
use App\Models\User;
use App\Services\Cache;
use App\Services\MFA\WebAuthn;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use ParagonIE\ConstantTime\Base64UrlSafe;
use Redis;
use Tests\Fixtures\WebAuthnV524Fixture;
use Tests\TestCase;
use Webauthn\PublicKeyCredentialSource;

final class WebAuthnCompatibilityTest extends TestCase
{
    private bool $hadBaseUrl;

    private mixed $originalBaseUrl;

    private Redis $redis;

    protected function setUp(): void
    {
        parent::setUp();

        $capsule = new Capsule();
        $capsule->addConnection([
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);
        $capsule->setAsGlobal();
        $capsule->bootEloquent();
        $capsule->schema()->create('user', static function (Blueprint $table): void {
            $table->increments('id');
            $table->string('email');
            $table->string('uuid');
        });
        $capsule->schema()->create('mfa_devices', static function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('userid');
            $table->string('name')->nullable();
            $table->string('rawid');
            $table->text('body');
            $table->string('created_at');
            $table->string('used_at')->nullable();
            $table->string('type');
        });

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        session_id('webauthn2562' . bin2hex(random_bytes(8)));
        session_start();

        $this->hadBaseUrl = array_key_exists('baseUrl', $_ENV);
        $this->originalBaseUrl = $_ENV['baseUrl'] ?? null;
        $_ENV['baseUrl'] = 'https://localhost';
        $this->redis = (new Cache())->initRedis();
    }

    protected function tearDown(): void
    {
        $this->redis->del('webauthn_register_' . session_id());
        $this->redis->del('webauthn_assertion_' . session_id());

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }

        if ($this->hadBaseUrl) {
            $_ENV['baseUrl'] = $this->originalBaseUrl;
        } else {
            unset($_ENV['baseUrl']);
        }

        parent::tearDown();
    }

    public function testLegacyCredentialJsonRoundTripsThroughTheCandidateSerializer(): void
    {
        $serializer = WebAuthn::getSerializer();
        $credential = $serializer->deserialize(
            WebAuthnV524Fixture::LEGACY_CREDENTIAL_SOURCE_JSON,
            PublicKeyCredentialSource::class,
            'json'
        );

        self::assertSame(100, $credential->counter);
        self::assertJsonStringEqualsJsonString(
            WebAuthnV524Fixture::LEGACY_CREDENTIAL_SOURCE_JSON,
            $serializer->serialize($credential, 'json')
        );
    }

    public function testRegistrationPersistsAReadableCredentialWithTheCandidateSerializer(): void
    {
        $_ENV['baseUrl'] = 'https://' . WebAuthnV524Fixture::REGISTRATION_RP_ID;
        $user = new User();
        $user->id = 101;
        $user->email = 'admin@example.com';
        $user->uuid = '101';
        $user->save();
        $this->redis->set(
            'webauthn_register_' . session_id(),
            WebAuthnV524Fixture::REGISTRATION_OPTIONS_JSON
        );
        $response = json_decode(
            WebAuthnV524Fixture::REGISTRATION_RESPONSE_JSON,
            true,
            flags: JSON_THROW_ON_ERROR
        );
        $response['name'] = 'Compatibility key';

        $result = WebAuthn::registerHandle($user, $response);

        self::assertSame(1, $result['ret']);
        $stored = (new MFADevice())->where('userid', 101)->where('type', 'passkey')->firstOrFail();
        self::assertSame(WebAuthnV524Fixture::REGISTRATION_CREDENTIAL_ID, $stored->rawid);
        self::assertSame('Compatibility key', $stored->name);
        $storedCredential = WebAuthn::getSerializer()->deserialize(
            $stored->body,
            PublicKeyCredentialSource::class,
            'json'
        );
        self::assertSame(
            WebAuthnV524Fixture::REGISTRATION_CREDENTIAL_ID,
            Base64UrlSafe::encodeUnpadded($storedCredential->publicKeyCredentialId)
        );
    }

    public function testPasswordlessAssertionUpdatesTheLegacyCredentialAndConsumesItsChallenge(): void
    {
        $credential = $this->createLegacyCredential();
        $challengeKey = 'webauthn_assertion_' . session_id();
        $this->redis->set($challengeKey, WebAuthnV524Fixture::ASSERTION_REQUEST_OPTIONS_JSON);

        $result = WebAuthn::assertHandle(json_decode(
            WebAuthnV524Fixture::ASSERTION_RESPONSE_JSON,
            true,
            flags: JSON_THROW_ON_ERROR
        ));

        self::assertSame(1, $result['ret']);
        self::assertSame(1, $result['user']->id);
        $stored = (new MFADevice())->findOrFail($credential->id);
        $source = WebAuthn::getSerializer()->deserialize(
            $stored->body,
            PublicKeyCredentialSource::class,
            'json'
        );
        self::assertSame(123, $source->counter);
        self::assertNotNull($stored->used_at);
        self::assertFalse($this->redis->get($challengeKey));
    }

    public function testWrongChallengeRejectsWithoutMutatingDatabaseOrRedis(): void
    {
        $credential = $this->createLegacyCredential();
        $challengeKey = 'webauthn_assertion_' . session_id();
        $this->redis->set(
            $challengeKey,
            WebAuthnV524Fixture::WRONG_CHALLENGE_REQUEST_OPTIONS_JSON
        );

        $result = WebAuthn::assertHandle(json_decode(
            WebAuthnV524Fixture::ASSERTION_RESPONSE_JSON,
            true,
            flags: JSON_THROW_ON_ERROR
        ));

        self::assertSame(0, $result['ret']);
        $stored = (new MFADevice())->findOrFail($credential->id);
        self::assertSame(WebAuthnV524Fixture::LEGACY_CREDENTIAL_SOURCE_JSON, $stored->body);
        self::assertNull($stored->used_at);
        self::assertSame(
            WebAuthnV524Fixture::WRONG_CHALLENGE_REQUEST_OPTIONS_JSON,
            $this->redis->get($challengeKey)
        );
    }

    private function createLegacyCredential(): MFADevice
    {
        $user = new User();
        $user->id = 1;
        $user->email = 'legacy@example.com';
        $user->uuid = 'foo';
        $user->save();
        $credential = new MFADevice();
        $credential->userid = 1;
        $credential->name = 'Legacy credential';
        $credential->rawid = WebAuthnV524Fixture::LEGACY_CREDENTIAL_ID;
        $credential->body = WebAuthnV524Fixture::LEGACY_CREDENTIAL_SOURCE_JSON;
        $credential->created_at = '2026-01-01 00:00:00';
        $credential->used_at = null;
        $credential->type = 'passkey';
        $credential->save();

        return $credential;
    }
}
