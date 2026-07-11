<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Workbench\App\Models\User;

// The demo app is the docs site.
Route::get('/', fn () => redirect('/docs'));

// Quick role switching for browsing the permission-aware docs.
Route::get('/demo/login/{role}', function (string $role) {
    $email = $role === 'admin' ? 'admin@acme.test' : 'member@acme.test';

    if ($user = User::query()->where('email', $email)->first()) {
        Auth::login($user);
    }

    return redirect('/docs');
})->name('workbench.demo.login');

Route::get('/demo/logout', function () {
    Auth::logout();

    return redirect('/docs');
})->name('workbench.demo.logout');

// A named route the docs reference via {{ link:billing.settings }}.
Route::get('/billing/settings', fn () => 'Acme Ledger — Billing Settings (demo)')
    ->name('workbench.billing.settings');
