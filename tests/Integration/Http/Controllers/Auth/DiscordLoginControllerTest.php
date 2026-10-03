<?php

namespace Everest\Tests\Integration\Http\Controllers\Auth;

use Everest\Models\User;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use Everest\Tests\Integration\Http\HttpTestCase;

class DiscordLoginControllerTest extends HttpTestCase
{
    private const STATE = 'discord-test-state';

    public function setUp(): void
    {
        parent::setUp();

        config()->set('modules.auth.discord.enabled', true);
        config()->set('modules.auth.discord.client_id', 'client');
        config()->set('modules.auth.discord.client_secret', 'secret');
        config()->set('modules.auth.jguard.enabled', false);
        config()->set('modules.auth.jguard.delay', 0);
    }

    public function testExistingAccountCanLoginWithDiscord(): void
    {
        $user = User::factory()->create();
        $this->fakeDiscord($user->email);

        $this->hitCallback(['code' => 'abc'])->assertRedirect('/');

        $this->assertAuthenticatedAs($user);
    }

    public function testDiscordSignupCreatesAnAccountWithoutAPassword(): void
    {
        config()->set('modules.auth.registration.enabled', true);
        $email = Str::random(12) . '@example.com';
        $this->fakeDiscord($email);

        $this->hitCallback(['code' => 'abc'])->assertRedirect('/');

        $user = User::query()->where('email', $email)->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertFalse($user->has_password);
    }

    /**
     * A failure must land back on the login page with the reason, not bounce the browser
     * "back" to Discord or render an error page.
     */
    public function testCancelledLoginReturnsToTheLoginPage(): void
    {
        $this->hitCallback(['error' => 'access_denied'])
            ->assertRedirect(route('auth.login'))
            ->assertSessionHas('auth_error', 'Discord login was cancelled.');

        $this->assertGuest();
    }

    public function testUnverifiedEmailIsRejected(): void
    {
        $user = User::factory()->create();
        $this->fakeDiscord($user->email, verified: false);

        $this->hitCallback(['code' => 'abc'])
            ->assertRedirect(route('auth.login'))
            ->assertSessionHas('auth_error');

        $this->assertGuest();
    }

    private function hitCallback(array $query)
    {
        return $this->withSession(['discord_oauth2_state' => self::STATE])
            ->get(route('auth.modules.discord.authenticate', ['state' => self::STATE] + $query));
    }

    private function fakeDiscord(string $email, bool $verified = true): void
    {
        Http::fake([
            'discord.com/api/oauth2/token' => Http::response(['access_token' => 'token']),
            'discord.com/api/users/@me' => Http::response(['email' => $email, 'verified' => $verified]),
        ]);
    }
}
