<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();

        // Password::defaults()->uncompromised() calls the HaveIBeenPwned API
        // over the network. Fake it so tests are deterministic and don't
        // depend on external connectivity (e.g. in CI).
        Http::fake([
            'api.pwnedpasswords.com/*' => Http::response('', 200),
        ]);
    }
}
