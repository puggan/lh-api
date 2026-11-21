<?php

namespace Tests;

use App\Models\WpUser;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // WpUser::loggedIn() remembers its answer for the request; start each test logged out.
        WpUser::setLoggedIn(null);
    }

    /**
     * Creates a WordPress user with $role (e.g. 'administrator', 'editor', 'subscriber') and acts as
     * that user for the API. Use with DatabaseTransactions, so the user is removed afterwards.
     *
     * @param array<string, array<string, mixed>> $sessions usermeta session_tokens, keyed by sha256 of the token
     */
    protected function createWpUser(string $role = 'administrator', array $sessions = []): WpUser
    {
        $login = 'lh-api-test-' . bin2hex(random_bytes(4));
        $id = DB::table(WpUser::TABLE_PREFIX . 'users')->insertGetId([
            'user_login' => $login,
            'user_pass' => '$P$B' . str_repeat('x', 30),
            'user_nicename' => $login,
            'user_email' => "{$login}@example.com",
            'user_registered' => now(),
            'display_name' => $login,
        ]);
        DB::table(WpUser::TABLE_PREFIX . 'usermeta')->insert([
            ['user_id' => $id, 'meta_key' => WpUser::TABLE_PREFIX . 'capabilities', 'meta_value' => serialize([$role => true])],
            ['user_id' => $id, 'meta_key' => 'session_tokens', 'meta_value' => serialize($sessions)],
        ]);
        return WpUser::query()->findOrFail($id);
    }

    protected function actingAsWpUser(string $role = 'administrator'): WpUser
    {
        $user = $this->createWpUser($role);
        WpUser::setLoggedIn($user);
        return $user;
    }
}
