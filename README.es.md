# laravel-mcp-guard

Permisos declarativos por token para servidores [`laravel/mcp`](https://github.com/laravel/mcp).

`laravel/mcp` permite ocultar una tool con `shouldRegister()`, pero la comprobación se escribe a mano en cada
clase, una llamada denegada no deja rastro y no hay rate limit por token. Este paquete añade:

- **Abilities de Sanctum por tool, prompt o resource**, declaradas con un atributo PHP (o un mapa en config).
- **Ocultar, no solo rechazar**: un token sin la ability no ve la pieza en `tools/list` (ni `prompts/list`,
  `resources/list`) y no puede llamarla; el servidor responde «not found».
- **Rate limit por token** en `tools/call`, global y por tool.
- **Bitácora** de escrituras **y de intentos denegados**, mediante un evento y un canal de log.

Requiere PHP ≥ 8.3, Laravel 12 o 13 y `laravel/mcp` ^1.0.

## Uso

```bash
composer require ortamarco/laravel-mcp-guard
```

```php
#[RequiresAbility('posts:write', 'posts:publish')]   // hacen falta todas
class PublishPost extends Tool
{
    use GuardsAbilities;   // implementa shouldRegister()
    // ...
}

Mcp::web('/mcp', MyServer::class)
    ->middleware(['auth:sanctum', 'mcp-guard:'.MyServer::class]);
```

El mapa `mcp-guard.abilities` en config (por `name()` de la pieza) tiene prioridad sobre el atributo.
`rate_limit.per_minute` limita por token; `rate_limit.tools` fija un tope extra por tool. Al excederlo responde
HTTP 429 con `Retry-After`; los intentos denegados también cuentan.

Cada `tools/call` denegado y cada llamada permitida a una tool sin `#[IsReadOnly]` lanza el evento
`McpCallAudited` y se escribe en el canal de log (los argumentos sensibles salen como `[redacted]`).

Por stdio (`php artisan mcp:start`) no hay ruta ni token: con `allow_local` (por defecto `true`) se confía como en
Artisan. Una petición enrutada sin usuario autenticado siempre se niega.

Limitaciones: ver la sección *Limits* del [README](README.md). Licencia MIT © Marco Orta.
