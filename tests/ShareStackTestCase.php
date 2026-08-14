<?php

namespace STS\Docent\Tests;

use Illuminate\Support\Facades\Gate;
use STS\Docent\Tests\Support\StampsResponse;

/**
 * A host stack with more below the guard than authentication alone, and a
 * share limiter tight enough to trip inside a test.
 *
 * The credential stands in for the guard and nothing else, so an authorization
 * middleware sitting under it still has to run and still has to be able to
 * refuse a share request.
 */
abstract class ShareStackTestCase extends ShareTestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('docent.share.throttle', '1,1');
        $app['config']->set('docent.sites.docs.route.middleware', [
            'web',
            'auth',
            'can:readDocs',
            StampsResponse::class,
        ]);

        Gate::define('readDocs', fn (?object $user = null): bool => (bool) config('docent_test.docs_readable', true));
    }
}
