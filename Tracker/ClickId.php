<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\RebelAdConversions\Tracker;

use Piwik\Plugins\RebelAdConversions\Platform;

/**
 * The ad click a browser sent with a tracking request. Nothing in it can be trusted.
 */
class ClickId
{
    // Holds "<URL parameter>:<click ID>". The tracker sends it only for a visitor who agreed.
    public const PARAMETER = 'rac';

    /**
     * @param array<string, mixed> $params
     * @return array{platform: string, click_id: string}|null
     */
    public static function fromRequestParams(array $params): ?array
    {
        $value = $params[self::PARAMETER] ?? '';
        if (!is_string($value) || !preg_match('/^([a-z]+):([A-Za-z0-9_.-]{8,200})$/D', $value, $match)) {
            return null;
        }

        $platform = Platform::ofParameter($match[1]);

        return $platform === null ? null : ['platform' => $platform, 'click_id' => $match[2]];
    }
}
