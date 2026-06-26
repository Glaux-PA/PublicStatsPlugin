<?php

/**
 * @file PublicStatsPlugin.php
 *
 * Copyright (c) 2026 Universitat Rovira i Virgili
 * Copyright (c) 2026 Glaux Publicaciones Académicas, S.L.
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PublicStatsPlugin
 * @ingroup plugins_generic_publicStats
 *
 * @brief Plugin entry point: registers hooks, navigation menu type and
 *        the settings modal action.
 */

declare(strict_types=1);

namespace APP\plugins\generic\publicStats;

use PKP\plugins\GenericPlugin;
use PKP\plugins\Hook;
use PKP\core\JSONMessage;
use PKP\linkAction\LinkAction;
use PKP\linkAction\request\AjaxModal;
use PKP\notification\Notification;
use APP\core\Application;
use APP\notification\NotificationManager;
use APP\plugins\generic\publicStats\classes\PublicStatsConstants;
use APP\plugins\generic\publicStats\controllers\PublicStatisticsHandler;

class PublicStatsPlugin extends GenericPlugin
{
    /** @copydoc GenericPlugin::register() */
    public function register($category, $path, $mainContextId = null): bool
    {
        $success = parent::register($category, $path, $mainContextId);

        if ($success && $this->getEnabled()) {
            Hook::add('NavigationMenus::itemTypes', [$this, 'addMenuItemType']);
            Hook::add('NavigationMenus::displaySettings', [$this, 'addMenuItemTypeSettings']);
            Hook::add('LoadHandler', [$this, 'loadHandler']);
        }

        return $success;
    }

    /** @copydoc Plugin::getDisplayName() */
    public function getDisplayName(): string
    {
        return __('plugins.generic.publicStats.displayName');
    }

    /** @copydoc Plugin::getDescription() */
    public function getDescription(): string
    {
        return __('plugins.generic.publicStats.description');
    }

    /**
     * @copydoc Plugin::getActions()
     */
    public function getActions($request, $actionArgs): array
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
    public function manage($args, $request): JSONMessage
    {
        switch ($request->getUserVar('verb')) {
            case 'settings':
                $form = new PublicStatsSettingsForm($this);
                
                if ($request->getUserVar('save')) {
                    $form->readInputData();
                    if ($form->validate()) {
                        $form->execute();
                        $notificationMgr = new NotificationManager();
                        $notificationMgr->createTrivialNotification(
                            $request->getUser()->getId(),
                            Notification::NOTIFICATION_TYPE_SUCCESS,
                            ['contents' => __('common.changesSaved')]
                        );
                        return new JSONMessage(true);
                    }
                } else {
                    $form->initData();
                }
                
                return new JSONMessage(true, $form->fetch($request));
        }
        return parent::manage($args, $request);
    }

    public function addMenuItemType(string $hookName, array $args): bool
    {
        $types = &$args[0];
        $types['NMI_TYPE_STATISTICS'] = [
            'title' => __('manager.setup.statistics'),
            'description' => __('manager.navigationMenus.statistics.description'),
        ];
        return false;
    }

    public function addMenuItemTypeSettings(string $hookName, array $args): bool
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
     * Enabled subsection IDs for a context. Auto-enables subsections added
     * after the last save using the knownSubsections snapshot.
     */
    public function getEnabledSubsections(int $contextId): array
    {
        $all = array_merge(...array_values(array_map('array_keys', PublicStatsConstants::SUBSECTIONS)));
        $saved = $this->getSetting($contextId, 'enabledSubsections');

        if (!is_array($saved)) {
            return $all;
        }

        $stillValid = array_values(array_intersect($saved, $all));
        $known = $this->getSetting($contextId, 'knownSubsections');
        if (!is_array($known)) {
            return $stillValid;
        }

        $newlyAdded = array_values(array_diff($all, $known));
        return array_values(array_merge($stillValid, $newlyAdded));
    }

    public function loadHandler(string $hookName, array $args): bool
    {
        $page = $args[0];
        $handler = &$args[3];

        if ($this->getEnabled() && $page == 'publicStats') {
            $handler = new PublicStatisticsHandler();
            return true;
        }
        return false;
    }
}

// For backwards compatibility -- expect this to be removed approx. OJS/OMP/OPS 3.6
if (!PKP_STRICT_MODE) {
    class_alias('\APP\plugins\generic\publicStats\PublicStatsPlugin', '\PublicStatsPlugin');
}