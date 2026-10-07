<?php

declare(strict_types=1);

namespace OrtaMarco\McpGuard\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Laravel\Sanctum\HasApiTokens;
use OrtaMarco\McpGuard\Attributes\RequiresAbility;
use OrtaMarco\McpGuard\Concerns\GuardsAbilities;

class TestUser extends Authenticatable
{
    use HasApiTokens;

    protected $table = 'users';

    protected $guarded = [];
}

#[RequiresAbility('posts:read')]
#[IsReadOnly]
class ListPosts extends Tool
{
    use GuardsAbilities;

    public function handle(Request $request): Response
    {
        return Response::text('posts');
    }
}

#[RequiresAbility('posts:write', 'posts:publish')]
class PublishPost extends Tool
{
    use GuardsAbilities;

    public function handle(Request $request): Response
    {
        return Response::text('published');
    }
}

#[IsReadOnly]
class Ping extends Tool
{
    use GuardsAbilities;

    public function handle(Request $request): Response
    {
        return Response::text('pong');
    }
}

/** Gated only through the `mcp-guard.abilities` config map. */
class ConfigGated extends Tool
{
    use GuardsAbilities;

    public function handle(Request $request): Response
    {
        return Response::text('config');
    }
}

#[RequiresAbility('posts:read')]
class SummarizePrompt extends Prompt
{
    use GuardsAbilities;

    public function handle(Request $request): Response
    {
        return Response::text('summary');
    }
}

#[RequiresAbility('posts:read')]
class GuidelinesResource extends Resource
{
    use GuardsAbilities;

    protected string $uri = 'file://guidelines';

    protected string $mimeType = 'text/plain';

    public function handle(Request $request): Response
    {
        return Response::text('guidelines');
    }
}

class DemoServer extends Server
{
    protected array $tools = [ListPosts::class, PublishPost::class, Ping::class, ConfigGated::class];

    protected array $prompts = [SummarizePrompt::class];

    protected array $resources = [GuidelinesResource::class];
}
