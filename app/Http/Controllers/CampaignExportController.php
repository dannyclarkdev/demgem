<?php

namespace App\Http\Controllers;

use App\Actions\Campaigns\ExportCampaign;
use App\Models\Campaign;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedJsonResponse;

/**
 * The whole campaign as a JSON download.
 *
 * A controller rather than a Livewire page: this is a file, and Livewire has no part
 * in a file. EnsureCampaignMember has already resolved {campaign} and set the context.
 */
class CampaignExportController extends Controller
{
    public function __invoke(Campaign $campaign, ExportCampaign $export): StreamedJsonResponse
    {
        Gate::authorize('export', $campaign);

        return response()->streamJson($export->handle($campaign), 200, [
            'Content-Disposition' => 'attachment; filename="'.$export->filename($campaign).'"',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}
