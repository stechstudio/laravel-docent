{{--
    A card grid generated from the navigation tree, for custom layouts and
    Blade views: `<x-docent::section-cards />` for top-level directories, or
    `section="billing"` for one directory's children. Renders through the
    same builder as the `::section-cards` markdown directive, and inherits
    navigation's per-viewer authorization filtering.
--}}
@props(['section' => '', 'columns' => 3])

@inject('docentManager', 'STS\Docent\DocentManager')

{!! $docentManager->sectionCardsHtml($section, (int) $columns, $docentManager->contextFor(request())) !!}
