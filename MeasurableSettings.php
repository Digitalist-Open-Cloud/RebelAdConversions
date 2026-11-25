<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\RebelAdConversions;

use Exception;
use Piwik\API\Request;
use Piwik\Common;
use Piwik\Piwik;
use Piwik\Settings\FieldConfig;
use Piwik\Settings\FieldConfig\MultiPair;
use Piwik\Settings\Setting;
use Piwik\Tracker\GoalManager;

class MeasurableSettings extends \Piwik\Settings\Measurable\MeasurableSettings
{
    private const MAX_NAME = 100;

    /**
     * @var Setting
     */
    public $conversions;

    protected function init()
    {
        $this->conversions = $this->makeSetting('conversions', [], FieldConfig::TYPE_ARRAY, function (FieldConfig $field) {
            $field->title = Piwik::translate('RebelAdConversions_SettingConversions');
            $field->inlineHelp = Piwik::translate('RebelAdConversions_SettingConversionsHelp');
            $field->uiControl = FieldConfig::UI_CONTROL_MULTI_TUPLE;

            $goal = new MultiPair(Piwik::translate('General_Goal'), 'goal', FieldConfig::UI_CONTROL_SINGLE_SELECT);
            $goal->availableValues = $this->getGoalChoices();
            $name = new MultiPair(Piwik::translate('RebelAdConversions_SettingConversionName'), 'name', FieldConfig::UI_CONTROL_TEXT);
            $field->uiControlAttributes['field1'] = $goal->toArray();
            $field->uiControlAttributes['field2'] = $name->toArray();

            $field->validate = function ($value) {
                foreach (self::rows($value) as $row) {
                    if ($row['name'] === '' || mb_strlen($row['name']) > self::MAX_NAME || !array_key_exists($row['goal'], $this->getGoalChoices())) {
                        throw new Exception(Piwik::translate('RebelAdConversions_ErrorConversion', self::MAX_NAME));
                    }
                }
            };
            $field->transform = function ($value) {
                return self::rows($value);
            };
        });
    }

    /**
     * @return array<int, string> the name on the ad platforms of each goal that is exported, by the ID of the goal
     */
    public function getConversionNames(): array
    {
        $names = [];
        foreach (self::rows($this->conversions->getValue()) as $row) {
            $names[(int) $row['goal']] = Common::unsanitizeInputValue($row['name']);
        }

        return $names;
    }

    /**
     * The rows that are filled in.
     *
     * @param mixed $value
     * @return array<int, array{goal: string, name: string}>
     */
    private static function rows($value): array
    {
        $rows = [];
        foreach (is_array($value) ? $value : [] as $row) {
            $goal = is_array($row) && is_scalar($row['goal'] ?? null) ? trim((string) $row['goal']) : '';
            $name = is_array($row) && is_scalar($row['name'] ?? null) ? trim((string) $row['name']) : '';
            if ($goal !== '' || $name !== '') {
                $rows[] = ['goal' => $goal, 'name' => $name];
            }
        }

        return $rows;
    }

    /**
     * @return array<string, string>
     */
    private function getGoalChoices(): array
    {
        $choices = [(string) GoalManager::IDGOAL_ORDER => Piwik::translate('RebelAdConversions_EcommerceOrders')];
        if ($this->idSite > 0) {
            foreach (Request::processRequest('Goals.getGoals', ['idSite' => $this->idSite, 'filter_limit' => -1], []) as $goal) {
                $choices[(string) $goal['idgoal']] = Common::unsanitizeInputValue($goal['name']);
            }
        }

        return $choices;
    }
}
