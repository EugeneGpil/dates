<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The deploy treats this endpoint as the verdict on a release (`deploy.php`, task
 * `deploy:health`), so both of its answers are pinned here.
 *
 * The failing one is the reason the endpoint does more than open a connection: a release that
 * cannot read .env still connects, because Laravel falls back to sqlite, and a bare "is the
 * database reachable" probe would report green while every real request 500s.
 */
class HealthTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_reports_ok_on_the_real_connection(): void
    {
        $response = $this->getJson('/api/health');

        $response->assertOk();
        $response->assertExactJson([
            'status' => 'ok',
            'connection' => 'pgsql',
        ]);
    }

    /**
     * The sqlite fallback, forced: this is the shape of a release whose .env never arrived.
     * `purge` is what makes the switch take effect — the pgsql connection is already resolved
     * by this point, and the controller would otherwise be handed the cached one.
     */
    public function test_it_reports_unhealthy_when_the_driver_is_not_the_expected_one(): void
    {
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);
        DB::purge('pgsql');

        $response = $this->getJson('/api/health');

        $response->assertStatus(503);
        $response->assertExactJson([
            'status' => 'error',
            'reason' => 'unexpected database driver [sqlite]',
        ]);
    }
}
