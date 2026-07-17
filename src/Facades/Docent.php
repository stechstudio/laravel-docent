<?php

declare(strict_types=1);

namespace STS\Docent\Facades;

use Illuminate\Support\Facades\Facade;
use STS\Docent\DocentManager;
use STS\Docent\Sites\SiteRegistry;

/**
 * @method static SiteRegistry condition(string $name, \Closure|string $resolver, ?string $label = null, ?string $description = null)
 * @method static SiteRegistry value(string $name, \Closure|string $resolver, ?string $label = null, ?string $description = null)
 * @method static SiteRegistry link(string $name, \Closure|string $resolver, ?string $label = null, ?string $description = null)
 * @method static SiteRegistry component(string $name, \Closure|string|\STS\Docent\Runtime\Contracts\DocumentationComponent $resolver, ?string $label = null, ?string $description = null)
 * @method static SiteRegistry audience(string $name, \Closure|string $resolver, ?string $label = null, ?string $description = null)
 * @method static SiteRegistry suggest(string $pattern, list<string> $slugs)
 * @method static DocentManager site(?string $key = null)
 * @method static \STS\Docent\Page|null page(string $slug)
 * @method static list<\STS\Docent\Navigation\NavigationItem|\STS\Docent\Navigation\NavigationGroup> navigation(\STS\Docent\Runtime\DocumentationContext $context)
 * @method static \STS\Docent\Runtime\DocumentationContext contextFor(?\Illuminate\Http\Request $request)
 * @method static string url(string $slug)
 * @method static string siteName()
 * @method static \STS\Docent\Runtime\IntegrationRegistry registry()
 *
 * @mixin DocentManager
 *
 * @see SiteRegistry
 */
final class Docent extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return SiteRegistry::class;
    }
}
