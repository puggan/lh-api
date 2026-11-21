<?php

namespace Tests\Unit;

use App\Models\WpUser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * WpUser::passFrag() must pick the same 4 characters as WordPress's wp_validate_auth_cookie(),
 * or logged in users are not recognised (as after the upgrade to WordPress 7.1).
 */
class WpUserPassFragTest extends TestCase
{
    public static function hashes(): array
    {
        return [
            'phpass (WordPress < 6.8)' => ['$P$Babcd' . 'FRAG' . 'efghijklmnopqrstuvwx', 'FRAG'],
            'vanilla bcrypt' => ['$2y$10$a' . 'FRAG' . 'bcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQ', 'FRAG'],
            'WordPress 6.8+ bcrypt' => ['$wp$2y$10$abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQR' . 'FRAG', 'FRAG'],
        ];
    }

    #[DataProvider('hashes')]
    public function test_pass_frag(string $hash, string $expected): void
    {
        $this->assertSame($expected, WpUser::passFrag($hash));
    }
}
