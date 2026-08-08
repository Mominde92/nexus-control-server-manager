<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use App\Models\Concerns\Searchable;
use App\Models\Concerns\TracksBlame;
use Database\Factories\ClientFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends Model
{
    /** @use HasFactory<ClientFactory> */
    use HasFactory;

    use LogsActivity;
    use Searchable;
    use SoftDeletes;
    use TracksBlame;

    public const SEARCHABLE = ['name', 'email', 'phone', 'company'];

    public const SORTABLE = ['name', 'email', 'company', 'created_at', 'updated_at', 'id'];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'company',
        'website',
        'address',
        'notes',
    ];

    /**
     * @return HasMany<Project, $this>
     */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
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
