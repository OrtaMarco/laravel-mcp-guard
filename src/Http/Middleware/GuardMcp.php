<?php

declare(strict_types=1);

namespace OrtaMarco\McpGuard\Http\Middleware;

use Closure;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Tool;
use OrtaMarco\McpGuard\Events\McpCallAudited;
use OrtaMarco\McpGuard\Guard;
use ReflectionClass;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rate limiting and audit for `tools/call`, placed AFTER the auth middleware:
 *
 *     Mcp::web('/mcp', MyServer::class)
 *         ->middleware(['auth:sanctum', 'mcp-guard:'.MyServer::class]);
 *
 * It does not decide visibility (that is `GuardsAbilities`); it records the calls
 * laravel/mcp answers with "not found" before they reach any tool, which is
 * exactly the attempt worth seeing, and it never blocks one except for rate limits.
 */
final class GuardMcp
{
    /** @var array<class-string, array<string, Tool>> */
    private static array $toolsByServer = [];

    public function __construct(
        private readonly Guard $guard,
        private readonly Repository $config,
        private readonly Dispatcher $events,
    ) {}

    public function handle(Request $request, Closure $next, string $server): Response
    {
        $calls = $this->toolCalls($request, $server);

        if ($calls === []) {
            return $next($request);
        }

        $actor = $this->guard->actor($request);
        $allowed = [];

        foreach ($calls as [$name, $arguments, $tool]) {
            $abilities = $tool !== null ? $this->guard->abilitiesFor($tool) : [];

            if (($retryAfter = $this->hit($actor['key'], $name)) !== null) {
                $this->audit($request, $actor, $name, 'denied', 'rate_limited', $arguments, $abilities);

                return response()->json(
                    ['jsonrpc' => '2.0', 'id' => null, 'error' => ['code' => -32000, 'message' => 'Rate limit exceeded.']],
                    429,
                    ['Retry-After' => $retryAfter],
                );
            }

            if ($tool === null) {
                continue; // unknown tool: laravel/mcp answers "not found".
            }

            if (! $this->guard->allows($tool, $request->user(), $request)) {
                $this->audit($request, $actor, $name, 'denied', 'missing_ability', $arguments, $abilities);

                continue;
            }

            $allowed[] = [$name, $arguments, $tool, $abilities];
        }

        $response = $next($request);

        foreach ($allowed as [$name, $arguments, $tool, $abilities]) {
            if ($this->guard->isWrite($tool) || $this->config->get('mcp-guard.audit.include_reads', false)) {
                $this->audit($request, $actor, $name, 'called', null, $arguments, $abilities);
            }
        }

        return $response;
    }

    /**
     * @return list<array{0: string, 1: array<string, mixed>, 2: Tool|null}>
     */
    private function toolCalls(Request $request, string $server): array
    {
        $payload = $request->json()->all();
        $messages = array_is_list($payload) ? $payload : [$payload];
        $calls = [];

        foreach ($messages as $message) {
            if (! is_array($message) || ($message['method'] ?? null) !== 'tools/call') {
                continue;
            }

            $name = (string) ($message['params']['name'] ?? '');
            $arguments = $message['params']['arguments'] ?? [];

            $calls[] = [$name, is_array($arguments) ? $arguments : [], $this->tools($server)[$name] ?? null];
        }

        return $calls;
    }

    /**
     * The tools the server declares, by name. Read from its default `$tools`
     * (flattening ToolSearch groups) so no server instance is needed.
     *
     * @return array<string, Tool>
     */
    private function tools(string $server): array
    {
        return self::$toolsByServer[$server] ??= (function () use ($server): array {
            $declared = (new ReflectionClass($server))->getDefaultProperties()['tools'] ?? [];
            $tools = [];

            array_walk_recursive($declared, function (mixed $tool) use (&$tools): void {
                $tool = is_string($tool) ? app($tool) : $tool;

                if ($tool instanceof Tool) {
                    $tools[$tool->name()] = $tool;
                }
            });

            return $tools;
        })();
    }

    /**
     * Counts the call and returns the seconds to wait when a limit is exceeded.
     */
    private function hit(string $actorKey, string $tool): ?int
    {
        $buckets = [
            ['mcp-guard:'.$actorKey, $this->config->get('mcp-guard.rate_limit.per_minute')],
            ['mcp-guard:'.$actorKey.':'.$tool, $this->config->get('mcp-guard.rate_limit.tools', [])[$tool] ?? null],
        ];

        foreach ($buckets as [$key, $max]) {
            if ($max === null) {
                continue;
            }

            if (RateLimiter::tooManyAttempts($key, (int) $max)) {
                return RateLimiter::availableIn($key);
            }

            RateLimiter::hit($key, 60);
        }

        return null;
    }

    /**
     * @param  array{token_id: int|string|null, client: string, user_id: int|string|null, key: string}  $actor
     * @param  array<string, mixed>  $arguments
     * @param  list<string>  $abilities
     */
    private function audit(Request $request, array $actor, string $tool, string $outcome, ?string $reason, array $arguments, array $abilities): void
    {
        $this->events->dispatch(new McpCallAudited(
            $tool, $outcome, $reason, $actor['client'], $actor['token_id'], $actor['user_id'],
            $request->ip(), $this->redact($arguments), $abilities,
        ));
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    private function redact(array $data): array
    {
        $hidden = array_map('strtolower', $this->config->get('mcp-guard.audit.redact', []));
        $max = (int) $this->config->get('mcp-guard.audit.max_string_length', 500);

        foreach ($data as $key => $value) {
            if (is_string($key) && in_array(strtolower($key), $hidden, true)) {
                $data[$key] = '[redacted]';
            } elseif (is_array($value)) {
                $data[$key] = $this->redact($value);
            } elseif (is_string($value) && $max > 0 && mb_strlen($value) > $max) {
                $data[$key] = mb_substr($value, 0, $max).'…[truncated]';
            }
        }

        return $data;
    }
}
