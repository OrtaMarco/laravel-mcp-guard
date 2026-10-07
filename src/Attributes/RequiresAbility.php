<?php

declare(strict_types=1);

namespace OrtaMarco\McpGuard\Attributes;

use Attribute;

/**
 * Sanctum abilities a token must carry to see and use a tool, prompt or resource.
 *
 * Every listed ability is required. The attribute is repeatable; all occurrences
 * are combined (and inherited from parent classes).
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final class RequiresAbility
{
    /** @var list<string> */
    public readonly array $abilities;

    public function __construct(string ...$abilities)
    {
        $this->abilities = array_values($abilities);
    }
}
