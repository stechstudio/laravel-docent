<?php

namespace STS\Docent\Tests;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use STS\Docent\DocentManager;

/**
 * Share links only mean anything behind a guard, so this suite runs the docs
 * with `auth` on the route group — the shape a host reaches for when the
 * documentation describes their application in detail.
 */
abstract class ShareTestCase extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('docent.share.enabled', true);
        $app['config']->set('docent.sites.docs.route.middleware', ['web', 'auth']);

        Gate::define('shareDocentPage', fn ($user) => (bool) ($user->is_admin ?? false));
    }

    protected function setUp(): void
    {
        parent::setUp();

        Route::get('/login', fn () => 'login page')->name('login');
    }

    /** The share URL for a page, as the share button would produce it. */
    protected function shareUrl(string $slug, ?int $days = null): string
    {
        return $this->docent()->sharing()->urlFor($slug, $days);
    }

    protected function docent(): DocentManager
    {
        return $this->app->make(DocentManager::class);
    }
}
