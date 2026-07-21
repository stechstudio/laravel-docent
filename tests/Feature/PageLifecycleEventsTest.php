<?php

use Illuminate\Support\Facades\Event;
use STS\Docent\Content\Events\PageDeleted;
use STS\Docent\Content\Events\PagePublished;
use STS\Docent\Content\Events\PageSaved;
use STS\Docent\Content\Events\PageUnpublished;
use STS\Docent\Content\Models\DocentPage;

it('dispatches PageSaved with created=true for a new page', function () {
    Event::fake([PageSaved::class]);

    DocentPage::write('events/new-page', '# New page', ['title' => 'New page']);

    Event::assertDispatched(PageSaved::class, fn (PageSaved $event): bool => $event->created === true
        && $event->page->slug === 'events/new-page');
});

it('dispatches PageSaved with created=false for an updated page', function () {
    DocentPage::write('events/updated-page', '# Original', ['title' => 'Updated page']);
    Event::fake([PageSaved::class]);

    DocentPage::write('events/updated-page', '# Updated', ['title' => 'Updated page']);

    Event::assertDispatched(PageSaved::class, fn (PageSaved $event): bool => $event->created === false
        && $event->page->slug === 'events/updated-page');
});

it('dispatches PagePublished with the affected page', function () {
    $page = DocentPage::write('events/published-page', '# Published');
    Event::fake([PagePublished::class]);

    $page->publish();

    Event::assertDispatched(PagePublished::class, fn (PagePublished $event): bool => $event->page->is($page));
});

it('dispatches PageUnpublished with the affected page', function () {
    $page = DocentPage::write('events/unpublished-page', '# Unpublished')->publish();
    Event::fake([PageUnpublished::class]);

    $page->unpublish();

    Event::assertDispatched(PageUnpublished::class, fn (PageUnpublished $event): bool => $event->page->is($page));
});

it('dispatches PageDeleted once with the affected page', function () {
    $page = DocentPage::write('events/deleted-page', '# Deleted');
    Event::fake([PageDeleted::class]);

    $page->delete();

    Event::assertDispatched(PageDeleted::class, fn (PageDeleted $event): bool => $event->page->is($page));
    Event::assertDispatchedTimes(PageDeleted::class, 1);
});
