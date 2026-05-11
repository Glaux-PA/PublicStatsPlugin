<?php

/**
 * @file PublicStatsSettingsForm.php
 *
 * Copyright (c) 2026 Glaux Publicaciones Académicas, S.L.
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PublicStatsSettingsForm
 * @ingroup plugins_generic_publicStats
 *
 * @brief Settings modal form: OpenAlex contact email, primary colour
 *        and the per-subsection visibility toggles.
 */

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
    /** @var PublicStatsPlugin Plugin instance */
    private PublicStatsPlugin $plugin;

    // Default primary color lives in ColorHelper so settings form, handler,
    // and any other consumer share a single source of truth.
    private const DEFAULT_PRIMARY_COLOR = ColorHelper::DEFAULT_COLOR;

    /**
     * Constructor
     */
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

        $allSubsections = array_merge(...array_values(array_map('array_keys', PublicStatsConstants::SUBSECTIONS)));
        $saved = $this->plugin->getSetting($contextId, 'enabledSubsections');

        if (!is_array($saved)) {
            $enabled = $allSubsections;
        } else {
            $stillValid  = array_values(array_intersect($saved, $allSubsections));
            $known       = $this->plugin->getSetting($contextId, 'knownSubsections');
            $newlyAdded  = is_array($known) ? array_values(array_diff($allSubsections, $known)) : [];
            $enabled     = array_values(array_merge($stillValid, $newlyAdded));
        }
        $this->setData('enabledSubsections', $enabled);
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

        // Save OpenAlex email — validate format and discard garbage so the
        // polite-pool query param never carries a non-email string.
        $emailRaw = trim((string) $this->getData('openAlexEmail'));
        $email = ($emailRaw !== '' && filter_var($emailRaw, FILTER_VALIDATE_EMAIL)) ? $emailRaw : '';
        $this->plugin->updateSetting($contextId, 'openAlexEmail', $email);

        // Save primary color
        $primaryColor = $this->getData('primaryColor');
        if (empty($primaryColor) || !preg_match('/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/', $primaryColor)) {
            $primaryColor = self::DEFAULT_PRIMARY_COLOR;
        }
        $this->plugin->updateSetting($contextId, 'primaryColor', $primaryColor);

        // Save enabled subsections — whitelist against all known subsection ids.
        // Also snapshot the set of subsections known to the form, so the reader
        // can distinguish "user unchecked" from "added in code after last save".
        $allSubsections = array_merge(...array_values(array_map('array_keys', PublicStatsConstants::SUBSECTIONS)));
        $submitted = $this->getData('enabledSubsections');
        $enabledSubsections = is_array($submitted)
            ? array_values(array_intersect($submitted, $allSubsections))
            : $allSubsections;
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

    /**
     * Get the default primary color
     */
    public static function getDefaultPrimaryColor(): string
    {
        return self::DEFAULT_PRIMARY_COLOR;
    }
}