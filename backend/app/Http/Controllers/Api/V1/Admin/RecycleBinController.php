<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Support\ListQuery;
use App\Support\RecycleBinRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class RecycleBinController extends Controller
{
    public function types(): JsonResponse
    {
        abort_unless(request()->user()?->isAdmin(), 403);

        $types = collect(RecycleBinRegistry::types())
            ->map(fn (array $config, string $key) => [
                'key' => $key,
                'label' => $config['label'],
                'module' => $config['module'],
            ])
            ->values();

        return response()->json(['data' => $types]);
    }

    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $type = $request->string('type')->toString();
        $config = RecycleBinRegistry::resolve($type);

        if ($config === null) {
            return response()->json(['message' => 'Unknown recycle bin type.'], 422);
        }

        /** @var class-string<\Illuminate\Database\Eloquent\Model> $modelClass */
        $modelClass = $config['model'];

        $query = $modelClass::onlyTrashed();

        $search = $request->string('search')->trim()->toString();
        if ($search !== '' && $config['name_column'] !== 'id') {
            $column = $config['name_column'];
            $pattern = '%'.str_replace(['%', '_'], ['\%', '\_'], strtolower($search)).'%';
            $query->whereRaw("LOWER({$column}) LIKE ?", [$pattern]);
        }

        $items = $query
            ->orderByDesc('deleted_at')
            ->paginate(ListQuery::perPage($request, 50));

        $data = $items->through(function ($item) use ($config, $type) {
            $label = $config['name_column'] === 'id'
                ? ($item->activityLabel() ?? class_basename($item).' #'.$item->getKey())
                : (string) $item->getAttribute($config['name_column']);

            return [
                'id' => $item->getKey(),
                'type' => $type,
                'label' => $label,
                'module' => $config['module'],
                'type_label' => $config['label'],
                'deleted_at' => $item->deleted_at?->toIso8601String(),
            ];
        });

        return response()->json([
            'data' => $data->items(),
            'meta' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
                'from' => $items->firstItem(),
                'to' => $items->lastItem(),
            ],
        ]);
    }

    public function restore(Request $request, string $type, int $id): JsonResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $config = RecycleBinRegistry::resolve($type);

        if ($config === null) {
            return response()->json(['message' => 'Unknown recycle bin type.'], 422);
        }

        /** @var class-string<\Illuminate\Database\Eloquent\Model&\Illuminate\Database\Eloquent\SoftDeletes> $modelClass */
        $modelClass = $config['model'];

        $record = $modelClass::onlyTrashed()->find($id);

        if ($record === null) {
            return response()->json(['message' => 'Deleted record not found.'], 404);
        }

        $record->restore();

        $label = $config['name_column'] === 'id'
            ? (method_exists($record, 'activityLabel') ? $record->activityLabel() : '#'.$record->getKey())
            : (string) $record->getAttribute($config['name_column']);

        return response()->json([
            'message' => Str::of($config['label'])->append(' "')->append($label)->append('" restored.')->toString(),
        ]);
    }
}
