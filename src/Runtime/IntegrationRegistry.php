<?php

declare(strict_types=1);

namespace STS\Docent\Runtime;

use Closure;
use Illuminate\Support\Str;
use InvalidArgumentException;
use STS\Docent\Runtime\Contracts\DocumentationComponent;
use STS\Docent\Runtime\Registered\RegisteredAudience;
use STS\Docent\Runtime\Registered\RegisteredComponent;
use STS\Docent\Runtime\Registered\RegisteredCondition;
use STS\Docent\Runtime\Registered\RegisteredLink;
use STS\Docent\Runtime\Registered\RegisteredValue;

/**
 * Central registry of everything an application teaches Docent about itself:
 * conditions, dynamic values, links, components, and audiences.
 *
 * Resolvers may be closures or class-strings; class-strings are instantiated
 * through an injectable resolver (default `new $class`) so the registry can be
 * exercised without a Laravel container.
 */
final class IntegrationRegistry
{
    /** @var array<string, RegisteredCondition> */
    private array $conditions = [];

    /** @var array<string, RegisteredValue> */
    private array $values = [];

    /** @var array<string, RegisteredLink> */
    private array $links = [];

    /** @var array<string, RegisteredComponent> */
    private array $components = [];

    /** @var array<string, RegisteredAudience> */
    private array $audiences = [];

    /** @var array<string, list<string>> */
    private array $suggestions = [];

    /** @var Closure(class-string): object */
    private Closure $classResolver;

    /**
     * @param  ?Closure(class-string): object  $classResolver
     */
    public function __construct(?Closure $classResolver = null)
    {
        $this->classResolver = $classResolver ?? static fn (string $class): object => new $class;
    }

    /**
     * @param  Closure|class-string  $resolver
     */
    public function condition(string $name, Closure|string $resolver, ?string $label = null, ?string $description = null): self
    {
        $this->conditions[$name] = new RegisteredCondition($name, $resolver, $label, $description);

        return $this;
    }

    /**
     * @param  Closure|class-string  $resolver
     */
    public function value(string $name, Closure|string $resolver, ?string $label = null, ?string $description = null): self
    {
        $this->values[$name] = new RegisteredValue($name, $resolver, $label, $description);

        return $this;
    }

    /**
     * @param  Closure|class-string  $resolver
     */
    public function link(string $name, Closure|string $resolver, ?string $label = null, ?string $description = null): self
    {
        $this->links[$name] = new RegisteredLink($name, $resolver, $label, $description);

        return $this;
    }

    /**
     * @param  Closure|class-string|DocumentationComponent  $resolver
     */
    public function component(string $name, Closure|string|DocumentationComponent $resolver, ?string $label = null, ?string $description = null): self
    {
        $this->components[$name] = new RegisteredComponent($name, $resolver, $label, $description);

        return $this;
    }

    /**
     * @param  Closure|class-string  $resolver
     */
    public function audience(string $name, Closure|string $resolver, ?string $label = null, ?string $description = null): self
    {
        $this->audiences[$name] = new RegisteredAudience($name, $resolver, $label, $description);

        return $this;
    }

    /**
     * Register documentation pages to suggest for a host application's page
     * identifier. Patterns use Laravel's simple wildcard matching. The slug
     * list is host-app input, so it is validated at runtime rather than
     * trusted to match the advertised list<string> shape.
     *
     * @param  array<array-key, mixed>  $slugs
     */
    public function suggest(string $pattern, array $slugs): self
    {
        $pattern = trim($pattern);

        if ($pattern === '') {
            throw new InvalidArgumentException('A Docent suggestion pattern cannot be empty.');
        }

        $normalized = [];

        foreach ($slugs as $slug) {
            if (! is_string($slug) || trim($slug, " \t\n\r\0\x0B/") === '') {
                throw new InvalidArgumentException('Docent suggestion slugs must be non-empty strings.');
            }

            $normalized[] = trim($slug, '/');
        }

        $this->suggestions[$pattern] = array_values(array_unique($normalized));

        return $this;
    }

    /**
     * Merged suggestions for a host page identifier, in registration order,
     * deduplicated, and capped so the widget section stays scannable.
     *
     * @return list<string>
     */
    public function suggestionsFor(string $page): array
    {
        $slugs = [];

        foreach ($this->suggestions as $pattern => $suggestions) {
            if (Str::is($pattern, $page)) {
                array_push($slugs, ...$suggestions);
            }
        }

        return array_slice(array_values(array_unique($slugs)), 0, 5);
    }

    /** @return array<string, list<string>> */
    public function suggestions(): array
    {
        return $this->suggestions;
    }

    public function hasCondition(string $name): bool
    {
        return isset($this->conditions[$name]);
    }

    public function hasValue(string $name): bool
    {
        return isset($this->values[$name]);
    }

    public function hasLink(string $name): bool
    {
        return isset($this->links[$name]);
    }

    public function hasComponent(string $name): bool
    {
        return isset($this->components[$name]);
    }

    public function hasAudience(string $name): bool
    {
        return isset($this->audiences[$name]);
    }

    /**
     * Resolve a condition. Returns null when the condition is not registered.
     */
    public function resolveCondition(string $name, DocumentationContext $context): ?bool
    {
        $registered = $this->conditions[$name] ?? null;

        if ($registered === null) {
            return null;
        }

        return (bool) $this->invoke($registered->resolver, [$context]);
    }

    /**
     * Resolve an audience. Returns null when the audience is not registered.
     */
    public function resolveAudience(string $name, DocumentationContext $context): ?bool
    {
        $registered = $this->audiences[$name] ?? null;

        if ($registered === null) {
            return null;
        }

        return (bool) $this->invoke($registered->resolver, [$context]);
    }

    /**
     * Resolve a dynamic value to a string. Returns null when not registered.
     *
     * @param  list<string>  $arguments
     */
    public function resolveValue(string $name, DocumentationContext $context, array $arguments = []): ?string
    {
        $registered = $this->values[$name] ?? null;

        if ($registered === null) {
            return null;
        }

        return (string) $this->invoke($registered->resolver, [$context, ...$arguments]);
    }

    /**
     * Human-facing placeholder label used when agent markdown deliberately
     * avoids resolving a viewer-specific dynamic value.
     */
    public function valueLabel(string $name): string
    {
        return $this->values[$name]->label ?? $name;
    }

    /**
     * Resolve an application link to a URL string. Returns null when not registered.
     *
     * @param  list<string>  $parameters
     */
    public function resolveLink(string $name, DocumentationContext $context, array $parameters = []): ?string
    {
        $registered = $this->links[$name] ?? null;

        if ($registered === null) {
            return null;
        }

        return (string) $this->invoke($registered->resolver, [$context, ...$parameters]);
    }

    /**
     * Resolve a component instance. Returns null when not registered.
     */
    public function resolveComponent(string $name): ?DocumentationComponent
    {
        $registered = $this->components[$name] ?? null;

        if ($registered === null) {
            return null;
        }

        $resolver = $registered->resolver;

        if ($resolver instanceof DocumentationComponent) {
            return $resolver;
        }

        $instance = is_string($resolver) ? ($this->classResolver)($resolver) : $resolver();

        return $instance instanceof DocumentationComponent ? $instance : null;
    }

    /**
     * Name/label/description metadata for every registered integration, grouped
     * by kind — the source for the admin picker endpoint. Resolvers are never
     * exposed; only what an editor needs to offer choices.
     *
     * @return array{
     *     conditions: list<array{name: string, label: ?string, description: ?string}>,
     *     values: list<array{name: string, label: ?string, description: ?string}>,
     *     links: list<array{name: string, label: ?string, description: ?string}>,
     *     components: list<array{name: string, label: ?string, description: ?string}>,
     *     audiences: list<array{name: string, label: ?string, description: ?string}>,
     * }
     */
    public function describe(): array
    {
        return [
            'conditions' => $this->describeAll($this->conditions),
            'values' => $this->describeAll($this->values),
            'links' => $this->describeAll($this->links),
            'components' => $this->describeAll($this->components),
            'audiences' => $this->describeAll($this->audiences),
        ];
    }

    /**
     * @param  array<string, RegisteredAudience|RegisteredComponent|RegisteredCondition|RegisteredLink|RegisteredValue>  $registered
     * @return list<array{name: string, label: ?string, description: ?string}>
     */
    private function describeAll(array $registered): array
    {
        return array_values(array_map(
            static fn (object $item): array => [
                'name' => $item->name,
                'label' => $item->label,
                'description' => $item->description,
            ],
            $registered,
        ));
    }

    /**
     * @param  Closure|class-string  $resolver
     * @param  array<int, mixed>  $arguments
     */
    private function invoke(Closure|string $resolver, array $arguments): mixed
    {
        $callable = is_string($resolver) ? ($this->classResolver)($resolver) : $resolver;

        return $callable(...$arguments);
    }
}
