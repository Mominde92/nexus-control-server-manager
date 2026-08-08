<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * Expiry-window behaviour shared by Hosting and Domain.
 *
 * Assumes an `expiry_date` (date) column plus the notification columns
 * `expiry_notification`, `notification_days_before` and `expiry_notified_at`.
 */
trait HasExpiry
{
    /**
     * Whole days until expiry: 0 = expires today, negative = already expired,
     * null = no expiry date recorded.
     */
    public function daysUntilExpiry(): ?int
    {
        $expiry = $this->expiry_date;

        if (! $expiry instanceof CarbonInterface) {
            return null;
        }

        return (int) now()->startOfDay()->diffInDays($expiry->copy()->startOfDay(), false);
    }

    public function isExpired(): bool
    {
        $days = $this->daysUntilExpiry();

        return $days !== null && $days < 0;
    }

    /**
     * True when the record falls inside its own configured warning window.
     */
    public function isExpiringSoon(): bool
    {
        $days = $this->daysUntilExpiry();

        if ($days === null || $days < 0) {
            return false;
        }

        return $days <= (int) ($this->notification_days_before ?? 30);
    }

    /**
     * Not yet expired, but due within the next $days days.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeExpiringWithin(Builder $query, int $days): Builder
    {
        $column = $query->qualifyColumn('expiry_date');

        return $query
            ->whereNotNull($column)
            ->whereDate($column, '>=', now()->toDateString())
            ->whereDate($column, '<=', now()->addDays($days)->toDateString());
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeExpired(Builder $query): Builder
    {
        $column = $query->qualifyColumn('expiry_date');

        return $query
            ->whereNotNull($column)
            ->whereDate($column, '<', now()->toDateString());
    }

    /**
     * Candidate records for an expiry reminder: notifications enabled, expiry
     * still in the future but within $horizon days, and not notified in the
     * last 24 hours.
     *
     * `$horizon` is a coarse outer bound only — deliberately no SQL date
     * arithmetic against `notification_days_before`, because `DATE_ADD` /
     * `INTERVAL` syntax differs between MySQL and PostgreSQL. The caller
     * narrows the (small) result set with `isExpiringSoon()`, which applies
     * each record's own window portably in PHP.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeDueForExpiryNotification(Builder $query, int $horizon = 365): Builder
    {
        $expiry = $query->qualifyColumn('expiry_date');
        $notified = $query->qualifyColumn('expiry_notified_at');

        return $query
            ->where($query->qualifyColumn('expiry_notification'), true)
            ->whereNotNull($expiry)
            ->whereDate($expiry, '>=', now()->toDateString())
            ->whereDate($expiry, '<=', now()->addDays($horizon)->toDateString())
            ->where(function (Builder $inner) use ($notified): void {
                $inner->whereNull($notified)
                    // Re-notify at most once every 24h.
                    ->orWhere($notified, '<', now()->subDay());
            });
    }
}
