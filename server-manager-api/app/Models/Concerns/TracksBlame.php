<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * Stamps `created_by` / `updated_by` from the authenticated user.
 *
 * Values already set explicitly (e.g. by a seeder) are respected.
 */
trait TracksBlame
{
    public static function bootTracksBlame(): void
    {
        static::creating(function ($model): void {
            $userId = Auth::id();

            if ($userId !== null) {
                $model->created_by ??= $userId;
                $model->updated_by ??= $userId;
            }
        });

        static::updating(function ($model): void {
            $userId = Auth::id();

            if ($userId !== null) {
                $model->updated_by = $userId;
            }
        });
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
