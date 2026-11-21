<?php

namespace Tests\Feature;

use App\Models\WpOptions;
use App\Models\WpUser;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tests\TestCase;

/**
 * GET /api/who-am-i, which checks the WordPress "logged in" cookie.
 *
 * Creates its own WordPress test users inside a transaction that is rolled back after each test.
 *
 * Each test runs in its own PHP process, because WpUser::loggedIn() caches its answer
 * in a static variable and reads $_COOKIE directly.
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class WhoAmITest extends TestCase
{
    use DatabaseTransactions;

    private const TOKEN = 'test-token';

    public function test_without_cookie_is_not_logged_in(): void
    {
        $this->getJson('/api/who-am-i')
            ->assertOk()
            ->assertExactJson(['loggedIn' => false]);
    }

    public function test_valid_cookie_of_an_administrator(): void
    {
        $user = $this->userWithSession('administrator');
        $this->setLoginCookie($user, time() + 3600);

        $this->getJson('/api/who-am-i')
            ->assertOk()
            ->assertExactJson(['loggedIn' => true, 'who' => $user->user_login, 'admin' => true]);
    }

    public function test_valid_cookie_of_a_subscriber_is_not_admin(): void
    {
        $user = $this->userWithSession('subscriber');
        $this->setLoginCookie($user, time() + 3600);

        $this->getJson('/api/who-am-i')
            ->assertOk()
            ->assertExactJson(['loggedIn' => true, 'who' => $user->user_login, 'admin' => false]);
    }

    public function test_cookie_of_a_logged_out_session_is_not_logged_in(): void
    {
        $user = $this->createWpUser('administrator'); // no active sessions: logged out
        $this->setLoginCookie($user, time() + 3600);

        $this->getJson('/api/who-am-i')
            ->assertOk()
            ->assertExactJson(['loggedIn' => false]);
    }

    public function test_cookie_with_wrong_signature_is_not_logged_in(): void
    {
        $user = $this->userWithSession('administrator');
        $this->setLoginCookie($user, time() + 3600, hmac: str_repeat('0', 64));

        $this->getJson('/api/who-am-i')
            ->assertOk()
            ->assertExactJson(['loggedIn' => false]);
    }

    public function test_expired_cookie_is_not_logged_in(): void
    {
        $user = $this->userWithSession('administrator');
        $this->setLoginCookie($user, time() - 60);

        $this->getJson('/api/who-am-i')
            ->assertOk()
            ->assertExactJson(['loggedIn' => false]);
    }

    /** A user with an active WordPress session for self::TOKEN, like after logging in. */
    private function userWithSession(string $role): WpUser
    {
        return $this->createWpUser($role, [hash('sha256', self::TOKEN) => ['expiration' => time() + 3600]]);
    }

    /**
     * Builds the cookie the way WordPress does (wp_generate_auth_cookie() with scheme 'logged_in'):
     * "username|expiration|token|hmac", where
     *   key  = hmac_md5("username|pass_frag|expiration|token", LOGGED_IN_KEY . LOGGED_IN_SALT)
     *   hmac = hmac_sha256("username|expiration|token", key)
     * and pass_frag is 4 characters of the stored password hash (see WpUser::passFrag()).
     */
    private function setLoginCookie(WpUser $user, int $expiration, ?string $hmac = null): void
    {
        $token = self::TOKEN;
        // Like WordPress 6.8+: old phpass/bcrypt hashes use characters 8-11, "$wp$" hashes the last 4.
        $passFrag = str_starts_with($user->user_pass, '$P$') || str_starts_with($user->user_pass, '$2y$')
            ? substr($user->user_pass, 8, 4)
            : substr($user->user_pass, -4);
        $key = hash_hmac(
            'md5',
            "{$user->user_login}|{$passFrag}|{$expiration}|{$token}",
            LOGGED_IN_KEY . LOGGED_IN_SALT,
        );
        $hmac ??= hash_hmac('sha256', "{$user->user_login}|{$expiration}|{$token}", $key);

        $cookieName = 'wordpress_logged_in_' . md5(WpOptions::getOptionValue('siteurl'));
        $_COOKIE[$cookieName] = "{$user->user_login}|{$expiration}|{$token}|{$hmac}";
    }
}
