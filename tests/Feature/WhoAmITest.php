<?php

namespace Tests\Feature;

use App\Models\WpOptions;
use App\Models\WpUser;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tests\TestCase;

/**
 * GET /api/who-am-i, which checks the WordPress "logged in" cookie.
 *
 * Read-only: it only reads lh_options and lh_users from the configured (WordPress) database.
 *
 * Each test runs in its own PHP process, because WpUser::loggedIn() caches its answer
 * in a static variable and reads $_COOKIE directly.
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class WhoAmITest extends TestCase
{
    public function test_without_cookie_is_not_logged_in(): void
    {
        $this->getJson('/api/who-am-i')
            ->assertOk()
            ->assertExactJson(['loggedIn' => false]);
    }

    public function test_valid_cookie_is_logged_in(): void
    {
        $user = $this->anyUser();
        $this->setLoginCookie($user, time() + 3600);

        $this->getJson('/api/who-am-i')
            ->assertOk()
            ->assertExactJson(['loggedIn' => true, 'who' => $user->user_login]);
    }

    public function test_cookie_with_wrong_signature_is_not_logged_in(): void
    {
        $user = $this->anyUser();
        $this->setLoginCookie($user, time() + 3600, hmac: str_repeat('0', 64));

        $this->getJson('/api/who-am-i')
            ->assertOk()
            ->assertExactJson(['loggedIn' => false]);
    }

    public function test_expired_cookie_is_not_logged_in(): void
    {
        $user = $this->anyUser();
        $this->setLoginCookie($user, time() - 60);

        $this->getJson('/api/who-am-i')
            ->assertOk()
            ->assertExactJson(['loggedIn' => false]);
    }

    private function anyUser(): WpUser
    {
        /** @var ?WpUser $user */
        $user = WpUser::query()->first();
        if (!$user) {
            $this->markTestSkipped('No WordPress users in the database.');
        }
        return $user;
    }

    /**
     * Builds the cookie the way WordPress does (wp_generate_auth_cookie() with scheme 'logged_in'):
     * "username|expiration|token|hmac", where
     *   key  = hmac_md5("username|pass_frag|expiration|token", LOGGED_IN_KEY . LOGGED_IN_SALT)
     *   hmac = hmac_sha256("username|expiration|token", key)
     * and pass_frag is 4 characters of the stored password hash.
     */
    private function setLoginCookie(WpUser $user, int $expiration, ?string $hmac = null): void
    {
        $token = 'test-token';
        $passFrag = substr($user->user_pass, 8, 4);
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
