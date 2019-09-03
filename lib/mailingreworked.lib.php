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

	$head[$h][0] = DOL_URL_ROOT."/custom/mailingreworked/card.php?id=".$object->id;
	$head[$h][1] = '<span class="fa-stack fa-1g"><i class="fa fa-circle-o fa-stack-2x"></i><strong class="fa-stack-1x">1</strong></span> '.$langs->trans("Edition");
	$head[$h][2] = 'card';
	$h++;

	if (empty($conf->global->MAIN_USE_ADVANCED_PERMS) || (! empty($conf->global->MAIN_USE_ADVANCED_PERMS) && $user->rights->mailing->mailing_advance->recipient))
	{
		$head[$h][0] = DOL_URL_ROOT."/custom/mailingreworked/cibles.php?id=".$object->id;
		$head[$h][1] = '<span class="fa-stack fa-1g"><i class="fa fa-circle-o fa-stack-2x"></i><strong class="fa-stack-1x">2</strong></span> '.$langs->trans("MailRecipients");
		if ($object->nbemail > 0) $head[$h][1].= ' <span class="badge">'.$object->nbemail.'</span>';
		$head[$h][2] = 'targets';
		$h++;
	}

	if (! empty($conf->global->EMAILING_USE_ADVANCED_SELECTOR))
	{
		$head[$h][0] = DOL_URL_ROOT."/custom/mailingreworked/advtargetemailing.php?id=".$object->id;
		$head[$h][1] = '<span class="fa-stack fa-1g"><i class="fa fa-circle-o fa-stack-2x"></i><strong class="fa-stack-1x">2</strong></span> '.$langs->trans("MailAdvTargetRecipients");
		$head[$h][2] = 'advtargets';
		$h++;
	}

	$head[$h][0] = DOL_URL_ROOT."/custom/mailingreworked/card.php?id=".$object->id."&action=visu";
	$head[$h][1] = '<span class="fa-stack fa-1g"><i class="fa fa-circle-o fa-stack-2x"></i><strong class="fa-stack-1x">3</strong></span> '.$langs->trans("Visualization");
	$head[$h][2] = 'visu';
	$h++;

	$head[$h][0] = DOL_URL_ROOT."/custom/mailingreworked/stat.php?id=".$object->id;
	$head[$h][1] = '<span class="fa-stack fa-1g"><i class="fa fa-circle-o fa-stack-2x"></i><strong class="fa-stack-1x">4</strong></span> '.$langs->trans("MonitoringMail");
	$head[$h][2] = 'stat';
	$h++;


	//complete_head_from_modules($conf,$langs,$object,$head,$h,'emailing');

	//complete_head_from_modules($conf,$langs,$object,$head,$h,'emailing','remove');

	return $head;
}

/**
 * Prepare array with list of tabs (only visual)
 *
 * @param   Mailing	$object		Object related to tabs
 * @return  array				Array of tabs to show
 */
function fb_emailing_prepare_head_inactiv(FBMailing $object)
{
	global $user, $langs, $conf;

	$h = 0;
	$head = array();

	$head[$h][0] = 'javascript:void(0);';
	$head[$h][1] = '<div style="pointer-events: none;cursor: default ; color: rgba(0,0,0,.5) !important"><span class="fa-stack fa-1g"><i class="fa fa-circle-o fa-stack-2x"></i><strong class="fa-stack-1x">1</strong></span> '.$langs->trans("Edition").'</div>';
	$head[$h][2] = 'card';
	$h++;

	if (empty($conf->global->MAIN_USE_ADVANCED_PERMS) || (! empty($conf->global->MAIN_USE_ADVANCED_PERMS) && $user->rights->mailing->mailing_advance->recipient))
	{
		$head[$h][0] = 'javascript:void(0);';
		$head[$h][1] = '<div style="pointer-events: none;cursor: default ; color: rgba(0,0,0,.5) !important"><span class="fa-stack fa-1g"><i class="fa fa-circle-o fa-stack-2x"></i><strong class="fa-stack-1x">2</strong></span> '.$langs->trans("MailRecipients").'</div>';
		if ($object->nbemail > 0) $head[$h][1].= ' <span class="badge">'.$object->nbemail.'</span>';
		$head[$h][2] = 'targets';
		$h++;
	}

	if (! empty($conf->global->EMAILING_USE_ADVANCED_SELECTOR))
	{
		$head[$h][0] = 'javascript:void(0);';
		$head[$h][1] = '<div style="pointer-events: none;cursor: default ; color: rgba(0,0,0,.5) !important"><span class="fa-stack fa-1g"><i class="fa fa-circle-o fa-stack-2x"></i><strong class="fa-stack-1x">2</strong></span> '.$langs->trans("MailAdvTargetRecipients").'</div>';
		$head[$h][2] = 'advtargets';
		$h++;
	}

	$head[$h][0] = 'javascript:void(0);';
	$head[$h][1] = '<div style="pointer-events: none;cursor: default; color: rgba(0,0,0,.5) !important"><span class="fa-stack fa-1g"><i class="fa fa-circle-o fa-stack-2x"></i><strong class="fa-stack-1x">3</strong></span> '.$langs->trans("Visualization").'</div>';
	$head[$h][2] = 'visu';
	$h++;

	$head[$h][0] = 'javascript:void(0);';
	$head[$h][1] = '<div style="pointer-events: none;cursor: default; color: rgba(0,0,0,.5) !important"><span class="fa-stack fa-1g"><i class="fa fa-circle-o fa-stack-2x"></i><strong class="fa-stack-1x">4</strong></span> '.$langs->trans("MonitoringMail").'</div>';
	$head[$h][2] = 'stat';
	$h++;


	//complete_head_from_modules($conf,$langs,$object,$head,$h,'emailing');

	//complete_head_from_modules($conf,$langs,$object,$head,$h,'emailing','remove');

	return $head;
}


/**
 * Get and save an upload file (for example after submitting a new file a mail form). Database index of file is also updated if donotupdatesession is set.
 * All information used are in db, conf, langs, user and _FILES.
 * Note: This function can be used only into a HTML page context.
 *
 * @param	string	$upload_dir				Directory where to store uploaded file (note: used to forge $destpath = $upload_dir + filename)
 * @param	int		$allowoverwrite			1=Allow overwrite existing file
 * @param	int		$donotupdatesession		1=Do no edit _SESSION variable but update database index. 0=Update _SESSION and not database index. -1=Do not update SESSION neither db.
 * @param	string	$varfiles				_FILES var name
 * @param	string	$savingdocmask			Mask to use to define output filename. For example 'XXXXX-__YYYYMMDD__-__file__'
 * @param	string	$link					Link to add (to add a link instead of a file)
 * @param   string  $trackid                Track id (used to prefix name of session vars to avoid conflict)
 * @param	int		$generatethumbs			1=Generate also thumbs for uploaded image files
 * @return	int                             <=0 if KO, >0 if OK
 */
function dol_fb_add_file_process($upload_dir, $allowoverwrite=0, $donotupdatesession=0, $varfiles='addedfile', $savingdocmask='', $link=null, $trackid='', $generatethumbs=1)
{
	global $db,$user,$conf,$langs;

	$res = 0;

	if (! empty($_FILES[$varfiles])) // For view $_FILES[$varfiles]['error']
	{
		dol_syslog('dol_add_file_process upload_dir='.$upload_dir.' allowoverwrite='.$allowoverwrite.' donotupdatesession='.$donotupdatesession.' savingdocmask='.$savingdocmask, LOG_DEBUG);
		if (dol_mkdir($upload_dir) >= 0)
		{
			$TFile = $_FILES[$varfiles];
			if (!is_array($TFile['name']))
			{
				foreach ($TFile as $key => &$val)
				{
					$val = array($val);
				}
			}

			$nbfile = count($TFile['name']);
			$nbok = 0;
			for ($i = 0; $i < $nbfile; $i++)
			{
				// Define $destfull (path to file including filename) and $destfile (only filename)
				$destfull=$upload_dir . "/" . $TFile['name'][$i];
				$destfile=$TFile['name'][$i];

				if ($savingdocmask)
				{
					$destfull=$upload_dir . "/" . preg_replace('/__file__/',$TFile['name'][$i],$savingdocmask);
					$destfile=preg_replace('/__file__/',$TFile['name'][$i],$savingdocmask);
				}

				// dol_sanitizeFileName the file name and lowercase extension
				$info = pathinfo($destfull);
				$destfull = $info['dirname'].'/'.dol_sanitizeFileName($info['filename'].($info['extension']!='' ? ('.'.strtolower($info['extension'])) : ''));
				$info = pathinfo($destfile);

				$destfile = dol_sanitizeFileName($info['filename'].($info['extension']!='' ? ('.'.strtolower($info['extension'])) : ''));
				// We apply dol_string_nohtmltag also to clean file names (this remove duplicate spaces) because
				// this function is also applied when we make try to download file (by the GETPOST(filename, 'alphanohtml') call).
				$destfile = dol_string_nohtmltag($destfile);
				$destfull = dol_string_nohtmltag($destfull);

				$resupload = dol_move_uploaded_file($TFile['tmp_name'][$i], $destfull, $allowoverwrite, 0, $TFile['error'][$i], 0, $varfiles);

				if (is_numeric($resupload) && $resupload > 0)   // $resupload can be 'ErrorFileAlreadyExists'
				{
					global $maxwidthsmall, $maxheightsmall, $maxwidthmini, $maxheightmini;

					include_once DOL_DOCUMENT_ROOT.'/core/lib/images.lib.php';

					// Generate thumbs.
					if ($generatethumbs)
					{
						if (image_format_supported($destfull) == 1)
						{
							// Create thumbs
							// We can't use $object->addThumbs here because there is no $object known

							// Used on logon for example
							$imgThumbSmall = vignette($destfull, $maxwidthsmall, $maxheightsmall, '_small', 50, "thumbs");
							// Create mini thumbs for image (Ratio is near 16/9)
							// Used on menu or for setup page for example
							$imgThumbMini = vignette($destfull, $maxwidthmini, $maxheightmini, '_mini', 50, "thumbs");
						}
					}

					// Update session
					if (empty($donotupdatesession))
					{
						include_once DOL_DOCUMENT_ROOT.'/custom/mailingreworked/class/html.formmail.class.php';
						$formmail = new FormMail($db);
						$formmail->trackid = $trackid;
						$formmail->add_attached_files($destfull, $destfile, $TFile['type'][$i]);
					}

					// Update table of files
					if ($donotupdatesession == 1)
					{
						$result = addFileIntoDatabaseIndex($upload_dir, basename($destfile), $TFile['name'][$i], 'uploaded', 0);
						if ($result < 0)
						{
							setEventMessages('FailedToAddFileIntoDatabaseIndex', '', 'warnings');
						}
					}

					$nbok++;
				}
				else
				{
					$langs->load("errors");
					if ($resupload < 0)	// Unknown error
					{
						setEventMessages($langs->trans("ErrorFileNotUploaded"), null, 'errors');
					}
					else if (preg_match('/ErrorFileIsInfectedWithAVirus/',$resupload))	// Files infected by a virus
					{
						setEventMessages($langs->trans("ErrorFileIsInfectedWithAVirus"), null, 'errors');
					}
					else	// Known error
					{
						setEventMessages($langs->trans($resupload), null, 'errors');
					}
				}
			}
			if ($nbok > 0)
			{
				$res = 1;
				setEventMessages($langs->trans("FileTransferComplete"), null, 'mesgs');
			}
		}
	} elseif ($link) {
		require_once DOL_DOCUMENT_ROOT . '/core/class/link.class.php';
		$linkObject = new Link($db);
		$linkObject->entity = $conf->entity;
		$linkObject->url = $link;
		$linkObject->objecttype = GETPOST('objecttype', 'alpha');
		$linkObject->objectid = GETPOST('objectid', 'int');
		$linkObject->label = GETPOST('label', 'alpha');
		$res = $linkObject->create($user);
		$langs->load('link');
		if ($res > 0) {
			setEventMessages($langs->trans("LinkComplete"), null, 'mesgs');
		} else {
			setEventMessages($langs->trans("ErrorFileNotLinked"), null, 'errors');
		}
	}
	else
	{
		$langs->load("errors");
		setEventMessages($langs->trans("ErrorFieldRequired", $langs->transnoentities("File")), null, 'errors');
	}

	return $res;
}


