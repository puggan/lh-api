<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * GET /api/places
 *
 * Runs against the configured database inside a transaction that is rolled back after each test.
 */
class PlacesTest extends TestCase
{
    use DatabaseTransactions;

    public function test_places_do_not_show_the_admin_phone(): void
    {
        $place = DB::table('places')->insertGetId([
            'name' => 'Test', 'description' => '', 'street' => 'Gata 1', 'zipcode' => 12345, 'city' => 'Test',
            'admin_phone' => 701234567,
        ]);

        $json = collect($this->getJson('/api/places')->assertOk()->json())->firstWhere('id', $place);

        $this->assertSame('Test', $json['name']);
        $this->assertArrayNotHasKey('admin_phone', $json);
    }
}
