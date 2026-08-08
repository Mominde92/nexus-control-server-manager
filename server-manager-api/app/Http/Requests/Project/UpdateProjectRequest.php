<?php

declare(strict_types=1);

namespace App\Http\Requests\Project;

/**
 * Partial update: only the submitted keys are validated and persisted.
 */
final class UpdateProjectRequest extends ProjectRequest
{
    protected bool $partial = true;
}
