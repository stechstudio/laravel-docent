<?php

declare(strict_types=1);

namespace STS\Docent\Support;

/**
 * The base gray palette that sets the whole UI's temperature. The views are
 * authored against Tailwind's `slate` utilities, which compile to
 * `var(--color-slate-*)` in the built stylesheet — so switching palette is a
 * runtime remap of those variables onto another scale, no rebuild required.
 *
 * Scales are Tailwind v4's default oklch values, hardcoded so the mapping is
 * self-contained and matches the shipped utilities exactly.
 */
enum GrayPalette: string
{
    case Slate = 'slate';
    case Zinc = 'zinc';
    case Stone = 'stone';
    case Neutral = 'neutral';

    public static function fromConfig(?string $value): self
    {
        return self::tryFrom($value ?? '') ?? self::Slate;
    }

    /**
     * CSS declarations remapping the slate utility variables the views use onto
     * this palette. Slate is the identity, so it emits nothing.
     */
    public function declarations(): string
    {
        if ($this === self::Slate) {
            return '';
        }

        $css = '';

        foreach ($this->scale() as $shade => $value) {
            $css .= '--color-slate-'.$shade.':'.$value.';';
        }

        return $css;
    }

    /**
     * @return array<int, string>
     */
    private function scale(): array
    {
        return match ($this) {
            self::Slate => [
                50 => 'oklch(98.4% 0.003 247.858)',
                100 => 'oklch(96.8% 0.007 247.896)',
                200 => 'oklch(92.9% 0.013 255.508)',
                300 => 'oklch(86.9% 0.022 252.894)',
                400 => 'oklch(70.4% 0.04 256.788)',
                500 => 'oklch(55.4% 0.046 257.417)',
                600 => 'oklch(44.6% 0.043 257.281)',
                700 => 'oklch(37.2% 0.044 257.287)',
                800 => 'oklch(27.9% 0.041 260.031)',
                900 => 'oklch(20.8% 0.042 265.755)',
                950 => 'oklch(12.9% 0.042 264.695)',
            ],
            self::Zinc => [
                50 => 'oklch(98.5% 0 0)',
                100 => 'oklch(96.7% 0.001 286.375)',
                200 => 'oklch(92% 0.004 286.32)',
                300 => 'oklch(87.1% 0.006 286.286)',
                400 => 'oklch(70.5% 0.015 286.067)',
                500 => 'oklch(55.2% 0.016 285.938)',
                600 => 'oklch(44.2% 0.017 285.786)',
                700 => 'oklch(37% 0.013 285.805)',
                800 => 'oklch(27.4% 0.006 286.033)',
                900 => 'oklch(21% 0.006 285.885)',
                950 => 'oklch(14.1% 0.005 285.823)',
            ],
            self::Stone => [
                50 => 'oklch(98.5% 0.001 106.423)',
                100 => 'oklch(97% 0.001 106.424)',
                200 => 'oklch(92.3% 0.003 48.717)',
                300 => 'oklch(86.9% 0.005 56.366)',
                400 => 'oklch(70.9% 0.01 56.259)',
                500 => 'oklch(55.3% 0.013 58.071)',
                600 => 'oklch(44.4% 0.011 73.639)',
                700 => 'oklch(37.4% 0.01 67.558)',
                800 => 'oklch(26.8% 0.007 34.298)',
                900 => 'oklch(21.6% 0.006 56.043)',
                950 => 'oklch(14.7% 0.004 49.25)',
            ],
            self::Neutral => [
                50 => 'oklch(98.5% 0 0)',
                100 => 'oklch(97% 0 0)',
                200 => 'oklch(92.2% 0 0)',
                300 => 'oklch(87% 0 0)',
                400 => 'oklch(70.8% 0 0)',
                500 => 'oklch(55.6% 0 0)',
                600 => 'oklch(43.9% 0 0)',
                700 => 'oklch(37.1% 0 0)',
                800 => 'oklch(26.9% 0 0)',
                900 => 'oklch(20.5% 0 0)',
                950 => 'oklch(14.5% 0 0)',
            ],
        };
    }
}
