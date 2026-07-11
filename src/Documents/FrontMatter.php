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

    public function searchExcluded(): bool
    {
        return (bool) $this->get('search.exclude', false);
    }

    public function redirect(): ?string
    {
        $redirect = $this->get('redirect');

        return is_scalar($redirect) ? (string) $redirect : null;
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
