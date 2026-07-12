<?php

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;

it('registers no widget routes and renders no launcher when disabled', function () {
    expect(Route::has('docent.widget.home'))->toBeFalse()
        ->and(Route::has('docent.widget.suggestions'))->toBeFalse()
        ->and(Route::has('docent.widget.show'))->toBeFalse()
        ->and(Blade::render('<x-docent::widget />'))->toBe('');

    $this->get('/docs/_widget')->assertNotFound();
});
