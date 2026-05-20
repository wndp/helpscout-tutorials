<?php

namespace Wndp\HelpScoutTutorials\Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;
use Wndp\HelpScoutTutorials\HelpScoutTutorialsServiceProvider;

abstract class TestCase extends BaseTestCase
{
    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [HelpScoutTutorialsServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('helpscout-tutorials.api_key', 'test-key');
    }
}
