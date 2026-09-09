<?php

use App\Actions\Entities\UpdateEntity;
use App\Models\Campaign;
use App\Models\Entity;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

it('preserves the intervening body when a second PostgreSQL writer waits for the first', function () {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('This test exercises PostgreSQL row locks.');
    }

    $campaign = Campaign::factory()->create();
    $owner = ownerOf($campaign);
    $entity = Entity::factory()->for($campaign)->create(['body' => 'Original body']);
    $connection = base64_encode(json_encode(config('database.connections.pgsql'), JSON_THROW_ON_ERROR));
    $process = new Process([PHP_BINARY, '-r', <<<'CODE'
        require $argv[1].'/vendor/autoload.php';
        $app = require $argv[1].'/bootstrap/app.php';
        $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        config(['database.default' => 'pgsql', 'database.connections.pgsql' => json_decode(base64_decode($argv[2]), true)]);
        Illuminate\Support\Facades\DB::purge('pgsql');
        Illuminate\Support\Facades\DB::statement("set application_name = 'demgem_body_history_test'");
        $entity = App\Models\Entity::query()->findOrFail($argv[3]);
        $actor = App\Models\User::query()->findOrFail($argv[4]);
        fwrite(STDOUT, "loaded\n");
        app(App\Actions\Entities\UpdateEntity::class)->handle($entity, $actor, ['body' => 'Second writer body']);
        CODE,
        base_path(), $connection, $entity->id, (string) $owner->id,
    ], base_path(), ['APP_ENV' => 'testing', 'SCOUT_DRIVER' => 'database', 'BROADCAST_CONNECTION' => 'null']);
    $process->setTimeout(10);

    /** Expose this test's fixtures to the independent connection, then restore the test transaction in finally. */
    DB::commit();

    try {
        DB::beginTransaction();
        app(UpdateEntity::class)->handle($entity, $owner, ['body' => 'First writer body']);
        $process->start();
        expect($process->waitUntil(fn (string $type, string $output) => str_contains($output, 'loaded')))->toBeTrue();
        $waiting = false;
        $deadline = microtime(true) + 5;

        while (microtime(true) < $deadline && ! $waiting) {
            $waiting = DB::table('pg_stat_activity')->where('application_name', 'demgem_body_history_test')
                ->where('wait_event_type', 'Lock')->exists();

            if (! $waiting) {
                usleep(10_000);
            }
        }

        expect($waiting)->toBeTrue('The second writer should wait for the first transaction.');
        DB::commit();
        expect($process->wait())->toBe(0, $process->getErrorOutput());
        expect($entity->fresh()->body)->toBe('Second writer body');
        expect($entity->bodyRevisions()->orderBy('id')->pluck('body')->all())
            ->toBe(['Original body', 'First writer body']);
    } finally {
        if ($process->isRunning()) {
            $process->stop();
        }

        while (DB::transactionLevel() > 0) {
            DB::rollBack();
        }

        DB::table('campaigns')->where('id', $campaign->id)->delete();
        DB::table('users')->where('id', $owner->id)->delete();
        DB::beginTransaction();
    }
});
