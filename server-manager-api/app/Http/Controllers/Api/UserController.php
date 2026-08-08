<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Contracts\Services\UserServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\ChangeUserStatusRequest;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Support\ApiResponse;
use App\Support\QueryOptions;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class UserController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private readonly UserServiceInterface $users) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', User::class);
        return ApiResponse::paginated(UserResource::collection($this->users->paginate(QueryOptions::fromRequest($request, ['status', 'role']))));
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $this->authorize('create', User::class);
        return ApiResponse::created(new UserResource($this->users->create($request->validated())), 'User created successfully.');
    }

    public function show(int $id): JsonResponse
    {
        $user = $this->users->find($id); $this->authorize('view', $user);
        return ApiResponse::resource(new UserResource($user));
    }

    public function update(UpdateUserRequest $request, int $id): JsonResponse
    {
        $user = $this->users->find($id); $this->authorize('update', $user);
        return ApiResponse::resource(new UserResource($this->users->update($user, $request->validated())), 'User updated successfully.');
    }

    public function destroy(int $id): JsonResponse
    {
        $user = $this->users->find($id); $this->authorize('delete', $user); $this->users->delete($user);
        return ApiResponse::deleted('User deleted successfully.');
    }

    public function restore(int $id): JsonResponse
    {
        $user = $this->users->findWithTrashed($id); $this->authorize('restore', $user); $this->users->restore($user);
        return ApiResponse::resource(new UserResource($this->users->find($id)), 'User restored successfully.');
    }

    public function status(ChangeUserStatusRequest $request, int $id): JsonResponse
    {
        $user = $this->users->find($id); $this->authorize('update', $user);
        return ApiResponse::resource(new UserResource($this->users->changeStatus($user, $request->status())), 'User status updated.');
    }
}
