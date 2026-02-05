<?php

/**
 * @file PublicStatsSettingsForm.php
 *
 * Copyright (c) 2017-2023 Simon Fraser University
 * Copyright (c) 2017-2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PublicStatsSettingsForm
 * @brief Form for managing PublicStats plugin settings.
 */

namespace APP\plugins\generic\publicStats;

use PKP\form\Form;
use PKP\form\validation\FormValidatorPost;
use PKP\form\validation\FormValidatorCSRF;
use APP\template\TemplateManager;

class PublicStatsSettingsForm extends Form
{
    /** @var PublicStatsPlugin Plugin instance */
    private PublicStatsPlugin $plugin;

    /** @var string Default primary color */
    private const DEFAULT_PRIMARY_COLOR = '#8b2635';

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
        $contextId = \Application::get()->getRequest()->getContext()->getId();

        $this->setData('openAlexEmail', $this->plugin->getSetting($contextId, 'openAlexEmail'));
        
        $primaryColor = $this->plugin->getSetting($contextId, 'primaryColor');
        $this->setData('primaryColor', $primaryColor ?: self::DEFAULT_PRIMARY_COLOR);
    }

    /**
     * @copydoc Form::readInputData()
     */
    public function readInputData()
    {
        $this->readUserVars(['openAlexEmail', 'primaryColor']);
    }

    /**
     * @copydoc Form::execute()
     */
    public function execute(...$functionArgs)
    {
        $contextId = \Application::get()->getRequest()->getContext()->getId();

        // Save OpenAlex email
        $this->plugin->updateSetting($contextId, 'openAlexEmail', trim($this->getData('openAlexEmail')));

        // Save primary color
        $primaryColor = $this->getData('primaryColor');
        if (empty($primaryColor) || !preg_match('/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/', $primaryColor)) {
            $primaryColor = self::DEFAULT_PRIMARY_COLOR;
        }
        $this->plugin->updateSetting($contextId, 'primaryColor', $primaryColor);

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
            'pluginName' => $this->plugin->getName(),
            'defaultPrimaryColor' => self::DEFAULT_PRIMARY_COLOR,
            'openAlexEmail' => $this->getData('openAlexEmail'),
            'primaryColor' => $this->getData('primaryColor') ?: self::DEFAULT_PRIMARY_COLOR,
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