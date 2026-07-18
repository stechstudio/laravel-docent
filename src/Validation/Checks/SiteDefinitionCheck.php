<?php

declare(strict_types=1);

namespace STS\Docent\Validation\Checks;

use STS\Docent\Sites\SiteRegistry;
use STS\Docent\Validation\Check;
use STS\Docent\Validation\CheckContext;
use STS\Docent\Validation\Issue;

/** Validates the global site map independently of any one site's content. */
final class SiteDefinitionCheck implements Check
{
    /** @param array<string, mixed> $config */
    public function __construct(
        private readonly array $config,
    ) {}

    public function run(CheckContext $context): iterable
    {
        yield from $this->issues();
    }

    /** @return iterable<Issue> */
    public function issues(): iterable
    {
        $sites = $this->config['sites'] ?? null;

        if (! is_array($sites) || $sites === []) {
            yield Issue::error(
                'invalid-sites',
                'docent.sites',
                'docent.sites must contain at least one site definition.',
            );

            return;
        }

        $default = $this->config['default'] ?? null;

        if (array_key_exists('default', $this->config)
            && (! is_string($default) || $default === '' || ! array_key_exists($default, $sites))) {
            yield Issue::error(
                'invalid-default-site',
                'docent.default',
                'docent.default must name a configured Docent site.',
            );
        }

        $validSites = [];

        foreach ($sites as $key => $definition) {
            $key = (string) $key;

            if (preg_match(SiteRegistry::KEY_PATTERN, $key) !== 1) {
                yield Issue::error(
                    'invalid-site-key',
                    'docent.sites.'.$key,
                    "Docent site key [{$key}] may contain only letters, numbers, underscores, and hyphens.",
                );

                continue;
            }

            if (! is_array($definition)) {
                yield Issue::error(
                    'invalid-site-definition',
                    'docent.sites.'.$key,
                    "Docent site [{$key}] must be configured as an array.",
                );

                continue;
            }

            if ($key !== 'docs' && ! $this->hasFilesystemPath($definition)) {
                yield Issue::error(
                    'missing-site-filesystem',
                    'docent.sites.'.$key,
                    "Docent site [{$key}] must define a non-empty filesystem.path.",
                );
            }

            $validSites[$key] = $definition;
        }

        $keys = array_keys($validSites);

        for ($left = 0; $left < count($keys); $left++) {
            for ($right = $left + 1; $right < count($keys); $right++) {
                $leftKey = $keys[$left];
                $rightKey = $keys[$right];
                $leftRoute = $this->route($validSites[$leftKey]);
                $rightRoute = $this->route($validSites[$rightKey]);

                if (! $this->domainsOverlap($leftRoute['domain'], $rightRoute['domain'])
                    || ! $this->pathsOverlap($leftRoute['prefix'], $rightRoute['prefix'])) {
                    continue;
                }

                yield Issue::warning(
                    'route-overlap',
                    'docent.sites.'.$leftKey.'.route',
                    "Docent sites [{$leftKey}] and [{$rightKey}] have overlapping route paths on overlapping domains.",
                );
            }
        }
    }

    /** @param array<string, mixed> $definition */
    private function hasFilesystemPath(array $definition): bool
    {
        $filesystem = $definition['filesystem'] ?? null;
        $path = is_array($filesystem) ? ($filesystem['path'] ?? null) : null;

        return is_string($path) && trim($path) !== '';
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return array{prefix: string, domain: ?string}
     */
    private function route(array $definition): array
    {
        $route = $definition['route'] ?? null;
        $route = is_array($route) ? $route : [];
        $prefix = $route['prefix'] ?? 'docs';
        $domain = $route['domain'] ?? null;

        return [
            'prefix' => trim(is_string($prefix) ? $prefix : 'docs', '/'),
            'domain' => is_string($domain) && trim($domain) !== '' ? trim($domain) : null,
        ];
    }

    private function pathsOverlap(string $left, string $right): bool
    {
        return $left === ''
            || $right === ''
            || $left === $right
            || str_starts_with($left, $right.'/')
            || str_starts_with($right, $left.'/');
    }

    private function domainsOverlap(?string $left, ?string $right): bool
    {
        if ($left === null || $right === null || $left === $right) {
            return true;
        }

        return str_contains($left, '{') || str_contains($right, '{');
    }
}
