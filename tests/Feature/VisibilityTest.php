<?php

use OrtaMarco\McpGuard\Guard;
use OrtaMarco\McpGuard\Tests\Fixtures\ConfigGated;
use OrtaMarco\McpGuard\Tests\Fixtures\ListPosts;
use OrtaMarco\McpGuard\Tests\Fixtures\PublishPost;

function names(array $response, string $key): array
{
    return collect($response['result'][$key])->pluck('name')->sort()->values()->all();
}

it('lists only the tools the token can use', function (): void {
    $read = $this->rpc('tools/list', headers: $this->bearer(['posts:read']))->assertOk()->json();
    $none = $this->rpc('tools/list', headers: $this->bearer([]))->assertOk()->json();
    $all = $this->rpc('tools/list', headers: $this->bearer(['posts:read', 'posts:write', 'posts:publish']))->assertOk()->json();

    expect(names($read, 'tools'))->toBe(['config-gated', 'list-posts', 'ping'])
        ->and(names($none, 'tools'))->toBe(['config-gated', 'ping'])
        ->and(names($all, 'tools'))->toBe(['config-gated', 'list-posts', 'ping', 'publish-post']);
});

it('requires every ability of the attribute, not just one', function (): void {
    $partial = $this->rpc('tools/list', headers: $this->bearer(['posts:write']))->json();

    expect(names($partial, 'tools'))->not->toContain('publish-post');
});

it('hides prompts and resources too', function (): void {
    $without = $this->bearer([]);
    $with = $this->bearer(['posts:read']);

    expect(names($this->rpc('prompts/list', headers: $without)->json(), 'prompts'))->toBe([])
        ->and(names($this->rpc('prompts/list', headers: $with)->json(), 'prompts'))->toBe(['summarize-prompt'])
        ->and(names($this->rpc('resources/list', headers: $without)->json(), 'resources'))->toBe([])
        ->and(names($this->rpc('resources/list', headers: $with)->json(), 'resources'))->toBe(['guidelines-resource']);
});

it('refuses to call a hidden tool as if it did not exist', function (): void {
    $this->rpc('tools/call', ['name' => 'publish-post'], $this->bearer(['posts:read']))
        ->assertJsonPath('error.code', -32602)
        ->assertJsonPath('error.message', 'Tool [publish-post] not found.');
});

it('refuses hidden prompts and resources at call time', function (): void {
    $this->rpc('prompts/get', ['name' => 'summarize-prompt'], $this->bearer([]))->assertJsonPath('error.code', -32602);
    $this->rpc('resources/read', ['uri' => 'file://guidelines'], $this->bearer([]))->assertJsonPath('error.code', -32602);
    $this->rpc('resources/read', ['uri' => 'file://guidelines'], $this->bearer(['posts:read']))->assertJsonPath('result.contents.0.text', 'guidelines');
});

it('lets a token with the wildcard ability through (Sanctum semantics)', function (): void {
    expect(names($this->rpc('tools/list', headers: $this->bearer(['*']))->json(), 'tools'))->toContain('publish-post');
});

it('requires a token at the HTTP layer', function (): void {
    $this->rpc('tools/list')->assertUnauthorized();
});

it('takes abilities from the config map instead of the attribute', function (): void {
    config(['mcp-guard.abilities' => ['config-gated' => ['ops:run'], 'list-posts' => ['other']]]);

    $tools = names($this->rpc('tools/list', headers: $this->bearer(['posts:read', 'ops:run']))->json(), 'tools');

    expect($tools)->toContain('config-gated')->not->toContain('list-posts');
    expect(names($this->rpc('tools/list', headers: $this->bearer(['posts:read']))->json(), 'tools'))->not->toContain('config-gated');
});

it('trusts a stdio request (no route, no user) and denies a routed one without a user', function (): void {
    $guard = app(Guard::class);

    expect($guard->allows(new PublishPost))->toBeTrue();

    config(['mcp-guard.allow_local' => false]);

    expect($guard->allows(new PublishPost))->toBeFalse()
        ->and($guard->allows(new ConfigGated))->toBeTrue()
        ->and($guard->abilitiesFor(new ListPosts))->toBe(['posts:read']);
});
