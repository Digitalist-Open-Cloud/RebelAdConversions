<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\RebelAdConversions\tests\Integration;

use Exception;
use Piwik\Common;
use Piwik\Date;
use Piwik\Db;
use Piwik\NoAccessException;
use Piwik\Nonce;
use Piwik\Plugins\Goals\API as GoalsApi;
use Piwik\Plugins\PrivacyManager\Model\DataSubjects;
use Piwik\Plugins\RebelAdConversions\Controller;
use Piwik\Plugins\RebelAdConversions\Dao\LogClick;
use Piwik\Plugins\RebelAdConversions\Export;
use Piwik\Plugins\SitesManager\API as SitesManagerApi;
use Piwik\Container\StaticContainer;
use Piwik\Tests\Framework\Fixture;
use Piwik\Tests\Framework\Mock\FakeAccess;
use Piwik\Tests\Framework\TestCase\IntegrationTestCase;

/**
 * @group RebelAdConversions
 * @group Plugins
 */
class AdConversionsTest extends IntegrationTestCase
{
    private const DAY = '2024-03-05';

    /**
     * @var int
     */
    private $visits = 0;

    /**
     * @var int
     */
    private $idGoal;

    public function setUp(): void
    {
        parent::setUp();

        FakeAccess::clearAccess(true);
        Fixture::loadAllTranslations();
        Fixture::createSuperUser();
        Fixture::createWebsite('2024-01-01 00:00:00', 1);
        Fixture::createWebsite('2024-01-01 00:00:00');
        $this->idGoal = GoalsApi::getInstance()->addGoal(1, 'Signup', 'manually', '', '');
        $this->exportConversions(1, [['goal' => (string) $this->idGoal, 'name' => 'Lead "qualified"'], ['goal' => '0', 'name' => 'Purchase']]);
        Date::$now = strtotime(self::DAY . ' 12:00:00');
        $this->visits = 0;
        $_GET = $_POST = [];
    }

    public function tearDown(): void
    {
        Date::$now = null;
        $_GET = $_POST = [];
        unset($_SERVER['REQUEST_METHOD'], $_SERVER['PHP_AUTH_PW']);
        parent::tearDown();
    }

    public function testAClickIsStoredWithTheVisitItCameIn()
    {
        $this->startVisit(1, 'gclid:Abc-123_xyz');

        $clicks = $this->getClicks();
        $this->assertCount(1, $clicks);
        $this->assertSame(['google', 'Abc-123_xyz', self::DAY . ' 10:00:00'], [$clicks[0]['platform'], $clicks[0]['click_id'], $clicks[0]['server_time']]);
        $this->assertEquals(1, $clicks[0]['idsite']);
        $this->assertEquals(Db::fetchOne('SELECT idvisit FROM ' . Common::prefixTable('log_visit')), $clicks[0]['idvisit']);
    }

    public function testNoClickIsStoredForAWebsiteThatExportsNothing()
    {
        $this->startVisit(2, 'gclid:Abc-123_xyz');

        $this->assertSame([], $this->getClicks());
    }

    public function testNoClickIsStoredOnceAWebsiteStopsExporting()
    {
        $this->exportConversions(1, []);

        $this->startVisit(1, 'gclid:Abc-123_xyz');

        $this->assertSame([], $this->getClicks());
    }

    public function testAClickThatIsSentAfterThePageViewIsStoredWithItsVisit()
    {
        $tracker = $this->startVisit(1);
        $this->assertSame([], $this->getClicks());

        $tracker->setCustomTrackingParameter('rac', 'msclkid:0123456789abcdef');
        Fixture::checkResponse($tracker->doPing());

        $this->assertSame(['microsoft'], array_column($this->getClicks(), 'platform'));
        $this->assertEquals(1, Db::fetchOne('SELECT COUNT(*) FROM ' . Common::prefixTable('log_visit')));
    }

    public function testAVisitKeepsOneClickForEachPlatformAndTheTimeItFirstCame()
    {
        $tracker = $this->startVisit(1, 'gclid:Abc-123_xyz');
        $tracker->setForceVisitDateTime(self::DAY . ' 10:05:00');
        Fixture::checkResponse($tracker->doTrackPageView('Again'));
        $tracker->setCustomTrackingParameter('rac', 'msclkid:0123456789abcdef');
        Fixture::checkResponse($tracker->doTrackPageView('Other platform'));

        $clicks = $this->getClicks();
        $this->assertSame(['google', 'microsoft'], array_column($clicks, 'platform'));
        $this->assertSame(self::DAY . ' 10:00:00', $clicks[0]['server_time']);

        $tracker->setCustomTrackingParameter('rac', 'gclid:Another-click');
        Fixture::checkResponse($tracker->doTrackPageView('Another ad'));
        $this->assertSame(['Another-click', self::DAY . ' 10:05:00'], [$this->getClicks()[0]['click_id'], $this->getClicks()[0]['server_time']]);
    }

    public function testTheFileHoldsTheConversionsOfVisitorsWhoCameFromAnAd()
    {
        $fromAd = $this->startVisit(1, 'gclid:Abc-123_xyz');
        $fromAd->setForceVisitDateTime(self::DAY . ' 10:02:00');
        Fixture::checkResponse($fromAd->doTrackGoal($this->idGoal, 12.5));
        $fromAd->setForceVisitDateTime(self::DAY . ' 10:03:00');
        Fixture::checkResponse($fromAd->doTrackEcommerceOrder('order-1', 199.999));

        $notFromAd = $this->startVisit(1);
        Fixture::checkResponse($notFromAd->doTrackGoal($this->idGoal, 5));

        $this->assertSame(
            "Parameters:TimeZone=+0000\n"
            . "\"Google Click ID\",\"Conversion Name\",\"Conversion Time\",\"Conversion Value\",\"Conversion Currency\"\n"
            . "Abc-123_xyz,\"Lead \"\"qualified\"\"\",\"" . self::DAY . " 10:02:00+0000\",12.5,USD\n"
            . "Abc-123_xyz,Purchase,\"" . self::DAY . " 10:03:00+0000\",200,USD\n",
            (new Export())->getCsv(1, 'google', 30)
        );
        $this->assertSame(
            "\"Microsoft Click ID\",\"Conversion Name\",\"Conversion Time\",\"Conversion Value\",\"Conversion Currency\"\n",
            (new Export())->getCsv(1, 'microsoft', 30)
        );
    }

    public function testAGoalThatIsNotChosenIsNotInTheFile()
    {
        $other = GoalsApi::getInstance()->addGoal(1, 'Other', 'manually', '', '');
        $tracker = $this->startVisit(1, 'gclid:Abc-123_xyz');
        Fixture::checkResponse($tracker->doTrackGoal($other));

        $this->assertSame(2, substr_count((new Export())->getCsv(1, 'google', 30), "\n"));
    }

    public function testAConversionCountsForTheLastClickBeforeItOfTheSameVisitor()
    {
        $tracker = $this->startVisit(1, 'gclid:First-click', '2024-02-20');
        $this->startVisit(1, 'gclid:Second-click', '2024-03-01', $tracker);
        $tracker->setForceVisitDateTime('2024-03-03 09:00:00');
        Fixture::checkResponse($tracker->doTrackGoal($this->idGoal));
        $this->startVisit(1, 'gclid:Later-click', '2024-03-04', $tracker);

        $file = (new Export())->getCsv(1, 'google', 30);

        $this->assertStringContainsString("Second-click,\"Lead \"\"qualified\"\"\",\"2024-03-03 09:00:00+0000\"", $file);
        $this->assertSame(3, substr_count($file, "\n"));
    }

    public function testAClickThatIsTooOldDoesNotCount()
    {
        $tracker = $this->startVisit(1, 'gclid:Old-click', '2023-11-01');
        $tracker->setForceVisitDateTime(self::DAY . ' 09:00:00');
        Fixture::checkResponse($tracker->doTrackGoal($this->idGoal));

        $this->assertSame(2, substr_count((new Export())->getCsv(1, 'google', 30), "\n"));
    }

    public function testTheFileHoldsOnlyTheDaysAskedFor()
    {
        $tracker = $this->startVisit(1, 'gclid:Abc-123_xyz', '2024-02-20');
        Fixture::checkResponse($tracker->doTrackGoal($this->idGoal));

        $this->assertSame(3, substr_count((new Export())->getCsv(1, 'google', 30), "\n"));
        $this->assertSame(2, substr_count((new Export())->getCsv(1, 'google', 7), "\n"));
    }

    public function testAnAdminOfTheWebsiteDownloadsTheFile()
    {
        FakeAccess::clearAccess(false, [1]);
        $_GET = ['idSite' => '1', 'platform' => 'google'];

        $this->assertStringStartsWith('Parameters:TimeZone=+0000', (new Controller())->export());
    }

    /**
     * @dataProvider getUsersWithoutAdminAccess
     */
    public function testAUserWhoIsNotAdminOfTheWebsiteGetsNoFileAndNoPage(array $access)
    {
        FakeAccess::clearAccess(...$access);
        $_GET = ['idSite' => '1', 'platform' => 'google'];

        foreach (['export', 'index', 'resetToken'] as $action) {
            try {
                (new Controller())->$action();
                $this->fail($action . ' was allowed.');
            } catch (NoAccessException $e) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function getUsersWithoutAdminAccess(): array
    {
        return [
            'write access' => [[false, [], [], null, [1]]],
            'admin of another website' => [[false, [2]]],
            'not logged in' => [[false]],
        ];
    }

    public function testTheFileIsFetchedWithTheSecretOfTheWebsiteAndNoLogin()
    {
        $token = Export::getToken(1);
        $otherToken = Export::getToken(2);
        FakeAccess::clearAccess(false);

        $_GET = ['idSite' => '1', 'platform' => 'google', 'token' => $token];
        $this->assertStringStartsWith('Parameters:TimeZone=+0000', (new Controller())->export());

        $_GET = ['idSite' => '1', 'platform' => 'google'];
        $_SERVER['PHP_AUTH_PW'] = $token;
        $this->assertStringStartsWith('Parameters:TimeZone=+0000', (new Controller())->export());

        foreach ([$otherToken, 'wrong', str_repeat('0', 32)] as $wrong) {
            $_SERVER['PHP_AUTH_PW'] = $wrong;
            $this->assertSame('', (new Controller())->export());
        }
    }

    public function testAWebsiteWithoutASecretIsNotOpenedByAnEmptyOne()
    {
        FakeAccess::clearAccess(false);
        $_GET = ['idSite' => '1', 'platform' => 'google', 'token' => ''];

        $this->expectException(NoAccessException::class);
        (new Controller())->export();
    }

    public function testAPlatformThatDoesNotExistGivesNoFile()
    {
        $_GET = ['idSite' => '1', 'platform' => 'other'];

        $this->assertSame('', (new Controller())->export());
    }

    public function testReplacingTheSecretClosesTheOldAddress()
    {
        $old = Export::getToken(1);
        $_GET = ['idSite' => '1'];
        $_POST = ['nonce' => Nonce::getNonce('RebelAdConversions.resetToken')];
        $_SERVER['REQUEST_METHOD'] = 'POST';

        try {
            (new Controller())->resetToken();
            $this->fail('There was no redirect.');
        } catch (Exception $e) {
            $this->assertStringContainsString('module=RebelAdConversions', $e->getMessage());
        }

        $this->assertFalse(Export::isToken(1, $old));
        $this->assertTrue(Export::isToken(1, Export::getToken(1)));
    }

    public function testTheSecretIsNotReplacedWithoutAPostAndANonce()
    {
        $old = Export::getToken(1);
        $_GET = ['idSite' => '1'];

        foreach (['GET' => ['nonce' => Nonce::getNonce('RebelAdConversions.resetToken')], 'POST' => ['nonce' => 'wrong']] as $method => $post) {
            $_SERVER['REQUEST_METHOD'] = $method;
            $_POST = $post;
            try {
                (new Controller())->resetToken();
                $this->fail('The secret was replaced.');
            } catch (Exception $e) {
                $this->assertTrue(Export::isToken(1, $old));
            }
        }
    }

    public function testThePageShowsWhatIsExportedAndTheAddresses()
    {
        $this->startVisit(1, 'gclid:Abc-123_xyz');
        $_GET = ['idSite' => '1'];

        $page = (new Controller())->index();

        $this->assertStringContainsString('<li>Lead &quot;qualified&quot;</li>', $page);
        $this->assertStringContainsString('platform=google&amp;token=' . Export::getToken(1), $page);
        $this->assertStringContainsString("_paq.push(['RebelAdConversions::setConsentGiven']);", $page);
        // No text of the page is left untranslated. The menu of Matomo carries the key of its item.
        $this->assertSame(0, preg_match('/RebelAdConversions_(?!AdConversions\b)[A-Z]/', $page));
    }

    /**
     * @dataProvider getInvalidConversions
     */
    public function testAConversionNeedsAGoalOfTheWebsiteAndAName(array $conversion)
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('needs a goal of the website');

        $this->exportConversions(1, [$conversion]);
    }

    public function getInvalidConversions(): array
    {
        return [
            'no name' => [['goal' => '0', 'name' => ' ']],
            'no goal' => [['goal' => '', 'name' => 'Lead']],
            'a goal that does not exist' => [['goal' => '99', 'name' => 'Lead']],
            'a name that is too long' => [['goal' => '0', 'name' => str_repeat('n', 101)]],
        ];
    }

    public function testDeletingAVisitDeletesItsClick()
    {
        $this->startVisit(1, 'gclid:Abc-123_xyz');
        $this->startVisit(1, 'gclid:Kept-click');
        $idVisit = $this->getClicks()[0]['idvisit'];

        StaticContainer::get(DataSubjects::class)->deleteDataSubjects([['idsite' => 1, 'idvisit' => $idVisit]]);

        $this->assertSame(['Kept-click'], array_column($this->getClicks(), 'click_id'));
    }

    public function testDeletingAWebsiteDeletesItsClicksAndItsSecret()
    {
        $this->startVisit(1, 'gclid:Abc-123_xyz');
        $token = Export::getToken(1);

        SitesManagerApi::getInstance()->deleteSite(1);

        $this->assertSame([], $this->getClicks());
        $this->assertFalse(Export::isToken(1, $token));
    }

    private function exportConversions(int $idSite, array $conversions): void
    {
        SitesManagerApi::getInstance()->updateSite($idSite, settingValues: [
            'RebelAdConversions' => [['name' => 'conversions', 'value' => $conversions]],
        ]);
    }

    /**
     * A page view of a new visitor, or of the given one, with the click it came from.
     */
    private function startVisit(int $idSite, string $click = '', string $day = self::DAY, ?\MatomoTracker $tracker = null): \MatomoTracker
    {
        if ($tracker === null) {
            $tracker = Fixture::getTracker($idSite, $day . ' 10:00:00', true, true);
            $tracker->setTokenAuth(Fixture::getTokenAuth());
            $tracker->setNewVisitorId();
            // Matomo takes requests from one address for one visit.
            $tracker->setIp('10.0.0.' . (1 + $this->visits++));
        }
        $tracker->setForceVisitDateTime($day . ' 10:00:00');
        $tracker->clearCustomTrackingParameters();
        if ($click !== '') {
            $tracker->setCustomTrackingParameter('rac', $click);
        }
        Fixture::checkResponse($tracker->doTrackPageView('Landing'));
        $tracker->clearCustomTrackingParameters();
        $tracker->setForceVisitDateTime($day . ' 10:01:00');

        return $tracker;
    }

    private function getClicks(): array
    {
        return Db::fetchAll('SELECT * FROM ' . Common::prefixTable(LogClick::TABLE) . ' ORDER BY idclick');
    }

    public function provideContainerConfig()
    {
        return ['Piwik\Access' => new FakeAccess()];
    }
}
