<?php

namespace Tests\Feature;

use App\Models\WpUser;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The admin endpoints (RequireWpAdmin): WordPress editors and administrators only, writes as JSON.
 *
 * Runs against the configured database inside a transaction that is rolled back after each test.
 */
class AdminAuthTest extends TestCase
{
    use DatabaseTransactions;

    public static function adminEndpoints(): array
    {
        return [
            'pending' => ['GET', '/api/request/pending'],
            'approve' => ['POST', '/api/request/approve'],
            'decline' => ['POST', '/api/request/decline'],
            'spam' => ['POST', '/api/request/spam'],
            'cleanup' => ['POST', '/api/request/cleanup'],
            'create period' => ['POST', '/api/periods'],
            'delete period' => ['DELETE', '/api/periods/1'],
            'publish terms' => ['POST', '/api/terms'],
            'calendar feeds' => ['GET', '/api/calender/feeds'],
        ];
    }

    #[DataProvider('adminEndpoints')]
    public function test_not_logged_in_is_401(string $method, string $uri): void
    {
        $this->json($method, $uri)
            ->assertStatus(401)
            ->assertJson(['ok' => false]);
    }

    #[DataProvider('adminEndpoints')]
    public function test_subscriber_is_403(string $method, string $uri): void
    {
        $this->actingAsWpUser('subscriber');

        $this->json($method, $uri)
            ->assertStatus(403)
            ->assertJson(['ok' => false]);
    }

    public function test_editor_is_allowed(): void
    {
        $this->actingAsWpUser('editor');

        $this->getJson('/api/request/pending')->assertOk();
    }

    public function test_writes_must_be_json(): void
    {
        $this->actingAsWpUser();

        // A plain form post, as an HTML form on another site would send it.
        $this->post('/api/request/decline', ['bookings_id' => 1], ['Accept' => 'application/json'])
            ->assertStatus(415);
    }

    public function test_calendar_shows_bookings_to_admins_only(): void
    {
        $place = DB::table('places')->insertGetId(['name' => 'Test', 'description' => '', 'street' => 'Gata 1', 'zipcode' => 12345, 'city' => 'Test']);
        $period = DB::table('periods')->insertGetId(['places_id' => $place, 'start_date' => '2030-01-01', 'end_date' => '2030-01-01']);
        $bookings = fn () => collect($this->getJson('/api/calender')->assertOk()->json())->firstWhere('id', $period)['bookings'];

        $this->assertNull($bookings(), 'not logged in');

        $this->actingAsWpUser('subscriber');
        $this->assertNull($bookings(), 'subscriber');

        $this->actingAsWpUser('administrator');
        $this->assertSame([], $bookings(), 'administrator');
    }
}
