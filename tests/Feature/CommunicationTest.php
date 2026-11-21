<?php

namespace Tests\Feature;

use App\Models\Bookings;
use App\Models\Places;
use App\Services\Communication;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Communication sends SMS through 46elks. The HTTP client is faked: nothing leaves the machine.
 */
class CommunicationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Communication::$sent = [];
        Http::preventStrayRequests();
    }

    private function configure(?string $username = 'u-test', ?string $from = null, bool $dryrun = false): void
    {
        config(['services.elks' => ['username' => $username, 'password' => $username ? 'p-test' : null, 'from' => $from, 'dryrun' => $dryrun]]);
    }

    public function test_sms_request_to_46elks(): void
    {
        $this->configure(from: 'LHHojden', dryrun: true);
        Http::fake([Communication::API_URL => Http::response(['status' => 'created', 'id' => 's1', 'parts' => 1])]);

        Communication::informUser($this->booking(), 'Hej åäö');

        Http::assertSent(fn (Request $request) => $request->url() === Communication::API_URL
            && $request->method() === 'POST'
            && $request->hasHeader('Authorization', 'Basic ' . base64_encode('u-test:p-test'))
            && $request['to'] === '+46701234567'
            && $request['message'] === 'Hej åäö'
            && $request['from'] === 'LHHojden'
            && $request['dryrun'] === 'yes');
    }

    public function test_default_sender_and_no_dryrun(): void
    {
        $this->configure();
        Http::fake([Communication::API_URL => Http::response(['status' => 'created'])]);

        Communication::informUser($this->booking(), 'Hej');

        Http::assertSent(fn (Request $request) => $request['from'] === Communication::DEFAULT_FROM
            && !isset($request['dryrun']));
    }

    public function test_without_credentials_nothing_is_sent(): void
    {
        $this->configure(username: null);
        Http::fake();

        Communication::informUser($this->booking(), 'Hej');

        Http::assertNothingSent();
        $this->assertCount(1, Communication::$sent, 'still collected for tests');
    }

    public function test_46elks_error_does_not_throw(): void
    {
        $this->configure();
        Http::fake([Communication::API_URL => Http::response('Invalid to number', 403)]);

        $log = tempnam(sys_get_temp_dir(), 'errlog');
        $previous = ini_set('error_log', $log);
        try {
            Communication::informUser($this->booking(), 'Hej');
        } finally {
            ini_set('error_log', $previous);
        }

        $this->assertStringContainsString('SMS to +46701234567 failed: HTTP 403: Invalid to number', file_get_contents($log));
        unlink($log);
    }

    public function test_admin_message_goes_to_the_place_admin_phone(): void
    {
        $this->configure();
        Http::fake([Communication::API_URL => Http::response(['status' => 'created'])]);

        $place = new Places();
        $place->id = 1;
        $place->admin_phone = 793377895;
        Communication::informAdmin($place, 'Ny förfrågan');

        Http::assertSent(fn (Request $request) => $request['to'] === '+46793377895');
    }

    private function booking(): Bookings
    {
        $booking = new Bookings();
        $booking->phone = 701234567;
        return $booking;
    }
}
