<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2008 Andreas Kiefer <kiefer@kennziffer.com>
*  All rights reserved
*
*  This script is part of the TYPO3 project. The TYPO3 project is
*  free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
*  The GNU General Public License can be found at
*  http://www.gnu.org/copyleft/gpl.html.
*
*  This script is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/

require_once(PATH_tslib.'class.tslib_pibase.php');
// RTE
require_once(t3lib_extMgm::extPath('rtehtmlarea').'pi2/class.tx_rtehtmlarea_pi2.php');
// Mail functions
require_once (PATH_t3lib.'class.t3lib_htmlmail.php');

/**
 * Plugin 'Insite Mails Form' for the 'ke_insitemailbox' extension.
 *
 * @author	Andreas Kiefer <kiefer@kennziffer.com>
 * @package	TYPO3
 * @subpackage	tx_keinsitemailbox
 */
class tx_keinsitemailbox_pi2 extends tslib_pibase {
	var $prefixId      = 'tx_keinsitemailbox_pi2';		// Same as class name
	var $scriptRelPath = 'pi2/class.tx_keinsitemailbox_pi2.php';	// Path to this script relative to the extension dir.
	var $extKey        = 'ke_insitemailbox';	// The extension key.
	var $predefinedRecipient = ''; // Predefined Recipient for new mail
	var $maxAttachments = 1; // How many files are allowed to be attached to a message?
	var $uploadsFolder = 'uploads/tx_keinsitemailbox/'; 	// path where attachments are stored
	var $table = 'tx_keinsitemailbox_messages'; // main table
	
	/* RTE vars */
	var $RTEObj;
	var $strEntryField;
	var $docLarge = 0;
	var $RTEcounter = 0;
	var $formName;
	var $additionalJS_initial = '';		// Initial JavaScript to be printed before the form (should be in head, but cannot due to IE6 timing bug)
	var $additionalJS_pre = array();	// Additional JavaScript to be printed before the form
	var $additionalJS_post = array();	// Additional JavaScript to be printed after the form
	var $additionalJS_submit = array();	// Additional JavaScript to be executed on submit
	var $PA = array(
		'itemFormElName' =>  '',
		'itemFormElValue' => '',
	);
	var $specConf = array();
	var $thisConfig = array();
	var $RTEtypeVal = 'text';
	
	
	
	
	
	/**
	 * The main method of the PlugIn
	 *
	 * @param	string		$content: The PlugIn content
	 * @param	array		$conf: The PlugIn configuration
	 * @return	The content that is displayed on the website
	 */
	function main($content,$conf)	{
		$this->conf=$conf;
		$this->pi_setPiVarDefaults();
		$this->pi_loadLL();
		$this->pi_USER_INT_obj=1;	// Configuring so caching is not expected. This value means that no cHash params are ever set. We do this, because it's a USER_INT object!
		
		
		// overwrite  conf
		$this->conf = $GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_keinsitemailbox.'];
		
		// include html template
		$this->defaultTemplateFile = t3lib_extMgm::siteRelPath($this->extKey).'res/template/template_keinsitemailbox_pi2.html';
		$this->templateFile = $this->conf['templateFile'] ? $this->conf['templateFile'] : $this->defaultTemplateFile;
		$this->templateCode = $this->cObj->fileResource($this->templateFile);		
		
		// include css file
		$this->defaultCSSFile = t3lib_extMgm::siteRelPath($this->extKey).'res/css/ke_insitemailbox.css';
		$cssfile = $this->conf['cssFile'] ? $this->conf['cssFile'] : $this->defaultCSSFile;
		$GLOBALS['TSFE']->additionalHeaderData[$this->prefixId] .= '<link rel="stylesheet" type="text/css" href="'.$cssfile.'" />';
		
		// handle form data 
		if ($this->piVars['submit']) $content = $this->handleFormData();
		// show form
		else $content=$this->showForm();
		
		return $this->pi_wrapInBaseClass($content);
	}
	
	
	
	/**
 	* Print form for creating new messages
	*
	* @return HTML form
 	*/ 
 	function showForm($errors=array()) {
		
		// message is a reply
		if ($this->piVars['reply']) {
			$data = $this->getMessageData($this->piVars['reply']);
			$this->predefinedRecipient = $data['sender'];
			$this->piVars['subject'] = 'RE: '.$data['subject'];
			// wrap the original message
			$this->piVars['bodytext'] = $this->cObj->getSubpart($this->templateCode,'###REPLY_PRE###');
			$this->piVars['bodytext'] = $this->cObj->substituteMarker($this->piVars['bodytext'],'###REPLY_PRE_TEXT###',$this->pi_getLL('reply_pre_text'));
			$this->piVars['bodytext'] = $this->cObj->substituteMarker($this->piVars['bodytext'],'###ORIGINAL_MESSAGE###',$data['bodytext']);
			$this->piVars['bodytext'] = $this->cObj->substituteMarker($this->piVars['bodytext'],'###REPLY_PRE_INFO_FROM###',$this->pi_getLL('label_sender'));
			$this->piVars['bodytext'] = $this->cObj->substituteMarker($this->piVars['bodytext'],'###REPLY_PRE_INFO_DATE###',$this->pi_getLL('label_date'));
			$this->piVars['bodytext'] = $this->cObj->substituteMarker($this->piVars['bodytext'],'###REPLY_PRE_INFO_SENDER###',$this->getUserData($data['sender'], 'username'));
			$this->piVars['bodytext'] = $this->cObj->substituteMarker($this->piVars['bodytext'],'###REPLY_PRE_INFO_SUBJECT_LABEL### ',$this->pi_getLL('label_subject'));
			$this->piVars['bodytext'] = $this->cObj->substituteMarker($this->piVars['bodytext'],'###REPLY_PRE_INFO_SUBJECT###',$data['subject']);
			$senddate = strftime('%d.%m.%y %H:%M', $data['tstamp']);
			$this->piVars['bodytext'] = $this->cObj->substituteMarker($this->piVars['bodytext'],'###REPLY_PRE_INFO_SENDDATE###',$senddate);
		}
		
		// message is forwarded
		if ($this->piVars['forward']) {
			$data = $this->getMessageData($this->piVars['forward']);
			$this->piVars['subject'] = 'FWD: '.$data['subject'];
			// wrap the forwarded message
			$this->piVars['bodytext'] = $this->cObj->getSubpart($this->templateCode,'###FORWARD_PRE###');
			$this->piVars['bodytext'] = $this->cObj->substituteMarker($this->piVars['bodytext'],'###FWD_PRE_TEXT###',$this->pi_getLL('forward_pre_text'));
			$this->piVars['bodytext'] = $this->cObj->substituteMarker($this->piVars['bodytext'],'###FORWARDED_MESSAGE###',$data['bodytext']);
			$this->piVars['bodytext'] = $this->cObj->substituteMarker($this->piVars['bodytext'],'###FWD_PRE_INFO_FROM###',$this->pi_getLL('label_sender'));
			$this->piVars['bodytext'] = $this->cObj->substituteMarker($this->piVars['bodytext'],'###FWD_PRE_INFO_DATE###',$this->pi_getLL('label_date'));
			$this->piVars['bodytext'] = $this->cObj->substituteMarker($this->piVars['bodytext'],'###FWD_PRE_INFO_SENDER###',$this->getUserData($data['sender'], 'username'));
			$this->piVars['bodytext'] = $this->cObj->substituteMarker($this->piVars['bodytext'],'###FWD_PRE_INFO_SUBJECT_LABEL### ',$this->pi_getLL('label_subject'));
			$this->piVars['bodytext'] = $this->cObj->substituteMarker($this->piVars['bodytext'],'###FWD_PRE_INFO_SUBJECT###',$data['subject']);
			$senddate = strftime('%d.%m.%y %H:%M', $data['tstamp']);
			$this->piVars['bodytext'] = $this->cObj->substituteMarker($this->piVars['bodytext'],'###FWD_PRE_INFO_SENDDATE###',$senddate);
		}
		
		// sender chosen as recipient
		if ($this->piVars['sender']) {
			$this->predefinedRecipient = $this->piVars['sender'];
		}
		
		$errorcontent = $this->checkAccess();
		if (!empty($errorcontent)) return $errorcontent;
		
		// Hook for predefined values
		if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['tx_keinsitemailbox']['predefinedRecipientHook'])) {
			foreach($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['tx_keinsitemailbox']['predefinedRecipientHook'] as $_classRef) {
				$_procObj = & t3lib_div::getUserObj($_classRef);
				$_procObj->processPredefinedRecipient($this);
			}
		}
		
		// form action
		$linkconf['parameter'] = $GLOBALS['TSFE']->id;
		$formAction = $this->cObj->typoLink_Url($linkconf);
		
		
		// ERRORS?
		if (!count($errors)) {
			$errortext = '';
		}
		else {
			$errorRowTemplate = $this->cObj->getSubpart($this->templateCode,'###ERROR_ROW###');
			
			$errorContent = '';
			foreach ($errors as $key => $errortext) {
				$tempContent = $errorRowTemplate;
				$tempContent = $this->cObj->substituteMarker($tempContent,'###ERRORTEXT###',$errortext);
				$errorContent .= $tempContent;
			}
			$errorText = $this->cObj->getSubpart($this->templateCode,'###ERRORS_TEMPLATE###');
			$errorText = $this->cObj->substituteMarker($errorText,'###ERROR_GENERAL_TEXT###',$this->pi_getLL('errors_occured'));
			$errorText = $this->cObj->substituteSubpart($errorText,'###ERROR_ROW###',$errorContent,$recursive=1);
		}
		
		$markerArray = array(
			'label_recipients' => $this->pi_getLL('label_recipients'),
			'recipients' => $this->getFormField('selectRecipient', 'recipients'),
			'label_subject' => $this->pi_getLL('label_subject'),
			'subject' => $this->getFormField('text', 'subject'),
			'label_bodytext' => $this->pi_getLL('label_bodytext'),
			'bodytext' => $this->getFormField('rte', 'bodytext'),
			'label_attachment' => $this->pi_getLL('label_attachment'),
			'attachment' => $this->getFormField('files', 'attachment'),
			'label_notification_read' => $this->pi_getLL('label_notification_read'),
			'notification_read' => $this->getFormField('checkbox', 'notification_read'),
			'submit' => $this->pi_getLL('submit'),
			'action' => $formAction,
			'ADDITIONALJS_PRE' => $this->additionalJS_initial.'<script type="text/javascript">'. implode(chr(10), $this->additionalJS_pre).'</script>',
			'ADDITIONALJS_POST' => '<script type="text/javascript">'. implode(chr(10), $this->additionalJS_post).'</script>',
			'ADDITIONALJS_SUBMIT' => implode(';', $this->additionalJS_submit),
			'errors' => $errorText,
		);
		
		// get html template and fill markers with content
		$content = $this->cObj->getSubpart($this->templateCode,'###FORM###');
		$content = $this->cObj->substituteMarkerArray($content,$markerArray,$wrap='###|###',$uppercase=1);
		
		return $content;    
 	}	
	

	/**
 	* get form fields content
	*
	* @param string 	type of field
	* @param string 	fieldname
	* @return string		HTML content of form field
 	*/ 
 	function getFormField($type, $fN) {
		switch($type) {
			
			case 'text':
				$content = '<input type="text" name="'.$this->prefixId.'['.$fN.']" id="'.$fN.'" value="'.$this->piVars[$fN].'">';
				break;
			
			case 'textarea':
				$content = '<textarea name="'.$this->prefixId.'['.$fN.']" id="'.$fN.'">'.$this->piVars[$fN].'</textarea>';
				break;
			
			case 'rte':
				// make RTE instance
				$this->RTEObj = t3lib_div::makeInstance('tx_rtehtmlarea_pi2');
				
				// initialize the RTE
				$this->RTEcounter++;
				$this->formName = 'newinsitemessage';
				$this->strEntryField = $fN;
				$this->PA['itemFormElName'] = $this->prefixId.'['.$fN.']';
				$this->PA['itemFormElValue'] = $this->piVars[$fN]; // TODO
				$this->thePidValue = $GLOBALS['TSFE']->id;
				
				// css-eigenschaften für rte mitgeben
				$this->RTEObj->RTEdivStyle = 'width:450px; height:200px;';
				
				// default config laden
				$tsConfig = $GLOBALS['TSFE']->getPagesTSconfig();
				$this->thisConfig = $tsConfig['RTE.']['default.']['FE.'];
				// config anpassen (buttons ausblenden)
				$this->thisConfig['showButtons'] = 'bold,italic,underline,orderedlist,unorderedlist';
				
				$content = $this->RTEObj->drawRTE($this,'',$this->strEntryField,$row=array(), $this->PA, $this->specConf, $this->thisConfig, $this->RTEtypeVal, '', $this->thePidValue);
				break;
			
			case 'selectRecipient':
				// recipient has to be prefilled
				if ($this->predefinedRecipient) {
					$content = $this->getUserData($this->predefinedRecipient, 'username');
					$content .= '<input type="hidden" name="'.$this->prefixId.'[recipients][]" value="'.$this->predefinedRecipient.'">';
				} 
				// no prefill -> select from all users
				else {
					$recipientsGroups = explode(',', $this->conf['recipientsGroups']);
					$listWhere = ' AND (';
					$i=0;
					foreach ($recipientsGroups as $key => $groupId) {
						if ($i>0) $listWhere .= ' OR ';
						$listWhere .= $GLOBALS['TYPO3_DB']->listQuery('usergroup', $groupId, 'fe_users');
						$i++;
					}
					$listWhere .= ') ';
					
					
					
					$fields = 'uid,username';
					$table = 'fe_users';
					$where = '1=1 ';
					$where .= 'AND uid<>"'.$this->conf['adminUser'].'" ';
					$where .= $listWhere;
					$where .= $this->cObj->enableFields($table);
					$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery($fields,$table,$where,$groupBy='',$orderBy='username',$limit='');
					$options = '';
					
					while ($row=$GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
						$options .= '<option value="'.$row['uid'].'"'; 
						if ($this->piVars[$fN]) {
							if (in_array($row['uid'], $this->piVars[$fN])) $options.= ' selected="selected" ';
						}
						$options .= '>'.$row['username'].'</option>';
					}
					$content = '<select name="'.$this->prefixId.'['.$fN.'][]" id="'.$fN.'" multiple size="3">'.$options.'</select>';
				}
				
				break;
			
			case 'files':
				// show the form elements for the new files
				$content .= '<input type="file" id="'.$fN.'" name="'.$fN.'" value="" maxlength="'.$this->conf['attachment.']['maxFileSize'].'">';
				break;
				
			case 'checkbox':
				$content .= '<input type="checkbox" class="checkbox" name="'.$this->prefixId.'['.$fN.']" value="1" />';
				break;
		}
		
		return $content;    
 	}
	
	
	
	/**
 	* gets single field from single feuser record
 	*
	* @param int 		user uid
	* @param string	name of field
 	*/
	function getUserData($uid, $fieldname) {
		$fields = $fieldname;
 		$table = 'fe_users';
 		$where = 'uid="'.intval($uid).'"';
 		$where .= $this->cObj->enableFields($table);
 		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery($fields,$table,$where,$groupBy='',$orderBy='',$limit='1');
 		$anz = $GLOBALS['TYPO3_DB']->sql_num_rows($res);
		$row=$GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
		return ($row[$fieldname]);
 	}
	
	
	/**
 	* Handle the data submitted by the form
 	*
	* @return HTML content
 	*/ 
 	function handleFormData() {
		// init empty error array
		$this->formErrors = array();
		
		// recipient set?
		if (empty($this->piVars['recipients'])) $this->formErrors[] = $this->pi_getLL('no_recipient');
		
		// subject set?
		if (empty($this->piVars['subject'])) $this->formErrors[] = $this->pi_getLL('no_subject');
		
		// bodytext set?
		if (empty($this->piVars['bodytext'])) $this->formErrors[] = $this->pi_getLL('no_bodytext');
		
		// handle attachments
		if (trim($_FILES['attachment']['name']) != "") $attachment = $this->handleUpload();
		else $attachment = '';
		
		// if errors occured: exit with error message
		if (count($this->formErrors)) {
			return $this->showForm($this->formErrors);
		}
		
		// if there were no errors: build insert query 
		$recipients = implode(',',$this->piVars['recipients']);
		
		$fields_values = array(
			'pid' => $this->conf['dataPid'],
			'crdate' => time(),
			'tstamp' => time(),
			'cruser_id' => $GLOBALS['TSFE']->fe_user->user['uid'],
			'sender' => $GLOBALS['TSFE']->fe_user->user['uid'],
			'recipient' => $recipients,
			'bodytext' => $this->piVars['bodytext'],
			'subject' => $this->sanitizeData($this->piVars['subject']),
			'attachment' => $attachment,
			'notification_read' => intval($this->piVars['notification_read']),
		);
		
		// write data into DB
		if ($GLOBALS['TYPO3_DB']->exec_INSERTquery($this->table,$fields_values,$no_quote_fields=FALSE)) {
			$responseMessage = $this->pi_getLL('response_ok');
			
			// get uid of new message
			$lastUid = $GLOBALS['TYPO3_DB']->sql_insert_id();
			
			// get subject from ts
			$subject = $this->conf['notification.']['subject'];
			
			// link to single view
			$linkconf['parameter'] = $this->conf['inboxPid'];
			$linkconf['additionalParams'] = '&tx_keinsitemailbox_pi1[message]='.$lastUid;
			$singleUrl = $this->cObj->typoLink_Url($linkconf);
			#$singleUrl = $GLOBALS['TSFE']->config['config']['baseUrl'].$singleUrl;
			$singleUrl = t3lib_div::getIndpEnv('TYPO3_SITE_URL').'/'.$singleUrl;
			
			
			
			
			// send notification mails to recipients
			foreach ($this->piVars['recipients'] as $key => $val) {
				$markerArray = array (
					'USERNAME' => $this->getUserData($val, 'username'),
					'SITENAME' => $this->conf['notification.']['sitename'],
					'SINGLE_LINK' => '<a href="'.$singleUrl.'">'.$singleUrl.'</a>',
					'FROM_NAME' => $this->conf['notification.']['from_name'],
				);
				$html_body = $this->cObj->getSubpart($this->templateCode,'###NOTIFICATION_MAIL###');
				$html_body = $this->cObj->substituteMarkerArray($html_body,$markerArray,$wrap='###|###',$uppercase=1);
				// get recipients' E-Mail address
				$toEMail = $this->getUserData($val, 'email');
				$this->sendNotificationEmail($toEMail, $subject, $html_body);
			}
			
		}
		else {
			$responseMessage = $this->pi_getLL('response_error');
			debug($GLOBALS['TYPO3_DB']->INSERTquery($this->table,$fields_values,$no_quote_fields=FALSE));
		}
		
		// Backlink
		$linkConf['parameter'] = $this->conf['inboxPid'];
		$linkConf['useCacheHash'] = false;
		$backlink = $this->cObj->typoLink($this->pi_getLL('back'),$linkConf);
		
		$content = $this->cObj->getSubpart($this->templateCode,'###RESPONSE###');
		$content = $this->cObj->substituteMarker($content,'###RESPONSE_MESSAGE###',$responseMessage);
		$content = $this->cObj->substituteMarker($content,'###BACKLINK###',$backlink);
		
		
		
		return $content;    
 	}
	
	
	
	/**
	 * Uploads the file given in the form-field $attachmentName to the server
	 *
	 * success: returns the new filename
	 * no success: returns false
	 *
	 * @param string $attachmentName
	 * @return array
	 */
	public function handleUpload($attachmentName='attachment') {
		$success = true;

		// does the directory exist?
		if (!is_dir($this->uploadsFolder)) {
			$this->formErrors[] = $this->pi_getLL('error_no_upload_directory','Upload directory does not exist.');
		}

		// set deault values
		$this->conf['attachment.']['maxFileSize'] = $this->conf['attachment.']['maxFileSize'] ? $this->conf['attachment.']['maxFileSize'] : 1048576;
		
		// get the destination filename
		$filefuncs = new t3lib_basicFilefunctions();
		$uploadfile = $filefuncs->getUniqueName($filefuncs->cleanFileName($_FILES[$attachmentName]['name']), $this->uploadsFolder);
		
		// Filesize OK?
		if($_FILES[$attachmentName]['size'] > $this->conf['attachment.']['maxFileSize']){
			$this->formErrors[] = $this->pi_getLL('error_file_too_big','Error: File is too big.');
			$success=false;
		}
		
		
		if($success && move_uploaded_file($_FILES[$attachmentName]['tmp_name'], $uploadfile)) {
			// success
			// $content .= $this->pi_getLL('fileupload.uploadSuccess','File upload was successfull.');
			// change rights so that everyone can read the file
			chmod($uploadfile,octdec('0744'));
 		} else {
			$this->formErrors[] = $this->pi_getLL('error_file_upload_not_successful','Error: File upload was not successfull.');
			$success=false;
		}
		
		if ($success) {
			return basename($uploadfile);
		} else {
			return false;
		}
	}
	
	/*
	 * sanitizeData
	 *
	 * @param string $data
	 * @access public
	 * @return string
	 */
	public function sanitizeData($data='') {
		return htmlspecialchars($data, ENT_QUOTES, $GLOBALS['TSFE']->renderCharset);
	}

	
	/**
	 * sends the notification email, uses the TYPO3 mail functions
	 *
	 * @param string $toEMail
	 * @param string $subject
	 * @param string $html_body
	 * @param int $sendAsHTML
	 * @access public
	 * @return void
	 */
	public function sendNotificationEmail($toEMail, $subject, $html_body, $sendAsHTML = 1) {
		
		// Only ASCII is allowed in the header
		$subject = html_entity_decode(t3lib_div::deHSCentities($subject), ENT_QUOTES, $GLOBALS['TSFE']->renderCharset);
		$subject = t3lib_div::encodeHeader($subject, 'base64');

		// create the plain message body
		$message = html_entity_decode(strip_tags($html_body), ENT_QUOTES, $GLOBALS['TSFE']->renderCharset);

		// inspired by code from tt_products, thanks
		$Typo3_htmlmail = t3lib_div::makeInstance('t3lib_htmlmail');
		$Typo3_htmlmail->start();

		$Typo3_htmlmail->subject = $subject;
		$Typo3_htmlmail->from_email = $this->conf['notification.']['from_email'];
		$Typo3_htmlmail->from_name = $this->conf['notification.']['from_name'];
		$Typo3_htmlmail->replyto_email = $Typo3_htmlmail->from_email;
		$Typo3_htmlmail->replyto_name = $Typo3_htmlmail->from_name;
		$Typo3_htmlmail->organisation = '';

		if ($sendAsHTML)  {
			$Typo3_htmlmail->theParts['html']['content'] = $html_body;
			$Typo3_htmlmail->theParts['html']['path'] = t3lib_div::getIndpEnv('TYPO3_REQUEST_HOST') . '/';

			$Typo3_htmlmail->extractMediaLinks();
			$Typo3_htmlmail->extractHyperLinks();
			$Typo3_htmlmail->fetchHTMLMedia();
			$Typo3_htmlmail->substMediaNamesInHTML(0);	// 0 = relative
			$Typo3_htmlmail->substHREFsInHTML();
			$Typo3_htmlmail->setHTML($Typo3_htmlmail->encodeMsg($Typo3_htmlmail->theParts['html']['content']));
			if ($message)	{
				$Typo3_htmlmail->addPlain($message);
			}
		} else {
			$Typo3_htmlmail->addPlain($message);
		}
		$Typo3_htmlmail->setHeaders();
		$Typo3_htmlmail->setContent();
		$Typo3_htmlmail->setRecipient(explode(',', $toEMail));
		$Typo3_htmlmail->sendTheMail();
	}
	
	
	
	/**
 	* Check Access
	*
	* @return 	string  	empty if access granted, contains content if not granted
 	*/ 
 	function checkAccess() {
		
		// link to login page
		if ($this->conf['loginPid']) {
			$linkConf['parameter'] = $this->conf['loginPid'];
			$loginpagelink = $this->cObj->typoLink($this->pi_getLL('loginpagelink'),$linkConf);
		} else $loginpagelink = '';
		
		// no user logged in
		if (!$GLOBALS['TSFE']->loginUser) {
			$content = $this->cObj->getSubpart($this->templateCode,'###NO_ACCESS###');
			$content = $this->cObj->substituteMarker($content,'###ERRORMESSAGE###',$this->pi_getLL('no_login'));
			$content = $this->cObj->substituteMarker($content,'###LOGINPAGE###',$loginpagelink);
			return $content;
		}
		else return '';
 	}
	
	
	/**
 	* Get data from db for single message
 	*
	* @param int	uid of message
	* @return array
 	*/ 
 	function getMessageData($uid) {
		$fields = '*';
 		$where = 'uid="'.intval($uid).'" ';
 		$where .= $this->cObj->enableFields($this->table);
 		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery($fields,$this->table,$where,$groupBy='',$orderBy='',$limit='1');
		return $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
 	}
	
	
}



if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/ke_insitemailbox/pi2/class.tx_keinsitemailbox_pi2.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/ke_insitemailbox/pi2/class.tx_keinsitemailbox_pi2.php']);
}

?>