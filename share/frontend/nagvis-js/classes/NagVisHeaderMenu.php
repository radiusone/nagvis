<?php
/*****************************************************************************
 *
 * NagVisHeaderMenu.php - Class for handling the header menu
 *
 * Copyright (c) 2004-2016 NagVis Project (Contact: info@nagvis.org)
 *
 * License:
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2 as
 * published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
 *
 *****************************************************************************/

/**
 * @author	Lars Michelsen <lm@larsmichelsen.com>
 */
class NagVisHeaderMenu
{
    /** @var GlobalMapCfg */
    private $OBJ;

    /** @var FrontendTemplateSystem */
    private $TMPL;

    /** @var Dwoo */
    private $TMPLSYS;

    /** @var string */
    private $templateName;

    /** @var string */
    private $pathHtmlBase;

    /** @var string */
    private $pathTemplateFile;

    /** @var array */
    private $aMacros = [];

    /** @var bool */
    private $bRotation = false;

    /**
     * @param string $templateName
     * @param GlobalMapCfg $OBJ
     * @throws NagVisException
     */
    public function __construct($templateName, $OBJ = null)
    {
        $this->OBJ = $OBJ;
        $this->templateName = $templateName;

        $this->pathHtmlBase = cfg('paths', 'htmlbase');
        $this->pathTemplateFile = path('sys', '', 'templates', $this->templateName . '.header.html');

        // Initialize template system
        $this->TMPL = New FrontendTemplateSystem();
        $this->TMPLSYS = $this->TMPL->getTmplSys();

        // Read the contents of the template file
        $this->checkTemplateReadable(1);
    }

    /**
     * Tells the header menu that the current view is rotating
     *
     * @return void
     * @author 	Lars Michelsen <lm@larsmichelsen.com>
     */
    public function setRotationEnabled()
    {
        $this->bRotation = true;
    }

    /**
     * Print the HTML code
     *
     * @return string HTML Code
     * @throws Dwoo_Exception
     * @throws NagVisException
     * @author    Lars Michelsen <lm@larsmichelsen.com>
     */
    public function __toString()
    {
        /**
         * @var CoreAuthHandler $AUTH
         * @var CoreAuthorisationHandler $AUTHORISATION
         * @var CoreUriHandler $UHANDLER
         */
        global $AUTH, $AUTHORISATION, $UHANDLER;

        // In case of some really bad errors, the header menu can not be rendered, because basic
        // objects like $UHANDLER, $AUTH and $AUTHORISATION have not been initialized. Catch this
        // case here and terminate rendering
        if (!isset($UHANDLER) || !isset($AUTH) || !isset($AUTHORISATION)) {
            return '';
        }

        // Get all macros
        $this->getMacros();

        // Build page based on the template file and the data array
        return $this->TMPLSYS->get($this->TMPL->getTmplFile($this->templateName, 'header'), $this->aMacros);
    }

    /**
     * Returns a list of available languages for the header menus macro list
     *
     * @return array
     * @throws NagVisException
     * @author    Lars Michelsen <lm@larsmichelsen.com>
     */
    private function getLangList()
    {
        /** @var GlobalCore $CORE */
        global $CORE;
        // Build language list
        $aLang = $CORE->getAvailableAndEnabledLanguages();
        $numLang = count($aLang);
        foreach ($aLang as $lang) {
            $aLangs[$lang] = [];
            $aLangs[$lang]['language'] = $lang;

            // Get translated language name
            switch ($lang) {
                case 'en_US':
                    $languageLocated = l('en_US');
                    break;
                case 'de_DE':
                    $languageLocated = l('de_DE');
                    break;
                case 'es_ES':
                    $languageLocated = l('es_ES');
                    break;
                case 'fr_FR':
                    $languageLocated = l('fr_FR');
                    break;
                case 'pt_BR':
                    $languageLocated = l('pt_BR');
                    break;
                case 'ru_RU':
                    $languageLocated = l('ru_RU');
                    break;
                default:
                    $languageLocated = l($lang);
                    break;
            }

            $aLangs[$lang]['langLanguageLocated'] = $languageLocated;
        }
        return $aLangs;
    }

    /**
     * Returns a list of maps for the header menus macro list
     *
     * @return array
     * @throws NagVisException
     * @throws Exception
     * @author    Lars Michelsen <lm@larsmichelsen.com>
     */
    private function getMapList()
    {
        /**
         * @var GlobalMainCfg $_MAINCFG
         * @var GlobalCore $CORE
         * @var CoreAuthorisationHandler $AUTHORISATION
         */
        global $_MAINCFG, $CORE, $AUTHORISATION;

        // Get all the maps global content and use only those which are needed
        $filename = cfg('paths', 'var') . 'maplist-full-global.cfg-' . CONST_VERSION . '-cache';

        $cfgFiles = $CORE->getAvailableMaps();
        $path = $CORE->getMainCfg()->getValue('paths', 'mapcfg');
        foreach ($cfgFiles as $name) {
            $cfgFiles[$name] = $path . $name . ".cfg";
        }

        $CACHE = new GlobalFileCache(
            $cfgFiles,
            cfg('paths', 'var') . 'maplist-full-global.cfg-' . count($cfgFiles) . '-' . CONST_VERSION . '-cache'
        );

        if (
            $CACHE->isCached() !== -1
            && $_MAINCFG->isCached() !== -1
            && $CACHE->isCached() >= $_MAINCFG->isCached()
        ) {
            // Read the whole list from the cache
            $list = $CACHE->getCache();
        } else {
            // Get all the maps global config sections and cache them
            $list = [];
            foreach ($CORE->getAvailableMaps() as $mapName) {
                $MAPCFG = new GlobalMapCfg($mapName);
                try {
                    $MAPCFG->readMapConfig(ONLY_GLOBAL);
                } catch (MapCfgInvalid $e) {
                    $map['configError'] = true;
                } catch (NagVisException $e) {
                    $map['configError'] = true;
                }

                // Only show maps which should be shown
                if ($MAPCFG->getValue(0, 'show_in_lists') != 1) {
                    continue;
                }

                $list[$mapName] = [
                    'mapName'   => $MAPCFG->getName(),
                    'mapAlias'  => $MAPCFG->getValue(0, 'alias'),
                    'childs'    => [],
                    'class'     => '',
                    'parent'    => $MAPCFG->getValue(0, 'parent_map'),
                ];
            }

            // Save the list as cache
            $CACHE->writeCache($list, 1);
        }

        $permEditAnyMap = false;
        $aMaps = [];
        $childMaps = [];

        // Perform user specific filtering on the cached data
        foreach ($list as $map) {
            // Remove unpermitted maps
            if (!$AUTHORISATION->isPermitted('Map', 'view', $map['mapName'])) {
                unset($list[$map['mapName']]);
                continue;
            }

            // Change permission to edit
            $map['permittedEdit'] = $AUTHORISATION->isPermitted('Map', 'edit', $map['mapName']);
            $permEditAnyMap |= $map['permittedEdit'];

            if ($map['parent'] === '') {
                $aMaps[$map['mapName']] = $map;
            } else {
                if (!isset($childMaps[$map['parent']])) {
                    $childMaps[$map['parent']] = [];
                }
                $childMaps[$map['parent']][$map['mapName']] = $map;
            }
        }

        // auto select current map and apply map specific options to the header menu
        if (
            $this->OBJ !== null && $this->aMacros['mod'] == 'Map'
            && isset($list[$this->OBJ->getName()])
        ) {
            $list[$this->OBJ->getName()]['selected'] = true;
        }

        return [$this->mapListToTree($aMaps, $childMaps), $permEditAnyMap];
    }

    /**
     * @param array $maps
     * @param array $childMaps
     * @return array
     */
    private function mapListToTree($maps, $childMaps)
    {
        foreach ($maps as $map) {
            $freeParent = $map['mapName'];
            if (isset($childMaps[$freeParent])) {
                $maps[$freeParent]['class'] = 'title';
                $maps[$freeParent]['childs'] = $this->mapListToTree($childMaps[$freeParent], $childMaps);
            }
        }
        usort($maps, ['GlobalCore', 'cmpMapAlias']);
        return $maps;
    }

    /**
     * Returns all macros for the header template
     *
     * @return void
     * @throws NagVisException
     * @author    Lars Michelsen <lm@larsmichelsen.com>
     */
    private function getMacros()
    {
        /**
         * @var GlobalCore $CORE
         * @var CoreAuthHandler $AUTH
         * @var CoreAuthorisationHandler $AUTHORISATION
         * @var CoreUriHandler $UHANDLER
         */
        global $CORE, $AUTH, $AUTHORISATION, $UHANDLER;
        // First get all static macros
        $this->aMacros = $this->getStaticMacros();

        // Save the page
        $this->aMacros['mod'] = $UHANDLER->get('mod');
        $this->aMacros['act'] = $UHANDLER->get('act');

        // In rotation?
        $this->aMacros['bRotation'] = $this->bRotation;

        $this->aMacros['permittedOverview'] = $AUTHORISATION->isPermitted('Overview', 'view', '*');

        // Check if the user is permitted to edit the current map
        $this->aMacros['permittedView']  = $this->OBJ !== null
            && $AUTHORISATION->isPermitted($this->aMacros['mod'], 'view', $this->OBJ->getName());
        $this->aMacros['permittedEdit']  = $this->OBJ !== null
            && $AUTHORISATION->isPermitted($this->aMacros['mod'], 'edit', $this->OBJ->getName());

        // Permissions for the option menu
        $this->aMacros['permittedSearch']            = $AUTHORISATION->isPermitted('Search', 'view', '*');
        $this->aMacros['permittedEditMainCfg']       = $AUTHORISATION->isPermitted('MainCfg', 'edit', '*');
        $this->aMacros['permittedManageShapes']      = $AUTHORISATION->isPermitted('ManageShapes', 'manage', '*');
        $this->aMacros['permittedManageBackgrounds'] = $AUTHORISATION->isPermitted('ManageBackgrounds', 'manage', '*');
        $this->aMacros['permittedManageBackgrounds'] = $AUTHORISATION->isPermitted('ManageBackgrounds', 'manage', '*');
        $this->aMacros['permittedManageMaps']        = $AUTHORISATION->isPermitted('Map', 'add', '*')
            && $AUTHORISATION->isPermitted('Map', 'edit', '*');

        $this->aMacros['currentUser'] = $AUTH->getUser();

        $this->aMacros['permittedChangePassword'] = $AUTHORISATION->isPermitted('ChangePassword', 'change', '*');

        $this->aMacros['permittedLogout'] = $AUTH->logoutSupported()
            & $AUTHORISATION->isPermitted('Auth', 'logout', '*');

        // Replace some special macros for maps
        if ($this->OBJ !== null && $this->aMacros['mod'] == 'Map') {
            $this->aMacros['currentMap']        = $this->OBJ->getName();
            $this->aMacros['currentMapAlias']   = $this->OBJ->getValue(0, 'alias');
            $this->aMacros['usesSources']       = count($this->OBJ->getValue(0, 'sources')) > 0;
            $this->aMacros['zoombar']           = $this->OBJ->getValue(0, 'zoombar');

            $this->aMacros['canAddObjects']  = !in_array('automap', $this->OBJ->getValue(0, 'sources'))
                && !in_array('geomap', $this->OBJ->getValue(0, 'sources'));
            $this->aMacros['canEditObjects'] = !in_array('automap', $this->OBJ->getValue(0, 'sources'));
            $this->aMacros['canMoveObjects'] = !in_array('automap', $this->OBJ->getValue(0, 'sources'))
                && !in_array('geomap', $this->OBJ->getValue(0, 'sources'));
            $this->aMacros['isWorldmap']     = in_array('worldmap', $this->OBJ->getValue(0, 'sources'));
        } else {
            $this->aMacros['currentMap']        = '';
            $this->aMacros['currentMapAlias']   = '';
            $this->aMacros['usesSources']       = false;
            $this->aMacros['zoombar']           = false;
            $this->aMacros['canAddObjects']     = false;
            $this->aMacros['canEditObjects']    = false;
            $this->aMacros['canMoveObjects']    = false;
            $this->aMacros['isWorldmap']        = true;
        }

        // Add permitted rotations
        $this->aMacros['rotations'] = [];
        foreach ($CORE->getDefinedRotationPools() as $poolName) {
            if ($AUTHORISATION->isPermitted('Rotation', 'view', $poolName)) {
                $this->aMacros['rotations'][] = $poolName;
            }
        }

        list($this->aMacros['maps'], $this->aMacros['permittedEditAnyMap']) = $this->getMapList();
        $this->aMacros['langs'] = $this->getLangList();

        // Specific information for special templates
        if ($this->templateName == 'on-demand-filter') {
            global $_BACKEND;
            $this->aMacros['hostgroups'] = $_BACKEND->getBackend($_GET['backend_id'])->getObjects('hostgroup', '', '');
            usort($this->aMacros['hostgroups'], [$this, 'sortHostgroups']);
            array_unshift($this->aMacros['hostgroups'], ['name1' => '', 'name2' => '']);

            $default = '';
            $USERCFG = new CoreUserCfg();
            $cfg = $USERCFG->doGet();
            if (isset($cfg['params-']['filter_group'])) {
                $default = $cfg['params-']['filter_group'];
            }

            $this->aMacros['filter_group'] = isset($_GET['filter_group'])
                ? htmlspecialchars($_GET['filter_group'])
                : $default;
        }

        $this->aMacros['mapNames'] = json_encode($CORE->getListMaps());
    }

    /**
     * @param array $a
     * @param array $b
     * @return int
     */
    private function sortHostgroups($a, $b)
    {
        return strnatcasecmp($a['name1'], $b['name1']);
    }

    /**
     * Checks if a documentation is available for the current language.
     * It either returns the language tag for the current language when a
     * documentation exists or en_US as fallback when no docs exist
     *
     * @return string
     * @throws NagVisException
     * @author    Lars Michelsen <lm@larsmichelsen.com>
     */
    private function getDocLanguage()
    {
        global $CORE;
        if (in_array(curLang(), $CORE->getAvailableDocs())) {
            return curLang();
        } else {
            return 'en_US';
        }
    }

    /**
     * Get all static macros for the template code
     *
     * @throws NagVisException
     * @author    Lars Michelsen <lm@larsmichelsen.com>
     */
    private function getStaticMacros()
    {
        /**
         * @var SessionHandler $SHANDLER
         * @var CoreAuthHandler $AUTH
         * @var CoreAuthorisationHandler $AUTHORISATION
         * @var CoreUriHandler $UHANDLER
         */
        global $SHANDLER, $AUTH, $AUTHORISATION, $UHANDLER;

        // Replace paths and language macros
        $aReturn = [
            'pathBase' => $this->pathHtmlBase,
            'currentUri'         => preg_replace(
                '/[&?]lang=[a-z]{2}_[A-Z]{2}/',
                '',
                $UHANDLER->getRequestUri()
            ),
            'pathImages'         => cfg('paths', 'htmlimages'),
            'showStates'         => cfg('defaults', 'header_show_states'),
            'pathHeaderJs'       => path(
                'html',
                'global',
                'templates',
                $this->templateName . '.header.js?v=' . CONST_VERSION
            ),
            'pathTemplates'      => path('html', 'global', 'templates'),
            'pathTemplateImages' => path('html', 'global', 'templateimages'),
            'langSearch'         => l('Search'),
            'langUserMgmt'       => l('Manage Users'),
            'langManageRoles'    => l('Manage Roles'),
            'currentLanguage'    => curLang(),
            'docLanguage'        => $this->getDocLanguage(),
            'langChooseLanguage' => l('Choose Language'),
            'langUser' => l('User menu'),
            'langActions' => l('Actions'),
            'langLoggedIn' => l('Logged in'),
            'langChangePassword' => l('Change password'),
            'langOpen' => l('Open'),
            'langMap' => l('Map'),
            'langRotations' => l('Rotations'),
            'langMapOptions' => l('Map Options'),
            'langMapManageTmpl' => l('Manage Templates'),
            'langMapAddIcon' => l('Add Icon'),
            'langMapAddLine' => l('Add Line'),
            'langLine' => l('Line'),
            'langMapAddSpecial' => l('Add Special'),
            'langHost' => l('host'),
            'langService' => l('service'),
            'langHostgroup' => l('hostgroup'),
            'langServicegroup' => l('servicegroup'),
            'langDynGroup' => l('Dynamic Group'),
            'langAggr' => l('Aggregation'),
            'langMapEdit' => l('Edit Map'),
            'langMaps' => l('Maps'),
            'langTextbox' => l('textbox'),
            'langContainer' => l('Container'),
            'langShape' => l('shape'),
            'langStateless' => l('Stateless'),
            'langSpecial' => l('special'),
            'langLockUnlockAll' => l('Lock/Unlock all'),
            'langViewMap' => l('View current map'),
            'langOptions' => l('Options'),
            'langEditMainCfg' => l('General Configuration'),
            'langMgmtBackends' => l('Manage Backends'),
            'langMgmtBackgrounds' => l('Manage Backgrounds'),
            'langMgmtMaps' => l('Manage Maps'),
            'langMgmtShapes' => l('Manage Shapes'),
            'langNeedHelp' => l('needHelp'),
            'langOnlineDoc' => l('onlineDoc'),
            'langForum' => l('forum'),
            'langSupportInfo' => l('supportInfo'),
            'langOverview' => l('overview'),
            'langInstance' => l('instance'),
            'langLogout' => l('Logout'),
            'langRotationStart' => l('rotationStart'),
            'langRotationStop' => l('rotationStop'),
            'langToggleGrid' => l('Show/Hide Grid'),
            'langToStaticMap' => l('Export to static map'),
            'langModifyParams' => l('Modify view'),
            'langMapViewport'      => l('Viewport'),
            'langSaveView'         => l('Save view'),
            'langSaveViewAsNewMap' => l('Save as new map'),
            'langScaleToAll'       => l('Show all objects'),
            // Supported by backend and not using trusted auth
            'supportedChangePassword' => $AUTH->checkFeature('changePassword') && !$AUTH->authedTrusted(),
            'permittedUserMgmt' => $AUTHORISATION->isPermitted('UserMgmt', 'manage'),
            'permittedRoleMgmt' => $AUTHORISATION->isPermitted('RoleMgmt', 'manage'),
            'rolesConfigurable' => $AUTHORISATION->rolesConfigurable()
        ];

        return $aReturn;
    }

    /**
     * Checks for readable header template
     *
     * @param bool $printErr
     * @return bool Is Check Successful?
     * @throws NagVisException
     * @author    Lars Michelsen <lm@larsmichelsen.com>
     */
    private function checkTemplateReadable($printErr)
    {
        /** @var GlobalCore $CORE */
        global $CORE;
        return $CORE->checkReadable($this->pathTemplateFile, $printErr);
    }
}
