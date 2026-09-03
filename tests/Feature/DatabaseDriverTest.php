<?php

use Illuminate\Support\Facades\DB;

/**
 * CI runs the suite against PostgreSQL because SQLite hides real differences.
 * It hid one already: bigint morphs against ULID keys. Without this test a
 * misconfigured job would pass on SQLite and prove nothing.
 */
it('runs on the database driver CI asks for', function () {
    $expected = env('CI_DB_DRIVER');

    if ($expected === null) {
        expect(DB::connection()->getDriverName())->not->toBeEmpty();

        return;
    }

    expect(DB::connection()->getDriverName())->toBe($expected);
});
