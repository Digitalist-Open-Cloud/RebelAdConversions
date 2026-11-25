<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\RebelAdConversions\Tracker;

use Piwik\Plugins\RebelAdConversions\Dao\LogClick;
use Piwik\Plugins\RebelAdConversions\RebelAdConversions;
use Piwik\Tracker\Cache;
use Piwik\Tracker\Request;
use Piwik\Tracker\RequestProcessor as MatomoRequestProcessor;
use Piwik\Tracker\Visit\VisitProperties;

class RequestProcessor extends MatomoRequestProcessor
{
    /**
     * Stores the ad click a request names with its visit, for a website that exports conversions.
     */
    public function recordLogs(VisitProperties $visitProperties, Request $request)
    {
        $click = ClickId::fromRequestParams($request->getParams());
        $idVisit = $visitProperties->getProperty('idvisit');
        if ($click === null || empty($idVisit)) {
            return;
        }

        $idSite = (int) $request->getIdSite();
        if (empty(Cache::getCacheWebsiteAttributes($idSite)[RebelAdConversions::SITE_ATTRIBUTE])) {
            return;
        }

        (new LogClick())->record($click + [
            'idsite' => $idSite,
            'idvisit' => $idVisit,
            'idvisitor' => $visitProperties->getProperty('idvisitor'),
            'server_time' => gmdate('Y-m-d H:i:s', (int) $request->getCurrentTimestamp()),
        ]);
    }
}
