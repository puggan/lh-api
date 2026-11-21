<?php

namespace Tests\Feature;

use App\Services\Communication;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * POST /api/calender/book, the booking request from the public calendar.
 *
 * Runs against the configured database inside a transaction that is rolled back after each test.
 * No mail is sent while testing; Communication::$sent collects the messages.
 */
class BookTest extends TestCase
{
    use DatabaseTransactions;

    private int $place;
    private int $terms;
    private int $period;

    protected function setUp(): void
    {
        parent::setUp();
        Communication::$sent = [];

        $this->place = DB::table('places')->insertGetId(['name' => 'Lokalen', 'description' => '', 'street' => 'Gata 1', 'zipcode' => 12345, 'city' => 'Test']);
        $this->terms = DB::table('terms')->insertGetId(['content' => 'test terms']);
        $this->period = $this->period(now()->addDays(10)->toDateString());
    }

    public function test_valid_request_creates_a_pending_booking_and_informs_both(): void
    {
        $response = $this->post('/api/calender/book', $this->form(['phone' => '+46 70-123 45 67', 'zipcode' => '123 45']))
            ->assertOk()
            ->assertJson(['status' => 'pending', 'phone' => 701234567, 'zipcode' => 12345]);

        $this->assertDatabaseHas('bookings', ['id' => $response->json('id'), 'status' => 'pending', 'description' => 'Kalas']);
        $this->assertDatabaseHas('logs', ['table_name' => 'bookings', 'row_id' => $response->json('id'), 'action' => 'created']);

        $this->assertCount(2, Communication::$sent);
        [$admin, $user] = Communication::$sent;
        $this->assertSame(Communication::ADMIN_EMAIL, $admin['to']);
        $this->assertSame('test@example.com', $admin['replyTo']);
        $this->assertStringContainsString('Telefon: 0701234567', $admin['message']);
        $this->assertSame('test@example.com', $user['to']);
        $this->assertStringContainsString('Lokal: Lokalen', $user['message']);
    }

    public function test_missing_and_wrong_fields_are_422_with_swedish_messages(): void
    {
        $this->post('/api/calender/book', $this->form(['name' => '', 'phone' => '12', 'apartment' => 'A1', 'email' => 'nope']))
            ->assertStatus(422)
            ->assertJson(['ok' => false])
            ->assertJsonValidationErrors(['name', 'phone', 'apartment', 'email'])
            ->assertJsonPath('errors.apartment.0', 'Fyll i ditt lägenhetsnummer, t.ex. 123.');

        $this->assertEmpty(Communication::$sent);
    }

    public function test_period_that_has_started_is_422(): void
    {
        $this->assertPeriodRejected($this->period(now()->toDateString()), 'Perioden har redan börjat och kan inte bokas.');
    }

    public function test_deleted_period_is_422(): void
    {
        $this->assertPeriodRejected($this->period(now()->addDays(10)->toDateString(), deleted: true), 'Perioden finns inte längre, ladda om sidan.');
    }

    public function test_booked_period_is_422(): void
    {
        DB::table('bookings')->insert($this->form(['status' => 'booked', 'periods_id' => $this->period, 'phone' => 701234567, 'description' => 'x', 'purpose' => null]) + []);
        $this->assertPeriodRejected($this->period, 'Perioden är redan bokad.');
    }

    public function test_old_terms_are_422(): void
    {
        DB::table('terms')->insert(['content' => 'newer terms']);

        $this->post('/api/calender/book', $this->form())
            ->assertStatus(422)
            ->assertJsonPath('errors.terms_id.0', 'Villkoren har ändrats, ladda om sidan och läs de nya villkoren.');
    }

    private function assertPeriodRejected(int $period, string $message): void
    {
        $this->post('/api/calender/book', $this->form(['periods_id' => $period]))
            ->assertStatus(422)
            ->assertJsonPath('errors.periods_id.0', $message);
        $this->assertEmpty(Communication::$sent);
    }

    private function form(array $override = []): array
    {
        return array_filter($override + [
            'periods_id' => $this->period, 'terms_id' => $this->terms, 'purpose' => 'Kalas',
            'name' => 'Test Person', 'phone' => '070-123 45 67', 'email' => 'test@example.com',
            'street' => 'Gata 1', 'apartment' => '466', 'zipcode' => '12345', 'city' => 'Test',
        ], static fn ($v) => $v !== null);
    }

    private function period(string $date, bool $deleted = false): int
    {
        return DB::table('periods')->insertGetId([
            'places_id' => $this->place, 'start_date' => $date, 'end_date' => $date,
            'deleted_at' => $deleted ? now() : null,
        ]);
    }
}
