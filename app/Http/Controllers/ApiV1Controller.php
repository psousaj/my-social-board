<?php

namespace App\Http\Controllers;

use App\Models\MediaItem;
use App\Models\MediaMetricSnapshot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApiV1Controller extends Controller
{
    public function media(Request $request): JsonResponse
    {
        $tenantId = (int) $request->attributes->get('tenant_id');
        $page = max(1, (int) $request->query('page', 1));
        $perPage = max(1, min(50, (int) $request->query('per_page', 10)));

        $query = MediaItem::query()->where('tenant_id', $tenantId)->orderByDesc('id');

        if ($request->filled('provider')) {
            $query->where('provider', (string) $request->query('provider'));
        }

        $total = $query->count();
        $items = $query->forPage($page, $perPage)->get();

        return response()->json([
            'data' => $items,
            'meta' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
            ],
        ]);
    }

    public function mediaItem(Request $request, int $itemId): JsonResponse
    {
        $tenantId = (int) $request->attributes->get('tenant_id');
        $item = MediaItem::query()->where('tenant_id', $tenantId)->where('id', $itemId)->first();

        if (! $item) {
            return response()->json(['message' => 'Media item not found.', 'error_code' => 'MEDIA_ITEM_NOT_FOUND'], 404);
        }

        return response()->json($item);
    }

    public function metrics(Request $request): JsonResponse
    {
        $tenantId = (int) $request->attributes->get('tenant_id');
        $page = max(1, (int) $request->query('page', 1));
        $perPage = max(1, min(50, (int) $request->query('per_page', 10)));

        $query = MediaMetricSnapshot::query()->where('tenant_id', $tenantId)->orderByDesc('id');

        if ($request->filled('media_item_id')) {
            $query->where('media_item_id', (int) $request->query('media_item_id'));
        }

        $total = $query->count();
        $items = $query->forPage($page, $perPage)->get();

        return response()->json([
            'data' => $items,
            'meta' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
            ],
        ]);
    }

    public function usage(Request $request): JsonResponse
    {
        $tenantId = (int) $request->attributes->get('tenant_id');

        return response()->json([
            'tenant_id' => $tenantId,
            'media_items' => MediaItem::query()->where('tenant_id', $tenantId)->count(),
            'snapshots' => MediaMetricSnapshot::query()->where('tenant_id', $tenantId)->count(),
        ]);
    }
}
