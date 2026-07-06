<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ActivityLogResource;
use App\Models\ActivityLog;
use App\Support\ListQuery;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ActivityLogController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $query = ActivityLog::query()
            ->with('user:id,name')
            ->when($request->string('action')->toString(), fn ($q, $action) => $q->where('action', $action))
            ->when($request->string('subject_type')->toString(), function ($q, $type) {
                $pattern = str_replace(['%', '_'], '', mb_strtolower($type, 'UTF-8')).'%';
                $q->where('subject_type', 'ilike', $pattern);
            })
            ->when($request->string('search')->toString(), function ($q, $search) {
                $pattern = str_replace(['%', '_'], '', mb_strtolower($search, 'UTF-8')).'%';
                $q->where('subject_label', 'ilike', $pattern);
            });

        $logs = $query
            ->orderByDesc('id')
            ->paginate(ListQuery::perPage($request, 50));

        return ActivityLogResource::collection($logs);
    }
}
