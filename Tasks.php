<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\RebelAdConversions;

use Piwik\Date;
use Piwik\Plugins\RebelAdConversions\Dao\LogClick;

class Tasks extends \Piwik\Plugin\Tasks
{
    public function schedule()
    {
        $this->daily('deleteOldClicks', null, self::LOW_PRIORITY);
    }

    /**
     * Deletes the clicks that are too old to be in a file: no conversion a file can hold counts for them.
     */
    public function deleteOldClicks(): void
    {
        (new LogClick())->deleteOlderThan(Date::now()->subDay(LogClick::ATTRIBUTION_DAYS + Export::MAX_DAYS)->getDatetime());
    }
}
