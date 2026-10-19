<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $app = parent::createApplication();
        $connection = $app['config']->get('database.default');
        $database = $app['config']->get('database.connections.pgsql');
        if ($connection !== 'pgsql' || $database['database'] !== 'brainsprint_test' || ! in_array($database['host'], ['127.0.0.1', 'localhost'], true) || ! empty($database['url'])) {
            throw new \RuntimeException('Tests require the isolated local brainsprint_test PostgreSQL database.');
        }

        return $app;
    }
}
