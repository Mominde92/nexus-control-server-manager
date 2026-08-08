<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Http\Resources\Concerns\FormatsEnums;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Minimal account shape — used for "created by" attributions and assignee
 * pickers, where the permission matrix would be wasted bytes.
 *
 * @mixin User
 */
class UserSummaryResource extends JsonResource
{
    use FormatsEnums;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'avatar_path' => $this->avatar_path,
            'role' => $this->whenLoaded('roles', fn (): ?array => $this->labelPayload($this->primaryRole())),
        ];
    }
}
