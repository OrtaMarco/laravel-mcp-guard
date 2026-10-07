<?php

declare(strict_types=1);

namespace OrtaMarco\McpGuard;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Http\Request as HttpRequest;
use Laravel\Mcp\Server\Primitive;
use Laravel\Mcp\Server\Tool;
use Laravel\Sanctum\PersonalAccessToken;
use OrtaMarco\McpGuard\Attributes\RequiresAbility;
use ReflectionClass;

/**
 * Decides who may use which primitive. Stateless apart from a reflection cache.
 */
final class Guard
{
    /** @var array<class-string, list<string>> */
    private static array $attributeAbilities = [];

    public function __construct(private readonly Repository $config) {}

    /**
     * Abilities required by a primitive: the config map wins over the attribute.
     *
     * @return list<string>
     */
    public function abilitiesFor(Primitive $primitive): array
    {
        $configured = $this->config->get('mcp-guard.abilities', [])[$primitive->name()] ?? null;

        if ($configured !== null) {
            return array_values((array) $configured);
        }

        return self::$attributeAbilities[$primitive::class] ??= $this->readAttributes($primitive);
    }

    public function allows(Primitive $primitive, ?Authenticatable $user = null, ?HttpRequest $request = null): bool
    {
        $abilities = $this->abilitiesFor($primitive);

        if ($abilities === []) {
            return true;
        }

        $request ??= request();
        $user ??= $request->user();

        if ($user === null) {
            // stdio (`mcp:start`) has no route and no token: trusted like Artisan itself.
            return $request->route() === null && (bool) $this->config->get('mcp-guard.allow_local', true);
        }

        if (! method_exists($user, 'tokenCan')) {
            return false;
        }

        foreach ($abilities as $ability) {
            if (! $user->tokenCan($ability)) {
                return false;
            }
        }

        return true;
    }

    public function isWrite(Tool $tool): bool
    {
        return ($tool->annotations()['readOnlyHint'] ?? false) !== true;
    }

    /**
     * Who is calling, for audit records and rate-limit buckets.
     *
     * @return array{token_id: int|string|null, client: string, user_id: int|string|null, key: string}
     */
    public function actor(HttpRequest $request): array
    {
        $user = $request->user();
        $token = $user !== null && method_exists($user, 'currentAccessToken') ? $user->currentAccessToken() : null;
        $userId = $user?->getAuthIdentifier();

        if ($token instanceof PersonalAccessToken) {
            return ['token_id' => $token->getKey(), 'client' => (string) $token->name, 'user_id' => $userId, 'key' => 'token:'.$token->getKey()];
        }

        return [
            'token_id' => null,
            'client' => $user !== null ? 'user' : 'anonymous',
            'user_id' => $userId,
            'key' => $userId !== null ? 'user:'.$userId : 'ip:'.$request->ip(),
        ];
    }

    /**
     * @return list<string>
     */
    private function readAttributes(Primitive $primitive): array
    {
        $abilities = [];
        $reflection = new ReflectionClass($primitive);

        do {
            foreach ($reflection->getAttributes(RequiresAbility::class) as $attribute) {
                array_push($abilities, ...$attribute->newInstance()->abilities);
            }
        } while ($reflection = $reflection->getParentClass());

        return array_values(array_unique($abilities));
    }
}
