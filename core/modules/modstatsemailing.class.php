<?php

/* Copyright (C) 2015 Inovea Conseil	<info@inovea-conseil.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * 		\defgroup   statsemailing     Module statsemailing
 *      \brief      Show the current activity

 *      \file       htdocs/includes/modules/modstatsemailing.class.php
 *      \ingroup    statsemailing
 *      \brief      Description and activation file for module statsemailing

 */
include_once(DOL_DOCUMENT_ROOT . "/core/modules/DolibarrModules.class.php");

/**
 * 		\class      modstatsemailing
 *      \brief      Description and activation class for module MyModule
 */
class modstatsemailing extends DolibarrModules {

    /**
     *   \brief      Constructor. Define names, constants, directories, boxes, permissions
     *   \param      DB      Database handler
     */
    function __construct($DB) {
        global $langs, $conf;

        $this->db = $DB;

        // Id for module (must be unique).
        // Use here a free id (See in Home -> System information -> Dolibarr for list of used modules id).
        $this->numero = 432405;
        // Key text used to identify module (for permissions, menus, etc...)
        $this->rights_class = 'statsemailing';

        // Family can be 'crm','financial','hr','projects','products','ecm','technic','other'
        // It is used to group modules in module setup page
        $this->family = "Inovea Conseil";
        $this->editor_name = 'Inovea Conseil';
        // Module label (no space allowed), used if translation string 'ModuleXXXName' not found (where XXX is value of numeric property 'numero' of module)
        $this->name = "statsemailing";
        // Module description, used if translation string 'ModuleXXXDesc' not found (where XXX is value of numeric property 'numero' of module)
        $this->description = "Module432405Desc";
        // Possible values for version are: 'development', 'experimental', 'dolibarr' or version
        $this->version = '1.4';
        // Key used in llx_const table to save module status enabled/disabled (where MYMODULE is value of property name of module in uppercase)
        $this->const_name = 'MAIN_MODULE_' . strtoupper($this->name);
        // Where to store the module in setup page (0=common,1=interface,2=others,3=very specific)
        $this->special = 0;
        // Name of image file used for this module.
        // If file is in theme/yourtheme/img directory under name object_pictovalue.png, use this->picto='pictovalue'
        // If file is in module/img directory under name object_pictovalue.png, use this->picto='pictovalue@module'
        $this->picto = 'statsemailing.png@statsemailing';

        // Defined if the directory /mymodule/includes/triggers/ contains triggers or not
        $this->triggers = 0;

        // Data directories to create when module is enabled.
        // Example: this->dirs = array("/mymodule/temp");
        $this->dirs = array();
        $r = 0;

        // Dependencies
        $this->depends = array();      // List of modules id that must be enabled if this module is enabled
        $this->requiredby = array();     // List of modules id to disable if this one is disabled
        $this->phpmin = array(5, 0);      // Minimum version of PHP required by module
        //$this->need_dolibarr_version = array(3,2);	// Minimum version of Dolibarr required by module
        $this->langfiles = array("statsemailing@statsemailing");

        // Constants
        $this->const = array();
        $country = explode(":", $conf->global->MAIN_INFO_SOCIETE_COUNTRY);
        if ($country[0] == $conf->entity && $country[2] == "France")
            $this->editor_url = "https://www.inovea-conseil.com (<a target='_blank' href='https://www.dolibiz.com/wp-content/uploads/attestation/attestation-" . $this->name . "-" . $this->version . ".pdf'>Attestation NF525</a>)";
        else
            $this->editor_url = 'https://www.inovea-conseil.com';
        
	$this->config_page_url = array();

        // Array to add new pages in new tabs
       $this->tabs = array('emailing:+statsemailing:Statsemailing:statsemailing@statsemailing:$user->rights->mailing->lire:/statsemailing/tabs/stat.php?id=__ID__');   

        // Dictionnaries
        $this->dictionnaries = array();

        // Boxes
        // Add here list of php file(s) stored in includes/boxes that contains class to show a box.
        $this->boxes = array();   // List of boxes
        //$this->boxes[0][1] = "box_statsemailing.php@statsemailing";

        // Permissions
        $this->rights = array();  // Permission array used by this module
        /*$r++;
        $this->rights[$r][0] = 100419893; // id de la permission
        $this->rights[$r][1] = 'Les primes'; // libelle de la permission
        $this->rights[$r][2] = 'r'; // type de la permission (deprecie a ce jour)
        $this->rights[$r][3] = 0; // La permission est-elle une permission par defaut
        $this->rights[$r][4] = 'seeall';

        $r++;
*/
        $r = 0;
        $this->menu =array();
        /*$this->menu[$r]=array(
        //	// Use 'fk_mainmenu=xxx' or 'fk_mainmenu=xxx,fk_leftmenu=yyy'
        	'fk_menu'=>'fk_mainmenu=tools',
        //	// This is a Left menu entry
        //	'type'=>'left',
        	'titre'=>'Commercial Bonus',
        	'mainmenu'=>'tools',
        	'leftmenu'=>'statsemailing',
        	'url'=>'/statsemailing/index.php',
        //	// Lang file to use (without .lang) by module.
        //	// File must be in langs/code_CODE/ directory.
        	'langs'=>'statsemailing@statsemailing',
        //	'position'=>100,
        //	// Define condition to show or hide menu entry.
        //	// Use '$conf->mymodule->enabled' if entry must be visible if module is enabled.
        //	// Use '$leftmenu==\'system\'' to show if leftmenu system is selected.
        	'enabled'=>'$conf->statsemailing->enabled',
        //	// Use 'perms'=>'$user->rights->mymodule->level1->level2'
        //	// if you want your menu with a permission rules
        //	'perms'=>'1',
        //	'target'=>'',
        //	// 0=Menu for internal users, 1=external users, 2=both
        //	'user'=>2
        );
        $r++;*/
    }

    /**
     * 		Function called when module is enabled.
     * 		The init function add constants, boxes, permissions and menus (defined in constructor) into Dolibarr database.
     * 		It also creates data directories.
     *      @return     int             1 if OK, 0 if KO
     */
    function init() {
        $sql = array();
        
        return $this->_init($sql);
    }

    /**
     * 		Function called when module is disabled.
     *      Remove from database constants, boxes and permissions from Dolibarr database.
     * 		Data directories are not deleted.
     *      @return     int             1 if OK, 0 if KO
     */
    function remove() {
        $sql = array();
       return $this->_remove($sql);
    }

    /**
     * 		\brief		Create tables, keys and data required by module
     * 					Files llx_table1.sql, llx_table1.key.sql llx_data.sql with create table, create keys
     * 					and create data commands must be stored in directory /mymodule/sql/
     * 					This function is called by this->init.
     * 		\return		int		<=0 if KO, >0 if OK
     */
    function load_tables() {
        //return $this->_load_tables('/statsemailing/sql/');
    }

}

?>
