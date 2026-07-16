<?php

declare(strict_types=1);

namespace STS\Docent\Documents;

use Illuminate\Support\Arr;

/**
 * Typed accessor over a page's parsed YAML front matter.
 */
final class FrontMatter
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        private readonly array $data = [],
    ) {}

    public function title(): ?string
    {
        $title = $this->get('title');

        return is_scalar($title) ? (string) $title : null;
    }

    public function description(): ?string
    {
        $description = $this->get('description');

        return is_scalar($description) ? (string) $description : null;
    }

    /**
     * Gate/ability string that authorizes viewing the whole page.
     */
    public function authorize(): ?string
    {
        $authorize = $this->get('authorize');

        return is_scalar($authorize) ? (string) $authorize : null;
    }

    public function audience(): ?string
    {
        $audience = $this->get('audience');

        return is_scalar($audience) ? (string) $audience : null;
    }

    public function order(): ?int
    {
        $order = $this->get('order');

        return is_numeric($order) ? (int) $order : null;
    }

    public function hidden(): bool
    {
        return (bool) $this->get('hidden', false);
    }

    public function locked(): bool
    {
        return $this->get('locked', false) === true;
    }

    public function searchExcluded(): bool
    {
        return (bool) $this->get('search.exclude', false);
    }

    /** @return list<string> */
    public function searchKeywords(): array
    {
        $keywords = $this->get('search.keywords', []);

        if (! is_array($keywords)) {
            return [];
        }

        return array_values(array_slice(array_filter(array_map(
            static fn (mixed $keyword): string => is_string($keyword) ? mb_substr(trim($keyword), 0, 80) : '',
            $keywords,
        )), 0, 12));
    }

    public function redirect(): ?string
    {
        $redirect = $this->get('redirect');

        return is_scalar($redirect) ? (string) $redirect : null;
    }

    public function hasRedirect(): bool
    {
        return array_key_exists('redirect', $this->data);
    }

    /**
     * The page layout: `docs` (default, full navigation chrome) or `landing`
     * (hero + centered body, no sidebar/TOC/prev-next).
     */
    public function layout(): string
    {
        $layout = $this->get('layout');

        return is_scalar($layout) ? (string) $layout : 'docs';
    }

    /**
     * Hero call-to-action buttons for a landing page. Each button is a label,
     * an href (an internal slug or external URL, resolved by the caller), and a
     * style (`primary` accent button, or `secondary` bordered).
     *
     * @return list<array{label: string, href: string, style: string}>
     */
    public function heroCta(): array
    {
        $cta = $this->get('hero.cta');

        if (! is_array($cta)) {
            return [];
        }

        $buttons = [];

        foreach ($cta as $item) {
            if (! is_array($item) || ! is_scalar($item['label'] ?? null) || ! is_scalar($item['href'] ?? null)) {
                continue;
            }

            $buttons[] = [
                'label' => (string) $item['label'],
                'href' => (string) $item['href'],
                'style' => ($item['style'] ?? null) === 'secondary' ? 'secondary' : 'primary',
            ];
        }

        return $buttons;
    }

    /**
     * Dot-notation access into the raw front matter.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return Arr::get($this->data, $key, $default);
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->data;
    }
}
