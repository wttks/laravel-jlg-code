<?php

declare(strict_types=1);

namespace Wttks\JlgCode\Tests;

use Orchestra\Testbench\TestCase as OrchestraTestCase;
use Wttks\JlgCode\AddressResolver;
use Wttks\JlgCode\JlgCodeServiceProvider;

class TestCase extends OrchestraTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // 索引はプロセス内に残るので、テストごとに捨てる
        AddressResolver::flushIndex();

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }

    protected function getPackageProviders($app): array
    {
        return [
            JlgCodeServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }
}
