<?php
/* Copyright (C) 2004		Rodolphe Quiedeville	<rodolphe@quiedeville.org>
 * Copyright (C) 2005-2016	Laurent Destailleur		<eldy@uers.sourceforge.net>
 * Copyright (C) 2005-2016	Regis Houssin			<regis.houssin@inodbox.com>
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
 *       \file       htdocs/comm/mailing/card.php
 *       \ingroup    mailing
 *       \brief      Fiche mailing, onglet general
 */

if (!defined('NOSTYLECHECK')) define('NOSTYLECHECK', '1');

require '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT . '/custom/mailingreworked/lib/mailingreworked.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT . '/custom/mailingreworked/class/CMailFile.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/functions2.lib.php';
require_once DOL_DOCUMENT_ROOT . '/custom/mailingreworked/class/mailing.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.formother.class.php';
require_once DOL_DOCUMENT_ROOT . '/custom/mailingreworked/class/html.formmail.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/extrafields.class.php';

// Load translation files required by the page
$langs->loadlangs(array ("mailingreworked@mailingreworked", "mails", "partnersdata@partnersdata"));

if (!$user->rights->mailing->lire || (empty($conf->global->EXTERNAL_USERS_ARE_AUTHORIZED) && $user->societe_id > 0)) accessforbidden();

$id = (GETPOST('mailid', 'int') ? GETPOST('mailid', 'int') : GETPOST('id', 'int'));
$action = GETPOST('action', 'alpha');
$confirm = GETPOST('confirm', 'alpha');
$urlfrom = GETPOST('urlfrom');

$object = new FBMailing($db);
$result = $object->fetch($id);

$extrafields = new ExtraFields($db);

// fetch optionals attributes and labels
$extralabels = $extrafields->fetch_name_optionals_label($object->table_element);

// Initialize technical object to manage hooks of page. Note that conf->hooks_modules contains array of hook context
$hookmanager->initHooks(array('mailingcard', 'globalcard'));

// Array of possible substitutions (See also file mailing-send.php that should manage same substitutions)
$object->substitutionarray = FormMail::getAvailableSubstitKey('emailing');


// Set $object->substitutionarrayfortest
$signature = ((!empty($user->signature) && empty($conf->global->MAIN_MAIL_DO_NOT_USE_SIGN)) ? $user->signature : '');

$targetobject = null;        // Not defined with mass emailing

$parameters = array('mode' => 'emailing');
$substitutionarray = FormMail::getAvailableSubstitKey('emailing', $targetobject);

$object->substitutionarrayfortest = $substitutionarray;

// List of sending methods
$listofmethods = array();
$listofmethods['mail'] = 'PHP mail function';
//$listofmethods['simplemail']='Simplemail class';
$listofmethods['smtps'] = 'SMTP/SMTPS socket library';


/*
 * Actions
 */

$parameters = array();
$reshook = $hookmanager->executeHooks('doActions', $parameters, $object, $action);    // Note that $action and $object may have been modified by some hooks
if ($reshook < 0) setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');

if (empty($reshook)) {
	// Action clone object
	if (($action == 'confirm_clone' && $confirm == 'yes') || ($action == 'confirm_clone_from_visu' && $confirm == 'yes')) {
		if (empty($_REQUEST["clone_content"]) && empty($_REQUEST["clone_receivers"])) {
			setEventMessages($langs->trans("NoCloneOptionsSpecified"), null, 'errors');
		} else {
			$result = $object->createFromClone($object->id, $_REQUEST["clone_content"], $_REQUEST["clone_receivers"]);
			if ($result > 0 && $action=='confirm_clone') {
				header("Location: " . $_SERVER['PHP_SELF'] . '?id=' . $result);
				exit;
			} elseif ($result > 0 && $action=='confirm_clone_from_visu') {
				header("Location: " . $_SERVER['PHP_SELF'] . '?action=visu&id=' . $result);
				exit;
			} else {
				setEventMessages($object->error, $object->errors, 'errors');
			}
		}
		if ($action == 'confirm_clone')
		$action = '';
		else
			$action= 'visu';
	}

	// Action send test emailing
	if (($action == 'send' && empty($_POST["cancel"])) || ($action == 'sendFromVisu' && empty($_POST["cancel"]))) {
		$error = 0;

		$upload_dir = $conf->mailing->dir_output . "/" . get_exdir($object->id, 2, 0, 1, $object, 'mailing');

		$object->sendto = $_POST["sendto"];
		if (!$object->sendto) {
			setEventMessages($langs->trans("ErrorFieldRequired", $langs->trans("MailTo")), null, 'errors');
			$error++;
		}

		if (!$error) {
			// Le message est-il en html
			$msgishtml = -1;    // Inconnu par defaut
			if (preg_match('/[\s\t]*<html>/i', $object->body)) $msgishtml = 1;

			// other are set at begin of page
			$object->substitutionarrayfortest['__EMAIL__'] = $object->sendto;
			$object->substitutionarrayfortest['__MAILTOEMAIL__'] = '<a href="mailto:' . $object->sendto . '">' . $object->sendto . '</a>';

			// Pratique les substitutions sur le sujet et message
			complete_substitutions_array($object->substitutionarrayfortest, $langs);
			$tmpsujet = make_substitutions($object->sujet, $object->substitutionarrayfortest);
			$tmpbody = make_substitutions($object->body, $object->substitutionarrayfortest);

			$arr_file = array();
			$arr_mime = array();
			$arr_name = array();
			$arr_css = array();

			// Ajout CSS
			if (!empty($object->bgcolor)) $arr_css['bgcolor'] = (preg_match('/^#/', $object->bgcolor) ? '' : '#') . $object->bgcolor;
			if (!empty($object->bgimage)) $arr_css['bgimage'] = $object->bgimage;

			// Attached files
			$listofpaths = dol_dir_list($upload_dir, 'all', 0, '', '', 'name', SORT_ASC, 0);
			if (count($listofpaths)) {
				foreach ($listofpaths as $key => $val) {
					$arr_file[] = $listofpaths[$key]['fullname'];
					$arr_mime[] = dol_mimetype($listofpaths[$key]['name']);
					$arr_name[] = $listofpaths[$key]['name'];
				}
			}

			$trackid = 'emailingtest';
			$mailfile = new CMailFile($tmpsujet, $object->sendto, $object->email_from, $tmpbody, $arr_file, $arr_mime, $arr_name, '', '', 0, $msgishtml, $object->email_errorsto, $arr_css, $trackid, '', 'emailing');

			$result = $mailfile->sendfile();
			if ($result) {
				setEventMessages($langs->trans("MailSuccessfulySent", $mailfile->getValidAddress($object->email_from, 2), $mailfile->getValidAddress($object->sendto, 2)), null, 'mesgs');
				$action = '';
			} else {
				setEventMessages($langs->trans("ResultKo") . '<br>' . $mailfile->error . ' ' . $result, null, 'errors');
				if ($action == 'send')
				$action = 'test';
				else
					$action ='testFromVisu';
			}
		}
	}

	// Action add emailing
	if ($action == 'add') {

		$mesgs = array();
		$object->email_from = trim($_POST["from"]);
		$object->email_replyto = trim($_POST["replyto"]);
		$object->email_errorsto = trim($_POST["errorsto"]);
		$object->titre = trim($_POST["titre"]);
		$object->sujet = trim($_POST["sujet"]);
		$object->body = trim($_POST["bodyemail"]);
		if (($_POST["checkread"]) == "on") {
			$object->body .= "\n\r __CHECK_READ__";
		}
		if (($_POST["unsubscribeLink"]) == "on") {
			$object->body .= "\n\r __UNSUBSCRIBE__";
		}
		$object->bgcolor = trim($_POST["bgcolor"]);
		$object->bgimage = trim($_POST["bgimage"]);


		if (!$object->titre) {
			//$mesgs[] = $langs->trans("ErrorFieldRequired",$langs->transnoentities("MailTitle"));
		}
		if (!$object->sujet) {
			$mesgs[] = $langs->trans("ErrorFieldRequired", $langs->transnoentities("MailTopic"));
		}
		if (!$object->body) {
			$mesgs[] = $langs->trans("ErrorFieldRequired", $langs->transnoentities("MailMessage"));
		}

		if (!count($mesgs)) {
			if ($object->create($user) >= 0) {
				if (($_FILES['addedfile']['name']) != '') {
					$upload_dir = $conf->mailing->dir_output . "/" . get_exdir($object->id, 2, 0, 1, $object, 'mailing');
					require_once DOL_DOCUMENT_ROOT . '/core/lib/files.lib.php';

					// Set tmp user directory
					dol_add_file_process($upload_dir, 0, 0, 'addedfile', '',null,'',0);
				}
				header("Location: " . DOL_URL_ROOT . "/custom/mailingreworked/cibles.php?id=" . $object->id );
				exit;
			}
			$mesgs[] = $object->error;
		}

		setEventMessages($mesg, $mesgs, 'errors');
		$action = "create";
	}

	// Action update description of emailing
	if ($action == 'settitre' || $action == 'setemail_from' || $action == 'setreplyto' || $action == 'setemail_errorsto') {
		$upload_dir = $conf->mailing->dir_output . "/" . get_exdir($object->id, 2, 0, 1, $object, 'mailing');

		if ($action == 'settitre') $object->titre = trim(GETPOST('titre', 'alpha'));
		else if ($action == 'setemail_from') $object->email_from = trim(GETPOST('email_from', 'alpha'));
		else if ($action == 'setemail_replyto') $object->email_replyto = trim(GETPOST('email_replyto', 'alpha'));
		else if ($action == 'setemail_errorsto') $object->email_errorsto = trim(GETPOST('email_errorsto', 'alpha'));
		else if ($action == 'settitre' && empty($object->titre)) {
			$mesg = $langs->trans("ErrorFieldRequired", $langs->transnoentities("MailTitle"));
		} else if ($action == 'setfrom' && empty($object->email_from)) {
			$mesg = $langs->trans("ErrorFieldRequired", $langs->transnoentities("MailFrom"));
		}

		if (!$mesg) {
			if ($object->update($user) >= 0) {
				header("Location: " . $_SERVER['PHP_SELF'] . "?id=" . $object->id);
				exit;
			}
			$mesg = $object->error;
		}

		setEventMessages($mesg, $mesgs, 'errors');
		$action = "";
	}

	/*
	 * Add file in email form
	 */
	if (!empty($_POST['addfile'])) {
		$upload_dir = $conf->mailing->dir_output . "/" . get_exdir($object->id, 2, 0, 1, $object, 'mailing');

		require_once DOL_DOCUMENT_ROOT . '/core/lib/files.lib.php';

		// Set tmp user directory
		dol_add_file_process($upload_dir, 0, 0, 'addedfile', '',null,'',0);

		$action = "";
	}

	// Action remove file
	if (!empty($_POST["removedfile"])) {
		$upload_dir = $conf->mailing->dir_output . "/" . get_exdir($object->id, 2, 0, 1, $object, 'mailing');

		require_once DOL_DOCUMENT_ROOT . '/core/lib/files.lib.php';

		dol_remove_file_process($_POST['removedfile'], 0, 0);    // We really delete file linked to mailing

		$action = "";
	}

	// Action update emailing
	if ($action == 'update' && empty($_POST["removedfile"]) && empty($_POST["cancel"])) {
		require_once DOL_DOCUMENT_ROOT . '/core/lib/files.lib.php';

		$isupload = 0;

		if (!$isupload) {
			$mesgs = array();

			$object->sujet = trim($_POST["sujet"]);
			$object->body = trim($_POST["bodyemail"]);
			$object->bgcolor = trim($_POST["bgcolor"]);
			$object->bgimage = trim($_POST["bgimage"]);

			if (!$object->sujet) {
				$mesgs[] = $langs->trans("ErrorFieldRequired", $langs->transnoentities("MailTopic"));
			}
			if (!$object->body) {
				$mesgs[] = $langs->trans("ErrorFieldRequired", $langs->transnoentities("MailMessage"));
			}

			if (!count($mesgs)) {
				if ($object->update($user) >= 0) {
					header("Location: " . $_SERVER['PHP_SELF'] . "?id=" . $object->id."&action=visu");
					exit;
				}
				$mesgs[] = $object->error;
			}

			setEventMessages($mesg, $mesgs, 'errors');
			$action = "";
		} else {
			$action = "";
		}
	}

	// Action confirmation validation
	if ($action == 'confirm_valid' && $confirm == 'yes') {
		if ($object->id > 0) {
			$object->valid($user);
			setEventMessages($langs->trans("MailingSuccessfullyValidated"), null, 'mesgs');
			header("Location: " . $_SERVER['PHP_SELF'] . "?id=" . $object->id."&action=sendall");
			exit;
		} else {
			dol_print_error($db);
		}
	}

	// Action confirmation validation
	if ($action == 'confirm_settodraft' && $confirm == 'yes') {
		if ($object->id > 0) {
			$result = $object->setStatut(0);
			if ($result > 0) {
				//setEventMessages($langs->trans("MailingSuccessfullyValidated"), null, 'mesgs');
				header("Location: " . $_SERVER['PHP_SELF'] . "?id=" . $object->id);
				exit;
			} else {
				setEventMessages($object->error, $object->errors, 'errors');
			}
		} else {
			dol_print_error($db);
		}
	}

	// Resend
	if ($action == 'confirm_reset' && $confirm == 'yes') {
		if ($object->id > 0) {
			$db->begin();

			$result = $object->valid($user);
			if ($result > 0) {
				$result = $object->reset_targets_status($user);
			}

			if ($result > 0) {
				$db->commit();
				header("Location: " . $_SERVER['PHP_SELF'] . "?id=" . $object->id);
				exit;
			} else {
				setEventMessages($object->error, $object->errors, 'errors');
				$db->rollback();
			}
		} else {
			dol_print_error($db);
		}
	}

	// Action confirmation suppression
	if (($action == 'confirm_delete' && $confirm == 'yes') || ($action == 'confirm_delete_from_visu' && $confirm == 'yes')){
		if ($object->delete($object->id)) {
			$url = (!empty($urlfrom) ? $urlfrom : 'list.php');
			header("Location: " . $url);
			exit;
		}
	}

	if (!empty($_POST["cancel"])) {
		if ($action== 'confirm_delete')
		$action = '';
		else
			$action = 'confirm_delete_from_visu';
	}
}


/*
 * View
 */

$form = new Form($db);
$htmlother = new FormOther($db);

$help_url = 'EN:Module_EMailing|FR:Module_Mailing|ES:M&oacute;dulo_Mailing';
llxHeader('', $langs->trans("Mailing"), $help_url, '', 0, 0,
	array(
		'/includes/ace/ace.js',
		'/includes/ace/ext-statusbar.js',
		'/includes/ace/ext-language_tools.js',
		//'/includes/ace/ext-chromevox.js'
	), array());

if ($action == 'create') {



	if (!empty (GETPOST('modele'))) {
		$modeleSelect = GETPOST('modele');
	} else
		$modeleSelect = "";

	if (!empty (GETPOST('titre'))) {
		$titreSelect = GETPOST('titre');
	} else
		$titreSelect = "";

	// EMailing in creation mode
	print '<form name="new_mailing" action="' . $_SERVER['PHP_SELF'] . '" method="POST" enctype="multipart/form-data">' . "\n";
	print '<input type="hidden" name="token" value="' . $_SESSION['newtoken'] . '">';
	print '<input type="hidden" name="action" value="add">';
	print '<input type="hidden" name="modele" value="'.$modeleSelect.'">';
	print '<input type="hidden" name="titre" value="'.$titreSelect.'">';
	print '<script type="text/javascript">
           $(document).ready(function(){
               
               					    $.get("./mails_templates_changeForm.php?categ=" + $(\'select[id="titre"]\').val(),false,resultAjax,"json");
					function resultAjax(precu) {
					   console.log(precu);		
					   
					   for (var i = 0; i < precu.length; i++) {
					       if (i == 0) {
					    document.getElementById("modele").innerHTML = "<option value=\'\'></option>";
					    document.getElementById("modele").innerHTML += "<option value=\'" + precu[i] + "\'>" + precu[i] + "</option>";
					      } else
					    document.getElementById("modele").innerHTML += "<option value=\'" + precu[i] + "\'>" + precu[i] + "</option>";
					       //document.getElementById("modele").options[i].value = precu[i];
					    //document.getElementById("modele").options[i].text = precu[i];
					    }
					    }
					    
               

        $(\'input[type="checkbox"][id="errorstosender"]\').click(function(){
           
                     if($(this).prop("checked") == true){
                         $(\'tr[id="error"]\').hide();
                         $(\'input[name="errorsto"]\').val($(\'input[name="from"]\').val()); 
                         
                     }
            else if($(this).prop("checked") == false){
                $(\'tr[id="error"]\').show()
            }
        });
                
                $(\'select[id="modele"]\').change(function() {
                document.location.href = "./card.php?action=create&modele=" + $(\'select[id="modele"]\').val() + "&titre=" + $(\'select[id="titre"]\').val();
                  	// $(\'input[name="titre"]\').val($(\'select[id="modele"]\').val());
					//$.get("./mails_templates_changeForm.php?categ=" + $(\'select[name"titre]\').val(),false,resultAjax);
					//function resultAjax(precu) {
					 //  console.log(precu);							 	 
					   // $(\'input[id="listeModeles"]\').val(precu);
					   // $(\'input[name="bodyemail"]\').val(precu.content);
					    //$(\'input[name="from"]\').val(precu.content);
                	//}                         
    	}); 
					    $(\'select[id="titre"]\').change(function() {
					    $.get("./mails_templates_changeForm.php?categ=" + $(\'select[id="titre"]\').val(),false,resultAjax,"json");
					function resultAjax(precu) {
					   console.log(precu);		
					   
					   for (var i = 0; i < precu.length; i++) {
					       if (i == 0) {
					    document.getElementById("modele").innerHTML = "<option value=\'\'></option>";
					    document.getElementById("modele").innerHTML += "<option value=\'" + precu[i] + "\'>" + precu[i] + "</option>";
					      } else
					    document.getElementById("modele").innerHTML += "<option value=\'" + precu[i] + "\'>" + precu[i] + "</option>";
					       //document.getElementById("modele").options[i].value = precu[i];
					    //document.getElementById("modele").options[i].text = precu[i];
					    }
					    }
					    });
    	});
</script>';

	$htmltext = '<i>' . $langs->trans("FollowingConstantsWillBeSubstituted") . ':<br>';
	foreach ($object->substitutionarray as $key => $val) {
		$htmltext .= $key . ' = ' . $langs->trans($val) . '<br>';
	}
	$htmltext .= '</i>';

	$availablelink = $form->textwithpicto($langs->trans("AvailableVariables"), $htmltext, 1, 'help', '', 0, 2, 'availvar');
	//print '<a href="javascript:document_preview(\''.DOL_URL_ROOT.'/admin/modulehelp.php?id='.$objMod->numero.'\',\'text/html\',\''.dol_escape_js($langs->trans("Module")).'\')">'.img_picto($langs->trans("ClickToShowDescription"), $imginfo).'</a>';


	// Print mail form
	print load_fiche_titre($langs->trans("NewMailing"), $availablelink, 'title_generic');
	$head = fb_emailing_prepare_head_inactiv($object);
	dol_fiche_head($head, '', $langs->trans("Mailing"), -1, 'email');

	print '<table class="border" width="100%">';


	/*
	 * Affichage des catégories présentes dans la BDD
	 */
	print '<tr><td>' . $langs->trans("ModeleType") . '</td><td><select id="titre">';
	print '<option><strong>'.$titreSelect.' </strong></option>';

	$sql = "SELECT DISTINCT type_template";
	$sql.= " FROM ".MAIN_DB_PREFIX.'c_email_templates';
	$resql = $db->query($sql);
	if ($resql) {
		$num = $db->num_rows($resql);
		while ($obj = $db->fetch_object($resql)) {
			print '<option>' . $obj->type_template. '</option>';
		}
		$db->free($resql);
	} else
		//TODO GERER ERREUR

	print '</select></td></tr>';


/*
 * Récupération des informations de la BDD à partir du modèle séléctionné
 */
	$line = new ModelMail();

	$sql = "SELECT rowid, label, topic, content, content_lines, lang, fk_user, private, position, type_template, fromMail";
	$sql.= " FROM ".MAIN_DB_PREFIX.'c_email_templates';
	$sql .= ' WHERE label="'.$modeleSelect.'"';
//$sql.= " WHERE type_template IN ('".$this->db->escape($type_template)."', 'all')";
//$sql.= " AND entity IN (".getEntity('c_email_templates').")";
//$sql.= " WHERE (private = 0 OR fk_user = ".$user->id.")";		// See all public templates or templates I own.
//if ($active >= 0) $sql.=" AND active = ".$active;
//if (is_object($outputlangs)) $sql.= " AND (lang = '".$outputlangs->defaultlang."' OR lang IS NULL OR lang = '')";	// Return all languages
//$sql.= $this->db->order("position,lang,label","ASC");
//print $sql;

	$resql = $db->query($sql);
	if ($resql)
	{

		$obj = $db->fetch_object($resql);
		$line->id = $obj->rowid;
		$line->label=$obj->label;
		$line->lang=$obj->lang;
		$line->fk_user=$obj->fk_user;
		$line->private=$obj->private;
		$line->position=$obj->position;
		$line->topic=$obj->topic;
		$line->content=$obj->content;
		$line->content_lines=$obj->content_lines;
		$line->type_template = $obj->type_template;
		$line->fromMail = $obj->fromMail;

		$db->free($resql);
	}
	else
	{
		print $sql;exit;
		//print $error=get_class().' '.__METHOD__.' ERROR:'.$db->lasterror();
	}


	//print '<tr><td class="titlefieldcreate">' . $langs->trans("Category") . '</td><td><input class="flat minwidth300" name="titre" value="' . $langs->trans($line->type_template). '" autofocus="autofocus"></td></tr>';
	/*
	 * LISTE DES MODELEs A PARTIR DE LA BDD (voir Javascript)
	 */
	print '<tr><td>Modèle</td><td><select id="modele">';
	print'<option>'.$modeleSelect.'</option>';

	print '</select></td></tr>';


	print '<tr><td class="fieldrequired">' . $langs->trans("MailFrom") . ' <strong style="color: red" >*</strong></td><td><input class="flat minwidth200" name="from" value="' .$line->fromMail. '">&nbsp; <input type="checkbox" id="errorstosender"  > <label for="errorstosender"> Erreurs vers l\'émetteur</label></td></tr>';
	print '<tr id="error"><td>' . $langs->trans("MailErrorsTo") . '</td><td><input class="flat minwidth200" name="errorsto" value="' . (!empty($conf->global->MAILING_EMAIL_ERRORSTO) ? $conf->global->MAILING_EMAIL_ERRORSTO : $conf->global->MAIN_MAIL_ERRORS_TO) . '"></td></tr>';

	// Other attributes
	$parameters = array();
	$reshook = $hookmanager->executeHooks('formObjectOptions', $parameters, $object, $action);    // Note that $action and $object may have been modified by hook
	print $hookmanager->resPrint;
	if (empty($reshook)) {
		print $object->showOptionals($extrafields, 'edit');
	}


	print '<tr><td class="fieldrequired titlefieldcreate">' . $langs->trans("MailTopic") . '  <strong style="color: red" >*</strong></td><td><input class="flat minwidth200 quatrevingtpercent" name="sujet" value="' . $line->topic . '"></td></tr>';

	$trackid = '';
	dol_init_file_process($upload_dir, $trackid);

	// Joined files
	$addfileaction = 'addfile';
	print '<tr><td>' . $langs->trans("MailFile") . '</td>';
	print '<td colspan="3">';

	// List of files

	$listofpaths = dol_dir_list($upload_dir, 'all', 0, '', '', 'name', SORT_ASC, 0);

	// TODO Trick to have param removedfile containing nb of image to delete. But this does not works without javascript
	$out .= '<input type="hidden" class="removedfilehidden" name="removedfile" value="">' . "\n";
	$out .= '<script type="text/javascript" language="javascript">';
	$out .= 'jQuery(document).ready(function () {';
	$out .= '    jQuery(".removedfile").click(function() {';
	$out .= '        jQuery(".removedfilehidden").val(jQuery(this).val());';
	$out .= '    });';
	$out .= '})';
	$out .= '</script>' . "\n";
	if (count($listofpaths)) {
		foreach ($listofpaths as $key => $val) {
			$out .= '<div id="attachfile_' . $key . '">';
			$out .= img_mime($listofpaths[$key]['name']) . ' ' . $listofpaths[$key]['name'];

			$out .= ' <input type="image" style="border: 0px;" src="' . img_picto($langs->trans("Search"), 'delete.png', '', '', 1) . '" value="' . ($key + 1) . '" class="removedfile" id="removedfile_' . $key . '" name="removedfile_' . $key . '" />';
			$out .= '<br></div>';
		}
	} else {
		$out .= $langs->trans("NoAttachedFiles") . '<br>';
	}
	// Add link to add file
	$out .= '<input type="file" class="flat" id="addedfile" name="addedfile" value="' . $langs->trans("Upload") . '" />';

	$out .= ' ';
	//$out .= '<input type="submit" class="button" id="' . $addfileaction . '" name="' . $addfileaction . '" value="' . $langs->trans("MailingAddFile") . '" />';
	print $out;
	print '</td></tr>';

	print '</table>';

	print '<div style="padding-top: 10px">';
	// Editeur wysiwyg
	require_once DOL_DOCUMENT_ROOT . '/custom/mailingreworked/class/doleditor.class.php';
	$doleditor = new DolEditor('bodyemail', $line->content, '', 600, 'dolibarr_mailings', '', true, true, $conf->global->FCKEDITOR_ENABLE_MAILING, 20, '90%');
	$doleditor->Create();
	print '</div>';
	print '<input type="checkbox" name="checkread" id="checkread"><label for="checkread">Suivre l\'ouverture de l\'email</label> <br> ';
	print '<input type="checkbox" name="unsubscribeLink" id="unsubscribeLink"><label for="unsubscribeLink">Lien de désinscription</label> <br> ';

	dol_fiche_end();

	//print '<div class="center"><input type="submit" class="button" id="' . $addfileaction . '" name="' . $addfileaction . '" value="' . $langs->trans("MailingAddFile") . '" /></div>';
	print '<div class="center"><input type="submit" class="button" value="' . $langs->trans("CreateMailing") . '"></div>';

	print '</form>';
} else {
	if ($object->id > 0) {
		$upload_dir = $conf->mailing->dir_output . "/" . get_exdir($object->id, 2, 0, 1, $object, 'mailing');

		$head = fb_emailing_prepare_head($object);

		// Confirmation back to draft
		if ($action == 'settodraft') {
			print $form->formconfirm($_SERVER["PHP_SELF"] . "?id=" . $object->id, $langs->trans("SetToDraft"), $langs->trans("ConfirmUnvalidateEmailing"), "confirm_settodraft", '', '', 1);
		}
		// Confirmation validation of mailing
		if ($action == 'valid') {
			print $form->formconfirm($_SERVER["PHP_SELF"] . "?id=" . $object->id, $langs->trans("SendMailing"), $langs->trans("ConfirmSendMailing"), "confirm_valid", '', '', 1);
		} // Confirm reset
		else if ($action == 'reset') {
			print $form->formconfirm($_SERVER["PHP_SELF"] . "?id=" . $object->id, $langs->trans("ResetMailing"), $langs->trans("ConfirmResetMailing", $object->ref), "confirm_reset", '', '', 2);
		} // Confirm delete
		else if ($action == 'delete') {
			print $form->formconfirm($_SERVER["PHP_SELF"] . "?id=" . $object->id . (!empty($urlfrom) ? '&urlfrom=' . urlencode($urlfrom) : ''), $langs->trans("DeleteAMailing"), $langs->trans("ConfirmDeleteMailing"), "confirm_delete", '', '', 1);
		}
		else if ($action == 'delete_from_visu') {
			print $form->formconfirm($_SERVER["PHP_SELF"] . "?action=visu&id=" . $object->id . (!empty($urlfrom) ? '&urlfrom=' . urlencode($urlfrom) : ''), $langs->trans("DeleteAMailing"), $langs->trans("ConfirmDeleteMailing"), "confirm_delete_from_visu", '', '', 1);
		}


/*
 * Mail en mode edition
 * (action = '')
 */
		if ($action != 'edit' && $action != 'visu' && $action!='valid' && $action!='confirm_valid' && $action != 'sendall' && $action!='testFromVisu' && $action !='clone_from_visu' && $action != 'delete_from_visu')  {

			dol_fiche_head($head, 'card', $langs->trans("Mailing"), -1, 'email');

			$linkback = '<a href="' . DOL_URL_ROOT . '/custom/mailingreworked/list.php">' . $langs->trans("BackToList") . '</a>';

			$morehtmlright = '';
			if ($object->statut == 2) $morehtmlright .= ' (' . $object->countNbOfTargets('alreadysent') . '/' . $object->nbemail . ') ';

			dol_banner_tab($object, 'id', $linkback, 1, 'rowid', 'ref', '', '', 0, '', $morehtmlright,0);
print '<div class="refid" style="margin-left: 8%;margin-bottom: 2%;margin-top: -3%;">'.$object->sujet.'</div>';
			print '<div class="fichecenter">';
			print '<div class="underbanner clearboth"></div>';

			print '<table class="border" width="100%">';

			/*
			print '<tr><td class="titlefield">'.$langs->trans("Ref").'</td>';
			print '<td colspan="3">';
			print $form->showrefnav($object,'id', $linkback);
			print '</td></tr>';
			*/

			// Topic
			print '<tr><td class="titlefield">' . $form->editfieldkey("MailTitle", 'titre', $object->titre, $object, $user->rights->mailing->creer && $object->statut < 3, 'string') . '</td><td colspan="3">';
			print $form->editfieldval("MailTitle", 'titre', $object->titre, $object, $user->rights->mailing->creer && $object->statut < 3, 'string');
			print '</td></tr>';
			// From
			print '<tr><td>' . $form->editfieldkey("MailFrom", 'email_from', $object->email_from, $object, $user->rights->mailing->creer && $object->statut < 3, 'string') . '</td><td colspan="3">';
			print $form->editfieldval("MailFrom", 'email_from', $object->email_from, $object, $user->rights->mailing->creer && $object->statut < 3, 'string');
			print '</td></tr>';
			// To
			print '<tr><td>' . $form->editfieldkey("MailErrorsTo", 'email_errorsto', $object->email_errorsto, $object, $user->rights->mailing->creer && $object->statut < 3, 'string') . '</td><td colspan="3">';
			print $form->editfieldval("MailErrorsTo", 'email_errorsto', $object->email_errorsto, $object, $user->rights->mailing->creer && $object->statut < 3, 'string');
			print '</td></tr>';

			// Nb of distinct emails
			print '<tr><td>';
			print $langs->trans("TotalNbOfDistinctRecipients");
			print '</td><td colspan="3">';
			$nbemail = ($object->nbemail ? $object->nbemail : 0);
			if (is_numeric($nbemail)) {
				$text = '';
				if ((!empty($conf->global->MAILING_LIMIT_SENDBYWEB) && $conf->global->MAILING_LIMIT_SENDBYWEB < $nbemail) && ($object->statut == 1 || $object->statut == 2)) {
					if ($conf->global->MAILING_LIMIT_SENDBYWEB > 0) {
						$text .= $langs->trans('LimitSendingEmailing', $conf->global->MAILING_LIMIT_SENDBYWEB);
					} else {
						$text .= $langs->trans('SendingFromWebInterfaceIsNotAllowed');
					}
				}
				if (empty($nbemail)) $nbemail .= ' ' . img_warning('') . ' <font class="warning"><a href="'.DOL_URL_ROOT.'/custom/mailingreworked/cibles.php?id='.$object->id.'">' . $langs->trans("NoTargetYet") . '</a></font>';
				if ($text) {
					print $form->textwithpicto($nbemail, $text, 1, 'warning');
				} else {
					print $nbemail;
						if ($nbemail > 0) print ' - <a href="'.DOL_URL_ROOT.'/custom/mailingreworked/cibles.php?id='.$object->id.'"><strong>' . $langs->trans("BackToCibles") . '</strong></a>';
				}
			}
			print '</td></tr>';

			// Other attributes
			$parameters = array();
			$reshook = $hookmanager->executeHooks('formObjectOptions', $parameters, $object, $action);    // Note that $action and $object may have been modified by hook
			print $hookmanager->resPrint;
			if (empty($reshook)) {
				print $object->showOptionals($extrafields, 'edit');
			}

			print '</table>';
			print '</div>';

			dol_fiche_end();


			print "<br>\n";


			// Clone confirmation
			if ($action == 'clone')
			{
				// Create an array for form
				$formquestion=array(
					'text' => $langs->trans("ConfirmClone"),
					array('type' => 'checkbox', 'name' => 'clone_content',   'label' => $langs->trans("CloneContent"),   'value' => 1),
					array('type' => 'checkbox', 'name' => 'clone_receivers', 'label' => $langs->trans("CloneReceivers"), 'value' => 0)
				);
				// Paiement incomplet. On demande si motif = escompte ou autre
				print $form->formconfirm($_SERVER["PHP_SELF"].'?id='.$object->id,$langs->trans('CloneEMailing'),$langs->trans('ConfirmCloneEMailing',$object->ref),'confirm_clone',$formquestion,'yes',2,240);
			}

			/*
			 * Boutons d'action depuis le mode edit
			 */
			print "\n\n<div class=\"tabsAction\">\n";


			if (($object->statut == 0 || $object->statut == 1) && $user->rights->mailing->creer) {
if ($action=='edithtml') {
	if (!empty($conf->use_javascript_ajax)) print '<a class="buttonStatic" style="color :#FFFFFF; float :none" href="' . $_SERVER['PHP_SELF'] . '?id=' . $object->id . '"> <i class="fa fa-undo"></i>&nbsp;&nbsp; '.$langs->trans("BackToEditor") . '</i></a>';
}else {
	if (!empty($conf->use_javascript_ajax)) print '<a class="buttonStatic" style="color :#FFFFFF; float :none" href="' . $_SERVER['PHP_SELF'] . '?action=edithtml&amp;id=' . $object->id . '">' . $langs->trans("EditHTMLSource") . '</a>';
}
			}

			//print '<a class="butAction" href="card.php?action=test&amp;id='.$object->id.'">'.$langs->trans("PreviewMailing").'</a>';

			if (!empty($conf->global->MAIN_USE_ADVANCED_PERMS) && !$user->rights->mailing->mailing_advance->send) {
				print '<a class="butActionRefused" href="#" title="' . dol_escape_htmltag($langs->transnoentitiesnoconv("NotEnoughPermissions")) . '">' . $langs->trans("TestMailing") . '</a>';
			} else {
				if ($action != 'test')
				print '<a class="buttonStatic" style="color :#FFFFFF; float :none" href="' . $_SERVER['PHP_SELF'] . '?action=test&amp;id=' . $object->id . '">' . $langs->trans("TestMailing") . '</a>';
			}

			if ($user->rights->mailing->creer)
			{
				print '<a class="buttonStatic" style="color :#FFFFFF; float :none" href="'.$_SERVER['PHP_SELF'].'?action=clone&amp;object=emailing&amp;id='.$object->id.'">'.$langs->trans("ToClone").'</a>';
			}

			if (($object->statut == 2 || $object->statut == 3) && $user->rights->mailing->valider)
			{
				if (! empty($conf->global->MAIN_USE_ADVANCED_PERMS) && ! $user->rights->mailing->mailing_advance->send)
				{
					print '<a class="butActionRefused" href="#" title="'.dol_escape_htmltag($langs->transnoentitiesnoconv("NotEnoughPermissions")).'">'.$langs->trans("ResetMailing").'</a>';
				}
				else
				{
					print '<a class="buttonStatic" style="color :#FFFFFF; float :none" href="'.$_SERVER['PHP_SELF'].'?action=reset&amp;id='.$object->id.'">'.$langs->trans("ResetMailing").'</a>';
				}
			}

			if (($object->statut <= 1 && $user->rights->mailing->creer) || $user->rights->mailing->supprimer)
			{
				if ($object->statut > 0 && (! empty($conf->global->MAIN_USE_ADVANCED_PERMS) && ! $user->rights->mailing->mailing_advance->delete))
				{
					print '<a class="butActionRefused" href="#" title="'.dol_escape_htmltag($langs->transnoentitiesnoconv("NotEnoughPermissions")).'">'.$langs->trans("DeleteMailing").'</a>';
				}
				else
				{
					print '<a class="buttonRed" style="color :#FFFFFF; float :none" href="'.$_SERVER['PHP_SELF'].'?action=delete&amp;id='.$object->id.(! empty($urlfrom) ? '&urlfrom='.$urlfrom : '').'">'.$langs->trans("DeleteMailing").'</a>';
				}
			}

			print '</div>';


			// Affichage formulaire de TEST depuis le mode edition
			if ($action == 'test')
			{
				print '<div id="formmailbeforetitle" name="formmailbeforetitle"></div>';
				print load_fiche_titre($langs->trans("TestMailing"));

				dol_fiche_head(null, '', '', -1);

				// Create l'objet formulaire mail
				include_once DOL_DOCUMENT_ROOT.'/custom/mailingreworked/class/html.formmail.class.php';
				$formmail = new FormMail($db);
				$formmail->fromname = $object->email_from;
				$formmail->frommail = $object->email_from;
				$formmail->withsubstit=1;
				$formmail->withfrom=0;
				$formmail->withto=$user->email?$user->email:1;
				$formmail->withtocc=0;
				$formmail->withtoccc=$conf->global->MAIN_EMAIL_USECCC;
				$formmail->withtopic=0;
				$formmail->withtopicreadonly=1;
				$formmail->withfile=0;
				$formmail->withbody=0;
				$formmail->withbodyreadonly=1;
				$formmail->withcancel=1;
				$formmail->withdeliveryreceipt=0;
				// Tableau des substitutions
				$formmail->substit=$object->substitutionarrayfortest;
				// Tableau des parametres complementaires du post
				$formmail->param["action"]="send";
				$formmail->param["models"]='none';
				$formmail->param["mailid"]=$object->id;
				$formmail->param["returnurl"]=$_SERVER['PHP_SELF']."?id=".$object->id;

				print $formmail->get_form();

				print '<br>';

				dol_fiche_end();

				print dol_set_focus('#sendto');
			}



			print '<form name="edit_mailing" action="card.php" method="post" enctype="multipart/form-data">' . "\n";
			print '<input type="hidden" name="token" value="' . $_SESSION['newtoken'] . '">';
			print '<input type="hidden" name="action" value="update">';
			print '<input type="hidden" name="id" value="' . $object->id . '">';

			$htmltext = '<i>' . $langs->trans("FollowingConstantsWillBeSubstituted") . ':<br>';
			foreach ($object->substitutionarray as $key => $val) {
				$htmltext .= $key . ' = ' . $langs->trans($val) . '<br>';
			}
			$htmltext .= '</i>';

			// Print mail content
			print load_fiche_titre($langs->trans("EMail"), $form->textwithpicto($langs->trans("AvailableVariables"), $htmltext, 1, 'help', '', 0, 2, 'emailsubstitionhelp'), 'title_generic');

			dol_fiche_head(null, '', '', -1);

			print '<table class="bordernooddeven" width="100%">';

			// Subject
			print '<tr><td class="fieldrequired titlefield">' . $langs->trans("MailTopic") . '</td><td colspan="3"><input class="flat quatrevingtpercent" type="text" name="sujet" value="' . $object->sujet . '"></td></tr>';

			$trackid = ''; // TODO To avoid conflicts with 2 mass emailing, we should set a trackid here, even if we use another one into email header.
			dol_init_file_process($upload_dir, $trackid);

			// Joined files
			$addfileaction = 'addfile';
			print '<tr><td>' . $langs->trans("MailFile") . '</td>';
			print '<td colspan="3">';
			// List of files
			$listofpaths = dol_dir_list($upload_dir, 'all', 0, '', '', 'name', SORT_ASC, 0);

			// TODO Trick to have param removedfile containing nb of image to delete. But this does not works without javascript
			$out .= '<input type="hidden" class="removedfilehidden" name="removedfile" value="">' . "\n";
			$out .= '<script type="text/javascript" language="javascript">';
			$out .= 'jQuery(document).ready(function () {';
			$out .= '    jQuery(".removedfile").click(function() {';
			$out .= '        jQuery(".removedfilehidden").val(jQuery(this).val());';
			$out .= '    });';
			$out .= '})';
			$out .= '</script>' . "\n";
			if (count($listofpaths)) {
				foreach ($listofpaths as $key => $val) {
					$out .= '<div id="attachfile_' . $key . '">';
					$out .= img_mime($listofpaths[$key]['name']) . ' ' . $listofpaths[$key]['name'];
					$out .= ' <input type="image" style="border: 0px;" src="' . img_picto($langs->trans("Search"), 'delete.png', '', '', 1) . '" value="' . ($key + 1) . '" class="removedfile" id="removedfile_' . $key . '" name="removedfile_' . $key . '" />';
					$out .= '<br></div>';
				}
			} else {
				$out .= $langs->trans("NoAttachedFiles") . '<br>';
			}
			// Add link to add file
			$out .= '<input type="file" class="flat" id="addedfile" name="addedfile" value="' . $langs->trans("Upload") . '" />';
			$out .= ' ';
			$out .= '<input type="submit" class="button" id="' . $addfileaction . '" name="' . $addfileaction . '" value="' . $langs->trans("MailingAddFile") . '" />';
			print $out;
			print '</td></tr>';

			// Background color
			//print '<tr><td>' . $langs->trans("BackgroundColorByDefault") . '</td><td colspan="3">';
			//print $htmlother->selectColor($object->bgcolor, 'bgcolor', '', 0);
			//	print '</td></tr>';

			print '</table>';

			// Message
			if ($action == 'edithtml')
			{
				// Editor HTML source
				require_once DOL_DOCUMENT_ROOT.'/core/class/doleditor.class.php';
				$doleditor=new DolEditor('bodyemail',$object->body,'',600,'full','',true,true,'ace',20,'90%');
				$doleditor->Create(0, '', false, 'HTML Source', 'php');
			} else {
				print '<div style="padding-top: 10px">';

				// Editeur wysiwyg
				require_once DOL_DOCUMENT_ROOT . '/custom/mailingreworked/class/doleditor.class.php';
				$doleditor = new DolEditor('bodyemail', $object->body, '', 600, 'full', '', true, true, $conf->global->FCKEDITOR_ENABLE_MAILING, 20, '90%');
				$doleditor->Create();
			}
			print '</div>';




			dol_fiche_end();

			print '<div class="center">';
			print '<input type="submit" class="button buttonforacesave" value="' . $langs->trans("Save") . '" name="save">';
			print '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;';
			print '<a class="button" style="color:#FFFFFF; margin-top: 0.95%" href="'.DOL_URL_ROOT.'/custom/mailingreworked/card.php?id='.$object->id.'&action=visu">'.$langs->trans("Cancel").'</a>';
			//print '<input type="submit" class="button" value="' . $langs->trans("Cancel") . '" name="cancel">';
			print '</div>';

			print '</form>';
			print '<br>';

			//dol_fiche_head($head, 'card', $langs->trans("Mailing"), -1, 'email');


		}
/*
 * Email en mode visualisation
 * (action = visu)
 */
		else/*if ($action == 'visu')*/ {

			dol_fiche_head($head, 'visu', $langs->trans("Mailing"), -1, 'email');

			$linkback = '<a href="' . DOL_URL_ROOT . '/custom/mailingreworked/list.php?restore_lastsearch_values=1">' . $langs->trans("BackToList") . '</a>';

			$morehtmlright = '';
			$nbtry = $nbok = 0;
			if ($object->statut == 2 || $object->statut == 3) {
				$nbtry = $object->countNbOfTargets('alreadysent');
				$nbko = $object->countNbOfTargets('alreadysentko');

				$morehtmlright .= ' (' . $nbtry . '/' . $object->nbemail;
				if ($nbko) $morehtmlright .= ' - ' . $nbko . ' ' . $langs->trans("Error");
				$morehtmlright .= ') &nbsp; ';
			}

			dol_banner_tab($object, 'id', $linkback, 1, 'rowid', 'ref', '', '', 0, '', $morehtmlright);
			print '<div class="refid" style="margin-left: 8%;margin-bottom: 2%;margin-top: -3%;">'.$object->sujet.'</div>';

			print '<a class="buttonStatic" style="color: #FFFFFF; margin-bottom: 1%; margin-top: -2%" href='.DOL_URL_ROOT."/custom/mailingreworked/card.php?id=".$object->id.'>	<i class="fa fa-edit fa-1g"></i> &nbsp&nbsp'.$langs->trans("EditMail").'</a>';
			print '<div class="fichecenter">';
			print '<div class="underbanner clearboth"></div>';


			print '<table class="border" width="100%">';

			// Description
			print '<tr><td class="titlefield">' . $langs->trans("MailTitle") . '</td><td colspan="3">' . $object->titre . '</td></tr>';

			// From
			print '<tr><td class="titlefield">' . $langs->trans("MailFrom") . '</td><td colspan="3">' . dol_print_email($object->email_from, 0, 0, 0, 0, 1) . '</td></tr>';

			// Errors to
			print '<tr><td>' . $langs->trans("MailErrorsTo") . '</td><td colspan="3">' . dol_print_email($object->email_errorsto, 0, 0, 0, 0, 1) . '</td></tr>';


			// Nb of distinct emails
			print '<tr><td>';
			print $langs->trans("TotalNbOfDistinctRecipients");
			print '</td><td colspan="3">';
			$nbemail = ($object->nbemail ? $object->nbemail : 0);
			if (is_numeric($nbemail)) {
				$text = '';
				if ((!empty($conf->global->MAILING_LIMIT_SENDBYWEB) && $conf->global->MAILING_LIMIT_SENDBYWEB < $nbemail) && ($object->statut == 1 || ($object->statut == 2 && $nbtry < $nbemail))) {
					if ($conf->global->MAILING_LIMIT_SENDBYWEB > 0) {
						$text .= $langs->trans('LimitSendingEmailing', $conf->global->MAILING_LIMIT_SENDBYWEB);
					} else {
						$text .= $langs->trans('SendingFromWebInterfaceIsNotAllowed');
					}
				}
				if (empty($nbemail)) $nbemail .= ' ' . img_warning('') . ' <font class="warning"><a href="'.DOL_URL_ROOT.'/custom/mailingreworked/cibles.php?id='.$object->id.'">' . $langs->trans("NoTargetYet") . '</a></font>';
				if ($text) {
					print $form->textwithpicto($nbemail, $text, 1, 'warning');
				} else {
					print $nbemail;
					if ($nbemail > 0) print ' - <a href="'.DOL_URL_ROOT.'/custom/mailingreworked/cibles.php?id='.$object->id.'"><strong>' . $langs->trans("BackToCibles") . '</strong></a>';
				}
			}
			print '</td></tr>';

			// Other attributes
			include DOL_DOCUMENT_ROOT . '/core/tpl/extrafields_view.tpl.php';

			print '</table>';

			print "</div>";

			dol_fiche_end();

			print'<br/>';




			// Clone confirmation
			if ($action == 'clone_from_visu')
			{
				// Create an array for form
				$formquestion=array(
					'text' => $langs->trans("ConfirmClone"),
					array('type' => 'checkbox', 'name' => 'clone_content',   'label' => $langs->trans("CloneContent"),   'value' => 1),
					array('type' => 'checkbox', 'name' => 'clone_receivers', 'label' => $langs->trans("CloneReceivers"), 'value' => 0)
				);
				// Paiement incomplet. On demande si motif = escompte ou autre
				print $form->formconfirm($_SERVER["PHP_SELF"].'?id='.$object->id,$langs->trans('CloneEMailing'),$langs->trans('ConfirmCloneEMailing',$object->ref),'confirm_clone_from_visu',$formquestion,'yes',2,240);
			}

			/*
 			* Boutons d'action
 			*/

			if (GETPOST('cancel','alpha') || $confirm=='no' || $action == 'visu' || in_array($action,array('settodraft','valid','delete_from_visu','sendall','clone_from_visu','testFromVisu'))) {
				print "\n\n<div class=\"tabsAction\">\n";

				//print '<a class="butAction" href="card.php?action=test&amp;id='.$object->id.'">'.$langs->trans("PreviewMailing").'</a>';

				if (!empty($conf->global->MAIN_USE_ADVANCED_PERMS) && !$user->rights->mailing->mailing_advance->send) {
					print '<a class="butActionRefused" href="#" title="' . dol_escape_htmltag($langs->transnoentitiesnoconv("NotEnoughPermissions")) . '">' . $langs->trans("TestMailing") . '</a>';
				} else {
					if ($action != 'testFromVisu')
					print '<a class="buttonStatic" style="color :#FFFFFF; float :none"  href="' . $_SERVER['PHP_SELF'] . '?action=testFromVisu&amp;id=' . $object->id . '">' . $langs->trans("TestMailing") . '</a>';
				}

				if ($user->rights->mailing->creer)
				{
					print '<a class="buttonStatic" style="color :#FFFFFF; float :none"  href="'.$_SERVER['PHP_SELF'].'?action=clone_from_visu&amp;object=emailing&amp;id='.$object->id.'">'.$langs->trans("ToClone").'</a>';
				}

				if (($object->statut == 2 || $object->statut == 3) && $user->rights->mailing->valider)
				{
					if (! empty($conf->global->MAIN_USE_ADVANCED_PERMS) && ! $user->rights->mailing->mailing_advance->send)
					{
						print '<a class="butActionRefused" href="#" title="'.dol_escape_htmltag($langs->transnoentitiesnoconv("NotEnoughPermissions")).'">'.$langs->trans("ResetMailing").'</a>';
					}
					else
					{
						print '<a class="buttonStatic" style="color :#FFFFFF; float :none"  href="'.$_SERVER['PHP_SELF'].'?action=reset&amp;id='.$object->id.'">'.$langs->trans("ResetMailing").'</a>';
					}
				}

				if (($object->statut <= 1 && $user->rights->mailing->creer) || $user->rights->mailing->supprimer)
				{
					if ($object->statut > 0 && (! empty($conf->global->MAIN_USE_ADVANCED_PERMS) && ! $user->rights->mailing->mailing_advance->delete))
					{
						print '<a class="butActionRefused" href="#" title="'.dol_escape_htmltag($langs->transnoentitiesnoconv("NotEnoughPermissions")).'">'.$langs->trans("DeleteMailing").'</a>';
					}
					else
					{
						print '<a class="buttonRed" style="color :#FFFFFF; float :none"  href="'.$_SERVER['PHP_SELF'].'?action=delete_from_visu&amp;id='.$object->id.(! empty($urlfrom) ? '&urlfrom='.$urlfrom : '').'">'.$langs->trans("DeleteMailing").'</a>';
					}
				}

				print '</div>';

				// Affichage formulaire de TEST depuis la visualisation
				if ($action =='testFromVisu')
				{
					print '<div id="formmailbeforetitle" name="formmailbeforetitle"></div>';
					print load_fiche_titre($langs->trans("TestMailing"));

					dol_fiche_head(null, '', '', -1);

					// Create l'objet formulaire mail
					include_once DOL_DOCUMENT_ROOT.'/custom/mailingreworked/class/html.formmail.class.php';
					$formmail = new FormMail($db);
					$formmail->fromname = $object->email_from;
					$formmail->frommail = $object->email_from;
					$formmail->withsubstit=1;
					$formmail->withfrom=0;
					$formmail->withto=$user->email?$user->email:1;
					$formmail->withtocc=0;
					$formmail->withtoccc=$conf->global->MAIN_EMAIL_USECCC;
					$formmail->withtopic=0;
					$formmail->withtopicreadonly=1;
					$formmail->withfile=0;
					$formmail->withbody=0;
					$formmail->withbodyreadonly=1;
					$formmail->withcancel=1;
					$formmail->withdeliveryreceipt=0;
					// Tableau des substitutions
					$formmail->substit=$object->substitutionarrayfortest;
					// Tableau des parametres complementaires du post
					$formmail->param["action"]="sendFromVisu";
					$formmail->param["models"]='none';
					$formmail->param["mailid"]=$object->id;
					$formmail->param["returnurl"]=$_SERVER['PHP_SELF']."?&id=".$object->id;

					print $formmail->get_form();

					print '<br>';

					dol_fiche_end();

					print dol_set_focus('#sendto');
				}
			}


			$htmltext = '<i>' . $langs->trans("FollowingConstantsWillBeSubstituted") . ':<br>';
			foreach ($object->substitutionarray as $key => $val) {
				$htmltext .= $key . ' = ' . $langs->trans($val) . '<br>';
			}
			$htmltext .= '</i>';

			// Print mail content
			print load_fiche_titre($langs->trans("EMail"), $form->textwithpicto('<span class="hideonsmartphone">' . $langs->trans("AvailableVariables") . '</span>', $htmltext, 1, 'help', '', 0, 2, 'emailsubstitionhelp'), 'title_generic');

			dol_fiche_head('', '', '', -1);

			print '<table class="bordernooddeven" width="100%">';

			// Subject
			print '<tr><td class="titlefield">' . $langs->trans("MailTopic") . '</td><td colspan="3">' . $object->sujet . '</td></tr>';

			// Joined files
			print '<tr><td>' . $langs->trans("MailFile") . '</td><td colspan="3">';
			// List of files
			$listofpaths = dol_dir_list($upload_dir, 'all', 0, '', '', 'name', SORT_ASC, 0);
			if (count($listofpaths)) {
				foreach ($listofpaths as $key => $val) {
					print img_mime($listofpaths[$key]['name']) . ' ' . $listofpaths[$key]['name'];
					print '<br>';
				}
			} else {
				print '<span class="opacitymedium">' . $langs->trans("NoAttachedFiles") . '</span><br>';
			}
			print '</td></tr>';

			print '</table>';

			// Message
			print '<div style="padding-top: 10px; background: ' . ($object->bgcolor ? (preg_match('/^#/', $object->bgcolor) ? '' : '#') . $object->bgcolor : 'white') . '">';
			if (empty($object->bgcolor) || strtolower($object->bgcolor) == 'ffffff')    // CKEditor does not apply the color of the div into its content area
			{
				//print '<textarea readonly rows="20" cols="130">'.$object->body.'</textarea>';
				print '<div style="border:1px solid rgb(224,224,225)">'.$object->body.'</div>';
				/*$readonly = 1;
				// Editeur wysiwyg
				require_once DOL_DOCUMENT_ROOT . '/core/class/doleditor.class.php';
				$doleditor = new DolEditor('bodyemail', $object->body, '', 600, 'dolibarr_mailings', '', false, true, empty($conf->global->FCKEDITOR_ENABLE_MAILING) ? 0 : 1, 20, '90%', $readonly);
				$doleditor->Create();*/
			} else print dol_htmlentitiesbr($object->body);
			print '</div>';
			print '<br/>';
			//print '<a class="button" style="color: #FFFFFF" href="' . DOL_URL_ROOT . '/custom/mailingreworked/cibles.php?id=' . $id . '">' . $langs->trans('MailRecipients') . '</a>';



			//print '<a class="buttonStatic" style="color:#FFFFFF; background-color: rgb(96, 217, 204)" href="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'&action=valid"><i class="fa fa-paper-plane"></i>&nbsp&nbsp '.$langs->trans("Send").'</a>';

/*
 * Vérifie qu'il y ait au moins un destinataire avant d'envoyer
 */
			if ($object->statut == 0)
			{
				if ($object->nbemail <= 0)
				{
					print '<a class="butActionRefused" style="float: right" href="#" title="'.dol_escape_htmltag($langs->transnoentitiesnoconv("NoTargetYet")).'">'.$langs->trans("ValidMailing").'</a>';
				}
				else if (empty($user->rights->mailing->valider))
				{
					print '<a class="butActionRefused" style="float: right" href="#" title="'.dol_escape_htmltag($langs->transnoentitiesnoconv("NotEnoughPermissions")).'">'.$langs->trans("ValidMailing").'</a>';
				}
				else
				{
					print '<a class="buttonStatic" style="color:#FFFFFF; background-color: rgb(96, 217, 204)" href="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'&action=valid"><i class="fa fa-paper-plane"></i>&nbsp&nbsp '.$langs->trans("Send").'</a>';
					//print '<a class="butAction" href="'.$_SERVER['PHP_SELF'].'?action=valid&amp;id='.$object->id.'">'.$langs->trans("ValidMailing").'</a>';
				}

			}



			/*
			 * Renvoie le mail (à retravailler)
			 *//*
			if (($object->statut == 2 || $object->statut == 3) && $user->rights->mailing->valider)
			{
				if (! empty($conf->global->MAIN_USE_ADVANCED_PERMS) && ! $user->rights->mailing->mailing_advance->send)
				{
					print '<a class="butActionRefused" style="float: right" href="#" title="'.dol_escape_htmltag($langs->transnoentitiesnoconv("NotEnoughPermissions")).'">'.$langs->trans("ResetMailing").'</a>';
				}
				else
				{
					print '<a class="buttonStatic" style="color:#FFFFFF; background-color: rgb(96, 217, 204)" href="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'&action=reset"><i class="fa fa-paper-plane"></i>&nbsp&nbsp '.$langs->trans("ResetMailing").'</a>';
				}
			}*/



			dol_fiche_end();

			if ($action == 'sendall') {
				// Define message to recommand from command line
				$sendingmode = $conf->global->EMAILING_MAIL_SENDMODE;
				if (empty($sendingmode)) $sendingmode = $conf->global->MAIN_MAIL_SENDMODE;
				if (empty($sendingmode)) $sendingmode = 'mail';    // If not defined, we use php mail function

				// MAILING_NO_USING_PHPMAIL may be defined or not.
				// MAILING_LIMIT_SENDBYWEB is always defined to something != 0 (-1=forbidden).
				// MAILING_LIMIT_SENDBYCLI may be defined ot not (-1=forbidden, 0 or undefined=no limit).
				if (!empty($conf->global->MAILING_NO_USING_PHPMAIL) && $sendingmode == 'mail') {
					// EMailing feature may be a spam problem, so when you host several users/instance, having this option may force each user to use their own SMTP agent.
					// You ensure that every user is using its own SMTP server when using the mass emailing module.
					$linktoadminemailbefore = '<a href="' . DOL_URL_ROOT . '/admin/mails.php">';
					$linktoadminemailend = '</a>';
					setEventMessages($langs->trans("MailSendSetupIs", $listofmethods[$sendingmode]), null, 'warnings');
					setEventMessages($langs->trans("MailSendSetupIs2", $linktoadminemailbefore, $linktoadminemailend, $langs->transnoentitiesnoconv("MAIN_MAIL_SENDMODE"), $listofmethods['smtps']), null, 'warnings');
					if (!empty($conf->global->MAILING_SMTP_SETUP_EMAILS_FOR_QUESTIONS)) setEventMessages($langs->trans("MailSendSetupIs3", $conf->global->MAILING_SMTP_SETUP_EMAILS_FOR_QUESTIONS), null, 'warnings');
					$_GET["action"] = '';
				} else if ($conf->global->MAILING_LIMIT_SENDBYWEB < 0) {
					if (!empty($conf->global->MAILING_LIMIT_WARNING_PHPMAIL) && $sendingmode == 'mail') setEventMessages($langs->transnoentitiesnoconv($conf->global->MAILING_LIMIT_WARNING_PHPMAIL), null, 'warnings');
					if (!empty($conf->global->MAILING_LIMIT_WARNING_NOPHPMAIL) && $sendingmode != 'mail') setEventMessages($langs->transnoentitiesnoconv($conf->global->MAILING_LIMIT_WARNING_NOPHPMAIL), null, 'warnings');

					// The feature is forbidden from GUI, we show just message to use from command line.
					setEventMessages($langs->trans("MailingNeedCommand"), null, 'warnings');
					setEventMessages('<textarea cols="60" rows="' . ROWS_1 . '" wrap="soft">php ./scripts/emailings/mailing-send.php ' . $object->id . '</textarea>', null, 'warnings');
					if ($conf->file->mailing_limit_sendbyweb != '-1')  // MAILING_LIMIT_SENDBYWEB was set to -1 in database, but it is allowed ot increase it.
					{
						setEventMessages($langs->trans("MailingNeedCommand2"), null, 'warnings');  // You can send online with constant...
					}
					$_GET["action"] = '';
				} else {
					if (!empty($conf->global->MAILING_LIMIT_WARNING_PHPMAIL) && $sendingmode == 'mail') setEventMessages($langs->transnoentitiesnoconv($conf->global->MAILING_LIMIT_WARNING_PHPMAIL), null, 'warnings');
					if (!empty($conf->global->MAILING_LIMIT_WARNING_NOPHPMAIL) && $sendingmode != 'mail') setEventMessages($langs->transnoentitiesnoconv($conf->global->MAILING_LIMIT_WARNING_NOPHPMAIL), null, 'warnings');

					$text = '';
					if ($conf->global->MAILING_LIMIT_SENDBYCLI >= 0) {
						$text .= $langs->trans("MailingNeedCommand");
						$text .= '<br><textarea cols="60" rows="' . ROWS_2 . '" wrap="soft">php ./scripts/emailings/mailing-send.php ' . $object->id . ' ' . $user->login . '</textarea>';
						$text .= '<br><br>';
					}
					$text .= $langs->trans('ConfirmSendingEmailing') . '<br>';
					$text .= $langs->trans('LimitSendingEmailing', $conf->global->MAILING_LIMIT_SENDBYWEB);
					$confirm = 'yes';
					//header("Location: " . $_SERVER['PHP_SELF'] . '?id=' . $object->id."&action=sendallconfirmed");
					//print $form->formconfirm($_SERVER['PHP_SELF'] . '?id=' . $object->id, $langs->trans('SendMailing'), $text, 'sendallconfirmed', $formquestion, '', 1, 330, 600);
					//ORIGINALEMENT ACTION SENDALLCONFIRMED, ICI, LES ACTIONS VALIDATE,CONFIRMVALIDATE, SEND ET SENDALLCONFIRM SONT TOUTES EFFECTUEES L'UNE APRES L'AUTRE POUR
					//FACILITER L'UTILISATION
					if (empty($conf->global->MAILING_LIMIT_SENDBYWEB)) {
						// As security measure, we don't allow send from the GUI
						setEventMessages($langs->trans("MailingNeedCommand"), null, 'warnings');
						setEventMessages('<textarea cols="70" rows="' . ROWS_2 . '" wrap="soft">php ./scripts/emailings/mailing-send.php ' . $object->id . '</textarea>', null, 'warnings');
						setEventMessages($langs->trans("MailingNeedCommand2"), null, 'warnings');
						$action = '';
					} else if ($conf->global->MAILING_LIMIT_SENDBYWEB < 0) {
						setEventMessages($langs->trans("NotEnoughPermissions"), null, 'warnings');
						$action = '';
					} else {
						$upload_dir = $conf->mailing->dir_output . "/" . get_exdir($object->id, 2, 0, 1, $object, 'mailing');

						if ($object->statut == 0) {
							dol_print_error('', 'ErrorMailIsNotValidated');
							exit;
						}

						$id = $object->id;
						$subject = $object->sujet;
						$message = $object->body;
						$from = $object->email_from;
						$replyto = $object->email_replyto;
						$errorsto = $object->email_errorsto;
						// Le message est-il en html
						$msgishtml = -1;    // Unknown by default
						if (preg_match('/[\s\t]*<html>/i', $message)) $msgishtml = 1;

						// Warning, we must not use begin-commit transaction here
						// because we want to save update for each mail sent.

						$nbok = 0;
						$nbko = 0;

						// On choisit les mails non deja envoyes pour ce mailing (statut=0)
						// ou envoyes en erreur (statut=-1)
						$sql = "SELECT mc.rowid, mc.fk_mailing, mc.lastname, mc.firstname, mc.email, mc.other, mc.source_url, mc.source_id, mc.source_type, mc.tag";
						$sql .= " FROM " . MAIN_DB_PREFIX . "mailing_cibles as mc";
						$sql .= " WHERE mc.statut < 1 AND mc.fk_mailing = " . $object->id;
						$sql .= " ORDER BY mc.statut DESC";        // first status 0, then status -1

						dol_syslog("card.php: select targets", LOG_DEBUG);
						$resql = $db->query($sql);
						if ($resql) {
							$num = $db->num_rows($resql);    // nb of possible recipients

							if ($num) {
								dol_syslog("comm/mailing/card.php: nb of targets = " . $num, LOG_DEBUG);

								$now = dol_now();

								// Positionne date debut envoi
								$sql = "UPDATE " . MAIN_DB_PREFIX . "mailing SET date_envoi='" . $db->idate($now) . "' WHERE rowid=" . $object->id;
								$resql2 = $db->query($sql);
								if (!$resql2) {
									dol_print_error($db);
								}

								// Loop on each email and send it
								$i = 0;

								while ($i < $num && $i < $conf->global->MAILING_LIMIT_SENDBYWEB) {
									// Here code is common with same loop ino mailing-send.php
									$res = 1;
									$now = dol_now();

									$obj = $db->fetch_object($resql);

									// sendto en RFC2822
									$sendto = str_replace(',', ' ', dolGetFirstLastname($obj->firstname, $obj->lastname)) . " <" . $obj->email . ">";

									// Make substitutions on topic and body. From (AA=YY;BB=CC;...) we keep YY, CC, ...
									$other = explode(';', $obj->other);
									$tmpfield = explode('=', $other[0], 2);
									$other1 = (isset($tmpfield[1]) ? $tmpfield[1] : $tmpfield[0]);
									$tmpfield = explode('=', $other[1], 2);
									$other2 = (isset($tmpfield[1]) ? $tmpfield[1] : $tmpfield[0]);
									$tmpfield = explode('=', $other[2], 2);
									$other3 = (isset($tmpfield[1]) ? $tmpfield[1] : $tmpfield[0]);
									$tmpfield = explode('=', $other[3], 2);
									$other4 = (isset($tmpfield[1]) ? $tmpfield[1] : $tmpfield[0]);
									$tmpfield = explode('=', $other[4], 2);
									$other5 = (isset($tmpfield[1]) ? $tmpfield[1] : $tmpfield[0]);

									$signature = ((!empty($user->signature) && empty($conf->global->MAIN_MAIL_DO_NOT_USE_SIGN)) ? $user->signature : '');

									$targetobject = null;        // Not defined with mass emailing
									$parameters = array('mode' => 'emailing');
									$substitutionarray = getCommonSubstitutionArray($langs, 0, array('object', 'objectamount'), $targetobject);            // Note: On mass emailing, this is null because be don't know object

									// Array of possible substitutions (See also file mailing-send.php that should manage same substitutions)
									$substitutionarray['__ID__'] = $obj->source_id;
									$substitutionarray['__EMAIL__'] = $obj->email;
									$substitutionarray['__LASTNAME__'] = $obj->lastname;
									$substitutionarray['__FIRSTNAME__'] = $obj->firstname;
									$substitutionarray['__MAILTOEMAIL__'] = '<a href="mailto:' . $obj->email . '">' . $obj->email . '</a>';
									$substitutionarray['__OTHER1__'] = $other1;
									$substitutionarray['__OTHER2__'] = $other2;
									$substitutionarray['__OTHER3__'] = $other3;
									$substitutionarray['__OTHER4__'] = $other4;
									$substitutionarray['__OTHER5__'] = $other5;
									$substitutionarray['__USER_SIGNATURE__'] = $signature;    // Signature is empty when ran from command line or taken from user in parameter)
									$substitutionarray['__SIGNATURE__'] = $signature;    // For backward compatibility
									$substitutionarray['__CHECK_READ__'] = '<img src="' . DOL_MAIN_URL_ROOT . '/public/emailing/mailing-read.php?tag=' . $obj->tag . '&securitykey=' . urlencode($conf->global->MAILING_EMAIL_UNSUBSCRIBE_KEY) . '" width="1" height="1" style="width:1px;height:1px" border="0"/>';
									$substitutionarray['__UNSUBSCRIBE__'] = '<a href="' . DOL_MAIN_URL_ROOT . '/public/emailing/mailing-unsubscribe.php?tag=' . $obj->tag . '&unsuscrib=1&securitykey=' . urlencode($conf->global->MAILING_EMAIL_UNSUBSCRIBE_KEY) . '" target="_blank">' . $langs->trans("MailUnsubcribe") . '</a>';

									$onlinepaymentenabled = 0;
									if (!empty($conf->paypal->enabled)) $onlinepaymentenabled++;
									if (!empty($conf->paybox->enabled)) $onlinepaymentenabled++;
									if (!empty($conf->stripe->enabled)) $onlinepaymentenabled++;
									if ($onlinepaymentenabled && !empty($conf->global->PAYMENT_SECURITY_TOKEN)) {
										$substitutionarray['__SECUREKEYPAYMENT__'] = dol_hash($conf->global->PAYMENT_SECURITY_TOKEN, 2);
										if (empty($conf->global->PAYMENT_SECURITY_TOKEN_UNIQUE)) {
											$substitutionarray['__SECUREKEYPAYMENT_MEMBER__'] = dol_hash($conf->global->PAYMENT_SECURITY_TOKEN, 2);
											$substitutionarray['__SECUREKEYPAYMENT_ORDER__'] = dol_hash($conf->global->PAYMENT_SECURITY_TOKEN, 2);
											$substitutionarray['__SECUREKEYPAYMENT_INVOICE__'] = dol_hash($conf->global->PAYMENT_SECURITY_TOKEN, 2);
											$substitutionarray['__SECUREKEYPAYMENT_CONTRACTLINE__'] = dol_hash($conf->global->PAYMENT_SECURITY_TOKEN, 2);
										} else {
											$substitutionarray['__SECUREKEYPAYMENT_MEMBER__'] = dol_hash($conf->global->PAYMENT_SECURITY_TOKEN . 'membersubscription' . $obj->source_id, 2);
											$substitutionarray['__SECUREKEYPAYMENT_ORDER__'] = dol_hash($conf->global->PAYMENT_SECURITY_TOKEN . 'order' . $obj->source_id, 2);
											$substitutionarray['__SECUREKEYPAYMENT_INVOICE__'] = dol_hash($conf->global->PAYMENT_SECURITY_TOKEN . 'invoice' . $obj->source_id, 2);
											$substitutionarray['__SECUREKEYPAYMENT_CONTRACTLINE__'] = dol_hash($conf->global->PAYMENT_SECURITY_TOKEN . 'contractline' . $obj->source_id, 2);
										}
									}
									/* For backward compatibility, deprecated */
									if (!empty($conf->paypal->enabled) && !empty($conf->global->PAYPAL_SECURITY_TOKEN)) {
										$substitutionarray['__SECUREKEYPAYPAL__'] = dol_hash($conf->global->PAYPAL_SECURITY_TOKEN, 2);

										if (empty($conf->global->PAYPAL_SECURITY_TOKEN_UNIQUE)) $substitutionarray['__SECUREKEYPAYPAL_MEMBER__'] = dol_hash($conf->global->PAYPAL_SECURITY_TOKEN, 2);
										else $substitutionarray['__SECUREKEYPAYPAL_MEMBER__'] = dol_hash($conf->global->PAYPAL_SECURITY_TOKEN . 'membersubscription' . $obj->source_id, 2);

										if (empty($conf->global->PAYPAL_SECURITY_TOKEN_UNIQUE)) $substitutionarray['__SECUREKEYPAYPAL_ORDER__'] = dol_hash($conf->global->PAYPAL_SECURITY_TOKEN, 2);
										else $substitutionarray['__SECUREKEYPAYPAL_ORDER__'] = dol_hash($conf->global->PAYPAL_SECURITY_TOKEN . 'order' . $obj->source_id, 2);

										if (empty($conf->global->PAYPAL_SECURITY_TOKEN_UNIQUE)) $substitutionarray['__SECUREKEYPAYPAL_INVOICE__'] = dol_hash($conf->global->PAYPAL_SECURITY_TOKEN, 2);
										else $substitutionarray['__SECUREKEYPAYPAL_INVOICE__'] = dol_hash($conf->global->PAYPAL_SECURITY_TOKEN . 'invoice' . $obj->source_id, 2);

										if (empty($conf->global->PAYPAL_SECURITY_TOKEN_UNIQUE)) $substitutionarray['__SECUREKEYPAYPAL_CONTRACTLINE__'] = dol_hash($conf->global->PAYPAL_SECURITY_TOKEN, 2);
										else $substitutionarray['__SECUREKEYPAYPAL_CONTRACTLINE__'] = dol_hash($conf->global->PAYPAL_SECURITY_TOKEN . 'contractline' . $obj->source_id, 2);
									}
									//$substitutionisok=true;

									complete_substitutions_array($substitutionarray, $langs);
									$newsubject = make_substitutions($subject, $substitutionarray);
									$newmessage = make_substitutions($message, $substitutionarray);

									$arr_file = array();
									$arr_mime = array();
									$arr_name = array();
									$arr_css = array();

									$listofpaths = dol_dir_list($upload_dir, 'all', 0, '', '', 'name', SORT_ASC, 0);
									if (count($listofpaths)) {
										foreach ($listofpaths as $key => $val) {
											$arr_file[] = $listofpaths[$key]['fullname'];
											$arr_mime[] = dol_mimetype($listofpaths[$key]['name']);
											$arr_name[] = $listofpaths[$key]['name'];
										}
									}

									// Fabrication du mail
									$trackid = 'emailing-' . $obj->fk_mailing . '-' . $obj->rowid;
									$mail = new CMailFile($newsubject, $sendto, $from, $newmessage, $arr_file, $arr_mime, $arr_name, '', '', 0, $msgishtml, $errorsto, $arr_css, $trackid, '', 'emailing');

									if ($mail->error) {
										$res = 0;
									}
									/*if (! $substitutionisok)
									{
										$mail->error='Some substitution failed';
										$res=0;
									}*/

									// Send mail
									if ($res) {
										$res = $mail->sendfile();
									}

									if ($res) {
										// Mail successful
										$nbok++;

										dol_syslog("comm/mailing/card.php: ok for #" . $i . ($mail->error ? ' - ' . $mail->error : ''), LOG_DEBUG);

										$sql = "UPDATE " . MAIN_DB_PREFIX . "mailing_cibles";
										$sql .= " SET statut=1, date_envoi='" . $db->idate($now) . "' WHERE rowid=" . $obj->rowid;
										$resql2 = $db->query($sql);
										if (!$resql2) {
											dol_print_error($db);
										} else {
											//if cheack read is use then update prospect contact status
											if (strpos($message, '__CHECK_READ__') !== false) {
												//Update status communication of thirdparty prospect
												$sql = "UPDATE " . MAIN_DB_PREFIX . "societe SET fk_stcomm=2 WHERE rowid IN (SELECT source_id FROM " . MAIN_DB_PREFIX . "mailing_cibles WHERE rowid=" . $obj->rowid . ")";
												dol_syslog("card.php: set prospect thirdparty status", LOG_DEBUG);
												$resql2 = $db->query($sql);
												if (!$resql2) {
													dol_print_error($db);
												}

												//Update status communication of contact prospect
												$sql = "UPDATE " . MAIN_DB_PREFIX . "societe SET fk_stcomm=2 WHERE rowid IN (SELECT sc.fk_soc FROM " . MAIN_DB_PREFIX . "socpeople AS sc INNER JOIN " . MAIN_DB_PREFIX . "mailing_cibles AS mc ON mc.rowid=" . $obj->rowid . " AND mc.source_type = 'contact' AND mc.source_id = sc.rowid)";
												dol_syslog("card.php: set prospect contact status", LOG_DEBUG);

												$resql2 = $db->query($sql);
												if (!$resql2) {
													dol_print_error($db);
												}
											}
										}

										if (!empty($conf->global->MAILING_DELAY)) {
											sleep($conf->global->MAILING_DELAY);
										}

										//test if CHECK READ change statut prospect contact
									} else {
										// Mail failed
										$nbko++;

										dol_syslog("comm/mailing/card.php: error for #" . $i . ($mail->error ? ' - ' . $mail->error : ''), LOG_WARNING);

										$sql = "UPDATE " . MAIN_DB_PREFIX . "mailing_cibles";
										$sql .= " SET statut=-1, error_text='" . $db->escape($mail->error) . "', date_envoi='" . $db->idate($now) . "' WHERE rowid=" . $obj->rowid;
										$resql2 = $db->query($sql);
										if (!$resql2) {
											dol_print_error($db);
										}
									}

									$i++;
								}
							} else {
								setEventMessages($langs->transnoentitiesnoconv("NoMoreRecipientToSendTo"), null, 'mesgs');
							}

							// Loop finished, set global statut of mail
							if ($nbko > 0) {
								$statut = 2;    // Status 'sent partially' (because at least one error)
								if ($nbok > 0) setEventMessages($langs->transnoentitiesnoconv("EMailSentToNRecipients", $nbok), null, 'mesgs');
								else setEventMessages($langs->transnoentitiesnoconv("EMailSentToNRecipients", $nbok), null, 'mesgs');
							} else {
								if ($nbok >= $num) {
									$statut = 3;    // Send to everybody
									setEventMessages($langs->transnoentitiesnoconv("EMailSentToNRecipients", $nbok), null, 'mesgs');
								} else {
									$statut = 2;    // Status 'sent partially' (because not send to everybody)
									setEventMessages($langs->transnoentitiesnoconv("EMailSentToNRecipients", $nbok), null, 'mesgs');
								}
							}

							$sql = "UPDATE " . MAIN_DB_PREFIX . "mailing SET statut=" . $statut . " WHERE rowid=" . $object->id;
							dol_syslog("comm/mailing/card.php: update global status", LOG_DEBUG);
							$resql2 = $db->query($sql);
							if (!$resql2) {
								dol_print_error($db);
							}
						} else {
							dol_syslog($db->error());
							dol_print_error($db);
						}

						$action = '';
					}

				}
			}
		}
	} else {
		dol_print_error($db, $object->error);
	}
}

// End of page
llxFooter();
$db->close();
