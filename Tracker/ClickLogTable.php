<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\RebelAdConversions\Tracker;

use Piwik\Plugins\RebelAdConversions\Dao\LogClick;
use Piwik\Tracker\LogTable;

class ClickLogTable extends LogTable
{
    public function getName()
    {
        return LogClick::TABLE;
    }

    public function getIdColumn()
    {
        return 'idclick';
    }

    public function getPrimaryKey()
    {
        return ['idclick'];
    }

    public function getColumnToJoinOnIdVisit()
    {
        return 'idvisit';
    }

    public function getDateTimeColumn()
    {
        return 'server_time';
    }

    public function hasIdVisitorColumn(): bool
    {
        return true;
    }

    public function shouldJoinWithSubSelect()
    {
        return true;
    }
}
