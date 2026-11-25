<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\RebelAdConversions\tests\Unit;

use PHPUnit\Framework\TestCase;
use Piwik\Plugins\RebelAdConversions\Tracker\ClickId;

/**
 * @group RebelAdConversions
 * @group Plugins
 */
class ClickIdTest extends TestCase
{
    public function testAClickIsReadWithItsPlatform()
    {
        $this->assertSame(['platform' => 'google', 'click_id' => 'Abc-123_x.z'], ClickId::fromRequestParams(['rac' => 'gclid:Abc-123_x.z']));
        $this->assertSame(['platform' => 'microsoft', 'click_id' => '0123456789abcdef'], ClickId::fromRequestParams(['rac' => 'msclkid:0123456789abcdef']));
    }

    /**
     * @dataProvider getUnusableValues
     */
    public function testWhatIsNotAClickIsLeftOut($value)
    {
        $this->assertNull(ClickId::fromRequestParams(['rac' => $value]));
    }

    public function getUnusableValues(): array
    {
        return [
            'no platform' => ['Abc-123_xyz'],
            'unknown platform' => ['fbclid:Abc-123_xyz'],
            'too short' => ['gclid:abc'],
            'too long' => ['gclid:' . str_repeat('a', 201)],
            'a formula' => ['gclid:=HYPERLINK("x")'],
            'a quote' => ["gclid:abcdefgh' OR 1"],
            'a line break' => ["gclid:abcdefgh\n"],
            'a list' => [['gclid:Abc-123_xyz']],
            'nothing' => [''],
        ];
    }

    public function testARequestWithoutTheParameterHasNoClick()
    {
        $this->assertNull(ClickId::fromRequestParams(['url' => 'http://example.org/?gclid=Abc-123_xyz']));
    }
}
