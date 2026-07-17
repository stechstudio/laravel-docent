<?php

declare(strict_types=1);

it('honors each site domain without cross-matching hosts', function () {
    $this->get('http://help.example.test/help')->assertOk();

    $this->resetDocentScope();
    $this->actingAs($this->adminDocsEditor())
        ->get('http://admin.example.test/admin/docs')
        ->assertOk();

    $this->get('http://admin.example.test/help')
        ->assertNotFound();

    $this->get('http://help.example.test/admin/docs')
        ->assertNotFound();
});
