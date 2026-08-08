<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

final class RecordAccess extends Model
{
    protected $table = 'record_access';
    protected $fillable = ['user_id', 'can_view', 'can_update', 'can_delete', 'can_view_credentials'];
    protected function casts(): array { return ['can_view'=>'boolean','can_update'=>'boolean','can_delete'=>'boolean','can_view_credentials'=>'boolean']; }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function accessible(): MorphTo { return $this->morphTo(); }
}
