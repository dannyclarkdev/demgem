<?php

namespace App\Livewire\Campaigns;

use App\Actions\Campaigns\ImportCampaign;
use App\Actions\Campaigns\ReadCampaignArchive;
use App\Actions\Campaigns\ReadCampaignFile;
use App\Models\Campaign;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Restore a campaign from an export.
 *
 * It lives outside the campaign route group on purpose: an import has no campaign to
 * point at, because it makes one. That is also why there is no policy check beyond
 * being signed in — there is nothing yet to be a member of.
 *
 * The file is read twice: once on upload, to show the GM a report of something
 * nothing has written yet, and once on confirm. Re-reading is deliberate. The confirm
 * acts on the file rather than on a summary this component remembered, so there is no
 * window in which the two could disagree.
 */
class Import extends Component
{
    use AuthorizesRequests, WithFileUploads;

    public ?TemporaryUploadedFile $file = null;

    /**
     * Why this file cannot be imported, in the reader's words.
     *
     * Not $errors: that name is Blade's own ViewErrorBag inside every view, and a
     * public property shadowing it is a debugging session nobody needs.
     *
     * @var list<string>
     */
    public array $problems = [];

    /** @var array<string, int> */
    public array $counts = [];

    /** @var list<array{label: string, detail: string}> */
    public array $losses = [];

    public ?string $gain = null;

    public bool $isArchive = false;

    public bool $read = false;

    public function mount(): void
    {
        $this->authorize('create', Campaign::class);
    }

    public function updatedFile(): void
    {
        $this->reset('problems', 'counts', 'losses', 'gain', 'read', 'isArchive');

        $this->validate(['file' => ['required', 'file', 'max:'.(int) (ReadCampaignFile::MAX_BYTES / 1024)]]);

        // Looked at, not unpacked. A preview that extracted would leave temporary
        // files behind for every archive a GM ever opened and changed their mind about.
        $result = $this->readUpload(extract: false);

        $this->read = true;
        $this->problems = $result->errors;
        $this->counts = $result->succeeded() ? $result->report->counts : [];
        $this->losses = $result->succeeded() ? $result->report->losses() : [];
        $this->gain = $result->succeeded() ? $result->report->gains() : null;
    }

    public function import(ImportCampaign $importCampaign): void
    {
        $this->authorize('create', Campaign::class);

        if ($this->file === null) {
            return;
        }

        $result = $this->readUpload(extract: true);

        if (! $result->succeeded()) {
            $this->problems = $result->errors;

            return;
        }

        $campaign = $importCampaign->handle($result->document, $this->user(), $result->restored);

        $files = $result->report->filesRestored;

        session()->flash('status', "{$campaign->name} was imported"
            .($files > 0 ? ", with {$files} ".str('file')->plural($files) : ', without its images')
            .'. Its members did not come with it.');

        $this->redirect(route('campaigns.show', $campaign));
    }

    /**
     * A zip or a document, decided by the first four bytes rather than the file name.
     * A GM who renames their download should still get the right reader.
     */
    private function readUpload(bool $extract): UploadReading
    {
        $path = (string) $this->file?->getRealPath();
        $head = (string) file_get_contents($path, false, null, 0, 4);

        if (ReadCampaignArchive::looksLikeArchive($head)) {
            $this->isArchive = true;

            return UploadReading::fromArchive(app(ReadCampaignArchive::class)->handle($path, $extract));
        }

        $this->isArchive = false;

        return UploadReading::fromDocument(app(ReadCampaignFile::class)->handle((string) file_get_contents($path)));
    }

    public function startOver(): void
    {
        $this->reset();
    }

    public function render(): View
    {
        return view('livewire.campaigns.import', [
            'ready' => $this->read && $this->problems === [],
            'maxMegabytes' => (int) round(ReadCampaignFile::MAX_BYTES / 1_048_576),
        ])->title('Import a campaign');
    }

    private function user(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}
