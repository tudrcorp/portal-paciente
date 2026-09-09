<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        $app = parent::createApplication();

        // Con config:cache, app.env queda en "local" aunque phpunit.xml fije APP_ENV=testing.
        $isTesting = ($_ENV['APP_ENV'] ?? null) === 'testing'
            || (getenv('APP_ENV') ?: null) === 'testing';

        if ($isTesting) {
            $connection = $_ENV['DB_CONNECTION'] ?? 'sqlite';
            $database = $_ENV['DB_DATABASE'] ?? ':memory:';

            $app['config']->set('app.env', 'testing');
            $app['config']->set('database.default', $connection);
            $app['config']->set("database.connections.{$connection}.database", $database);

            if ($app->bound('db')) {
                $app->make('db')->purge();
            }
        }

        return $app;
    }
}
