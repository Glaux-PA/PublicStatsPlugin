<?php
/**
 * @file PublicStatsPlugin.php
 *
 * Copyright (c) 2017-2023 Simon Fraser University
 * Copyright (c) 2017-2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PublicStatsPlugin
 * @brief Plugin class for the publicStats plugin.
 */

namespace APP\plugins\generic\publicStats;

use PKP\plugins\GenericPlugin;
use PKP\plugins\Hook;
use APP\core\Application;
use APP\plugins\generic\publicStats\controllers\PublicStatisticsHandler;



class PublicStatsPlugin extends GenericPlugin
{
    /** @copydoc GenericPlugin::register() */
    public function register($category, $path, $mainContextId = null): bool
    {
        $success = parent::register($category, $path);

        if ($success && $this->getEnabled()) {
            // Display the publication statement on the article details page
            Hook::add('NavigationMenus::itemTypes', [$this, 'addMenuItemType']);
            Hook::add('NavigationMenus::displaySettings', callback: [$this, 'addMenuItemTypeSettings']);
            Hook::add("LoadHandler",callback: [$this,"loadHandler"]);

        }

        return $success;
    }

    /**
     * Provide a name for this plugin
     *
     * The name will appear in the Plugin Gallery where editors can
     * install, enable and disable plugins.
     */
    public function getDisplayName(): string
    {
        return __('plugins.generic.publicStats.displayName');
    }

    /**
     * Provide a description for this plugin
     *
     * The description will appear in the Plugin Gallery where editors can
     * install, enable and disable plugins.
     */
    public function getDescription(): string
    {
        return __('plugins.generic.publicStats.description');
    }


    public function addMenuItemType($hookName, $args){

        $types=&$args[0];
        $types["NMI_TYPE_STATISTICS"]=[
            'title' => __('manager.setup.statistics'),
            'description' => __('manager.navigationMenus.statistics.description'),

        ];

        return false;
    }


    public function addMenuItemTypeSettings($hookName, $args){

        $navigationMenuItem = $args[0];
        $navigationMenu = $args[1];
        
        if ($navigationMenuItem->getType() == "NMI_TYPE_STATISTICS") {
            $request = Application::get()->getRequest();
    
            if ($navigationMenuItem->getIsDisplayed()) {
                $dispatcher = $request->getDispatcher();
                $contextPath=$request->getContext()->getPath();
                $url = $dispatcher->url(
                    $request,
                    \PKP\core\PKPApplication::ROUTE_PAGE,
                    $contextPath,
                    'publicStats',
                    "total",
                    null,
                    null

                );
                $navigationMenuItem->setUrl($url);
            }
            
            return true; 
        }
        
        return false; 
    }

   public function loadHandler($hookName, $args){

        $page=$args[0];
        $op = $args[1] ?? '';

        if($this->getEnabled() && $page=="publicStats"){

            define('HANDLER_CLASS', PublicStatisticsHandler::class);
            
            if ($op === 'countries') {
                define('HANDLER_OP', 'getCountryData');
            } 
            return true;

        }
        return false;
    }

    
    

}

// For backwards compatibility -- expect this to be removed approx. OJS/OMP/OPS 3.6
if (!PKP_STRICT_MODE) {
    class_alias('\APP\plugins\generic\publicStats\PublicStatsPlugin', '\PublicStatsPlugin');
}
