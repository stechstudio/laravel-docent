<?php

namespace STS\Docent\Tests;

use STS\Docent\Tests\Support\RejectsGuests;

/**
 * A host whose guard implements neither `AuthenticatesRequests` nor
 * `Authenticate`, naming itself through `share.before`.
 *
 * Laravel only places a middleware relative to an anchor already in its
 * priority map, and appends it otherwise — so without seating the anchor
 * first, this configuration would sort the credential *after* the guard and
 * turn every share link away.
 */
abstract class ShareAnchorTestCase extends ShareTestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('docent.share.before', RejectsGuests::class);
        $app['config']->set('docent.sites.docs.route.middleware', ['web', RejectsGuests::class]);
    }
}
