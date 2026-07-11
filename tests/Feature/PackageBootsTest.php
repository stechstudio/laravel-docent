<?php

use STS\Docent\DocentServiceProvider;

it('boots the service provider', function () {
    expect($this->app->getProviders(DocentServiceProvider::class))->not->toBeEmpty();
});
