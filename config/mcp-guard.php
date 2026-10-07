<?php

return [

    /*
    | Abilities per primitive name (Tool/Prompt/Resource `name()`), used instead of
    | the `#[RequiresAbility]` attribute when present. All listed abilities are required.
    |
    |   'abilities' => ['delete-post' => ['posts:write', 'posts:delete']],
    */
    'abilities' => [],

    /*
    | A request with no HTTP route (`php artisan mcp:start`, stdio) has no token.
    | When true, it is trusted like running Artisan itself. Requests that DO go
    | through a route and have no authenticated user are always denied.
    */
    'allow_local' => true,

    'rate_limit' => [
        // tools/call per minute, per token (user or IP as fallback), all tools together. null = off.
        'per_minute' => null,
        // Extra per-tool ceilings, per token: ['send-email' => 5].
        'tools' => [],
    ],

    'audit' => [
        // Write the audit records to a log channel. Listen to McpCallAudited to store them elsewhere.
        'log' => true,
        'channel' => null, // null = default channel
        // Also audit allowed calls of read-only tools (denials are always audited).
        'include_reads' => false,
        // Argument keys (case-insensitive, any depth) replaced by [redacted].
        'redact' => ['password', 'token', 'secret', 'authorization', 'api_key'],
        'max_string_length' => 500,
    ],

];
