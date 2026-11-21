<?php

namespace App\Http\Controllers;

use App\Actions\CleanupOldBookings;
use App\Models\Bookings;
use App\Models\Periods;
use App\Models\Terms;
use App\Models\WpUser;
use App\Services\Communication;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BookingController
{
    /**
     * A booking request from the public calendar form.
     *
     * 422 {"ok": false, "errors": {field: [message, ...]}} with Swedish messages for the booker
     * if a field is missing or wrong, or the period is not bookable.
     */
    public static function book(Request $request): JsonResponse
    {
        $input = $request->all();
        $input['phone'] = self::normalizePhone($request->input('phone'));
        $input['zipcode'] = preg_replace('/\s+/', '', (string)$request->input('zipcode'));

        $validator = Validator::make(
            $input,
            [
                'periods_id' => ['required', 'integer'],
                'name' => ['required', 'string', 'max:255'],
                'phone' => ['required', 'integer'],
                'email' => ['required', 'email', 'max:255'],
                'street' => ['required', 'string', 'max:255'],
                'apartment' => ['required', 'digits:4'],
                'zipcode' => ['required', 'digits:5'],
                'city' => ['required', 'string', 'max:255'],
                'purpose' => ['required', 'string', 'max:5000'],
                'terms_id' => ['required', 'integer'],
            ],
            [
                'periods_id' => 'Välj en period i kalendern.',
                'name' => 'Fyll i ditt namn.',
                'phone' => 'Fyll i ett svenskt telefonnummer, t.ex. 070-123 45 67.',
                'email' => 'Fyll i en giltig e-postadress.',
                'street' => 'Fyll i din gatuadress.',
                'apartment' => 'Fyll i ditt lägenhetsnummer, 4 siffror, t.ex. 1101.',
                'zipcode' => 'Fyll i ett postnummer med 5 siffror.',
                'city' => 'Fyll i din ort.',
                'purpose' => 'Beskriv vad lokalen ska användas till.',
                'terms_id' => 'Du måste godkänna villkoren.',
            ],
        );
        $validator->after(static function (\Illuminate\Validation\Validator $validator) use ($input) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            /** @var ?Periods $period */
            $period = Periods::query()->find($input['periods_id']);
            if (!$period) {
                $validator->errors()->add('periods_id', 'Perioden finns inte längre, ladda om sidan.');
            } elseif ($period->start_date <= now()->toDateString()) {
                $validator->errors()->add('periods_id', 'Perioden har redan börjat och kan inte bokas.');
            } elseif ($period->bookings()->where('status', '=', 'booked')->exists()) {
                $validator->errors()->add('periods_id', 'Perioden är redan bokad.');
            }
            if ((int)$input['terms_id'] !== (int)Terms::query()->max('id')) {
                $validator->errors()->add('terms_id', 'Villkoren har ändrats, ladda om sidan och läs de nya villkoren.');
            }
        });

        if ($validator->fails()) {
            return new JsonResponse(['ok' => false, 'errors' => $validator->errors()], 422);
        }

        $booking = new Bookings();
        $booking->status = 'pending';
        $booking->periods_id = $input['periods_id'];
        $booking->name = $input['name'];
        $booking->phone = $input['phone'];
        $booking->email = $input['email'];
        $booking->street = $input['street'];
        $booking->apartment = $input['apartment'];
        $booking->zipcode = $input['zipcode'];
        $booking->city = $input['city'];
        $booking->description = $input['purpose'];
        $booking->terms_id = $input['terms_id'];
        $booking->save();
        $booking->load('period.place');

        $summary = <<<TEXT_BLOCK
            Lokal: {$booking->period->place->name}
            Period: {$booking->period->start_date} – {$booking->period->end_date}
            Namn: {$booking->name}
            Telefon: 0{$booking->phone}
            E-post: {$booking->email}
            Adress: {$booking->street}, lgh {$booking->apartment}, {$booking->zipcode} {$booking->city}
            Syfte: {$booking->description}
            TEXT_BLOCK;

        Communication::informAdmin(
            'bokningsförfrågan',
            <<<TEXT_BLOCK
                Det har kommit in en bokningsförfrågan.
                Var vänlig svara inom kort från:

                https://hgf-tuve.se/bokningskalender-for-lokalen/request

                {$summary}
                TEXT_BLOCK,
            $booking,
        );
        Communication::informUser(
            $booking,
            'bokningsförfrågan mottagen',
            <<<TEXT_BLOCK
                Vi har tagit emot din bokningsförfrågan
                och kommer att svara inom kort.

                {$summary}
                TEXT_BLOCK,
        );

        return new JsonResponse($booking);
    }

    /**
     * Swedish phone number to the stored form: digits without the leading 0, e.g. "070-123 45 67" → 701234567.
     * Accepts +46 / 0046 and a missing leading 0. Returns null if it is not a Swedish number (8-10 digits with the 0).
     */
    private static function normalizePhone(mixed $phone): ?int
    {
        $digits = preg_replace('/[\s\-()\/.]/', '', (string)$phone);
        $digits = preg_replace('/^(\+46|0046)/', '0', $digits);
        if (preg_match('/^0?([1-9]\d{6,8})$/', $digits, $match)) {
            return (int)$match[1];
        }
        return null;
    }

    public static function pending(): JsonResponse
    {
        $pending = Bookings::query()->with('period')->where('status', '=', 'pending')->get();
        return new JsonResponse($pending);
    }
    /**
     * Runs the same cleanup as the scheduled `bookings:cleanup` command,
     * in case the scheduler is not running. ?dry_run=1 only counts.
     */
    public static function cleanup(Request $request): JsonResponse
    {
        return new JsonResponse(
            CleanupOldBookings::run(
                dryRun: $request->boolean('dry_run'),
                wpUsersId: WpUser::loggedIn()?->ID,
                source: 'endpoint',
            )
        );
    }

    /**
     * Books the period for this booking and copies the place's deposit onto it.
     * 409 if the period already has a booked booking (unique index on booked_periods_id).
     */
    public static function approve(Request $request): JsonResponse
    {
        // Through the model, not a query builder update, so the change is logged.
        /** @var Bookings $booking */
        $booking = Bookings::query()->with('period.place')->findOrFail($request->input('bookings_id'));
        $place = $booking->period->place;
        $booking->status = 'booked';
        $booking->deposit = $place->deposit;

        try {
            $booking->save();
        } catch (UniqueConstraintViolationException) {
            return new JsonResponse(['ok' => false, 'error' => 'The period already has a booked booking'], 409);
        }

        $message = "Din bokning av {$place->name} {$booking->period->start_date} är godkänd.";
        if ($booking->deposit > 0) {
            $message .= " Depositionen är {$booking->deposit} kr.";
        }
        Communication::informUser($booking, 'bokning godkänd', $message);

        return new JsonResponse(['ok' => true]);
    }

    public static function declined(Request $request): JsonResponse
    {
        // Through the model, not a query builder update, so the change is logged.
        /** @var Bookings $booking */
        $booking = Bookings::query()->findOrFail($request->input('bookings_id'));
        $booking->status = 'rejected';
        $booking->save();

        Communication::informUser(
            $booking,
            'bokningsförfrågan avböjd',
            "Tyvärr kan vi inte ta emot din bokning av {$booking->period->place->name} {$booking->period->start_date}.",
        );

        return new JsonResponse(['ok' => true]);
    }
}
