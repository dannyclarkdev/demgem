<?php

namespace App\Console\Commands;

use App\Actions\Sessions\SendSessionReminders;
use Illuminate\Console\Command;

class SendSessionRemindersCommand extends Command
{
    protected $signature = 'demgem:send-reminders';

    protected $description = 'Queue a reminder email for every session inside its campaign\'s lead window';

    public function handle(SendSessionReminders $reminders): int
    {
        $queued = $reminders->handle();

        $this->components->info($queued === 0
            ? 'Nothing to remind anyone about.'
            : 'Queued '.$queued.' '.str('reminder')->plural($queued).'.');

        return self::SUCCESS;
    }
}
