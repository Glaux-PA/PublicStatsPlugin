<?php

/**
 * @file PublicStatsSettingsForm.php
 *
 * Copyright (c) 2026 Universitat Rovira i Virgili
 * Copyright (c) 2026 Glaux Publicaciones Académicas, S.L.
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PublicStatsSettingsForm
 * @ingroup plugins_generic_publicStats
 *
 * @brief Settings modal form: OpenAlex contact email, primary colour
 *        and the per-subsection visibility toggles.
 */

declare(strict_types=1);

namespace APP\plugins\generic\publicStats;

use PKP\form\Form;
use PKP\form\validation\FormValidatorPost;
use PKP\form\validation\FormValidatorCSRF;
use APP\core\Application;
use APP\template\TemplateManager;
use APP\plugins\generic\publicStats\classes\ColorHelper;
use APP\plugins\generic\publicStats\classes\PublicStatsConstants;

class PublicStatsSettingsForm extends Form
{
    private PublicStatsPlugin $plugin;

    private const DEFAULT_PRIMARY_COLOR = ColorHelper::DEFAULT_COLOR;

    public function __construct(PublicStatsPlugin $plugin)
    {
        $this->plugin = $plugin;
        parent::__construct($plugin->getTemplateResource('settingsForm.tpl'));

        $this->addCheck(new FormValidatorPost($this));
        $this->addCheck(new FormValidatorCSRF($this));
    }

    /**
     * @copydoc Form::initData()
     */
    public function initData()
    {
        $contextId = Application::get()->getRequest()->getContext()->getId();

        $this->setData('openAlexEmail', $this->plugin->getSetting($contextId, 'openAlexEmail'));

        $primaryColor = $this->plugin->getSetting($contextId, 'primaryColor');
        $this->setData('primaryColor', $primaryColor ?: self::DEFAULT_PRIMARY_COLOR);

        $this->setData('enabledSubsections', $this->plugin->getEnabledSubsections($contextId));
    }

    /**
     * @copydoc Form::readInputData()
     */
    public function readInputData()
    {
        $this->readUserVars(['openAlexEmail', 'primaryColor', 'enabledSubsections']);
    }

    /**
     * @copydoc Form::execute()
     */
    public function execute(...$functionArgs)
    {
        $contextId = Application::get()->getRequest()->getContext()->getId();

        // Polite-pool requires a real address; invalid values stored as ''.
        $emailRaw = trim((string) $this->getData('openAlexEmail'));
        $email = ($emailRaw !== '' && filter_var($emailRaw, FILTER_VALIDATE_EMAIL)) ? $emailRaw : '';
        $this->plugin->updateSetting($contextId, 'openAlexEmail', $email);

        $primaryColor = $this->getData('primaryColor');
        if (empty($primaryColor) || !preg_match('/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/', $primaryColor)) {
            $primaryColor = self::DEFAULT_PRIMARY_COLOR;
        }
        $this->plugin->updateSetting($contextId, 'primaryColor', $primaryColor);

        // Whitelist enabled subsections; snapshot the known set so initData can detect new ones later.
        $allSubsections = array_merge(...array_values(array_map('array_keys', PublicStatsConstants::SUBSECTIONS)));
        $submitted = $this->getData('enabledSubsections');
        $enabledSubsections = is_array($submitted)
            ? array_values(array_intersect($submitted, $allSubsections))
            : [];
        $this->plugin->updateSetting($contextId, 'enabledSubsections', $enabledSubsections);
        $this->plugin->updateSetting($contextId, 'knownSubsections', $allSubsections);

        parent::execute(...$functionArgs);
        
        return true;
    }

    /**
     * @copydoc Form::fetch()
     */
    public function fetch($request, $template = null, $display = false)
    {
        $templateMgr = TemplateManager::getManager($request);
        
        $templateMgr->assign([
            'pluginName'       => $this->plugin->getName(),
            'defaultPrimaryColor' => self::DEFAULT_PRIMARY_COLOR,
            'openAlexEmail'    => $this->getData('openAlexEmail'),
            'primaryColor'     => $this->getData('primaryColor') ?: self::DEFAULT_PRIMARY_COLOR,
            'enabledSubsections' => $this->getData('enabledSubsections'),
            'sectionGroups'      => PublicStatsConstants::SECTION_GROUPS,
            'subsections'        => PublicStatsConstants::SUBSECTIONS,
        ]);

        return parent::fetch($request, $template, $display);
    }

    public static function getDefaultPrimaryColor(): string
    {
        return self::DEFAULT_PRIMARY_COLOR;
    }
}