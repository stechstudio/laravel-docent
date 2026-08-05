<?php

declare(strict_types=1);

namespace STS\Docent\Runtime;

use BackedEnum;
use Closure;
use Illuminate\Support\Str;
use InvalidArgumentException;
use STS\Docent\Runtime\Contracts\DocumentationComponent;
use STS\Docent\Runtime\Registered\RegisteredAudience;
use STS\Docent\Runtime\Registered\RegisteredComponent;
use STS\Docent\Runtime\Registered\RegisteredCondition;
use STS\Docent\Runtime\Registered\RegisteredLink;
use STS\Docent\Runtime\Registered\RegisteredValue;
use Throwable;

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

    /** @var null|Closure(): mixed|class-string|list<string> */
    private mixed $abilities = null;

    /** @var Closure(class-string): object */
    private Closure $classResolver;

    /** @var ?Closure(Throwable, string, string): void */
    private ?Closure $resolutionFailureHandler = null;

    private int $resolutionFailures = 0;

    /**
     * @param  ?Closure(class-string): object  $classResolver
     */
    public function __construct(?Closure $classResolver = null, private readonly ?self $parent = null)
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
        $slugs = $this->parent?->suggestionsFor($page) ?? [];

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

    /**
     * Declare the abilities this application can authorize against, so
     * `docent:check` and the admin's `authorize:` autocompletion can tell a real
     * permission from a typo.
     *
     * Docent never invokes these — authorization still runs through the Gate.
     * This is only the introspectable list, which `Gate::has()` cannot supply
     * for an application that bridges permissions with a single `Gate::before`
     * callback and therefore defines no gates at all.
     *
     * Accepts a list of strings, a backed-enum class-string, or a closure
     * returning either.
     *
     * @param  Closure(): mixed|class-string|list<string>  $abilities
     */
    public function abilities(Closure|string|array $abilities): self
    {
        $this->abilities = $abilities;

        return $this;
    }

    /**
     * The declared ability surface, or null when this application (and any
     * parent registry) declared none — in which case callers fall back to
     * `Gate::has()`. Site-local declarations replace the global one rather than
     * merging: a site that names its surface means that surface.
     *
     * @return ?list<string>
     */
    public function declaredAbilities(): ?array
    {
        if ($this->abilities === null) {
            return $this->parent?->declaredAbilities();
        }

        return self::normalizeAbilities(
            $this->abilities instanceof Closure ? ($this->abilities)() : $this->abilities,
        );
    }

    /**
     * Coerce a declared surface into a plain list of ability strings. A
     * class-string is read as a backed enum, which is where the permission list
     * lives in most applications; an array may hold ability strings or the enum
     * cases themselves.
     *
     * Entries are validated rather than cast. A surface is the sole authority on
     * which abilities exist once declared, so a stray null or nested array must
     * fail loudly here rather than become an empty ability that silently accepts
     * `authorize:` with no value.
     *
     * @internal
     *
     * @return list<string>
     */
    public static function normalizeAbilities(mixed $abilities): array
    {
        if (is_string($abilities)) {
            return self::abilitiesFromEnum($abilities);
        }

        if (! is_array($abilities)) {
            $type = get_debug_type($abilities);

            throw new InvalidArgumentException(
                "A Docent ability surface must be a list of strings, a backed-enum class-string, or a closure returning either; got {$type}.",
            );
        }

        $normalized = [];

        foreach ($abilities as $index => $ability) {
            $normalized[] = match (true) {
                $ability instanceof BackedEnum => (string) $ability->value,
                is_string($ability) && trim($ability) !== '' => $ability,
                default => throw new InvalidArgumentException(
                    "A Docent ability surface may contain only non-empty strings or backed-enum cases; entry [{$index}] is ".get_debug_type($ability).'.',
                ),
            };
        }

        return $normalized;
    }

    /**
     * @return list<string>
     */
    private static function abilitiesFromEnum(string $class): array
    {
        if (! enum_exists($class)) {
            $reason = class_exists($class) || interface_exists($class) ? 'is not an enum' : 'does not exist';

            throw new InvalidArgumentException(
                "A Docent ability surface given as a class-string must be a backed enum; [{$class}] {$reason}.",
            );
        }

        if (! is_subclass_of($class, BackedEnum::class)) {
            throw new InvalidArgumentException(
                "A Docent ability surface given as a class-string must be a BACKED enum; [{$class}] is a pure enum, whose cases have no values.",
            );
        }

        return array_map(static fn (BackedEnum $case): string => (string) $case->value, $class::cases());
    }

    public function hasCondition(string $name): bool
    {
        return isset($this->conditions[$name]) || ($this->parent?->hasCondition($name) ?? false);
    }

    public function hasValue(string $name): bool
    {
        return isset($this->values[$name]) || ($this->parent?->hasValue($name) ?? false);
    }

    public function hasLink(string $name): bool
    {
        return isset($this->links[$name]) || ($this->parent?->hasLink($name) ?? false);
    }

    public function hasComponent(string $name): bool
    {
        return isset($this->components[$name]) || ($this->parent?->hasComponent($name) ?? false);
    }

    public function hasAudience(string $name): bool
    {
        return isset($this->audiences[$name]) || ($this->parent?->hasAudience($name) ?? false);
    }

    /**
     * Resolve a condition. Returns null when the condition is not registered.
     */
    public function resolveCondition(string $name, DocumentationContext $context): ?bool
    {
        $registered = $this->conditions[$name] ?? null;

        if ($registered === null) {
            return $this->parent?->resolveCondition($name, $context);
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
            return $this->parent?->resolveAudience($name, $context);
        }

        return (bool) $this->invoke($registered->resolver, [$context]);
    }

    /**
     * How a throwing value/link resolver is handled. Without a handler the
     * exception propagates, which is what keeps this class usable outside a
     * Laravel container. Docent installs one that reports the throwable and
     * lets the rest of the page render — a help center is where someone goes
     * when something is already wrong for them, so a closure that breaks for a
     * half-initialized session should cost that reader one token, not the whole
     * document.
     *
     * @param  ?Closure(Throwable, string, string): void  $handler  Receives the throwable, token kind, and key.
     */
    public function handleResolutionFailures(?Closure $handler): self
    {
        $this->resolutionFailureHandler = $handler;

        return $this;
    }

    /**
     * How many tokens this registry has degraded rather than resolved. Callers
     * that cache rendered output compare this across a render: a cache key
     * cannot see the session state that made a resolver throw, so storing a
     * degraded render would serve the missing value to every later reader.
     *
     * @internal
     */
    public function resolutionFailures(): int
    {
        return $this->resolutionFailures;
    }

    /**
     * Invoke a host-registered resolver under this registry's failure policy,
     * returning null when it failed and was handled.
     *
     * Only the invocation itself is guarded. Instantiating a class-string
     * resolver and converting its result are the package's own work against its
     * own contract — a resolver class that does not exist, or one returning an
     * array, is a deterministic defect that every reader hits, and it must fail
     * loudly rather than render as a quietly missing value.
     *
     * @param  Closure|class-string  $resolver
     * @param  array<int, mixed>  $arguments
     */
    private function attempt(Closure|string $resolver, array $arguments, string $kind, string $name): ?string
    {
        $callable = is_string($resolver) ? ($this->classResolver)($resolver) : $resolver;

        if ($this->resolutionFailureHandler === null) {
            return (string) $callable(...$arguments);
        }

        try {
            $result = $callable(...$arguments);
        } catch (Throwable $e) {
            $this->resolutionFailures++;
            ($this->resolutionFailureHandler)($e, $kind, $name);

            return null;
        }

        return (string) $result;
    }

    /**
     * Resolve a dynamic value to a string. Returns null when not registered, or
     * when the resolver failed under a non-strict failure policy.
     *
     * @param  list<string>  $arguments
     */
    public function resolveValue(string $name, DocumentationContext $context, array $arguments = []): ?string
    {
        $registered = $this->registeredValue($name);

        return $registered === null
            ? null
            : $this->attempt($registered->resolver, [$context, ...$arguments], 'value', $name);
    }

    /**
     * Find a registration anywhere in the chain, so a globally registered
     * resolver is still invoked under *this* registry's failure policy. Handing
     * the whole resolution to the parent would run it under the parent's policy
     * — and the global registry deliberately has none.
     */
    private function registeredValue(string $name): ?RegisteredValue
    {
        return $this->values[$name] ?? $this->parent?->registeredValue($name);
    }

    /**
     * Human-facing placeholder label used when agent markdown deliberately
     * avoids resolving a viewer-specific dynamic value.
     */
    public function valueLabel(string $name): string
    {
        if (isset($this->values[$name])) {
            return $this->values[$name]->label ?? $name;
        }

        return $this->parent?->valueLabel($name) ?? $name;
    }

    /**
     * Resolve an application link to a URL string. Returns null when not
     * registered, or when the resolver failed under a non-strict failure policy.
     *
     * @param  list<string>  $parameters
     */
    public function resolveLink(string $name, DocumentationContext $context, array $parameters = []): ?string
    {
        $registered = $this->registeredLink($name);

        return $registered === null
            ? null
            : $this->attempt($registered->resolver, [$context, ...$parameters], 'link', $name);
    }

    /** @see RegisteredValue() */
    private function registeredLink(string $name): ?RegisteredLink
    {
        return $this->links[$name] ?? $this->parent?->registeredLink($name);
    }

    /**
     * Resolve a component instance. Returns null when not registered.
     */
    public function resolveComponent(string $name): ?DocumentationComponent
    {
        $registered = $this->components[$name] ?? null;

        if ($registered === null) {
            return $this->parent?->resolveComponent($name);
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
        $parent = $this->parent?->describe();

        return [
            'conditions' => $this->mergeDescriptions($parent['conditions'] ?? [], $this->describeAll($this->conditions)),
            'values' => $this->mergeDescriptions($parent['values'] ?? [], $this->describeAll($this->values)),
            'links' => $this->mergeDescriptions($parent['links'] ?? [], $this->describeAll($this->links)),
            'components' => $this->mergeDescriptions($parent['components'] ?? [], $this->describeAll($this->components)),
            'audiences' => $this->mergeDescriptions($parent['audiences'] ?? [], $this->describeAll($this->audiences)),
        ];
    }

    /**
     * @param  list<array{name: string, label: ?string, description: ?string}>  $parent
     * @param  list<array{name: string, label: ?string, description: ?string}>  $local
     * @return list<array{name: string, label: ?string, description: ?string}>
     */
    private function mergeDescriptions(array $parent, array $local): array
    {
        $merged = [];

        foreach ([...$parent, ...$local] as $item) {
            $merged[$item['name']] = $item;
        }

        return array_values($merged);
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
