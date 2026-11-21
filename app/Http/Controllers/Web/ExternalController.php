<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Shipment;
use App\Services\GeocodingService;
use App\Services\TrackingService;
use App\Services\WeatherService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/** JSON endpoints used by vanilla-JS fetch() calls on the pages. */
class ExternalController extends Controller
{
    public function geocode(Request $request, GeocodingService $geocoding): JsonResponse
    {
        $data = $request->validate(['q' => ['required', 'string', 'min:2', 'max:150']]);

        $result = $geocoding->search($data['q']);

        // 502 when the upstream failed, 200 (possibly empty list) otherwise
        return response()->json($result, $result['ok'] ? 200 : 502);
    }

    public function weather(Request $request, Shipment $shipment, WeatherService $weather, TrackingService $tracking): JsonResponse
    {
        Gate::authorize('view', $shipment);

        $point = $request->query('point', 'current');
        $shipment->loadMissing(['origin', 'destination']);

        if ($point === 'destination') {
            [$lat, $lng, $label] = [$shipment->destination->latitude, $shipment->destination->longitude, $shipment->destination->name];
        } elseif ($point === 'origin') {
            [$lat, $lng, $label] = [$shipment->origin->latitude, $shipment->origin->longitude, $shipment->origin->name];
        } else {
            $current = $tracking->currentLocation($shipment);
            if ($current && $current->hasCoordinates()) {
                [$lat, $lng, $label] = [$current->latitude, $current->longitude, $current->location?->name ?? 'Current position'];
            } else {
                [$lat, $lng, $label] = [$shipment->origin->latitude, $shipment->origin->longitude, $shipment->origin->name];
            }
        }

        $result = $weather->current((float) $lat, (float) $lng);
        $result['place'] = $label;

        return response()->json($result, $result['ok'] ? 200 : 502);
    }
}
