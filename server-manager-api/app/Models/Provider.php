<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ProviderStatus;
use App\Enums\ProviderType;
use App\Models\Concerns\LogsActivity;
use App\Models\Concerns\Searchable;
use App\Models\Concerns\TracksBlame;
use Database\Factories\ProviderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Provider extends Model
{
    /** @use HasFactory<ProviderFactory> */
    use HasFactory;

    use LogsActivity;
    use Searchable;
    use SoftDeletes;
    use TracksBlame;

    public const SEARCHABLE = ['name', 'url', 'support_email'];

    public const SORTABLE = ['name', 'type', 'status', 'created_at', 'updated_at', 'id'];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'url',
        'type',
        'status',
        'support_email',
        'support_phone',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ProviderType::class,
            'status' => ProviderStatus::class,
        ];
    }

    /**
     * @return HasMany<Hosting, $this>
     */
    public function hostings(): HasMany
    {
        return $this->hasMany(Hosting::class);
    }

    /**
     * @return HasMany<Domain, $this>
     */
    public function domains(): HasMany
    {
        return $this->hasMany(Domain::class);
    }
}
