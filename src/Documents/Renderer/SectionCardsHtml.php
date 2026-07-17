<?php

declare(strict_types=1);

namespace STS\Docent\Documents\Renderer;

use Illuminate\Support\Str;
use STS\Docent\Navigation\SectionCard;
use STS\Docent\Support\Icon;

/**
 * The one place section-card grids become HTML, shared by the
 * `::section-cards` directive and the `x-docent::section-cards` component
 * so both always render identically.
 */
final class SectionCardsHtml
{
    /**
     * @param  list<SectionCard>  $cards
     */
    public static function render(array $cards, int $columns): string
    {
        if ($cards === []) {
            return '';
        }

        $html = '<div class="docent-cards" data-columns="'.$columns.'">';

        foreach ($cards as $card) {
            $inner = '';

            if ($card->icon !== null && ($icon = Icon::svg($card->icon)) !== null) {
                $inner .= '<div class="docent-card-icon">'.$icon.'</div>';
            }

            $inner .= '<div class="docent-card-title">'.e($card->title).'</div>';

            if ($card->description !== null && $card->description !== '') {
                $inner .= '<div class="docent-card-body"><p>'.e($card->description).'</p></div>';
            }

            if ($card->count !== null) {
                $inner .= '<div class="docent-card-count">'.$card->count.' '.Str::plural('article', $card->count).'</div>';
            }

            $html .= '<a class="docent-card" href="'.e($card->url).'">'.$inner.'</a>';
        }

        return $html.'</div>';
    }
}
