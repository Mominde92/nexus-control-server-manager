<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\Permission as PermissionEnum;
use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

final class RoleController extends Controller
{
    private function authorizeManage(Request $request): void { abort_unless($request->user()?->can(PermissionEnum::ManageRole->value), 403); }
    public function index(Request $request): JsonResponse { $this->authorizeManage($request); return ApiResponse::success(Role::with('permissions:id,name')->orderBy('name')->get()->map(fn(Role $r)=>$this->payload($r))); }
    public function show(Request $request, Role $role): JsonResponse { $this->authorizeManage($request); return ApiResponse::success($this->payload($role->load('permissions:id,name'))); }
    public function store(Request $request): JsonResponse { $this->authorizeManage($request); $data=$this->validated($request); $role=DB::transaction(function()use($data){$role=Role::create(['name'=>$data['name'],'guard_name'=>'web']);$role->syncPermissions($data['permissions']);return $role;});app(PermissionRegistrar::class)->forgetCachedPermissions();return ApiResponse::created($this->payload($role->load('permissions:id,name')),'Role created successfully.'); }
    public function update(Request $request, Role $role): JsonResponse { $this->authorizeManage($request); abort_if($role->name==='admin'&&$request->input('name')!=='admin',422,'The Admin role cannot be renamed.');$data=$this->validated($request,$role);DB::transaction(function()use($role,$data){$role->update(['name'=>$data['name']]);$role->syncPermissions($data['permissions']);});app(PermissionRegistrar::class)->forgetCachedPermissions();return ApiResponse::success($this->payload($role->load('permissions:id,name')),'Role updated successfully.'); }
    public function destroy(Request $request, Role $role): JsonResponse { $this->authorizeManage($request);abort_if(in_array($role->name,['admin','manager','employee'],true),422,'System roles cannot be deleted.');abort_if(DB::table('model_has_roles')->where('role_id',$role->id)->exists(),422,'Assign users to another role before deleting this role.');$role->delete();app(PermissionRegistrar::class)->forgetCachedPermissions();return ApiResponse::deleted('Role deleted successfully.'); }
    private function validated(Request $request,?Role $role=null): array { return $request->validate(['name'=>['required','string','max:80','regex:/^[a-z0-9_-]+$/',Rule::unique('roles','name')->ignore($role?->id)],'permissions'=>['array'],'permissions.*'=>['string',Rule::exists('permissions','name')]]); }
    private function payload(Role $role): array { return ['id'=>$role->id,'name'=>$role->name,'permissions'=>$role->permissions->pluck('name')->values(),'users_count'=>DB::table('model_has_roles')->where('role_id',$role->id)->count(),'system'=>in_array($role->name,['admin','manager','employee'],true)]; }
}
