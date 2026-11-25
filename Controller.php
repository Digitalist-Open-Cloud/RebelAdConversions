<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\RebelAdConversions;

use Exception;
use Piwik\Common;
use Piwik\Date;
use Piwik\Nonce;
use Piwik\Piwik;
use Piwik\Plugin\ControllerAdmin;
use Piwik\Plugins\RebelAdConversions\Dao\LogClick;
use Piwik\Plugins\SitesManager\API as SitesManagerApi;
use Piwik\Request;
use Piwik\SettingsPiwik;
use Piwik\Url;

class Controller extends ControllerAdmin
{
    private const NONCE = 'RebelAdConversions.resetToken';

    /**
     * The page of a website: what is exported, how many clicks are stored and where the files are.
     */
    public function index(): string
    {
        $idSite = (int) $this->idSite ?: (int) (SitesManagerApi::getInstance()->getSitesIdWithAdminAccess()[0] ?? 0);
        Piwik::checkUserHasAdminAccess($idSite);

        $clicks = (new LogClick())->countSince($idSite, Date::now()->subDay(Export::DEFAULT_DAYS)->getDatetime());
        $export = ['module' => 'RebelAdConversions', 'action' => 'export', 'idSite' => $idSite];
        $platforms = [];
        foreach (Platform::ALL as $id => $platform) {
            $platforms[] = [
                'name' => $platform['name'],
                'clicks' => $clicks[$id] ?? 0,
                'downloadUrl' => '?' . http_build_query($export + ['platform' => $id]),
                'scheduledUrl' => SettingsPiwik::getPiwikUrl() . 'index.php?' . http_build_query($export + ['platform' => $id, 'token' => Export::getToken($idSite)]),
            ];
        }

        return $this->renderTemplate('index', [
            'idSite' => $idSite,
            'sites' => SitesManagerApi::getInstance()->getSitesWithAdminAccess(),
            'conversionNames' => (new MeasurableSettings($idSite))->getConversionNames(),
            'platforms' => $platforms,
            'days' => Export::DEFAULT_DAYS,
            'maxDays' => Export::MAX_DAYS,
            'attributionDays' => LogClick::ATTRIBUTION_DAYS,
            'settingsUrl' => '?' . http_build_query(['module' => 'SitesManager', 'action' => 'index', 'idSite' => $idSite]),
            'resetUrl' => '?' . http_build_query(['module' => 'RebelAdConversions', 'action' => 'resetToken', 'idSite' => $idSite]),
            'nonce' => Nonce::getNonce(self::NONCE),
        ]);
    }

    /**
     * The file of a platform. An admin of the website downloads it; an ad platform fetches it with
     * the secret of the website, in the address or as the password of HTTP authentication.
     */
    public function export(): string
    {
        $request = Request::fromRequest();
        $idSite = $request->getIntegerParameter('idSite', 0);
        $platform = $request->getStringParameter('platform', '');
        $token = $request->getStringParameter('token', '') ?: (string) ($_SERVER['PHP_AUTH_PW'] ?? '');

        if ($token === '') {
            Piwik::checkUserHasAdminAccess($idSite);
        } elseif (!Export::isToken($idSite, $token)) {
            Common::sendResponseCode(403);

            return '';
        }

        if (!isset(Platform::ALL[$platform])) {
            Common::sendResponseCode(400);

            return '';
        }

        Common::sendHeader('Content-Type: text/csv; charset=utf-8');
        Common::sendHeader('Content-Disposition: attachment; filename="conversions-' . $platform . '.csv"');
        Common::sendHeader('Cache-Control: no-store');

        return (new Export())->getCsv($idSite, $platform, $request->getIntegerParameter('days', Export::DEFAULT_DAYS));
    }

    /**
     * Replaces the secret of a website, so that the addresses given out before stop working.
     */
    public function resetToken(): void
    {
        $idSite = Request::fromRequest()->getIntegerParameter('idSite', 0);
        Piwik::checkUserHasAdminAccess($idSite);
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            throw new Exception(Piwik::translate('General_ExceptionSecurityCheckFailed'));
        }
        Nonce::checkNonce(self::NONCE, Request::fromPost()->getStringParameter('nonce', ''));

        Export::resetToken($idSite);

        Url::redirectToUrl('?' . http_build_query(['module' => 'RebelAdConversions', 'action' => 'index', 'idSite' => $idSite]));
    }
}
