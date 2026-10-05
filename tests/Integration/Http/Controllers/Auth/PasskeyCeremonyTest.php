<?php

namespace Everest\Tests\Integration\Http\Controllers\Auth;

use CBOR\MapObject;
use Everest\Models\User;
use CBOR\ByteStringObject;
use CBOR\TextStringObject;
use CBOR\NegativeIntegerObject;
use CBOR\UnsignedIntegerObject;
use Everest\Models\UserPasskey;
use ParagonIE\ConstantTime\Base64UrlSafe;
use Everest\Tests\Integration\Http\HttpTestCase;

/**
 * Runs a full registration and login ceremony against a software authenticator, so the
 * whole round trip (options → browser → verification → login) is covered without a browser.
 */
class PasskeyCeremonyTest extends HttpTestCase
{
    private \OpenSSLAsymmetricKey $key;

    private string $credentialId;

    public function setUp(): void
    {
        parent::setUp();

        $this->key = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        $this->credentialId = random_bytes(16);
    }

    protected function tearDown(): void
    {
        UserPasskey::query()->forceDelete();

        parent::tearDown();
    }

    public function testPasskeyCanBeRegisteredAndUsedToLogin(): void
    {
        $user = User::factory()->create();

        $options = $this->actingAs($user)
            ->postJson('/api/client/account/passkeys/options', ['password' => 'password'])
            ->assertOk()
            ->json();

        $this->postJson('/api/client/account/passkeys', [
            'name' => 'Laptop',
            'credential' => $this->attestation($options),
        ])->assertOk()->assertJsonPath('attributes.name', 'Laptop');

        $this->assertSame(1, $user->passkeys()->count());

        // Registering a second passkey must still work once the account has one, since the
        // existing credential is now serialized into the exclude list.
        $this->actingAs($user->fresh())->postJson('/api/client/account/passkeys/options', ['password' => 'password'])
            ->assertOk()
            ->assertJsonPath('excludeCredentials.0.id', Base64UrlSafe::encodeUnpadded($this->credentialId));

        // Sign out, and undo the Sanctum guard the API middleware switched this app instance to.
        $this->app['auth']->forgetGuards();
        $this->app['auth']->shouldUse('web');
        $this->flushSession();

        $options = $this->postJson(route('auth.passkey.options'))->assertOk()->json();

        $this->postJson(route('auth.passkey.login'), ['credential' => $this->assertion($options, $user, 1)])
            ->assertOk()
            ->assertJsonPath('data.complete', true);

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->passkeys()->first()->last_used_at);
    }

    private function attestation(array $options): array
    {
        $details = openssl_pkey_get_details($this->key);

        $publicKey = MapObject::create()
            ->add(UnsignedIntegerObject::create(1), UnsignedIntegerObject::create(2))
            ->add(UnsignedIntegerObject::create(3), NegativeIntegerObject::create(-7))
            ->add(NegativeIntegerObject::create(-1), UnsignedIntegerObject::create(1))
            ->add(NegativeIntegerObject::create(-2), ByteStringObject::create($details['ec']['x']))
            ->add(NegativeIntegerObject::create(-3), ByteStringObject::create($details['ec']['y']));

        $authData = hash('sha256', $options['rp']['id'], true)
            . chr(0x45) // user present, user verified, attested credential data
            . pack('N', 0)
            . str_repeat("\0", 16)
            . pack('n', strlen($this->credentialId)) . $this->credentialId
            . $publicKey;

        $attestationObject = MapObject::create()
            ->add(TextStringObject::create('fmt'), TextStringObject::create('none'))
            ->add(TextStringObject::create('attStmt'), MapObject::create())
            ->add(TextStringObject::create('authData'), ByteStringObject::create($authData));

        return $this->credential([
            'clientDataJSON' => $this->clientData('webauthn.create', $options['challenge']),
            'attestationObject' => Base64UrlSafe::encodeUnpadded((string) $attestationObject),
            'transports' => ['internal'],
        ]);
    }

    private function assertion(array $options, User $user, int $counter): array
    {
        $authData = hash('sha256', $options['rpId'], true) . chr(0x05) . pack('N', $counter);
        $clientData = $this->clientData('webauthn.get', $options['challenge']);

        openssl_sign($authData . hash('sha256', Base64UrlSafe::decodeNoPadding($clientData), true), $signature, $this->key, OPENSSL_ALGO_SHA256);

        return $this->credential([
            'clientDataJSON' => $clientData,
            'authenticatorData' => Base64UrlSafe::encodeUnpadded($authData),
            'signature' => Base64UrlSafe::encodeUnpadded($signature),
            'userHandle' => Base64UrlSafe::encodeUnpadded($user->uuid),
        ]);
    }

    private function clientData(string $type, string $challenge): string
    {
        $url = parse_url(config('app.url'));

        return Base64UrlSafe::encodeUnpadded(json_encode([
            'type' => $type,
            'challenge' => $challenge,
            'origin' => $url['scheme'] . '://' . $url['host'] . (isset($url['port']) ? ':' . $url['port'] : ''),
            'crossOrigin' => false,
        ]));
    }

    private function credential(array $response): array
    {
        return [
            'id' => Base64UrlSafe::encodeUnpadded($this->credentialId),
            'rawId' => Base64UrlSafe::encodeUnpadded($this->credentialId),
            'type' => 'public-key',
            'response' => $response,
            'clientExtensionResults' => (object) [],
            'authenticatorAttachment' => 'platform',
        ];
    }
}
