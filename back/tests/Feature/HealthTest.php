<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * The deploy treats this endpoint as the verdict on a release, so the branch worth pinning is
 * the one that says no: a release that cannot read .env still connects, because Laravel falls
 * back to sqlite, and a bare "is the database reachable" probe would report green while every
 * real request 500s.
 *
 * The suite runs on that same sqlite fallback (phpunit.xml), which is why the guard is
 * observable here at all — the 200 path needs the real pgsql connection and is exercised by
 * `deploy:health` against the running containers.
 */
class HealthTest extends TestCase
{
    public function test_it_reports_unhealthy_when_the_driver_is_not_the_expected_one(): void
    {
        $response = $this->getJson('/api/health');

        $response->assertStatus(503);
        $response->assertJson([
            'status' => 'error',
            'reason' => 'unexpected database driver [sqlite]',
        ]);
    }
}
