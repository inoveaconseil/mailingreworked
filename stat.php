<?php
/* Copyright (C) 2004      Rodolphe Quiedeville <rodolphe@quiedeville.org>
 * Copyright (C) 2005-2013 Laurent Destailleur  <eldy@uers.sourceforge.net>
 * Copyright (C) 2005-2010 Regis Houssin        <regis.houssin@capnetworks.com>
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

$res = 0;
if (!$res && file_exists("../main.inc.php"))
    $res = @include '../main.inc.php';     // to work if your module directory is into dolibarr root htdocs directory
if (!$res && file_exists("../../main.inc.php"))
    $res = @include '../../main.inc.php';   // to work if your module directory is into a subdir of root htdocs directory
if (!$res && file_exists("../../../main.inc.php"))
    $res = @include '../../../main.inc.php';   // to work if your module directory is into a subdir of root htdocs directory
if (!$res && file_exists("../../../dolibarr/htdocs/main.inc.php"))
    $res = @include '../../../dolibarr/htdocs/main.inc.php';     // Used on dev env only
if (!$res && file_exists("../../../../dolibarr/htdocs/main.inc.php"))
    $res = @include '../../../../dolibarr/htdocs/main.inc.php';   // Used on dev env only
require_once DOL_DOCUMENT_ROOT.'/custom/mailingreworked/core/modules/modules_mailings.php';
require_once DOL_DOCUMENT_ROOT.'/custom/mailingreworked/class/mailing.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/emailing.lib.php';
require_once DOL_DOCUMENT_ROOT.'/custom/mailingreworked/class/CMailFile.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
require_once DOL_DOCUMENT_ROOT . '/custom/mailingreworked/lib/mailingreworked.lib.php';

$langs->loadlangs(array ("mailingreworked@mailingreworked", "mails", "partnersdata@partnersdata"));

global $conf;
// Security check
if (! $user->rights->mailing->lire || $user->societe_id > 0) accessforbidden();



$id=GETPOST('id','int');
// Search modules dirs
$modulesdir = dolGetModulesDirs('/mailings');

$object = new FBMailing($db);





/*
 * View
 */
llxHeader('',$langs->trans("Mailing"),'EN:Module_EMailing|FR:Module_Mailing|ES:M&oacute;dulo_Mailing');

$form = new Form($db);

if ($object->fetch($id) >= 0)
{
	$head = fb_emailing_prepare_head($object);

	dol_fiche_head($head, 'stat', $langs->trans("Mailing"), 0, 'email');


	print '<table class="border" width="100%">';

	$linkback = '<a href="'.DOL_URL_ROOT.'/custom/mailingreworked/list.php">'.$langs->trans("BackToList").'</a>';

	print '<tr><td width="25%">'.$langs->trans("Ref").'</td>';
	print '<td colspan="3">';
	print $form->showrefnav($object,'id', $linkback);

	print '</td></tr>';


	print '<tr><td width="25%">'.$langs->trans("MailTopic").'</td><td colspan="3">'.$object->sujet.'</td></tr>';

	print '<tr><td width="25%">'.$langs->trans("ModeleType").'</td><td colspan="3">'.$object->titre.'</td></tr>';

	print '<tr><td width="25%">'.$langs->trans("MailFrom").'</td><td colspan="3">'.dol_print_email($object->email_from,0,0,0,0,1).'</td></tr>';

	// Errors to
	print '<tr><td width="25%">'.$langs->trans("MailErrorsTo").'</td><td colspan="3">'.dol_print_email($object->email_errorsto,0,0,0,0,1);
	print '</td></tr>';

	// Status
	print '<tr><td width="25%">'.$langs->trans("Status").'</td><td colspan="3">'.$object->getLibStatut(4).'</td></tr>';

	// Nb of distinct emails
	print '<tr><td width="25%">';
	print $langs->trans("TotalNbOfDistinctRecipients");
	print '</td><td colspan="3">';
	$nbemail = ($object->nbemail?$object->nbemail:'0');
	if (!empty($conf->global->MAILING_LIMIT_SENDBYWEB) && $conf->global->MAILING_LIMIT_SENDBYWEB < $nbemail)
	{
		$text=$langs->trans('LimitSendingEmailing',$conf->global->MAILING_LIMIT_SENDBYWEB);
		print $form->textwithpicto($nbemail,$text,1,'warning');
	}
	else
	{
		print $nbemail;
	}
	print '</td></tr>';
if($object->statut == 3 || $object->statut == 2){
            $sql  = "SELECT COUNT(*) as count";
            $sql .= " FROM ".MAIN_DB_PREFIX."mailing_cibles as mc";
            $sql .= " WHERE mc.fk_mailing=".$object->id." AND mc.statut = -1";
            $e=$db->query($sql);
            if($e) $err = $db->fetch_object($e);
            
            $sql  = "SELECT COUNT(*) as count";
            $sql .= " FROM ".MAIN_DB_PREFIX."mailing_cibles as mc";
            $sql .= " WHERE mc.fk_mailing=".$object->id." AND mc.statut = 1";
            $e=$db->query($sql);
            if($e) $send = $db->fetch_object($e);

            $sql  = "SELECT COUNT(*) as count";
            $sql .= " FROM ".MAIN_DB_PREFIX."mailing_cibles as mc";
            $sql .= " WHERE mc.fk_mailing=".$object->id." AND mc.statut = 2";
            $e=$db->query($sql);
            if($e) $see = $db->fetch_object($e);

            $sql  = "SELECT COUNT(*) as count";
            $sql .= " FROM ".MAIN_DB_PREFIX."mailing_cibles as mc";
            $sql .= " WHERE mc.fk_mailing=".$object->id." AND mc.statut = 3";
            $e=$db->query($sql);
            if($e) $notc = $db->fetch_object($e);
            
            // Stats
            print '<tr><td>';
            print $langs->trans("Statistiques");
            print '</td><td colspan="3">';
            print $langs->trans("send").": ".($send->count + $see->count)."<br />".$langs->trans("see").": ".$see->count."<br />".$langs->trans("notcontacted").": ".$notc->count."<br />".$langs->trans("error").": ".$err->count;
            print '</td></tr>';
            
            print '</table>';

	print "</div>";

        print '<div class="titre">'.$langs->trans('File').'</div>';
        print '<table class="liste formdoc noborder" summary="listofdocumentstable" width="100%">';
        print '<tbody><tr class="liste_titre"><th align="center" colspan="2" class="formdoc liste_titre maxwidthonsmartphone"></th></tr>';

        if (!file_exists($conf->statsemailing->dir_output))
            mkdir($conf->statsemailing->dir_output);
        
        if($send->count > 0){
                $sendcsv = array();
                $sql  = "SELECT * ";
                $sql .= " FROM ".MAIN_DB_PREFIX."mailing_cibles as mc";
                $sql .= " WHERE mc.fk_mailing=".$object->id." AND mc.statut = 1";
                $e=$db->query($sql);
                
                while($obj = $db->fetch_object($e)){
                    $sendcsv[]= array($obj->email,$obj->lastname,$obj->firstname,$obj->other);
                
                    
                }
                $pathcsv = $conf->statsemailing->dir_output."/send"."-".$object->id.".csv";
                $delimiteur = ";";
                $filecsv = fopen($pathcsv, 'w+');
                fprintf($filecsv, chr(0xEF).chr(0xBB).chr(0xBF));
                foreach($sendcsv as $s){
                    fputcsv($filecsv, $s, $delimiteur);
                }
                @fclose($fichier_csv);
             
                print '<tr><td>'.$langs->trans('CSVSendFile').'</td><td><a data-ajax="false" href="'.DOL_URL_ROOT.'/document.php?modulepart=statsemailing&file=send-'.$object->id.'.csv" target="_blank"><img src="'.DOL_URL_ROOT.'/theme/common/mime/text.png" border="0" alt="">'.$langs->trans('send').'-'.$object->id.'.csv</a></td></tr>';
        }

        
        if($err->count > 0){
                $errcsv = array();
                $sql  = "SELECT *";
                $sql .= " FROM ".MAIN_DB_PREFIX."mailing_cibles as mc";
                $sql .= " WHERE mc.fk_mailing=".$object->id." AND mc.statut = -1";
                $e=$db->query($sql);
                
                while($obj = $db->fetch_object($e)){
                    $errcsv[]= array($obj->email,$obj->lastname,$obj->firstname,$obj->other);
                
                    
                }
                $pathcsv = $conf->statsemailing->dir_output."/error"."-".$object->id.".csv";
                $delimiteur = ";";
                $filecsv = fopen($pathcsv, 'w+');
                fprintf($filecsv, chr(0xEF).chr(0xBB).chr(0xBF));
                foreach($errcsv as $s){
                    fputcsv($filecsv, $s, $delimiteur);
                }
                @fclose($fichier_csv);
             
                print '<tr><td>'.$langs->trans('CSVErrorFile').'</td><td><a data-ajax="false" href="'.DOL_URL_ROOT.'/document.php?modulepart=statsemailing&file=error-'.$object->id.'.csv" target="_blank"><img src="'.DOL_URL_ROOT.'/theme/common/mime/text.png" border="0" alt="">'.$langs->trans('error').'-'.$object->id.'.csv</a></td></tr>';
        }

        
        if($see->count > 0){
                $seecsv = array();
                $sql  = "SELECT * ";
                $sql .= " FROM ".MAIN_DB_PREFIX."mailing_cibles as mc";
                $sql .= " WHERE mc.fk_mailing=".$object->id." AND mc.statut = 2";
                $e=$db->query($sql);
                
                while($obj = $db->fetch_object($e)){
                    $seecsv[]= array($obj->email,$obj->lastname,$obj->firstname,$obj->other);
                
                    
                }
                $pathcsv = $conf->statsemailing->dir_output."/see"."-".$object->id.".csv";
                $delimiteur = ";";
                $filecsv = fopen($pathcsv, 'w+');
                fprintf($filecsv, chr(0xEF).chr(0xBB).chr(0xBF));
                foreach($seecsv as $s){
                    fputcsv($filecsv, $s, $delimiteur);
                }
                @fclose($fichier_csv);

                print '<tr><td>'.$langs->trans('CSVSeeFile').'</td><td><a data-ajax="false" href="'.DOL_URL_ROOT.'/document.php?modulepart=statsemailing&file=see-'.$object->id.'.csv" target="_blank"><img src="'.DOL_URL_ROOT.'/theme/common/mime/text.png" border="0" alt="">'.$langs->trans('see').'-'.$object->id.'.csv</a></td></tr>';
        }

        
        if($notc->count > 0){
                $notccsv = array();
                $sql  = "SELECT * ";
                $sql .= " FROM ".MAIN_DB_PREFIX."mailing_cibles as mc";
                $sql .= " WHERE mc.fk_mailing=".$object->id." AND mc.statut = 3";
                $e=$db->query($sql);
                
                while($obj = $db->fetch_object($e)){
                    $notccsv[]= array($obj->email,$obj->lastname,$obj->firstname,$obj->other);
                
                    
                }
                $pathcsv = $conf->statsemailing->dir_output."/notcontacted"."-".$object->id.".csv";
                $delimiteur = ";";
                $filecsv = fopen($pathcsv, 'w+');
                fprintf($filecsv, chr(0xEF).chr(0xBB).chr(0xBF));
                foreach($notccsv as $s){
                    fputcsv($filecsv, $s, $delimiteur);
                }
                @fclose($fichier_csv);
             
                print '<tr><td>'.$langs->trans('CSVNotcontactedFile').'</td><td><a data-ajax="false" href="'.DOL_URL_ROOT.'/document.php?modulepart=statsemailing&file=notcontacted-'.$object->id.'.csv" target="_blank"><img src="'.DOL_URL_ROOT.'/theme/common/mime/text.png" border="0" alt="">'.$langs->trans('notcontacted').'-'.$object->id.'.csv</a></td></tr>';
        }

        print '</tbody></table></div>';
        }else{
            // Stats
            print '<tr><td>';
            print $langs->trans("Statistiques");
            print '</td><td colspan="3">';
            print $langs->trans("Emailingnotsend");
            print '</td></tr>';
        }

	

}



llxFooter();

$db->close();
