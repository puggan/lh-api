<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
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
    protected $table = 'lh_users';
    protected $primaryKey = 'ID';
    public $timestamps = false;
    protected $casts = [
        'user_registered' => 'immutable_datetime',
    ];

    public static function loggedIn(): ?self
    {
        /** @var self|false|null $loggedInUser */
        static $loggedInUser = null;

        if ($loggedInUser === false) {
            return null;
        }

        if ($loggedInUser instanceof self) {
            return $loggedInUser;
        }

        $siteUrl = WpOptions::getOptionValue('siteurl');
        if (!$siteUrl) {
            $loggedInUser = false;
            return null;
        }

        $cookieRawValue = $_COOKIE['wordpress_logged_in_' . md5($siteUrl)] ?? null;

        if (!$cookieRawValue) {
            $loggedInUser = false;
            return null;
        }

        /** @var object{username: string, expiration: string, token: string, hmac: string, scheme: string} $cookieValues */
        $cookieValues = (object)array_combine(
            [
                'username',
                'expiration',
                'token',
                'hmac',
                'scheme',
            ],
            array_slice(
                explode('|', $cookieRawValue . '|logged_in||||'),
                0,
                5
            )
        );

        if ($cookieValues->expiration < time()) {
            return null;
        }

        $user = self::query()->where('user_login', '=', $cookieValues->username)->first();
        if (!$user) {
            $loggedInUser = false;
            return null;
        }

        $hash = hash_hmac(
            'sha256',
            implode(
                '|',
                [
                    $cookieValues->username,
                    $cookieValues->expiration,
                    $cookieValues->token,
                ]
            ),
            hash_hmac(
                'md5',
                implode(
                    '|',
                    [
                        $cookieValues->username,
                        substr($user->user_pass, 8, 4),
                        $cookieValues->expiration,
                        $cookieValues->token,
                    ]
                ),
                LOGGED_IN_KEY . LOGGED_IN_SALT
            )
        );

        if (!hash_equals($hash, $cookieValues->hmac)) {
            $loggedInUser = false;
            return null;
        }

        $loggedInUser = $user;
        return $loggedInUser;
    }
}
