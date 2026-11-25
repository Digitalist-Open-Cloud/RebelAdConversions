<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\RebelAdConversions\Dao;

use Piwik\Common;
use Piwik\Db;
use Piwik\DbHelper;

/**
 * The ad click a visit came from, one for each platform.
 */
class LogClick
{
    public const TABLE = 'rebeladconversions_click';

    // A conversion counts for a click that is at most this old, as the ad platforms take it.
    public const ATTRIBUTION_DAYS = 90;

    public function install(): void
    {
        DbHelper::createTable(self::TABLE, "
            `idclick` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `idsite` INT UNSIGNED NOT NULL,
            `idvisit` BIGINT UNSIGNED NOT NULL,
            `idvisitor` BINARY(8) NOT NULL,
            `platform` VARCHAR(10) NOT NULL,
            `click_id` VARCHAR(200) NOT NULL,
            `server_time` DATETIME NOT NULL,
            PRIMARY KEY (`idclick`),
            UNIQUE KEY `visit_platform` (`idvisit`, `platform`),
            KEY `visitor_time` (`idsite`, `idvisitor`, `server_time`)
        ");
    }

    public function uninstall(): void
    {
        Db::dropTables([$this->table()]);
    }

    /**
     * Stores the click of a visit. A visit that arrives through another ad keeps the later click.
     *
     * @param array<string, mixed> $row
     */
    public function record(array $row): void
    {
        $columns = array_keys($row);
        // The time is compared before the ID is replaced: MySQL assigns from left to right.
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)
             ON DUPLICATE KEY UPDATE
                server_time = IF(click_id = VALUES(click_id), server_time, VALUES(server_time)),
                click_id = VALUES(click_id)',
            $this->table(),
            implode(', ', $columns),
            implode(', ', array_fill(0, count($columns), '?'))
        );
        Db::query($sql, array_values($row));
    }

    /**
     * @return array<string, int> the number of clicks stored since then, by platform
     */
    public function countSince(int $idSite, string $datetime): array
    {
        $rows = Db::fetchAll(
            'SELECT platform, COUNT(*) AS clicks FROM ' . $this->table() . ' WHERE idsite = ? AND server_time >= ? GROUP BY platform',
            [$idSite, $datetime]
        );

        return array_map('intval', array_column($rows, 'clicks', 'platform'));
    }

    /**
     * The conversions of the given goals since then, each with the last click of its visitor on
     * the platform before it. A conversion without such a click is left out.
     *
     * @param int[] $idGoals
     * @return array<int, array{click_id: string, idgoal: string, server_time: string, revenue: string|null}>
     */
    public function getConversions(int $idSite, string $platform, array $idGoals, string $since): array
    {
        $idGoals = array_map('intval', $idGoals);
        if (!$idGoals) {
            return [];
        }

        return Db::fetchAll(
            'SELECT k.click_id, c.idgoal, c.server_time, c.revenue
               FROM ' . Common::prefixTable('log_conversion') . ' c
               JOIN ' . $this->table() . ' k ON k.idclick = (
                    SELECT k2.idclick FROM ' . $this->table() . ' k2
                     WHERE k2.idsite = c.idsite AND k2.idvisitor = c.idvisitor AND k2.platform = ?
                       AND k2.server_time <= c.server_time
                       AND k2.server_time >= DATE_SUB(c.server_time, INTERVAL ' . self::ATTRIBUTION_DAYS . ' DAY)
                     ORDER BY k2.server_time DESC, k2.idclick DESC LIMIT 1)
              WHERE c.idsite = ? AND c.server_time >= ? AND c.idgoal IN (' . Common::getSqlStringFieldsArray($idGoals) . ')
              ORDER BY c.server_time, c.idvisit, c.idgoal, c.buster',
            array_merge([$platform, $idSite, $since], $idGoals)
        );
    }

    public function deleteOlderThan(string $datetime): void
    {
        Db::query('DELETE FROM ' . $this->table() . ' WHERE server_time < ?', [$datetime]);
    }

    public function deleteSite(int $idSite): void
    {
        Db::query('DELETE FROM ' . $this->table() . ' WHERE idsite = ?', [$idSite]);
    }

    private function table(): string
    {
        return Common::prefixTable(self::TABLE);
    }
}
