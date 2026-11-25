<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\RebelAdConversions;

use Piwik\Common;
use Piwik\Plugin;
use Piwik\Plugins\RebelAdConversions\Dao\LogClick;

class RebelAdConversions extends Plugin
{
    // Set in the tracker cache of a website that has conversions to export.
    public const SITE_ATTRIBUTE = 'rebeladconversions';

    public function registerEvents()
    {
        return [
            'Db.getTablesInstalled' => 'getTablesInstalled',
            'Tracker.Cache.getSiteAttributes' => 'addSiteAttributes',
            'SitesManager.deleteSite.end' => 'deleteSite',
        ];
    }

    public function isTrackerPlugin()
    {
        return true;
    }

    public function install()
    {
        (new LogClick())->install();
    }

    public function uninstall()
    {
        (new LogClick())->uninstall();
        Export::deleteAllTokens();
    }

    public function getTablesInstalled(&$tables)
    {
        $tables[] = Common::prefixTable(LogClick::TABLE);
    }

    public function addSiteAttributes(&$attributes, $idSite)
    {
        $attributes[self::SITE_ATTRIBUTE] = !empty((new MeasurableSettings((int) $idSite))->getConversionNames());
    }

    public function deleteSite($idSite)
    {
        (new LogClick())->deleteSite((int) $idSite);
        Export::deleteToken((int) $idSite);
    }
}
