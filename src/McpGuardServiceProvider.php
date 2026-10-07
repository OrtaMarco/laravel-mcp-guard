<?php

declare(strict_types=1);

namespace OrtaMarco\McpGuard;

use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use OrtaMarco\McpGuard\Events\McpCallAudited;
use OrtaMarco\McpGuard\Http\Middleware\GuardMcp;

final class McpGuardServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/mcp-guard.php', 'mcp-guard');
        $this->app->singleton(Guard::class);
    }

    public function boot(Router $router): void
    {
        $this->publishes([__DIR__.'/../config/mcp-guard.php' => config_path('mcp-guard.php')], 'mcp-guard-config');

        $router->aliasMiddleware('mcp-guard', GuardMcp::class);

        Event::listen(McpCallAudited::class, function (McpCallAudited $event): void {
            if (config('mcp-guard.audit.log', true)) {
                Log::channel(config('mcp-guard.audit.channel'))->info('mcp-guard', $event->toArray());
            }
        });
    }
}
