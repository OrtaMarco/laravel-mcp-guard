<?php

declare(strict_types=1);

namespace OrtaMarco\McpGuard\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Facades\Mcp;
use Laravel\Mcp\Server\McpServiceProvider;
use Laravel\Sanctum\SanctumServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use OrtaMarco\McpGuard\Events\McpCallAudited;
use OrtaMarco\McpGuard\McpGuardServiceProvider;
use OrtaMarco\McpGuard\Tests\Fixtures\DemoServer;
use OrtaMarco\McpGuard\Tests\Fixtures\TestUser;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    /** @var list<McpCallAudited> */
    protected array $audited = [];

    protected function getPackageProviders($app): array
    {
        return [SanctumServiceProvider::class, McpServiceProvider::class, McpGuardServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('k', 32)));
        $app['config']->set('mcp-guard.audit.log', false);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../vendor/laravel/sanctum/database/migrations');
        $this->loadMigrationsFrom(__DIR__.'/migrations');
    }

    protected function defineRoutes($router): void
    {
        Route::group([], function (): void {
            Mcp::web('/mcp', DemoServer::class)
                ->middleware(['auth:sanctum', 'mcp-guard:'.DemoServer::class]);
        });
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->audited = [];
        Event::listen(McpCallAudited::class, function (McpCallAudited $event): void {
            $this->audited[] = $event;
        });
    }

    /**
     * @param  list<string>  $abilities
     * @return array<string, string>
     */
    protected function bearer(array $abilities, string $name = 'agent'): array
    {
        $user = TestUser::query()->firstOrCreate(['email' => 'a@example.test'], ['name' => 'A', 'password' => 'x']);

        return ['Authorization' => 'Bearer '.$user->createToken($name, $abilities)->plainTextToken];
    }

    /**
     * @param  array<string, mixed>  $params
     * @param  array<string, string>  $headers
     */
    protected function rpc(string $method, array $params = [], array $headers = []): \Illuminate\Testing\TestResponse
    {
        // The request guard caches the resolved user; a new token must be resolved afresh.
        $this->app['auth']->forgetGuards();

        return $this->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => $params], $headers + ['Accept' => 'application/json']);
    }
}
