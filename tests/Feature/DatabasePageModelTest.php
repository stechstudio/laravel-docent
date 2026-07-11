<?php

use STS\Docent\Content\Models\DocentPage;

it('upserts a page by slug and snapshots a revision on write', function () {
    $page = DocentPage::write('announcements', '# Hello', ['title' => 'Announcements']);

    expect($page->slug)->toBe('announcements')
        ->and($page->title)->toBe('Announcements')
        ->and($page->content)->toBe('# Hello')
        ->and($page->format)->toBe('markdown')
        ->and($page->revisions()->count())->toBe(1);

    $again = DocentPage::write('announcements', '# Hello again', ['title' => 'Announcements']);

    expect($again->getKey())->toBe($page->getKey())
        ->and(DocentPage::count())->toBe(1)
        ->and($again->revisions()->count())->toBe(2);
});

it('derives the title from the slug when front matter omits it', function () {
    $page = DocentPage::write('release-notes/june', 'body');

    expect($page->title)->toBe('June');
});

it('does not snapshot a new revision when nothing changed', function () {
    $page = DocentPage::write('x', 'same', ['title' => 'X']);
    DocentPage::write('x', 'same', ['title' => 'X']);

    expect($page->fresh()->revisions()->count())->toBe(1);
});

it('publishes the latest revision by default', function () {
    $page = DocentPage::write('x', 'v1');

    expect($page->isPublished())->toBeFalse();

    $page->publish();

    expect($page->isPublished())->toBeTrue()
        ->and($page->published_revision_id)->toBe($page->latestRevision()->getKey());
});

it('reports unpublished changes after an edit until re-published', function () {
    $page = DocentPage::write('x', 'v1')->publish();

    expect($page->hasUnpublishedChanges())->toBeFalse();

    DocentPage::write('x', 'v2');

    expect($page->fresh()->hasUnpublishedChanges())->toBeTrue();

    $page->fresh()->publish();

    expect($page->fresh()->hasUnpublishedChanges())->toBeFalse();
});

it('unpublishes a page', function () {
    $page = DocentPage::write('x', 'v1')->publish();

    $page->unpublish();

    expect($page->isPublished())->toBeFalse()
        ->and($page->published_revision_id)->toBeNull();
});

it('reverts to a past revision as a new revision, preserving history', function () {
    $page = DocentPage::write('x', 'v1');
    $first = $page->latestRevision();
    DocentPage::write('x', 'v2');

    $page->fresh()->revertTo($first);

    $page = $page->fresh();

    expect($page->content)->toBe('v1')
        ->and($page->revisions()->count())->toBe(3)
        ->and($page->latestRevision()->content)->toBe('v1');
});

it('casts front matter to an array', function () {
    $page = DocentPage::write('x', 'body', ['title' => 'X', 'order' => 3]);

    expect($page->fresh()->front_matter)->toBe(['title' => 'X', 'order' => 3]);
});
