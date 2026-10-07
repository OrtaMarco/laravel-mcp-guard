# Changelog

## [Unreleased]

## 0.1.0

- `#[RequiresAbility]` attribute and `mcp-guard.abilities` config map: Sanctum abilities per tool, prompt and resource.
- `GuardsAbilities` trait: hides what the token cannot use from `*/list` and makes it uncallable.
- `mcp-guard` route middleware: per-token rate limit (global and per tool) for `tools/call`, audit of writes and of denied attempts via the `McpCallAudited` event and a log channel.
- Tested on Laravel 12 and 13 with `laravel/mcp` 1.0.
