<?php
/* Copyright (C) 2019 Flavien Belli
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * \file    mailingreworked/lib/mailingreworked.lib.php
 * \ingroup mailingreworked
 * \brief   Library files with common functions for Mailingreworked
 */

/**
 * Prepare admin pages header
 *
 * @return array
 */
function mailingreworkedAdminPrepareHead()
{
	global $langs, $conf;

	$langs->load("mailingreworked@mailingreworked");

	$h = 0;
	$head = array();

	$head[$h][0] = dol_buildpath("/mailingreworked/admin/setup.php", 1);
	$head[$h][1] = $langs->trans("Settings");
	$head[$h][2] = 'settings';
	$h++;
	$head[$h][0] = dol_buildpath("/mailingreworked/admin/about.php", 1);
	$head[$h][1] = $langs->trans("About");
	$head[$h][2] = 'about';
	$h++;

	// Show more tabs from modules
	// Entries must be declared in modules descriptor with line
	//$this->tabs = array(
	//	'entity:+tabname:Title:@mailingreworked:/mailingreworked/mypage.php?id=__ID__'
	//); // to add new tab
	//$this->tabs = array(
	//	'entity:-tabname:Title:@mailingreworked:/mailingreworked/mypage.php?id=__ID__'
	//); // to remove a tab
	complete_head_from_modules($conf, $langs, $object, $head, $h, 'mailingreworked');

	return $head;
}

/**
 * Prepare array with list of tabs
 *
 * @param   Mailing	$object		Object related to tabs
 * @return  array				Array of tabs to show
 */
function fb_emailing_prepare_head(FBMailing $object)
{
    global $user, $langs, $conf;

    $h = 0;
    $head = array();

    if (empty($conf->global->MAIN_USE_ADVANCED_PERMS) || (! empty($conf->global->MAIN_USE_ADVANCED_PERMS) && $user->rights->mailing->mailing_advance->recipient))
    {
        $head[$h][0] = DOL_URL_ROOT."/custom/mailingreworked/cibles.php?id=".$object->id;
        $head[$h][1] = $langs->trans("MailRecipients");
        if ($object->nbemail > 0) $head[$h][1].= ' <span class="badge">'.$object->nbemail.'</span>';
        $head[$h][2] = 'targets';
        $h++;
    }

    if (! empty($conf->global->EMAILING_USE_ADVANCED_SELECTOR))
    {
        $head[$h][0] = DOL_URL_ROOT."/custom/mailingreworked/advtargetemailing.php?id=".$object->id;
        $head[$h][1] = $langs->trans("MailAdvTargetRecipients");
        $head[$h][2] = 'advtargets';
        $h++;
    }

    $head[$h][0] = DOL_URL_ROOT."/custom/mailingreworked/card.php?id=".$object->id."&action=visu";
    $head[$h][1] = $langs->trans("Visualization");
    $head[$h][2] = 'visu';
    $h++;

	$head[$h][0] = DOL_URL_ROOT."/custom/mailingreworked/card.php?id=".$object->id;
	$head[$h][1] = $langs->trans("Edit");
	$head[$h][2] = 'card';
	$h++;


	complete_head_from_modules($conf,$langs,$object,$head,$h,'emailing');

    complete_head_from_modules($conf,$langs,$object,$head,$h,'emailing','remove');

    return $head;
}
