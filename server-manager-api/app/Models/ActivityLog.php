<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ActivityAction;
use App\Models\Concerns\Searchable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Append-only audit record. Deliberately has no SoftDeletes and no
 * LogsActivity: an audit trail must not be editable, and logging the log would
 * recurse.
 */
class ActivityLog extends Model
{
    use Searchable;

    public const SEARCHABLE = ['user_name', 'action', 'subject_label', 'description'];

    public const SORTABLE = ['created_at', 'action', 'user_name', 'id'];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'user_name',
        'action',
        'subject_type',
        'subject_id',
        'subject_label',
        'description',
        'properties',
        'ip_address',
        'user_agent',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'action' => ActivityAction::class,
            'properties' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    /**
     * The affected record, when still present.
     *
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Short, human-readable sentence for the dashboard timeline, e.g.
     * `Hosting "Production Server" was created by Admin`.
     */
    public function summary(): string
    {
        if ($this->description !== null && $this->description !== '') {
            return $this->description;
        }

        $action = $this->action instanceof ActivityAction
            ? $this->action->label()
            : (string) $this->action;

        $actor = $this->user_name ?? 'System';

        if ($this->subject_type === null) {
            return ucfirst($actor).' '.$action.'.';
        }

        $type = class_basename($this->subject_type);
        $label = $this->subject_label;

        return $label === null
            ? sprintf('%s was %s by %s', $type, $action, $actor)
            : sprintf('%s "%s" was %s by %s', $type, $label, $action, $actor);
    }
}
