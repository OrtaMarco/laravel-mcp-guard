# DESIGN — laravel-mcp-guard

## Problem

`laravel/mcp` lets any authenticated user see and call every tool of a server. The only
built-in hook is imperative: each tool/prompt/resource may implement
`shouldRegister(Request $request): bool` (hides it from the list and blocks calling it), and
`handle()` may call `$request->user()->can(...)` by hand. There is no declarative way to say
"this tool needs this Sanctum ability", no per-token rate limit, and denied calls leave no trace
(a hidden tool answers "not found" before reaching `handle()`).

## What `laravel/mcp` already covers (verified in laravel.com/docs/12.x/mcp)

- `shouldRegister(Request)` on Tool, Prompt and Resource: hides from `*/list` and blocks invocation.
- `Mcp::web(...)->middleware(...)`: Sanctum (`auth:sanctum`) or Passport (`auth:api`), `throttle:*`.
- `$request->user()` inside `handle()`.

Not covered: declarative per-capability abilities, per-token rate limiting keyed on the token,
an audit trail of writes and of denied attempts.

## Competitors (Packagist, checked 2026-10-06)

- `stvnrlnd/laravel-mcp-guard` (v0.1.0, 2026-07): same name in another vendor. Per-agent
  allowlists in config, per-caller/per-tool rate limit, audit log, human approval. Own
  token→permissions mapping; no Sanctum abilities; unclear whether `tools/list` hides tools.
- `heihallo/mcp-kit`, `cleaniquecoders/laravel-mcp-kit`: full toolkits (catalogues, Gate per
  tool, Sanctum/Passport). Broader, not a thin guard layer.

**Differential:** native Sanctum abilities, declared next to the tool via PHP attribute (or
config), enforced in the listing AND at call time, plus denied-call audit. Small and composable.

## Scope v0.1 (proposal)

- `#[RequiresAbility('x', 'y')]` attribute (all required) on Tool/Prompt/Resource classes; config
  map `mcp-guard.abilities` as alternative/override.
- Trait `GuardsAbilities` providing `shouldRegister()` for the three primitives.
- Middleware `mcp-guard.audit`: logs writes and denied `tools/call` (token id, client, tool, args).
- Middleware `mcp-guard.throttle`: rate limit per token (fallback: user, IP).
- stdio (`Mcp::local`): no route/user → allowed (same convention as the seed code).
- Laravel 12/13, PHP ≥ 8.3, Orchestra Testbench + Pest.

## Non-goals

OAuth/Passport scopes, human approval workflows, UI, a tool catalogue.

## Open points (to resolve against the `laravel/mcp` source)

- Exact `shouldRegister` signature/version in the supported `laravel/mcp` range.
- Whether the attribute can be read without instantiating tools; whether a hook exists to
  register the audit for hidden-tool calls (hence the middleware).
