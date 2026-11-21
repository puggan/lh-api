<?php

namespace App\Http\Controllers;

use App\Models\Periods;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Admin: bookable periods.
 */
class PeriodsController
{
    /**
     * Creates a bookable period. 422 if a field is wrong, the period starts in the past,
     * or it overlaps another (not deleted) period of the same place.
     */
    public static function create(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'places_id' => ['required', 'integer', 'exists:places,id'],
            'start_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
        ]);
        $validator->after(static function (\Illuminate\Validation\Validator $validator) use ($request) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $overlapping = Periods::query()
                ->where('places_id', '=', $request->input('places_id'))
                ->where('start_date', '<=', $request->input('end_date'))
                ->where('end_date', '>=', $request->input('start_date'))
                ->first();
            if ($overlapping) {
                $validator->errors()->add('start_date', "Overlaps period {$overlapping->id} ({$overlapping->start_date} – {$overlapping->end_date}).");
            }
        });

        if ($validator->fails()) {
            return new JsonResponse(['ok' => false, 'errors' => $validator->errors()], 422);
        }

        $period = new Periods();
        $period->places_id = $request->input('places_id');
        $period->start_date = $request->input('start_date');
        $period->end_date = $request->input('end_date');
        $period->save();

        return new JsonResponse($period->refresh(), 201);
    }

    /**
     * Soft deletes a period. 404 if it does not exist or is already deleted.
     * 409 if it has a pending or booked booking: decline those first, so the booker is informed.
     */
    public static function delete(int $id): JsonResponse
    {
        /** @var Periods $period */
        $period = Periods::query()->findOrFail($id);

        if ($period->bookings()->whereIn('status', ['pending', 'booked'])->exists()) {
            return new JsonResponse(['ok' => false, 'error' => 'The period has pending or booked bookings'], 409);
        }

        // Through the model, so it is a soft delete and it is logged.
        $period->delete();

        return new JsonResponse(['ok' => true]);
    }
}
