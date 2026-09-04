<?php

namespace App\Livewire\Campaigns;

use App\Actions\Campaigns\ImportCampaign;
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

    public bool $read = false;

    public function mount(): void
    {
        $this->authorize('create', Campaign::class);
    }

    public function updatedFile(): void
    {
        $this->reset('problems', 'counts', 'losses', 'read');

        $this->validate(['file' => ['required', 'file', 'max:'.(int) (ReadCampaignFile::MAX_BYTES / 1024)]]);

        $result = app(ReadCampaignFile::class)->handle((string) file_get_contents($this->file->getRealPath()));

        $this->read = true;
        $this->problems = $result->errors;
        $this->counts = $result->report->counts;
        $this->losses = $result->report->losses();
    }

    public function import(ImportCampaign $importCampaign): void
    {
        $this->authorize('create', Campaign::class);

        if ($this->file === null) {
            return;
        }

        $result = app(ReadCampaignFile::class)->handle((string) file_get_contents($this->file->getRealPath()));

        if (! $result->succeeded()) {
            $this->problems = $result->errors;

            return;
        }

        $campaign = $importCampaign->handle($result->document, $this->user());

        session()->flash('status', "{$campaign->name} was imported. Its images and its members did not come with it.");

        $this->redirect(route('campaigns.show', $campaign));
    }

    public function startOver(): void
    {
        $this->reset();
    }

    public function render(): View
    {
        return view('livewire.campaigns.import', [
            'ready' => $this->read && $this->problems === [],
        ])->title('Import a campaign');
    }

    private function user(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}
