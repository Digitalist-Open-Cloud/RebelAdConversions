<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\RebelAdConversions;

use Piwik\Access;
use Piwik\Date;
use Piwik\Option;
use Piwik\Plugins\RebelAdConversions\Dao\LogClick;
use Piwik\Site;

/**
 * The conversions of a website as the file an ad platform imports.
 */
class Export
{
    public const DEFAULT_DAYS = 30;
    public const MAX_DAYS = 90;

    private const TOKEN_OPTION = 'RebelAdConversions_token_';

    /**
     * The file for a platform with the conversions of the last days. Times are in UTC.
     */
    public function getCsv(int $idSite, string $platform, int $days): string
    {
        $days = max(1, min(self::MAX_DAYS, $days));
        [$names, $currency] = Access::doAsSuperUser(function () use ($idSite) {
            return [(new MeasurableSettings($idSite))->getConversionNames(), Site::getCurrencyFor($idSite)];
        });

        // ponytail: the file is built in memory. Stream it if a website has more conversions from ads than fit.
        $file = fopen('php://temp', 'w+');
        if ($platform === 'google') {
            fwrite($file, "Parameters:TimeZone=+0000\n");
        }
        fputcsv($file, [Platform::ALL[$platform]['column'], 'Conversion Name', 'Conversion Time', 'Conversion Value', 'Conversion Currency'], ',', '"', '');

        $since = Date::now()->subDay($days)->getDatetime();
        foreach ((new LogClick())->getConversions($idSite, $platform, array_keys($names), $since) as $conversion) {
            fputcsv($file, [
                $conversion['click_id'],
                $names[(int) $conversion['idgoal']],
                $conversion['server_time'] . '+0000',
                round((float) $conversion['revenue'], 2),
                $currency,
            ], ',', '"', '');
        }

        rewind($file);

        return (string) stream_get_contents($file);
    }

    /**
     * The secret an ad platform fetches the file of a website with.
     */
    public static function getToken(int $idSite): string
    {
        $token = Option::get(self::TOKEN_OPTION . $idSite);

        return is_string($token) && $token !== '' ? $token : self::resetToken($idSite);
    }

    public static function resetToken(int $idSite): string
    {
        $token = bin2hex(random_bytes(16));
        Option::set(self::TOKEN_OPTION . $idSite, $token);

        return $token;
    }

    public static function isToken(int $idSite, string $token): bool
    {
        $stored = Option::get(self::TOKEN_OPTION . $idSite);

        return is_string($stored) && $stored !== '' && hash_equals($stored, $token);
    }

    public static function deleteToken(int $idSite): void
    {
        Option::delete(self::TOKEN_OPTION . $idSite);
    }

    public static function deleteAllTokens(): void
    {
        Option::deleteLike(str_replace('_', '\_', self::TOKEN_OPTION) . '%');
    }
}
