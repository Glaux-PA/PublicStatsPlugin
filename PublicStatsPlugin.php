<?php

/**
 * @file PublicStatsPlugin.php
 *
 * Copyright (c) 2026 Glaux Publicaciones Académicas, S.L.
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PublicStatsPlugin
 * @ingroup plugins_generic_publicStats
 *
 * @brief Plugin entry point: registers hooks, navigation menu type and
 *        the settings modal action.
 */

namespace APP\plugins\generic\publicStats;

use PKP\plugins\GenericPlugin;
use PKP\plugins\Hook;
use PKP\core\JSONMessage;
use PKP\linkAction\LinkAction;
use PKP\linkAction\request\AjaxModal;
use APP\core\Application;
use APP\plugins\generic\publicStats\controllers\PublicStatisticsHandler;

class PublicStatsPlugin extends GenericPlugin
{
    /** @copydoc GenericPlugin::register() */
    public function register($category, $path, $mainContextId = null): bool
    {
        $success = parent::register($category, $path);

        if ($success && $this->getEnabled()) {
            Hook::add('NavigationMenus::itemTypes', [$this, 'addMenuItemType']);
            Hook::add('NavigationMenus::displaySettings', [$this, 'addMenuItemTypeSettings']);
            Hook::add('LoadHandler', [$this, 'loadHandler']);
        }

        return $success;
    }

    /**
     * Provide a name for this plugin
     */
    public function getDisplayName(): string
    {
        return __('plugins.generic.publicStats.displayName');
    }

    /**
     * Provide a description for this plugin
     */
    public function getDescription(): string
    {
        return __('plugins.generic.publicStats.description');
    }

    /**
     * @copydoc Plugin::getActions()
     */
    public function getActions($request, $actionArgs)
    {
        $router = $request->getRouter();
        return array_merge(
            $this->getEnabled() ? [
                new LinkAction(
                    'settings',
                    new AjaxModal(
                        $router->url(
                            $request,
                            null,
                            null,
                            'manage',
                            null,
                            [
                                'verb' => 'settings',
                                'plugin' => $this->getName(),
                                'category' => 'generic'
                            ]
                        ),
                        $this->getDisplayName()
                    ),
                    __('manager.plugins.settings'),
                    null
                ),
            ] : [],
            parent::getActions($request, $actionArgs)
        );
    }

    /**
     * @copydoc Plugin::manage()
     */
    public function manage($args, $request)
    {
        switch ($request->getUserVar('verb')) {
            case 'settings':
                $form = new PublicStatsSettingsForm($this);
                
                if ($request->getUserVar('save')) {
                    // Handle form submission
                    $form->readInputData();
                    if ($form->validate()) {
                        $form->execute();
                        return new JSONMessage(true);
                    }
                } else {
                    // Display form - must call initData first!
                    $form->initData();
                }
                
                return new JSONMessage(true, $form->fetch($request));
        }
        return parent::manage($args, $request);
    }

    /**
     * Add navigation menu item type
     */
    public function addMenuItemType($hookName, $args)
    {
        $types = &$args[0];
        $types['NMI_TYPE_STATISTICS'] = [
            'title' => __('manager.setup.statistics'),
            'description' => __('manager.navigationMenus.statistics.description'),
        ];
        return false;
    }

    /**
     * Add navigation menu item settings
     */
    public function addMenuItemTypeSettings($hookName, $args)
    {
        $navigationMenuItem = $args[0];
        
        if ($navigationMenuItem->getType() == 'NMI_TYPE_STATISTICS') {
            $request = Application::get()->getRequest();

            if ($navigationMenuItem->getIsDisplayed()) {
                $dispatcher = $request->getDispatcher();
                $contextPath = $request->getContext()->getPath();
                $url = $dispatcher->url(
                    $request,
                    \PKP\core\PKPApplication::ROUTE_PAGE,
                    $contextPath,
                    'publicStats',
                    'total',
                    null,
                    null
                );
                $navigationMenuItem->setUrl($url);
            }
            
            return true;
        }
        
        return false;
    }

    /**
     * Load handler for public stats pages
     */
    public function loadHandler($hookName, $args)
    {
        $page = $args[0];

        if ($this->getEnabled() && $page == 'publicStats') {
            define('HANDLER_CLASS', PublicStatisticsHandler::class);
            return true;
        }
        return false;
    }
}

// For backwards compatibility -- expect this to be removed approx. OJS/OMP/OPS 3.6
if (!PKP_STRICT_MODE) {
    class_alias('\APP\plugins\generic\publicStats\PublicStatsPlugin', '\PublicStatsPlugin');
}