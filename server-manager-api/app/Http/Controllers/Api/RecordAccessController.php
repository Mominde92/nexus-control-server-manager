<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Domain;
use App\Models\Hosting;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class RecordAccessController extends Controller
{
    public function index(Request $request, string $resource, int $id): JsonResponse
    {
        abort_unless($request->user()?->can(Permission::ManageRole->value), 403);
        $record = $this->record($resource, $id);
        return ApiResponse::success($record->accessRules()->with('user:id,name,email')->get()->map(fn ($rule) => [
            'user_id' => $rule->user_id, 'user' => $rule->user,
            'can_view' => $rule->can_view, 'can_update' => $rule->can_update,
            'can_delete' => $rule->can_delete, 'can_view_credentials' => $rule->can_view_credentials,
        ])->values());
    }

    public function update(Request $request, string $resource, int $id): JsonResponse
    {
        abort_unless($request->user()?->can(Permission::ManageRole->value), 403);
        $data = $request->validate(['rules' => ['array'], 'rules.*.user_id' => ['required','integer','exists:users,id'], 'rules.*.can_view' => ['boolean'], 'rules.*.can_update' => ['boolean'], 'rules.*.can_delete' => ['boolean'], 'rules.*.can_view_credentials' => ['boolean']]);
        $record = $this->record($resource, $id);
        DB::transaction(function () use ($record, $data): void {
            $record->accessRules()->delete();
            foreach ($data['rules'] ?? [] as $rule) $record->accessRules()->create($rule);
        });
        return ApiResponse::success(null, 'Record access updated successfully.');
    }

    private function record(string $resource, int $id): Model
    {
        $class = match ($resource) { 'hostings' => Hosting::class, 'domains' => Domain::class, default => abort(404) };
        return $class::query()->findOrFail($id);
    }
}
