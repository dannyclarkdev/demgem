<?php

namespace App\Console\Commands;

use App\Actions\Campaigns\ImportCampaign;
use App\Actions\Campaigns\ReadCampaignFile;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * The importer from a terminal.
 *
 * It exists for the case the browser is wrong for: a self-hoster moving a campaign
 * between installs over ssh, where a 40MB upload through a form is the slowest part
 * of an otherwise simple job. Same reader, same writer, same four losses.
 */
class ImportCampaignCommand extends Command
{
    protected $signature = 'demgem:import
        {file : Path to a campaign JSON export}
        {--user= : Email of the user who will own the imported campaign}';

    protected $description = 'Build a campaign from a demgem export file';

    public function handle(ReadCampaignFile $reader, ImportCampaign $importer): int
    {
        $path = (string) $this->argument('file');

        if (! is_file($path) || ! is_readable($path)) {
            $this->components->error("No readable file at {$path}.");

            return self::FAILURE;
        }

        $email = (string) $this->option('user');
        $owner = User::query()->where('email', $email)->first();

        if ($owner === null) {
            $this->components->error($email === ''
                ? 'Say who will own the campaign: --user=you@example.com'
                : "No user with the email {$email}.");

            return self::FAILURE;
        }

        $result = $reader->handle((string) file_get_contents($path));

        if (! $result->succeeded()) {
            $this->components->error('That file cannot be imported.');

            foreach ($result->errors as $problem) {
                $this->components->bulletList([$problem]);
            }

            return self::FAILURE;
        }

        $report = $result->report;

        $this->components->twoColumnDetail('<fg=green>Will import</>', '');

        foreach ($report->counts as $section => $rows) {
            $this->components->twoColumnDetail(str_replace('_', ' ', $section), (string) $rows);
        }

        foreach ($report->losses() as $loss) {
            $this->components->warn($loss['label']);
        }

        $campaign = $importer->handle($result->document, $owner);

        $this->components->info("Imported \"{$campaign->name}\" for {$owner->email}.");

        return self::SUCCESS;
    }
}
