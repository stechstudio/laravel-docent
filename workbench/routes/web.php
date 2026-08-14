<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Workbench\App\Models\User;

// A small host-app dashboard for dogfooding the in-app help widget.
Route::get('/', function (Request $request) {
    if ($request->string('mode')->toString() === 'push') {
        config()->set('docent.widget.mode', 'push');
    }

    return view('demo');
})->name('dashboard.overview');

// Quick role switching for browsing the permission-aware docs.
Route::get('/demo/login/{role}', function (string $role) {
    $email = $role === 'admin' ? 'admin@acme.test' : 'member@acme.test';

    if ($user = User::query()->where('email', $email)->first()) {
        Auth::login($user);
    }

    return redirect('/docs');
})->name('workbench.demo.login');

// Somewhere for the guard to send a signed-out visitor, and the route the
// share page's "sign in to read everything" offer looks for.
Route::get('/login', fn () => <<<'HTML'
    <p>Sign in to browse the documentation.</p>
    <p><a href="/demo/login/admin">Account owner</a> &middot; <a href="/demo/login/member">Team member</a></p>
    HTML)->name('login');

Route::get('/demo/logout', function () {
    Auth::logout();

    return redirect('/docs');
})->name('workbench.demo.logout');

// A named route the docs reference via {{ link:billing.settings }}.
Route::get('/billing/settings', fn () => 'Acme Ledger — Billing Settings (demo)')
    ->name('workbench.billing.settings');
