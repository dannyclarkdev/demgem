<?php

namespace App\Http\Controllers;

use App\Actions\Campaigns\BuildCampaignArchive;
use App\Models\Campaign;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * The campaign as one zip: the document, the media, and the readable copy.
 *
 * A download rather than a stream, because a zip's central directory is written last
 * and there is nothing to send until it is. The file is temporary and goes as soon as
 * it has been sent.
 */
class CampaignArchiveController extends Controller
{
    public function __invoke(Campaign $campaign, BuildCampaignArchive $archive): BinaryFileResponse
    {
        Gate::authorize('export', $campaign);

        return response()
            ->download($archive->handle($campaign), $archive->filename($campaign))
            ->deleteFileAfterSend();
    }
}
