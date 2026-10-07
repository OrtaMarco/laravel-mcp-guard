<?php

declare(strict_types=1);

namespace OrtaMarco\McpGuard\Concerns;

use OrtaMarco\McpGuard\Guard;

/**
 * Use on a Tool, Prompt or Resource to enforce `#[RequiresAbility]` (or the
 * `mcp-guard.abilities` config map).
 *
 * laravel/mcp calls `shouldRegister()` for every `*\/list` and before every
 * call/get/read, so a primitive the token cannot use is hidden AND not callable.
 * If your class already defines its own `shouldRegister()`, alias this one and
 * combine: `return $this->guardAllows() && $mine;`.
 */
trait GuardsAbilities
{
    public function shouldRegister(): bool
    {
        return $this->guardAllows();
    }

    protected function guardAllows(): bool
    {
        return app(Guard::class)->allows($this);
    }
}
