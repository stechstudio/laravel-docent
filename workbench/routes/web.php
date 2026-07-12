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
});

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
