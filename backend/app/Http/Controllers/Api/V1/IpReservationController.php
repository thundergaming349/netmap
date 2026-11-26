<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Reservations\CreateReservation;
use App\Actions\Reservations\DeleteReservation;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\StoreReservationRequest;
use App\Http\Resources\V1\IpReservationResource;
use App\Models\IpReservation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class IpReservationController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = IpReservation::query()->with('segment')->orderBy('segment_id')->orderBy('start_octet');

        if ($request->filled('segment_id')) {
            $query->where('segment_id', $request->integer('segment_id'));
        }

        return IpReservationResource::collection($query->get());
    }

    public function store(StoreReservationRequest $request, CreateReservation $createReservation): JsonResponse
    {
        /** @var array{segment_id: int, start_octet: int, end_octet: int, label: string, description?: string|null} $validated */
        $validated = $request->validated();
        $reservation = $createReservation->handle($validated, $request->user());

        return IpReservationResource::make($reservation)
            ->additional(['message' => 'Reservasi IP berhasil disimpan.'])
            ->response()
            ->setStatusCode(201);
    }

    public function destroy(Request $request, IpReservation $ipReservation, DeleteReservation $deleteReservation): JsonResponse
    {
        $deleteReservation->handle($ipReservation, $request->user());

        return response()->json(['message' => 'Reservasi IP dihapus.']);
    }
}
