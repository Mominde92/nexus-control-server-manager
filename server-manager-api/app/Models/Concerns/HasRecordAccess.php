<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\RecordAccess;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasRecordAccess
{
    public function accessRules(): MorphMany { return $this->morphMany(RecordAccess::class, 'accessible'); }

    public function allowsUser(User $user, string $ability): bool
    {
        if (! $this->accessRules()->exists()) return true;
        return $this->accessRules()->where('user_id', $user->id)->where($ability, true)->exists();
    }
}
