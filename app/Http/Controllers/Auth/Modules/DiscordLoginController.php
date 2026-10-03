<?php

namespace Everest\Http\Controllers\Auth\Modules;

use Everest\Models\User;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\RedirectResponse;
use Everest\Exceptions\DisplayException;
use Everest\Http\Controllers\Auth\AbstractLoginController;

class DiscordLoginController extends AbstractLoginController
{
    private const STATE_SESSION_KEY = 'discord_oauth2_state';

    /**
     * DiscordLoginController constructor.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Get the user's Discord token in order to access the account.
     *
     * @throws DisplayException
     * @throws \Illuminate\Validation\ValidationException
     */
    public function requestToken(Request $request): string
    {
        $this->assertEnabled();

        if ($this->hasTooManyLoginAttempts($request)) {
            $this->fireLockoutEvent($request);
            $this->sendLockoutResponse($request);
        }

        // Generate an unguessable, single-use state value and bind it to this session so
        // that the callback can verify the response actually belongs to a flow this
        // browser initiated (CSRF protection for the OAuth handshake). The "discord-"
        // prefix lets VerifyReCaptcha tell this apart from its own "state" parameter
        // (an encrypted reCAPTCHA payload) so it doesn't try to decrypt it and 500.
        $state = 'discord-' . Str::random(40);
        $request->session()->put(self::STATE_SESSION_KEY, $state);

        return 'https://discord.com/api/oauth2/authorize?' . http_build_query([
            'client_id' => config('modules.auth.discord.client_id'),
            'redirect_uri' => route('auth.modules.discord.authenticate'),
            'response_type' => 'code',
            'scope' => 'identify email',
            'state' => $state,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * Authenticate with the Discord OAuth2 service.
     */
    public function authenticate(Request $request): RedirectResponse
    {
        return $this->handleOAuthCallback('Discord', fn () => $this->handleCallback($request));
    }

    /**
     * @throws DisplayException
     */
    private function handleCallback(Request $request): RedirectResponse
    {
        $this->assertEnabled();

        $expectedState = $request->session()->pull(self::STATE_SESSION_KEY);
        $providedState = $request->query('state');

        if (!is_string($expectedState) || !is_string($providedState) || !hash_equals($expectedState, $providedState)) {
            throw new DisplayException('This Discord login request is invalid or has expired, please try again.');
        }

        // The user pressed "Cancel" on Discord's consent screen.
        if ($request->has('error') || !$request->filled('code')) {
            throw new DisplayException('Discord login was cancelled.');
        }

        $token = Http::asForm()->post('https://discord.com/api/oauth2/token', [
            'client_id' => config('modules.auth.discord.client_id'),
            'client_secret' => config('modules.auth.discord.client_secret'),
            'grant_type' => 'authorization_code',
            'code' => $request->input('code'),
            'redirect_uri' => route('auth.modules.discord.authenticate'),
        ])->json('access_token');

        if (!is_string($token)) {
            throw new DisplayException('Discord rejected this login request. Please check the Discord module\'s client ID and secret.');
        }

        $account = Http::withToken($token)->get('https://discord.com/api/users/@me')->object();

        // Discord does not require a user to verify ownership of the email address
        // stored on their account. Trusting an unverified email here would let anyone
        // log in as (or register as) any panel user whose email they merely guess or
        // enter into their own Discord profile, without proving they own it.
        if (empty($account->email) || empty($account->verified)) {
            throw new DisplayException('Your Discord account does not have a verified email address. Please verify your email with Discord and try again.');
        }

        if (User::where('email', $account->email)->exists()) {
            $user = User::where('email', $account->email)->first();

            return $this->completeOAuthLogin($user, $request);
        }
        $user = $this->createAccount(['email' => $account->email, 'username' => 'null_user_' . $this->randStr(16)], $request);

        return $this->completeOAuthLogin($user, $request);
    }

    /**
     * Create a random string we can use for a temporary username.
     */
    public function randStr(int $length = 10): string
    {
        return substr(str_shuffle(str_repeat($x = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', ceil($length / strlen($x)))), 1, $length);
    }

    /**
     * @throws DisplayException
     */
    private function assertEnabled(): void
    {
        if (!config('modules.auth.discord.enabled')) {
            throw new DisplayException('Discord login is not enabled on this Panel.');
        }
    }
}
