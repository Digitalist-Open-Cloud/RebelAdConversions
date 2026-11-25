<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\RebelAdConversions;

/**
 * The ad platforms conversions are exported for.
 */
class Platform
{
    // Platform => the URL parameter its click ID arrives in, and the column of that ID in its file.
    public const ALL = [
        'google' => ['parameter' => 'gclid', 'column' => 'Google Click ID', 'name' => 'Google Ads'],
        'microsoft' => ['parameter' => 'msclkid', 'column' => 'Microsoft Click ID', 'name' => 'Microsoft Advertising'],
    ];

    public static function ofParameter(string $parameter): ?string
    {
        foreach (self::ALL as $platform => $definition) {
            if ($definition['parameter'] === $parameter) {
                return $platform;
            }
        }

        return null;
    }
}
