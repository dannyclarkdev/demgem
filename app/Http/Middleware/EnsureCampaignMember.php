<?php

namespace App\Http\Middleware;

use App\Models\Campaign;
use App\Support\CurrentCampaign;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves {campaign}, requires membership, and sets the CurrentCampaign context.
 * Non-members get 404 so the campaign's existence is not confirmed.
 */
class EnsureCampaignMember
{
    public function handle(Request $request, Closure $next): Response
    {
        $parameter = $request->route('campaign');
        $campaign = $parameter instanceof Campaign ? $parameter : Campaign::find($parameter);

        abort_if($campaign === null, 404);

        $member = $campaign->memberFor($request->user());

        abort_if($member === null, 404);

        $request->route()?->setParameter('campaign', $campaign);
        app(CurrentCampaign::class)->set($campaign, $member->role);

        return $next($request);
    }
}
