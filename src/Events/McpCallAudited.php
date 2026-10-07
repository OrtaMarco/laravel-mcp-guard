<?php

declare(strict_types=1);

namespace OrtaMarco\McpGuard\Events;

/**
 * One audited `tools/call`. `outcome` is `called` or `denied`; for denials `reason`
 * is `missing_ability` or `rate_limited`.
 */
final class McpCallAudited
{
    /**
     * @param  array<string, mixed>  $arguments  already redacted
     * @param  list<string>  $abilities  abilities the tool requires
     */
    public function __construct(
        public readonly string $tool,
        public readonly string $outcome,
        public readonly ?string $reason,
        public readonly string $client,
        public readonly int|string|null $tokenId,
        public readonly int|string|null $userId,
        public readonly ?string $ip,
        public readonly array $arguments,
        public readonly array $abilities,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'tool' => $this->tool,
            'outcome' => $this->outcome,
            'reason' => $this->reason,
            'client' => $this->client,
            'token_id' => $this->tokenId,
            'user_id' => $this->userId,
            'ip' => $this->ip,
            'arguments' => $this->arguments,
            'abilities' => $this->abilities,
        ];
    }
}
