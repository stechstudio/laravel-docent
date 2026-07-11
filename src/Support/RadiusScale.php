<?php

declare(strict_types=1);

namespace STS\Docent\Support;

/**
 * The corner-radius feel of the UI. The views use Tailwind's `rounded-*`
 * utilities, which compile to `var(--radius-*)` — so shifting the whole UI
 * sharper or softer is a runtime remap of those variables, no rebuild required.
 *
 * `Default` mirrors Tailwind's stock scale and emits nothing.
 */
enum RadiusScale: string
{
    case Sharp = 'sharp';
    case Default = 'default';
    case Soft = 'soft';

    public static function fromConfig(?string $value): self
    {
        return self::tryFrom($value ?? '') ?? self::Default;
    }

    public function declarations(): string
    {
        if ($this === self::Default) {
            return '';
        }

        $css = '';

        foreach ($this->scale() as $step => $value) {
            $css .= '--radius-'.$step.':'.$value.';';
        }

        return $css;
    }

    /**
     * @return array<string, string>
     */
    private function scale(): array
    {
        return match ($this) {
            self::Sharp => [
                'sm' => '0.125rem',
                'md' => '0.1875rem',
                'lg' => '0.25rem',
                'xl' => '0.375rem',
                '2xl' => '0.5rem',
            ],
            self::Default => [
                'sm' => '0.25rem',
                'md' => '0.375rem',
                'lg' => '0.5rem',
                'xl' => '0.75rem',
                '2xl' => '1rem',
            ],
            self::Soft => [
                'sm' => '0.375rem',
                'md' => '0.5rem',
                'lg' => '0.75rem',
                'xl' => '1rem',
                '2xl' => '1.5rem',
            ],
        };
    }
}
