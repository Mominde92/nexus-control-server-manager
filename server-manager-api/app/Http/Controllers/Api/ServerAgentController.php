<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Contracts\Services\ServerServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Server\IssueAgentCredentialRequest;
use App\Http\Resources\ServerAgentCredentialResource;
use App\Models\Server;
use App\Models\ServerAgentCredential;
use App\Support\ApiResponse;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;

/**
 * Agent credential lifecycle: issue, rotate, revoke, and install instructions.
 *
 * Every action here is gated by the `manageAgent` ability rather than by
 * `update`, because minting a credential produces a bearer secret that grants
 * ingest access to a host — a security operation, not an edit.
 *
 * The plaintext token appears in exactly two responses (store and rotate) and
 * nowhere else, ever. It is not recoverable afterwards: only a SHA-256 hash of
 * the secret half is stored.
 */
class ServerAgentController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly ServerServiceInterface $servers,
    ) {}

    /**
     * Credential history for one server, newest first.
     *
     * Revoked and expired rows are included deliberately: the history of what was
     * issued, by whom and when it was last used is the audit trail.
     */
    public function index(Server $server): JsonResponse
    {
        $this->authorize('manageAgent', $server);

        $credentials = $server->credentials()
            ->orderByDesc('id')
            ->paginate((int) config('monitoring.limits.default_page_size', 15));

        return ApiResponse::paginated(
            ServerAgentCredentialResource::collection($credentials),
        );
    }

    /**
     * Mint a credential. The token is shown ONCE.
     */
    public function store(IssueAgentCredentialRequest $request, Server $server): JsonResponse
    {
        $this->authorize('manageAgent', $server);

        $issued = $this->servers->issueCredential($server, $request->validated()['name'] ?? null);

        return ApiResponse::created([
            'credential' => new ServerAgentCredentialResource($issued['credential']),
            // The only time this value is ever returned by the API.
            'token' => $issued['token'],
            'warning' => 'Copy this token now — it cannot be retrieved again.',
        ], 'Agent credential issued.');
    }

    /**
     * Issue a successor and revoke the predecessor atomically.
     */
    public function rotate(IssueAgentCredentialRequest $request, Server $server): JsonResponse
    {
        $this->authorize('manageAgent', $server);

        $issued = $this->servers->rotateCredential($server, $request->validated()['name'] ?? null);

        return ApiResponse::created([
            'credential' => new ServerAgentCredentialResource($issued['credential']),
            'token' => $issued['token'],
            'warning' => 'Copy this token now — it cannot be retrieved again. The previous token has been revoked.',
        ], 'Agent credential rotated.');
    }

    /**
     * Revoke a credential.
     *
     * Revoked rather than deleted: the row is the record of what had access, and
     * the agent middleware rejects it from the next request onwards.
     */
    public function destroy(Server $server, ServerAgentCredential $credential): JsonResponse
    {
        $this->authorize('manageAgent', $server);

        // Guard against a credential id from another server being passed in.
        if ((int) $credential->server_id !== (int) $server->getKey()) {
            return ApiResponse::notFound('Agent credential not found.');
        }

        $this->servers->revokeCredential($credential, 'Revoked via API.');

        return ApiResponse::deleted('Agent credential revoked.');
    }

    /**
     * OS-appropriate install steps, derived from the agent flavour.
     */
    public function instructions(Server $server): JsonResponse
    {
        $this->authorize('manageAgent', $server);

        return ApiResponse::success(
            $this->servers->installInstructions($server),
            'Agent install instructions.',
        );
    }
}
