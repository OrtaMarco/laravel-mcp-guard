# laravel-mcp-guard

Declarative, per-token permissions for [`laravel/mcp`](https://github.com/laravel/mcp) servers.

> Español: [README.es.md](README.es.md)

`laravel/mcp` lets you hide a tool with `shouldRegister()`, but you write that check by hand in every class,
a denied call leaves no trace, and there is no per-token rate limit. This package adds:

- **Sanctum abilities per tool, prompt or resource**, declared with a PHP attribute (or a config map).
- **Hidden, not just rejected**: a token that lacks the ability does not see the primitive in `tools/list`
  (`prompts/list`, `resources/list`) and cannot call it; the server answers "not found".
- **Rate limit per token** for `tools/call`, global and per tool.
- **Audit** of write calls **and of denied attempts** (the ones laravel/mcp answers with "not found" before any
  of your code runs), through an event and a log channel.

Requires PHP ≥ 8.3, Laravel 12 or 13, `laravel/mcp` ^1.0. Tested on Laravel 12.69 and 13.35 (see the CI matrix).

## What laravel/mcp already gives you (and what this adds)

| | laravel/mcp 1.0 | + this package |
|---|---|---|
| Hide a primitive per request | `shouldRegister()` in each class | `#[RequiresAbility('posts:write')]` |
| Auth | `auth:sanctum` / Passport middleware | same; abilities read from the token |
| Rate limit | `throttle:*` per route (the whole endpoint) | per token, per `tools/call`, per tool |
| Audit | none | writes and denials, with redacted arguments |

## Install

```bash
composer require ortamarco/laravel-mcp-guard
php artisan vendor:publish --tag=mcp-guard-config   # optional
```

## Usage

```php
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use OrtaMarco\McpGuard\Attributes\RequiresAbility;
use OrtaMarco\McpGuard\Concerns\GuardsAbilities;

#[RequiresAbility('posts:write', 'posts:publish')]   // every listed ability is required
class PublishPost extends Tool
{
    use GuardsAbilities;                              // implements shouldRegister()

    public function handle(Request $request): Response { /* ... */ }
}
```

The trait works the same on `Prompt` and `Resource`. If your class has its own `shouldRegister()`, call
`$this->guardAllows()` from it.

Abilities can also live in config, keyed by the primitive's `name()`; config wins over the attribute:

```php
// config/mcp-guard.php
'abilities' => ['publish-post' => ['posts:write', 'posts:publish']],
```

Route middleware (after the auth middleware) for rate limiting and audit; pass the server class:

```php
Mcp::web('/mcp', MyServer::class)
    ->middleware(['auth:sanctum', 'mcp-guard:'.MyServer::class]);
```

```php
$user->createToken('reporting-agent', ['posts:read'])->plainTextToken;   // sees and calls read tools only
```

### Rate limit

```php
'rate_limit' => [
    'per_minute' => 60,                        // per token, all tools together
    'tools' => ['publish-post' => 5],          // extra ceiling per tool, per token
],
```

Exceeding it returns HTTP 429 with `Retry-After`. Only `tools/call` is counted. Denied attempts count too, so
probing for hidden tools is throttled. Bucket key: token id, falling back to user id, then IP.

### Audit

Every denied `tools/call` (`missing_ability`, `rate_limited`) and every allowed call to a tool that is not
`#[IsReadOnly]` dispatches `OrtaMarco\McpGuard\Events\McpCallAudited` (tool, outcome, reason, client = token name,
token id, user id, IP, redacted arguments, required abilities) and is written to the configured log channel.
Set `audit.include_reads` to also record allowed reads, `audit.log` to `false` to handle storage yourself
(listen to the event and write it to a table).

### stdio

`php artisan mcp:start` has no route and no token. With `allow_local` (default `true`) it is trusted like running
Artisan itself. A routed request with no authenticated user is always denied.

## Limits (be aware)

- The guard enforces what the token's abilities say; it does not issue tokens or define your ability names.
- Users without Sanctum's `HasApiTokens` (`tokenCan`) are denied for any gated primitive (fail closed).
- The middleware reads tools from the server's declared `$tools` property; tools added at runtime are not audited.
- A write is "any tool not annotated `#[IsReadOnly]`". Annotate your read tools.
- Audit outcome `called` means the call was authorized and dispatched, not that the tool succeeded.
- Only `tools/call` is rate limited and audited; hidden prompts and resources are still hidden and uncallable.
- OAuth/Passport scopes are not interpreted (laravel/mcp does not support custom Passport scopes either).

## Alternatives

- [`stvnrlnd/laravel-mcp-guard`](https://packagist.org/packages/stvnrlnd/laravel-mcp-guard): config-based per-agent
  allowlists, audit and approval workflow; different vendor, same idea. Choose it if you want approvals.
- [`cleaniquecoders/laravel-mcp-kit`](https://packagist.org/packages/cleaniquecoders/laravel-mcp-kit),
  [`heihallo/mcp-kit`](https://packagist.org/packages/heihallo/mcp-kit): broader toolkits.

## Development

```bash
composer install
vendor/bin/pest
```

## License

MIT © Marco Orta
