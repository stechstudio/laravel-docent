<?php

declare(strict_types=1);

namespace STS\Docent\Facades;

use Illuminate\Support\Facades\Facade;
use STS\Docent\DocentManager;

/**
 * @method static DocentManager condition(string $name, \Closure|string $resolver, ?string $label = null, ?string $description = null)
 * @method static DocentManager value(string $name, \Closure|string $resolver, ?string $label = null, ?string $description = null)
 * @method static DocentManager link(string $name, \Closure|string $resolver, ?string $label = null, ?string $description = null)
 * @method static DocentManager component(string $name, \Closure|string|\STS\Docent\Runtime\Contracts\DocumentationComponent $resolver, ?string $label = null, ?string $description = null)
 * @method static DocentManager audience(string $name, \Closure|string $resolver, ?string $label = null, ?string $description = null)
 * @method static DocentManager suggest(string $pattern, array $slugs)
 * @method static \STS\Docent\Page|null page(string $slug)
 * @method static array navigation(\STS\Docent\Runtime\DocumentationContext $context)
 * @method static \STS\Docent\Runtime\DocumentationContext contextFor(?\Illuminate\Http\Request $request)
 * @method static string url(string $slug)
 * @method static string siteName()
 * @method static \STS\Docent\Runtime\IntegrationRegistry registry()
 *
 * @see DocentManager
 */
final class Docent extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return DocentManager::class;
    }
}
