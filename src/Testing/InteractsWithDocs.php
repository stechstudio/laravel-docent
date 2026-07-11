<?php

declare(strict_types=1);

namespace STS\Docent\Testing;

use STS\Docent\DocentManager;

/**
 * Test-side entry point for asserting on rendered documentation and search.
 *
 * ```php
 * $this->docs()->page('billing/payment-methods')->as($user)
 *     ->assertVisible()->assertSee('Update your payment method')->assertDontSee('Internal');
 *
 * $this->docs()->search('payroll', as: $user)->assertSees('Payroll')->assertMissing('Secret');
 * ```
 */
trait InteractsWithDocs
{
    protected function docs(): DocsTester
    {
        return new DocsTester(app(DocentManager::class));
    }
}
