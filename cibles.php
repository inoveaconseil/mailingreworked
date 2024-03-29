<?php
/* Copyright (C) 2004      Rodolphe Quiedeville <rodolphe@quiedeville.org>
 * Copyright (C) 2005-2016 Laurent Destailleur  <eldy@uers.sourceforge.net>
 * Copyright (C) 2005-2010 Regis Houssin        <regis.houssin@inodbox.com>
 * Copyright (C) 2014	   Florian Henry        <florian.henry@open-concept.pro>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
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
 *       \file       htdocs/comm/mailing/cibles.php
 *       \ingroup    mailing
 *       \brief      Page to define emailing targets
 */

require '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/custom/mailingreworked/core/modules/modules_mailings.php';
require_once DOL_DOCUMENT_ROOT.'/custom/mailingreworked/class/mailing.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/mailingreworked/class/html.formmailing.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/mailingreworked/lib/mailingreworked.lib.php';
require_once DOL_DOCUMENT_ROOT.'/custom/mailingreworked/class/CMailFile.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';

// Load translation files required by the page
$langs->loadLangs(array("errors","admin","mails","languages","mailingreworked@mailingreworked","partnersdata@partnersdata"));

// Security check
if (! $user->rights->mailing->lire || $user->societe_id > 0) accessforbidden();


// Load variable for pagination
$limit = GETPOST('limit','int')?GETPOST('limit','int'):$conf->liste_limit;
$sortfield = GETPOST('sortfield','alpha');
$sortorder = GETPOST('sortorder','alpha');
$page = GETPOST('page','int');
if (empty($page) || $page == -1) { $page = 0; }     // If $page is not defined, or '' or -1
$offset = $limit * $page;
$pageprev = $page - 1;
$pagenext = $page + 1;
if (! $sortfield) $sortfield="email";
if (! $sortorder) $sortorder="ASC";

$id=GETPOST('id','int');
$rowid=GETPOST('rowid','int');
$action=GETPOST('action','aZ09');
$search_lastname=GETPOST("search_lastname");
$search_firstname=GETPOST("search_firstname");
$search_email=GETPOST("search_email");
$search_other=GETPOST("search_other");
$search_dest_status=GETPOST('search_dest_status');

// Search modules dirs
$modulesdir = dolGetModulesDirs('/mailings');

$object = new FBMailing($db);


/*
 * Actions
 */

if ($action == 'add')
{
	$module=GETPOST("module");
	$result=-1;

	foreach ($modulesdir as $dir)
	{
	    // Load modules attributes in arrays (name, numero, orders) from dir directory
	    //print $dir."\n<br>";
	    dol_syslog("Scan directory ".$dir." for modules");

	    // Loading Class
	    $file = $dir."/".$module.".modules.php";
	    $classname = "mailing_".$module;

		if (file_exists($file))
		{
			require_once $file;

			// We fill $filtersarray. Using this variable is now deprecated. Kept for backward compatibility.
			$filtersarray=array();
			if (isset($_POST["filter"])) $filtersarray[0]=$_POST["filter"];

			// Add targets into database
			$obj = new $classname($db);
			dol_syslog("Call add_to_target on class ".$classname);
			$result=$obj->add_to_target($id,$filtersarray);
		}
	}
	if ($result > 0)
	{
		setEventMessages($langs->trans("XTargetsAdded",$result), null, 'mesgs');

		header("Location: ".$_SERVER['PHP_SELF']."?id=".$id);
		exit;
	}
	if ($result == 0)
	{
		setEventMessages($langs->trans("WarningNoEMailsAdded"), null, 'warnings');
	}
	if ($result < 0)
	{
		setEventMessages($langs->trans("Error").($obj->error?' '.$obj->error:''), null, 'errors');
	}
}

if (GETPOST('clearlist'))
{
	// Loading Class
	$obj = new FBMailingTargets($db);
	$obj->clear_target($id);
	/* Avoid this to allow reposition
	header("Location: ".$_SERVER['PHP_SELF']."?id=".$id);
	exit;
	*/
}

if ($action == 'delete')
{
	// Ici, rowid indique le destinataire et id le mailing
	$sql="DELETE FROM ".MAIN_DB_PREFIX."mailing_cibles WHERE rowid=".$rowid;
	$resql=$db->query($sql);
	if ($resql)
	{
		if (!empty($id))
		{
			$obj = new FBMailingTargets($db);
			$obj->update_nb($id);

			header("Location: ".$_SERVER['PHP_SELF']."?id=".$id);
			exit;
		}
		else
		{
			header("Location: list.php");
			exit;
		}
	}
	else
	{
		dol_print_error($db);
	}
}

// Purge search criteria
if (GETPOST('button_removefilter_x','alpha') || GETPOST('button_removefilter.x','alpha') ||GETPOST('button_removefilter','alpha')) // All tests are required to be compatible with all browsers
{
	$search_lastname='';
	$search_firstname='';
	$search_email='';
	$search_other='';
	$search_dest_status='';
}



/*
 * View
 */

llxHeader('',$langs->trans("Mailing"),'EN:Module_EMailing|FR:Module_Mailing|ES:M&oacute;dulo_Mailing');

$form = new Form($db);
$formmailing = new FormMailing($db);
/*
print "<script type='application/javascript'>

$(document).ready(function () {
    $(\"#destinataire\").click(function () {
    		
    		$('.destinataire').toggle('fast');
    		group = $(this).children(\"i\").attr('id');
    		$('#'+group).toggleClass('fa-chevron-down');
    		$('#'+group).toggleClass('fa-chevron-right');
    		$('#destinataire').toggleClass('roleSelected');
    		$('#destinataire').toggleClass('role');
    
    	});
    		
    		
    		$(\"#filter\").click(function () {
    		
    		$('.filter') . toggle('slow');
    		group = $(this) . children(\"i\").attr('id');
    		$('#' + group) . toggleClass('fa-chevron-down');
    		$('#' + group) . toggleClass('fa-chevron-right');
    		$('#filter') . toggleClass('roleSelected');
    		$('#filter') . toggleClass('role');
    
    	});
    });
       

	  </script>";

*/

if ($object->fetch($id) >= 0)
{
	$head = fb_emailing_prepare_head($object);

	dol_fiche_head($head, 'targets', $langs->trans("Mailing"), -1, 'email');

	$linkback = '<a href="'.DOL_URL_ROOT.'/custom/mailingreworked/list.php">'.$langs->trans("BackToList").'</a>';

	$morehtmlright='';
	$nbtry = $nbok = 0;
	if ($object->statut == 2 || $object->statut == 3)
	{
		$nbtry = $object->countNbOfTargets('alreadysent');
		$nbko  = $object->countNbOfTargets('alreadysentko');

		$morehtmlright.=' ('.$nbtry.'/'.$object->nbemail;
		if ($nbko) $morehtmlright.=' - '.$nbko.' '.$langs->trans("Error");
		$morehtmlright.=') &nbsp; ';
	}

	dol_banner_tab($object, 'id', $linkback, 1, 'rowid', 'ref', '', '', 0, '', $morehtmlright);
	print '<div class="refid" style="margin-left: 8%;margin-bottom: 2%;margin-top: -3%;">'.$object->sujet.'</div>';
	print '<a class="buttonStatic" style="color:#FFFFFF; margin-bottom: 1%; margin-top: -2%" href="'.DOL_URL_ROOT.'/custom/mailingreworked/card.php?id='.$object->id.'&action=visu">'.$langs->trans("VisualizationMail").'</a>';
	print '<div class="fichecenter">';
	print '<div class="underbanner clearboth"></div>';

	print '<table class="border" width="100%">';

	print '<tr><td class="titlefield">'.$langs->trans("MailTitle").'</td><td colspan="3">'.$object->titre.'</td></tr>';

	print '<tr><td>'.$langs->trans("MailFrom").'</td><td colspan="3">'.dol_print_email($object->email_from,0,0,0,0,1).'</td></tr>';

	// Errors to
	print '<tr><td>'.$langs->trans("MailErrorsTo").'</td><td colspan="3">'.dol_print_email($object->email_errorsto,0,0,0,0,1);
	print '</td></tr>';

	// Nb of distinct emails
	print '<tr><td>';
	print $langs->trans("TotalNbOfDistinctRecipients");
	print '</td><td colspan="3">';
	$nbemail = ($object->nbemail?$object->nbemail:0);
	if (is_numeric($nbemail))
	{
		$text='';
		if ((! empty($conf->global->MAILING_LIMIT_SENDBYWEB) && $conf->global->MAILING_LIMIT_SENDBYWEB < $nbemail) && ($object->statut == 1 || ($object->statut == 2 && $nbtry < $nbemail)))
		{
			if ($conf->global->MAILING_LIMIT_SENDBYWEB > 0)
			{
				$text.=$langs->trans('LimitSendingEmailing',$conf->global->MAILING_LIMIT_SENDBYWEB);
			}
			else
			{
				$text.=$langs->trans('SendingFromWebInterfaceIsNotAllowed');
			}
		}
		if (empty($nbemail)) $nbemail.=' '.img_warning('').' <font class="warning">'.$langs->trans("NoTargetYet").'</font>';
		if ($text)
		{
			print $form->textwithpicto($nbemail,$text,1,'warning');
		}
		else
		{
			print $nbemail;
		}
	}
	print '</td></tr>';

	print '</table>';

	print "</div>";

	dol_fiche_end();

	print '<br>';


	$allowaddtarget=($object->statut == 0);

	// Show email selectors
	if ($allowaddtarget && $user->rights->mailing->creer)
	{
		//print load_fiche_titre($langs->trans("ToAddRecipientsChooseHere"), ($user->admin?info_admin($langs->trans("YouCanAddYourOwnPredefindedListHere"),1):''), 'title_generic');

		/*print '<table style="width: 100% "><tr><td colspan="2" class="role clickgroup" id="filter">';
		print '<span class="roleTitle ">' . $langs->trans('Filter') . '</span>';
		print '<i class="fa fa-chevron-right pictoFa" style="font-size: 0.86em" id="filtergroup"></i>';
		print '</td></tr></table><br/>';

		print '<div class="filter" style="display: none">';*/
		//print '<table class="noborder" width="100%">';
		print '<div class="tagtable centpercent liste_titre_bydiv borderbottom" id="tablelines">';

		//print '<tr class="liste_titre">';
		print '<div class="tagtr liste_titre">';
		//print '<td class="liste_titre">'.$langs->trans("RecipientSelectionModules").'</td>';
		print '<div class="tagtd">'.$langs->trans("RecipientSelectionModules").'</div>';
		//print '<td class="liste_titre" align="center">'.$langs->trans("NbOfUniqueEMails").'</td>';
		print '<div class="tagtd" align="center">'.$langs->trans("NbOfUniqueEMails").'</div>';
		//print '<td class="liste_titre" align="left">'.$langs->trans("Filter").'</td>';
		print '<div class="tagtd" align="left">'.$langs->trans("Filter").'</div>';
		//print '<td class="liste_titre" align="center">&nbsp;</td>';
		print '<div class="tagtd">&nbsp;</div>';
		//print "</tr>\n";
		print '</div>';

		clearstatcache();

		foreach ($modulesdir as $dir)
		{
		    $modulenames=array();

		    // Load modules attributes in arrays (name, numero, orders) from dir directory
		    //print $dir."\n<br>";
		    dol_syslog("Scan directory ".$dir." for modules");
		    $handle=@opendir($dir);
			if (is_resource($handle))
			{
				while (($file = readdir($handle))!==false)
				{
					if (substr($file, 0, 1) <> '.' && substr($file, 0, 3) <> 'CVS')
					{
						if (preg_match("/(.*)\.modules\.php$/i",$file,$reg))
						{
							if ($reg[1] == 'example') continue;
							$modulenames[]=$reg[1];
						}
					}
				}
				closedir($handle);
			}

			// Sort $modulenames
			sort($modulenames);

			$var = true;

			// Loop on each submodule
			foreach($modulenames as $modulename)
			{
				// Loading Class
				$file = $dir.$modulename.".modules.php";
				$classname = "mailing_".$modulename;
				require_once $file;

				$obj = new $classname($db);

				// Check dependencies
				$qualified=(isset($obj->enabled)?$obj->enabled:1);
				foreach ($obj->require_module as $key)
				{
					if (! $conf->$key->enabled || (! $user->admin && $obj->require_admin))
					{
						$qualified=0;
						//print "Les prerequis d'activation du module mailing ne sont pas respectes. Il ne sera pas actif";
						break;
					}
				}

				// Si le module mailing est qualifie
				if ($qualified)
				{
					$var = ! $var;

					if ($allowaddtarget)
					{
						print '<form '.$bctag[$var].' name="'.$modulename.'" action="'.$_SERVER['PHP_SELF'].'?action=add&id='.$object->id.'&module='.$modulename.'" method="POST" enctype="multipart/form-data">';
						print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
					}
					else
					{
					    print '<div '.$bctag[$var].'>';
					}

					print '<div class="tagtd">';
					if (empty($obj->picto)) $obj->picto='generic';
					print img_object($langs->trans("EmailingTargetSelector").': '.get_class($obj),$obj->picto);
					print ' ';
					print $obj->getDesc();
					print '</div>';

					try {
						$nbofrecipient=$obj->getNbOfRecipients('');
					}
					catch(Exception $e)
					{
						dol_syslog($e->getMessage(), LOG_ERR);
					}

					print '<div class="tagtd center">';
					if ($nbofrecipient >= 0)
					{
						print $nbofrecipient;
					}
					else
					{
						print $langs->trans("Error").' '.img_error($obj->error);
					}
					print '</div>';

					print '<div class="tagtd" align="left">';
					if ($allowaddtarget)
					{
    					try {
    						$filter=$obj->formFilter();
    					}
    					catch(Exception $e)
    					{
    						dol_syslog($e->getMessage(), LOG_ERR);
    					}
    					if ($filter) print $filter;
    					else print $langs->trans("None");
					}
					print '</div>';

					print '<div class="tagtd" align="right">';
					if ($allowaddtarget)
					{
						print '<input type="submit" class="button" name="button_'.$modulename.'" value="'.$langs->trans("Add").'">';
					}
					else
					{
					    print '<input type="submit" class="button disabled" disabled="disabled" name="button_'.$modulename.'" value="'.$langs->trans("Add").'">';
						//print $langs->trans("MailNoChangePossible");
						print "&nbsp;";
					}
					print '</div>';

					if ($allowaddtarget) print '</form>';
					else print '</div>';
				}
			}
		}	// End foreach dir

		print '</div></div>';

		print '<br>';
	}
/*
	print '<table style="width: 100% "><tr><td colspan="2" class="role clickgroup" id="destinataire">';
	print '<span class="roleTitle ">' . $langs->trans('Destinataire') . '</span>';
	print '<i class="fa fa-chevron-right pictoFa" style="font-size: 0.86em" id="destinatairegroup"></i>';
	print '</td></tr></table><br/>';

	print '<div class="destinataire" style="display: none">';
	print '<div class="destinataire" style="display: none">';*/
	// List of selected targets
	$sql  = "SELECT mc.rowid, mc.lastname, mc.firstname, mc.email, mc.other, mc.statut, mc.date_envoi, mc.source_url, mc.source_id, mc.source_type, mc.error_text";
	$sql .= " FROM ".MAIN_DB_PREFIX."mailing_cibles as mc";
	$sql .= " WHERE mc.fk_mailing=".$object->id;
	if ($search_lastname)  $sql.= natural_search("mc.lastname", $search_lastname);
	if ($search_firstname) $sql.= natural_search("mc.firstname", $search_firstname);
	if ($search_email)     $sql.= natural_search("mc.email", $search_email);
	if ($search_other)     $sql.= natural_search("mc.other", $search_other);
	if ($search_dest_status != '' && $search_dest_status >= -1) $sql.= " AND mc.statut=".$db->escape($search_dest_status)." ";
	$sql .= $db->order($sortfield,$sortorder);

	// Count total nb of records
	$nbtotalofrecords = '';
	if (empty($conf->global->MAIN_DISABLE_FULL_SCANLIST))
	{
	    $result = $db->query($sql);
	    $nbtotalofrecords = $db->num_rows($result);
	    if (($page * $limit) > $nbtotalofrecords)	// if total resultset is smaller then paging size (filtering), goto and load page 0
	    {
	    	$page = 0;
	    	$offset = 0;
	    }
	}

	//$nbtotalofrecords=$object->nbemail;     // nbemail is a denormalized field storing nb of targets
	$sql .= $db->plimit($limit+1, $offset);

	$resql=$db->query($sql);
	if ($resql)
	{

		$num = $db->num_rows($resql);

		$param = "&amp;id=".$object->id;
		//if (! empty($contextpage) && $contextpage != $_SERVER["PHP_SELF"]) $param.='&contextpage='.urlencode($contextpage);
		if ($limit > 0 && $limit != $conf->liste_limit) $param.='&limit='.urlencode($limit);
		if ($search_lastname)  $param.= "&amp;search_lastname=".urlencode($search_lastname);
		if ($search_firstname) $param.= "&amp;search_firstname=".urlencode($search_firstname);
		if ($search_email)     $param.= "&amp;search_email=".urlencode($search_email);
		if ($search_other)     $param.= "&amp;search_other=".urlencode($search_other);

		print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
		print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
		print '<input type="hidden" name="sortfield" value="'.$sortfield.'">';
		print '<input type="hidden" name="sortorder" value="'.$sortorder.'">';
        print '<input type="hidden" name="page" value="'.$page.'">';
		print '<input type="hidden" name="id" value="'.$object->id.'">';

		$cleartext='';
		if ($allowaddtarget) {
		    $cleartext=$langs->trans("ToClearAllRecipientsClickHere").' '.'<a href="'.$_SERVER["PHP_SELF"].'?clearlist=1&id='.$object->id.'" class="button reposition">'.$langs->trans("TargetsReset").'</a>';
		}
		print_barre_liste($langs->trans("MailSelectedRecipients"),$page,$_SERVER["PHP_SELF"],$param,$sortfield,$sortorder,$cleartext,$num,$nbtotalofrecords,'title_generic',0,'','', $limit);

		print '</form>';

		print "\n<!-- Liste destinataires selectionnes -->\n";
		print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
		print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
		print '<input type="hidden" name="sortfield" value="'.$sortfield.'">';
		print '<input type="hidden" name="sortorder" value="'.$sortorder.'">';
        print '<input type="hidden" name="page" value="'.$page.'">';
		print '<input type="hidden" name="id" value="'.$object->id.'">';
		print '<input type="hidden" name="limit" value="'.$limit.'">';


		if ($page)	$param.= "&amp;page=".$page;

		print '<div class="div-table-responsive">';
		print '<table class="noborder" width="100%">';

		// Ligne des champs de filtres
		print '<tr class="liste_titre_filter">';
		// EMail
		print '<td class="liste_titre">';
		print '<input class="flat maxwidth100" type="text" name="search_email" value="'.dol_escape_htmltag($search_email).'">';
		print '</td>';
		// Name
		print '<td class="liste_titre">';
		print '<input class="flat maxwidth100" type="text" name="search_lastname" value="'.dol_escape_htmltag($search_lastname).'">';
		print '</td>';
		// Firstname
		print '<td class="liste_titre">';
		print '<input class="flat maxwidth100" type="text" name="search_firstname" value="'.dol_escape_htmltag($search_firstname).'">';
		print '</td>';
		// Other
		print '<td class="liste_titre">';
		print '<input class="flat maxwidth100" type="text" name="search_other" value="'.dol_escape_htmltag($search_other).'">';
		print '</td>';
		// Source
		print '<td class="liste_titre">';
		print '&nbsp';
		print '</td>';

		// Date sending
		print '<td class="liste_titre">';
		print '&nbsp';
		print '</td>';
		//Statut
		print '<td class="liste_titre" align="right">';
		print $formmailing->selectDestinariesStatus($search_dest_status,'search_dest_status',1);
		print '</td>';
		// Action column
		print '<td class="liste_titre" align="right">';
		$searchpicto=$form->showFilterAndCheckAddButtons($massactionbutton?1:0, 'checkforselect', 1);
		print $searchpicto;
		print '</td>';
		print '</tr>';

		print '<tr class="liste_titre">';
		print_liste_field_titre("EMail",$_SERVER["PHP_SELF"],"mc.email",$param,"","",$sortfield,$sortorder);
		print_liste_field_titre("Lastname",$_SERVER["PHP_SELF"],"mc.lastname",$param,"","",$sortfield,$sortorder);
		print_liste_field_titre("Firstname",$_SERVER["PHP_SELF"],"mc.firstname",$param,"","",$sortfield,$sortorder);
		print_liste_field_titre("OtherInformations",$_SERVER["PHP_SELF"],"",$param,"","",$sortfield,$sortorder);
		print_liste_field_titre("Source",$_SERVER["PHP_SELF"],"",$param,"",'align="center"',$sortfield,$sortorder);
		// Date sending
		if ($object->statut < 2)
		{
			print_liste_field_titre('');
		}
		else
		{
			print_liste_field_titre("DateSending",$_SERVER["PHP_SELF"],"mc.date_envoi",$param,'','align="center"',$sortfield,$sortorder);
		}
		print_liste_field_titre("Status",$_SERVER["PHP_SELF"],"mc.statut",$param,'','align="right"',$sortfield,$sortorder);
		print_liste_field_titre('',$_SERVER["PHP_SELF"],"",'','','',$sortfield,$sortorder,'maxwidthsearch ');
		print '</tr>';

		$i = 0;

		if ($num)
		{
			while ($i < min($num,$limit))
			{
				$obj = $db->fetch_object($resql);

				print '<tr class="oddeven">';
				print '<td>'.$obj->email.'</td>';
				print '<td>'.$obj->lastname.'</td>';
				print '<td>'.$obj->firstname.'</td>';
				print '<td>'.$obj->other.'</td>';
				print '<td align="center">';
                if (empty($obj->source_id) || empty($obj->source_type))
                {
                    print empty($obj->source_url)?'':$obj->source_url; // For backward compatibility
                }
                else
                {
                    if ($obj->source_type == 'member')
                    {
                        include_once DOL_DOCUMENT_ROOT.'/adherents/class/adherent.class.php';
                        $objectstatic=new Adherent($db);
						$objectstatic->fetch($obj->source_id);
                        print $objectstatic->getNomUrl(1);
                    }
                    else if ($obj->source_type == 'user')
                    {
                        include_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
                        $objectstatic=new User($db);
						$objectstatic->fetch($obj->source_id);
                        $objectstatic->id=$obj->source_id;
                        print $objectstatic->getNomUrl(1);
                    }
                    else if ($obj->source_type == 'thirdparty')
                    {
                        include_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
                        $objectstatic=new Societe($db);
						$objectstatic->fetch($obj->source_id);
                        print $objectstatic->getNomUrl(1);
                    }
                    else if ($obj->source_type == 'contact')
                    {
                    	include_once DOL_DOCUMENT_ROOT.'/contact/class/contact.class.php';
                    	$objectstatic=new Contact($db);
                    	$objectstatic->fetch($obj->source_id);
                    	print $objectstatic->getNomUrl(1);
                    }
                    else
                    {
                        print $obj->source_url;
                    }
                }
				print '</td>';

				// Status of recipient sending email (Warning != status of emailing)
				if ($obj->statut == 0)
				{
					print '<td align="center">&nbsp;</td>';
					print '<td align="right" class="nowrap">'.$langs->trans("MailingStatusNotSent");
					print '</td>';
				}
				else
				{
					print '<td align="center">'.$obj->date_envoi.'</td>';
					print '<td align="right" class="nowrap">';
					print $object::libStatutDest($obj->statut, 2, $obj->error_text);
					print '</td>';
				}

				// Search Icon
				print '<td align="right">';
				if ($obj->statut == 0)	// Not sent yet
				{
					if ($user->rights->mailing->creer && $allowaddtarget) {
						print '<a href="'.$_SERVER['PHP_SELF'].'?action=delete&rowid='.$obj->rowid.$param.'">'.img_delete($langs->trans("RemoveRecipient")).'</a>';
					}
				}
				/*if ($obj->statut == -1)	// Sent with error
				{
					print '<a href="'.$_SERVER['PHP_SELF'].'?action=retry&rowid='.$obj->rowid.$param.'">'.$langs->trans("Retry").'</a>';
				}*/
				print '</td>';
				print '</tr>';

				$i++;
			}
		}
		else
		{
			if ($object->statut < 2)
			{
			    print '<tr><td colspan="8" class="opacitymedium">';
    			print $langs->trans("NoTargetYet");
    			print '</td></tr>';
			}
		}
		print "</table><br>";
		print '</div>';


		print '<a class="button" style="color:#FFFFFF" href="'.DOL_URL_ROOT.'/custom/mailingreworked/card.php?id='.$object->id.'&action=visu">'.$langs->trans("VisualizationMail").'</a>';

		print '</form>';
		print '</div>';

		$db->free($resql);


	}
	else
	{
		dol_print_error($db);
	}

	print "\n<!-- Fin liste destinataires selectionnes -->\n";
}

// End of page
llxFooter();
$db->close();
