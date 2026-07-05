<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Support\ListQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UserController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', User::class);

        $query = User::query()
            ->when($request->string('role')->toString(), fn ($q, $role) => $q->where('role', $role))
            ->when($request->string('status')->toString(), fn ($q, $status) => $q->where('status', $status));

        ListQuery::applySearch($query, $request, ['name', 'username', 'email']);

        $users = $query
            ->orderBy('name')
            ->paginate(ListQuery::perPage($request, 50));

        return UserResource::collection($users);
    }

    public function store(StoreUserRequest $request): UserResource
    {
        $this->authorize('create', User::class);

        $user = User::query()->create([
            ...$request->safe()->except(['password', 'password_confirmation']),
            'password' => $request->string('password'),
            'status' => $request->enum('status', UserStatus::class) ?? UserStatus::Active,
            'is_manager' => $request->boolean('is_manager'),
        ]);

        return new UserResource($user);
    }

    public function show(User $user): UserResource
    {
        $this->authorize('view', $user);

        return new UserResource($user);
    }

    public function update(UpdateUserRequest $request, User $user): UserResource
    {
        $this->authorize('update', $user);

        $attributes = $request->safe()->except(['password', 'password_confirmation']);

        if ($request->filled('password')) {
            $attributes['password'] = $request->string('password');
        }

        $user->update($attributes);

        return new UserResource($user->fresh());
    }

    public function destroy(User $user): JsonResponse
    {
        $this->authorize('delete', $user);

        $user->delete();

        return response()->json(['message' => 'User deleted.']);
    }
}
