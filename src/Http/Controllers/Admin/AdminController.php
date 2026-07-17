<?php

declare(strict_types=1);

namespace STS\Docent\Http\Controllers\Admin;

use Illuminate\View\View;
use STS\Docent\DocentManager;

/**
 * Serves the admin panel shell. The interactive UI (Alpine + the prebuilt
 * docent-admin bundle) mounts into it in a later phase; this round ships only
 * the mount point.
 */
final class AdminController
{
    public function __construct(
        private readonly DocentManager $docent,
    ) {}

    public function __invoke(): View
    {
        return view('docent::admin', ['docent' => $this->docent]);
    }
}
