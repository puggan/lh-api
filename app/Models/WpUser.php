<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * A WordPress user (lh_users). The API shares the login with WordPress: see loggedIn().
 *
 * @property int $ID BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 * @property string $user_login VARCHAR(60) DEFAULT '' NOT NULL,
 * @property string $user_pass VARCHAR(255) DEFAULT '' NOT NULL,
 * @property string $user_nicename VARCHAR(50) DEFAULT '' NOT NULL,
 * @property string $user_email VARCHAR(100) DEFAULT '' NOT NULL,
 * @property string $user_url VARCHAR(100) DEFAULT '' NOT NULL,
 * @property \DateTimeImmutable $user_registered DATETIME DEFAULT '0000-00-00 00:00:00' NOT NULL,
 * @property string $user_activation_key VARCHAR(255) DEFAULT '' NOT NULL,
 * @property int $user_status INT DEFAULT 0 NOT NULL,
 * @property string $display_name VARCHAR(250) DEFAULT '' NOT NULL
 */
class WpUser extends Model
{
    /** WordPress table prefix ($table_prefix in wp-config.php). */
    public const TABLE_PREFIX = 'lh_';

    protected $table = self::TABLE_PREFIX . 'users';
    protected $primaryKey = 'ID';
    public $timestamps = false;
    protected $casts = [
        'user_registered' => 'immutable_datetime',
    ];

    /** The answer of loggedIn() for this request: a user, false for "nobody", null for "not checked yet". */
    private static self|false|null $loggedInUser = null;

    /** @var ?array<string, bool> */
    private ?array $capabilities = null;

    /**
     * The user of WordPress's "logged in" cookie, checked the way wp_validate_auth_cookie() does:
     * not expired, signed with LOGGED_IN_KEY/LOGGED_IN_SALT and the user's password hash,
     * and its session still active (logging out of WordPress ends it).
     */
    public static function loggedIn(): ?self
    {
        if (self::$loggedInUser === null) {
            self::$loggedInUser = self::fromCookie() ?? false;
        }
        return self::$loggedInUser ?: null;
    }

    /** For tests: act as $user (or as nobody), without a cookie. Null checks the cookie again. */
    public static function setLoggedIn(self|false|null $user): void
    {
        self::$loggedInUser = $user;
    }

    private static function fromCookie(): ?self
    {
        $siteUrl = WpOptions::getOptionValue('siteurl');
        $cookie = $siteUrl ? ($_COOKIE['wordpress_logged_in_' . md5($siteUrl)] ?? null) : null;
        if (!is_string($cookie)) {
            return null;
        }

        $parts = explode('|', $cookie);
        if (count($parts) !== 4) {
            return null;
        }
        [$username, $expiration, $token, $hmac] = $parts;

        if ((int)$expiration < time()) {
            return null;
        }

        /** @var ?self $user */
        $user = self::query()->where('user_login', '=', $username)->first();
        if (!$user) {
            return null;
        }

        $key = hash_hmac(
            'md5',
            implode('|', [$username, self::passFrag($user->user_pass), $expiration, $token]),
            LOGGED_IN_KEY . LOGGED_IN_SALT,
        );
        $expected = hash_hmac('sha256', implode('|', [$username, $expiration, $token]), $key);
        if (!hash_equals($expected, $hmac)) {
            return null;
        }

        if (!$user->hasActiveSession($token)) {
            return null;
        }

        return $user;
    }

    /**
     * WordPress keeps the user's sessions in the usermeta "session_tokens", keyed by sha256 of the
     * cookie's token (WP_User_Meta_Session_Tokens). A logout removes the session.
     */
    public function hasActiveSession(string $token): bool
    {
        $sessions = $this->meta('session_tokens');
        $session = is_array($sessions) ? ($sessions[hash('sha256', $token)] ?? null) : null;
        return is_array($session) && ($session['expiration'] ?? 0) >= time();
    }

    /**
     * Whether the user has a WordPress capability, e.g. 'edit_pages' (editors and administrators),
     * from the roles in usermeta "<prefix>capabilities" and the role definitions in the option
     * "<prefix>user_roles", like WP_User::has_cap() (without plugins' map_meta_cap filters).
     */
    public function hasCapability(string $capability): bool
    {
        if ($this->capabilities === null) {
            $roles = @unserialize(
                (string)WpOptions::query()->where('option_name', '=', self::TABLE_PREFIX . 'user_roles')->value('option_value'),
                ['allowed_classes' => false],
            ) ?: [];
            $capabilities = [];
            foreach ((array)$this->meta(self::TABLE_PREFIX . 'capabilities') as $name => $granted) {
                if (!$granted) {
                    continue;
                }
                if (isset($roles[$name]['capabilities'])) {
                    $capabilities = array_merge($capabilities, array_filter($roles[$name]['capabilities']));
                } else {
                    $capabilities[$name] = true; // a capability given directly to the user
                }
            }
            $this->capabilities = $capabilities;
        }
        return !empty($this->capabilities[$capability]);
    }

    private function meta(string $key): mixed
    {
        $value = DB::table(self::TABLE_PREFIX . 'usermeta')
            ->where('user_id', '=', $this->ID)
            ->where('meta_key', '=', $key)
            ->value('meta_value');
        // @: WordPress stores some values unserialized; those come back as false.
        return $value === null ? null : @unserialize($value, ['allowed_classes' => false]);
    }

    /**
     * The 4 characters of the password hash that are part of the cookie signature, as in
     * WordPress's wp_validate_auth_cookie(). Since WordPress 6.8, passwords are re-hashed on login
     * to the "$wp$2y$..." format, and for those the fragment is taken from the end of the hash.
     */
    public static function passFrag(string $userPass): string
    {
        if (str_starts_with($userPass, '$P$') || str_starts_with($userPass, '$2y$')) {
            return substr($userPass, 8, 4);
        }
        return substr($userPass, -4);
    }
}
