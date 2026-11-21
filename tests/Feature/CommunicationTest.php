<?php

namespace Tests\Feature;

use App\Models\Bookings;
use App\Models\Places;
use App\Services\Communication;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Communication sends SMS through SMSAPI. The HTTP client is faked: nothing leaves the machine.
 */
class CommunicationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Communication::$sent = [];
        Http::preventStrayRequests();
    }

    public function test_sms_request_to_smsapi(): void
    {
        config(['services.smsapi' => ['token' => 'test-token', 'from' => 'LH Hojden', 'test' => true]]);
        Http::fake([Communication::API_URL => Http::response(['count' => 1, 'list' => [['id' => '1', 'status' => 'QUEUE']]])]);

        Communication::informUser($this->booking(), 'Hej åäö');

        Http::assertSent(fn (Request $request) => $request->url() === Communication::API_URL
            && $request->method() === 'POST'
            && $request->hasHeader('Authorization', 'Bearer test-token')
            && $request['to'] === '46701234567'
            && $request['message'] === 'Hej åäö'
            && $request['from'] === 'LH Hojden'
            && $request['format'] === 'json'
            && (string)$request['test'] === '1');
    }

    public function test_without_token_nothing_is_sent(): void
    {
        config(['services.smsapi' => ['token' => null, 'from' => null, 'test' => false]]);
        Http::fake();

        Communication::informUser($this->booking(), 'Hej');

        Http::assertNothingSent();
        $this->assertCount(1, Communication::$sent, 'still collected for tests');
    }

    public function test_smsapi_error_does_not_throw(): void
    {
        config(['services.smsapi' => ['token' => 'test-token', 'from' => null, 'test' => false]]);
        Http::fake([Communication::API_URL => Http::response(['error' => 101, 'message' => 'Authorization failed'])]);

        $log = tempnam(sys_get_temp_dir(), 'errlog');
        $previous = ini_set('error_log', $log);
        try {
            Communication::informUser($this->booking(), 'Hej');
        } finally {
            ini_set('error_log', $previous);
        }

        $this->assertStringContainsString('SMS to 46701234567 failed: error 101: Authorization failed', file_get_contents($log));
        unlink($log);
    }

    public function test_admin_message_goes_to_the_place_admin_phone(): void
    {
        config(['services.smsapi' => ['token' => 'test-token', 'from' => null, 'test' => false]]);
        Http::fake([Communication::API_URL => Http::response(['count' => 1])]);

        $place = new Places();
        $place->id = 1;
        $place->admin_phone = 793377895;
        Communication::informAdmin($place, 'Ny förfrågan');

        Http::assertSent(fn (Request $request) => $request['to'] === '46793377895');
    }

    private function booking(): Bookings
    {
        $booking = new Bookings();
        $booking->phone = 701234567;
        return $booking;
    }
}
