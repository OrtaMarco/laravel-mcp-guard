<?php

use Illuminate\Support\Facades\Log;

it('audits a successful write with the token name and redacted arguments', function (): void {
    $headers = $this->bearer(['posts:write', 'posts:publish'], 'claude-wsl');

    $this->rpc('tools/call', ['name' => 'publish-post', 'arguments' => ['id' => 7, 'api_key' => 'sk-123', 'nested' => ['Password' => 'x']]], $headers)
        ->assertJsonPath('result.content.0.text', 'published');

    expect($this->audited)->toHaveCount(1);
    $event = $this->audited[0];

    expect($event->tool)->toBe('publish-post')
        ->and($event->outcome)->toBe('called')
        ->and($event->client)->toBe('claude-wsl')
        ->and($event->tokenId)->not->toBeNull()
        ->and($event->arguments)->toBe(['id' => 7, 'api_key' => '[redacted]', 'nested' => ['Password' => '[redacted]']]);
});

it('does not audit allowed reads unless asked to', function (): void {
    $headers = $this->bearer(['posts:read']);

    $this->rpc('tools/call', ['name' => 'list-posts'], $headers)->assertOk();
    expect($this->audited)->toBe([]);

    config(['mcp-guard.audit.include_reads' => true]);
    $this->rpc('tools/call', ['name' => 'list-posts'], $headers)->assertOk();
    expect($this->audited)->toHaveCount(1);
});

it('audits a denied attempt even though laravel/mcp answers "not found"', function (): void {
    $this->rpc('tools/call', ['name' => 'publish-post', 'arguments' => ['id' => 1]], $this->bearer(['posts:read'], 'suspicious'))
        ->assertJsonPath('error.message', 'Tool [publish-post] not found.');

    expect($this->audited)->toHaveCount(1)
        ->and($this->audited[0]->outcome)->toBe('denied')
        ->and($this->audited[0]->reason)->toBe('missing_ability')
        ->and($this->audited[0]->client)->toBe('suspicious')
        ->and($this->audited[0]->abilities)->toBe(['posts:write', 'posts:publish']);
});

it('ignores unknown tools and non tools/call methods', function (): void {
    $headers = $this->bearer([]);

    $this->rpc('tools/call', ['name' => 'nope'], $headers);
    $this->rpc('tools/list', headers: $headers);

    expect($this->audited)->toBe([]);
});

it('writes audit records to the configured log channel', function (): void {
    config(['mcp-guard.audit.log' => true, 'mcp-guard.audit.channel' => 'stack']);
    Log::shouldReceive('channel')->with('stack')->once()->andReturnSelf();
    Log::shouldReceive('info')->once()->withArgs(fn (string $message, array $context): bool => $message === 'mcp-guard' && $context['tool'] === 'publish-post' && $context['outcome'] === 'denied');

    $this->rpc('tools/call', ['name' => 'publish-post'], $this->bearer([]));
});

it('rate limits per token across tools and answers 429', function (): void {
    config(['mcp-guard.rate_limit.per_minute' => 2]);
    $a = $this->bearer(['posts:read'], 'a');
    $b = $this->bearer(['posts:read'], 'b');

    $this->rpc('tools/call', ['name' => 'ping'], $a)->assertOk();
    $this->rpc('tools/call', ['name' => 'list-posts'], $a)->assertOk();
    $this->rpc('tools/call', ['name' => 'ping'], $a)->assertStatus(429)->assertHeader('Retry-After');
    $this->rpc('tools/call', ['name' => 'ping'], $b)->assertOk();

    expect(collect($this->audited)->pluck('reason')->all())->toBe(['rate_limited']);
});

it('applies a per-tool ceiling on top of the global one', function (): void {
    config(['mcp-guard.rate_limit.tools' => ['publish-post' => 1]]);
    $headers = $this->bearer(['posts:write', 'posts:publish']);

    $this->rpc('tools/call', ['name' => 'publish-post'], $headers)->assertOk();
    $this->rpc('tools/call', ['name' => 'publish-post'], $headers)->assertStatus(429);
    $this->rpc('tools/call', ['name' => 'ping'], $headers)->assertOk();
});

it('does not rate limit tools/list', function (): void {
    config(['mcp-guard.rate_limit.per_minute' => 1]);
    $headers = $this->bearer([]);

    foreach (range(1, 3) as $_) {
        $this->rpc('tools/list', headers: $headers)->assertOk();
    }
});
