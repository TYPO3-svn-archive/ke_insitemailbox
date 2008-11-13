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

/*	
	### READY ###
	- Anhänge versenden
	- Empfänger per Hook vorgeben
	- Gelesene / Ungelesene Nachrichten darstellen
	- als gelesen markieren in Inbox
	- Löschen in Inbox
	- Löschen in Singleview
	- Benachrichtigung per E-Mail bei Erhalt neuer Message
	- Antworten in Inbox
	- Weiterleiten in Inbox
	- Antworten in Singleview
	- Weiterleiten in Singleview
	
	
	###TODO###
	- Gesendete Nachrichten anzeigen (Outbox)
	- Access Check bei Forward/Reply
	- Sortierung in Inbox/Outbox per Klick auf Spalte
	- Empfängerauswahl bei Erstellung einer Message einschränken ?!?
	- Eigenen Benutzer beim Verfassen einer Mail nicht mit anbieten ?!?
	- doppeltes Abschicken des Formulars unterbinden
	- Pagebrowser in inbox / outbox

*/


require_once(PATH_tslib.'class.tslib_pibase.php');


/**
 * Plugin 'Show Insite Mailbox' for the 'ke_insitemailbox' extension.
 *
 * @author	Andreas Kiefer <kiefer@kennziffer.com>
 * @package	TYPO3
 * @subpackage	tx_keinsitemailbox
 */
class tx_keinsitemailbox_pi1 extends tslib_pibase {
	var $prefixId      = 'tx_keinsitemailbox_pi1';		// Same as class name
	var $scriptRelPath = 'pi1/class.tx_keinsitemailbox_pi1.php';	// Path to this script relative to the extension dir.
	var $extKey        = 'ke_insitemailbox';	// The extension key.
	var $table 		= 'tx_keinsitemailbox_messages'; 	// main data table where messages are stored
	var $logtable 	= 'tx_keinsitemailbox_log'; 	// table where read messages are stored
	var $uploadsFolder = 'uploads/tx_keinsitemailbox/'; 	// path where attachments are stored
	var $defaultDateFormat = '%d.%m.%Y %H:%M';
	/**
	 * Main method of your PlugIn
	 *
	 * @param	string		$content: The content of the PlugIn
	 * @param	array		$conf: The PlugIn Configuration
	 * @return	The content that should be displayed on the website
	 */
	function main($content,$conf)	{
		$this->conf=$conf;		// Setting the TypoScript passed to this function in $this->conf
		$this->pi_setPiVarDefaults();
		$this->pi_loadLL();		// Loading the LOCAL_LANG values
		$this->pi_USER_INT_obj=1;	// Configuring so caching is not expected. This value means that no cHash params are ever set. We do this, because it's a USER_INT object!
		
		// include html template
		$this->defaultTemplateFile = t3lib_extMgm::siteRelPath($this->extKey).'res/template/template_keinsitemailbox_pi1.html';
		$this->templateFile = $this->conf['templateFile'] ? $this->conf['templateFile'] : $this->defaultTemplateFile;
		$this->templateCode = $this->cObj->fileResource($this->templateFile);
		
		// include css file
		$this->defaultCSSFile = t3lib_extMgm::siteRelPath($this->extKey).'res/css/ke_insitemailbox.css';
		$cssfile = $this->conf['cssFile'] ? $this->conf['cssFile'] : $this->defaultCSSFile;
		$GLOBALS['TSFE']->additionalHeaderData[$this->prefixId] .= '<link rel="stylesheet" type="text/css" href="'.$cssfile.'" />';
		
		// init images
		$this->initImages();
		
		// show single message if message chosen
		if ($this->piVars['message']) $content = $this->showSingle($this->piVars['message']);
		// otherwise show inbox/outbox
		else {
			if ($this->piVars['mode'] == '') $this->piVars['mode'] = 'inbox';
		
			if ($this->piVars['mode'] == 'inbox') {
				// mark message as deleted
				if ($this->piVars['delmessage']) $this->markMessageAsDeleted($this->piVars['delmessage']);
				// mark message as read
				if ($this->piVars['markread']) $this->markMessageAsRead($this->piVars['markread']);
				// show inbox
				$content = $this->showMenu();
				$content .= $this->showInbox();
			}
			else if ($this->piVars['mode'] == 'outbox') {
				$content = $this->showMenu();
				$content .= $this->showOutbox();
			}
		}
		
		return $this->pi_wrapInBaseClass($content);
	}
	
	
	
	
	/**
 	* Show menu to select between inbox and outbox
 	*
	* @return HTML form containing form and select object
 	*/ 
 	function showMenu() {
		
		$inboxSelected = $this->piVars['mode'] == 'inbox' ? 'selected="selected" ' : '';
		$outboxSelected = $this->piVars['mode'] == 'outbox' ? 'selected="selected" ' : '';
		
		// Form action
		$linkconf['parameter'] = $GLOBALS['TSFE']->id;
 		$formAction = $this->cObj->typoLink_URL($linkconf);
		
		$markerArray = array(
			'inbox_selected' => $inboxSelected,
			'inbox_text' => $this->pi_getLL('inbox'),
			'outbox_selected' => $outboxSelected,
			'outbox_text' => $this->pi_getLL('outbox'),
			'menuaction' => $formAction,
		);
		
		$content = $this->cObj->getSubpart($this->templateCode,'###MENU###');
		$content = $this->cObj->substituteMarkerArray($content,$markerArray,$wrap='###|###',$uppercase=1);
		
		
		return $content;    
 	}
	
	
	
	/**
	 * Show list view of all messages sent to current
	 *
	 * @return	HTML list
	 */
	function showInbox()	{
		
		// if no user is logged in: exit with error message and link to login page
		$errorcontent = $this->checkAccess();
		if ($errorcontent) return $errorcontent;
		
		// get template or single message
		$entryTemplate = $this->cObj->getSubpart($this->templateCode,'###INBOX_ENTRY###');
		
		// inital empty content
		$inboxEntries = '';
		
		// get all the mails sent to current user from db
		$where = $GLOBALS['TYPO3_DB']->listQuery('recipient', intval($GLOBALS['TSFE']->fe_user->user['uid']), $this->table);
		$where .= $this->cObj->enableFields($this->table);
 		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*',$this->table,$where,$groupBy='',$orderBy='crdate desc',$limit='');
		
		$anz = 0;
		while ($row=$GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
			
			// show only if not marked as deleted
			if (!$this->messageDeletedByRecipient($row['uid'])) {
				
				// count undeleted messages
				$anz++;
				
				// show attachment icon if message has attachment
				$attachment = !empty($row['attachment']) ? $this->attachmentIcon : '&nbsp;';
				
				// get senders username
				$linkconf['parameter'] = $this->conf['formpage'];
				$linkconf['additionalParams'] = '&tx_keinsitemailbox_pi2[sender]='.intval($row['sender']);
				$senderLink = $this->cObj->typoLink($this->getUserData($row['sender'], 'username'),$linkconf);
				
				
				// get formatted date
				$format = $this->conf['dateFormat'] ? $this->conf['dateFormat'] : $this->defaultDateFormat;
				$date = strftime($format, $row['crdate']);
								
				// row style differs for read and unread messages
				$trclass = $this->messageReadByRecipient($row['uid']) ? 'read' : 'unread';
				
				// link to singleview
				$linkconf['parameter'] = $GLOBALS['TSFE']->id;
				$linkconf['additionalParams'] = '&tx_keinsitemailbox_pi1[message]='.intval($row['uid']);
				$linkconf['additionalParams'] .= '&tx_keinsitemailbox_pi1[mode]=inbox';
				$singleLink = $this->cObj->typoLink($row['subject'],$linkconf);
				
				// fill template markers
				$tempMarker = array(
					'trclass' => $trclass,
					'attachment' => $attachment,
					'sender' => $senderLink,
					'subject' => $singleLink,
					'date' => $date,
					'deletelink' => $this->getActionLink('delete', $row['uid']),
					'markreadlink' => $this->getActionLink('markread', $row['uid']),
					'replylink' => $this->getActionLink('reply', $row['uid']),
					'forwardlink' => $this->getActionLink('forward', $row['uid']),
				);
				$tempContent = $entryTemplate;
				$tempContent = $this->cObj->substituteMarkerArray($tempContent,$tempMarker,$wrap='###|###',$uppercase=1);
				
				// add single row to content variable
				$inboxEntries .= $tempContent;
			}
		}
		
		// no messages found?
		if ($anz == 0) {
			$noEntriesContent = $this->cObj->getSubpart($this->templateCode,'###INBOX_NO_ENTRIES###');
			$noEntriesContent = $this->cObj->substituteMarker($noEntriesContent,'###TEXT###',$this->pi_getLL('no_messages'));
		}
		
		
		// formlink icon
		$linkConf['parameter'] = $this->conf['formpage'];
		$linkConf['useCacheHash'] = false;
		$formlink = $this->cObj->typoLink($this->pi_getLL('new_message','Nachricht verfassen'),$linkConf);
		
		$numMessages = sprintf($this->pi_getLL('num_messages_inbox'), $anz);
		
		// get marker fills for inbox
		$markerArray = array(
			'header_attachment' => '&nbsp;',
			'header_sender' => $this->pi_getLL('header_sender'),
			'header_subject' => $this->pi_getLL('header_subject'),
			'header_date' => $this->pi_getLL('header_date'),
			'header_delete' => '&nbsp;',
			'header_markread' => '&nbsp;',
			'header_reply' => '&nbsp;',
			'header_forward' => '&nbsp;',
			'formlink' => $formlink,
			'formlinkicon' => $this->addIcon,
			'num_messages' => $numMessages,
		);
		
		// get inbox subpart from template file and fill markers
		$content = $this->cObj->getSubpart($this->templateCode,'###INBOX###');
		$content = $this->cObj->substituteMarkerArray($content,$markerArray,$wrap='###|###',$uppercase=1);
		if ($anz) {
			$content = $this->cObj->substituteSubpart($content,'###INBOX_ENTRY###',$inboxEntries,$recursive=1);
			$content = $this->cObj->substituteSubpart($content,'###INBOX_NO_ENTRIES###','',$recursive=1);
		}
		else {
			$content = $this->cObj->substituteSubpart($content,'###INBOX_ENTRY###','',$recursive=1);
			$content = $this->cObj->substituteSubpart($content,'###INBOX_NO_ENTRIES###',$noEntriesContent,$recursive=1);
		}
		
		
		return $content;
		
	}
	
	
	/**
 	* Show outbox
 	*
 	*/ 
 	function showOutbox() {
		
		// get template or single message
		$entryTemplate = $this->cObj->getSubpart($this->templateCode,'###OUTBOX_ENTRY###');
		
		// inital empty content
		$outboxEntries = '';
		
		// get all the mails sent from current user from db
		$where = 'sender="'.intval($GLOBALS['TSFE']->fe_user->user['uid']).'" ';
		$where .= $this->cObj->enableFields($this->table);
 		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*',$this->table,$where,$groupBy='',$orderBy='crdate desc',$limit='');
		$anz = $GLOBALS['TYPO3_DB']->sql_num_rows($res);
		while ($row=$GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
			
			// show attachment icon if message has attachment
				$attachment = !empty($row['attachment']) ? $this->attachmentIcon : '&nbsp;';
				
				// get recipients' username
				$recipientsContentTemplate = $this->cObj->getSubpart($this->templateCode,'###OUTBOX_RECIPIENT###');
				$recipients = explode(',', $row['recipient']);
				$recipientsContent = '';
				foreach ($recipients as $key => $val) {
					$linkconf['parameter'] = $this->conf['formpage'];
					$linkconf['additionalParams'] = '&tx_keinsitemailbox_pi2[sender]='.intval($val);
					$recipientLink = $this->cObj->typoLink($this->getUserData($val, 'username'),$linkconf);
					
					$recipientsTemp = $recipientsContentTemplate;
					$recipientsTemp = $this->cObj->substituteMarker($recipientsTemp,'###USERNAME###',$recipientLink);
					$recipientsContent .= $recipientsTemp;
					
				}
				
				// get formatted date
				$format = $this->conf['dateFormat'] ? $this->conf['dateFormat'] : $this->defaultDateFormat;
				$date = strftime($format, $row['crdate']);
				
				// link to singleview
				$linkconf['parameter'] = $GLOBALS['TSFE']->id;
				$linkconf['additionalParams'] = '&tx_keinsitemailbox_pi1[message]='.intval($row['uid']);
				$linkconf['additionalParams'] .= '&tx_keinsitemailbox_pi1[mode]=outbox';
				$singleLink = $this->cObj->typoLink($row['subject'],$linkconf);
				
				// fill template markers
				$tempMarker = array(
					'attachment' => $attachment,
					'read' => $this->getReceiptStatus($row['uid'], $row['recipient']),
					'sender' => $senderLink,
					'subject' => $singleLink,
					'date' => $date,
					'deletelink' => $this->getActionLink('delete', $row['uid']),
					'markreadlink' => $this->getActionLink('markread', $row['uid']),
					'replylink' => $this->getActionLink('reply', $row['uid']),
					'forwardlink' => $this->getActionLink('forward', $row['uid']),
				);
				$tempContent = $entryTemplate;
				$tempContent = $this->cObj->substituteMarkerArray($tempContent,$tempMarker,$wrap='###|###',$uppercase=1);
				$tempContent = $this->cObj->substituteSubpart($tempContent,'###OUTBOX_RECIPIENT###',$recipientsContent,$recursive=1);
				
				// add single row to content variable
				$outboxEntries .= $tempContent;
		
		}
		
		$numMessages = sprintf($this->pi_getLL('num_messages_outbox'), $anz);
		
		// formlink icon
		$linkConf['parameter'] = $this->conf['formpage'];
		$linkConf['useCacheHash'] = false;
		$formlink = $this->cObj->typoLink($this->pi_getLL('new_message','Nachricht verfassen'),$linkConf);
		
		// get marker fills for inbox
		$markerArray = array(
			'header_attachment' => '&nbsp;',
			'header_read' => '&nbsp;',
			'header_recipients' => $this->pi_getLL('header_recipients'),
			'header_subject' => $this->pi_getLL('header_subject'),
			'header_date' => $this->pi_getLL('header_date'),
			'header_forward' => '&nbsp;',
			'num_messages' => $numMessages,
			'formlink' => $formlink,
			'formlinkicon' => $this->addIcon,
		);
		
		$content = $this->cObj->getSubpart($this->templateCode,'###OUTBOX###');
		$content = $this->cObj->substituteMarkerArray($content,$markerArray,$wrap='###|###',$uppercase=1);
		
		if ($anz) {
			$content = $this->cObj->substituteSubpart($content,'###OUTBOX_ENTRY###',$outboxEntries,$recursive=1);
			$content = $this->cObj->substituteSubpart($content,'###OUTBOX_NO_ENTRIES###','',$recursive=1);
		}
		else {
			$content = $this->cObj->substituteSubpart($content,'###OUTBOX_ENTRY###','',$recursive=1);
			$content = $this->cObj->substituteSubpart($content,'###OUTBOX_NO_ENTRIES###',$noEntriesContent,$recursive=1);
		}
		
		return $content;    
 	}
	
	
	

	
	
	
	
	/**
 	* Get fields from feuser table
 	*
	* @param 	int	uid of user record
	* @param 	string	get content of this database field
	* @return string		content of selected field if found in db, otherwise empty value
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
 	* Init images needed for inbox
 	*
 	*/ 
 	function initImages() {
		// attachment icon
		unset($imageConf);
		$imageConf['file'] = t3lib_extMgm::siteRelPath($this->extKey).'res/img/attach.png';
		$imageConf['altText'] = $this->pi_getLL('attachment');
		$this->attachmentIcon=$this->cObj->IMAGE($imageConf);
		
		// delete Icon
		unset($imageConf);
		$imageConf['file'] = t3lib_extMgm::siteRelPath($this->extKey).'res/img/bin.png';
		$imageConf['altText'] = $this->pi_getLL('delete');
		$this->deleteIcon=$this->cObj->IMAGE($imageConf);
		
		// add Icon
		unset($imageConf);
		$imageConf['file'] = t3lib_extMgm::siteRelPath($this->extKey).'res/img/add.png';
		$imageConf['altText'] = $this->pi_getLL('add');
		$this->addIcon=$this->cObj->IMAGE($imageConf);
		
		// mark as read Icon
		unset($imageConf);
		$imageConf['file'] = t3lib_extMgm::siteRelPath($this->extKey).'res/img/markread.png';
		$imageConf['altText'] = $this->pi_getLL('markread');
		$this->markreadIcon=$this->cObj->IMAGE($imageConf);
		
		// forward icon
		unset($imageConf);
		$imageConf['file'] = t3lib_extMgm::siteRelPath($this->extKey).'res/img/forward.gif';
		$imageConf['altText'] = $this->pi_getLL('forward');
		$this->forwardIcon=$this->cObj->IMAGE($imageConf);
		
		// reply icon
		unset($imageConf);
		$imageConf['file'] = t3lib_extMgm::siteRelPath($this->extKey).'res/img/reply.gif';
		$imageConf['altText'] = $this->pi_getLL('reply');
		$this->replyIcon=$this->cObj->IMAGE($imageConf);
		
		// back icon
		unset($imageConf);
		$imageConf['file'] = t3lib_extMgm::siteRelPath($this->extKey).'res/img/back.gif';
		$imageConf['altText'] = $this->pi_getLL('back');
		$this->backIcon=$this->cObj->IMAGE($imageConf);
		
		// read status: all
		unset($imageConf);
		$imageConf['file'] = t3lib_extMgm::siteRelPath($this->extKey).'res/img/readbyall.gif';
		$imageConf['altText'] = $this->pi_getLL('readbyall');
		$this->readByAllIcon=$this->cObj->IMAGE($imageConf);
		
		// read status: some
		unset($imageConf);
		$imageConf['file'] = t3lib_extMgm::siteRelPath($this->extKey).'res/img/readbysome.gif';
		$imageConf['altText'] = $this->pi_getLL('readbysome');
		$this->readBySomeIcon=$this->cObj->IMAGE($imageConf);
		
		// read status: none
		unset($imageConf);
		$imageConf['file'] = t3lib_extMgm::siteRelPath($this->extKey).'res/img/readbynone.gif';
		$imageConf['altText'] = $this->pi_getLL('readbynone');
		$this->readByNoneIcon=$this->cObj->IMAGE($imageConf);
 	}
	
	
	
	/**
 	* Show single message
 	*
	* @param int	uid of message record to show
	* @return HTML output
 	*/ 
 	function showSingle($uid) {
		
		$this->markMessageAsRead($uid);
		
		// if no user is logged in: exit with error message and link to login page
		$errorcontent = $this->checkAccess($uid);
		if ($errorcontent) return $errorcontent;
		
		
		$where = 'uid="'.intval($uid).'"';
 		$where .= $this->cObj->enableFields($this->table);
 		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*',$this->table,$where,$groupBy='',$orderBy='',$limit='1');
 		while ($row=$GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
			
			// get sender
			$sender = $this->getUserData($row['sender'],'username');
			
			// get recipient subpart from template
			$recipientTemplate = $this->cObj->getSubpart($this->templateCode,'###MESSAGE_RECIPIENT###');
			
			// initial empty value
			$recipients = '';
			
			// get recipients
			$allRecipients = explode(',', $row['recipient']);
			foreach ($allRecipients as $key => $val) {
				$tempContent  = $recipientTemplate;
				$tempContent = $this->cObj->substituteMarker($tempContent,'###USERNAME###',$this->getUserData($val, 'username'));
				$recipients .= $tempContent;
			}
			
			// get formatted date
			$this->defaultDateFormat = '%d.%m.%Y<br />%H:%M Uhr';
			$format = $this->conf['dateFormat'] ? $this->conf['dateFormat'] : $this->defaultDateFormat;
			$date = strftime($format, $row['crdate']);
			
			// Backlink
			$lcObj=t3lib_div::makeInstance('tslib_cObj');
			$linkConf['parameter'] = $GLOBALS['TSFE']->id;
			$linkConf['additionalParams'] = '&tx_keinsitemailbox_pi1[mode]='.$this->piVars['mode'];
			$linkConf['useCacheHash'] = false;
			$backlink = $lcObj->typoLink($this->backIcon.' '.$this->pi_getLL('back'),$linkConf);
			
			// delete link
			$linkconf['parameter'] = $GLOBALS['TSFE']->id;
			$linkconf['additionalParams'] = '&tx_keinsitemailbox_pi1[delmessage]='.$row['uid'];
			$deleteLink = $this->cObj->typoLink($this->pi_getLL('delete'),$linkconf);
			
			// fill the template markers with content
			$markerArray = array(
				'subject' => $row['subject'],
				'label_subject' => $this->pi_getLL('header_subject'),
				'sender' => $sender,
				'label_sender' => $this->pi_getLL('header_sender'),
				'label_recipients' => $this->pi_getLL('header_recipients'),
				'date' => $date,
				'label_date' => $this->pi_getLL('header_date'),
				'bodytext' => $this->pi_RTEcssText($row['bodytext']),
				'label_bodytext' => $this->pi_getLL('header_bodytext'),
				'backlink' => $backlink,
				'replylink' => $this->getActionLink('reply', $row['uid'], true),
				'forwardlink' => $this->getActionLink('forward', $row['uid'], true),
				'deletelink' => $this->getActionLink('delete', $row['uid'], true),
			);
			
			// get Attachment
			if (!empty($row['attachment'])) {
				$linkconf['parameter'] = $this->uploadsFolder.$row['attachment'];
				$linkconf['target'] = '_blank';
				$attachmentLink = $this->cObj->typoLink($row['attachment'],$linkconf);
				
				// show thumbs
				$filetype = substr(strrchr($row['attachment'], '.'), 1);
				$filetype = strtolower($filetype);
				if (t3lib_div::inList($this->conf['thumbnails'],$filetype)) {
					$this->conf['thumbnail.']['file'] = $this->uploadsFolder.$row['attachment'];
					$this->conf['thumbnail.']['imageLinkWrap'] = 1;
					$this->conf['thumbnail.']['imageLinkWrap.']['enable'] = 1;
					$this->conf['thumbnail.']['altText'] = $row['attachment'];
				}
				else {
					$this->conf['thumbnail.']['file'] = $this->conf['nopreview'];
				}
				$markerArray['attachment']=$this->cObj->IMAGE($this->conf['thumbnail.']);
				$markerArray['label_attachment'] = $this->pi_getLL('header_attachment');
			}
			else {
				$markerArray['attachment'] = '';
				$markerArray['label_attachment'] = '';
			}
			
		}		
		
		$content = $this->cObj->getSubpart($this->templateCode,'###MESSAGE###');
		$content = $this->cObj->substituteMarkerArray($content,$markerArray,$wrap='###|###',$uppercase=1);
		$content = $this->cObj->substituteSubpart($content,'###MESSAGE_RECIPIENT###',$recipients,$recursive=1);
		
		
		return $content;    
 	}
	
	
	/*
	* checks if a user has access
	*
	* @param int $uid	check access for this single message
	* @return string		empty if user is logged in, otherwise HTML error message
	*/
	function checkAccess($uid='') {
		
		// link to login page
		if ($this->conf['loginpage']) {
			$linkConf['parameter'] = $this->conf['loginpage'];
			$loginpagelink = $this->cObj->typoLink($this->pi_getLL('loginpagelink'),$linkConf);
		} else $loginpagelink = '';
		
		// no user logged in
		if (!$GLOBALS['TSFE']->loginUser) {
			$content = $this->cObj->getSubpart($this->templateCode,'###NO_ACCESS###');
			$content = $this->cObj->substituteMarker($content,'###ERRORMESSAGE###',$this->pi_getLL('no_login'));
			#$content = $this->cObj->substituteMarker($content,'###LOGINPAGE###',$loginpagelink);
			$content = $this->cObj->substituteMarker($content,'###FORM###',$this->showLoginForm());
			return $content;
		}
		else {
			// check for single message
			if ($uid) {
				// get data from db
				$where = 'uid="'.intval($uid).'"';
		 		$where .= $this->cObj->enableFields($this->table);
		 		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*',$this->table,$where,$groupBy='',$orderBy='',$limit='1');
				$row=$GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
				
				// user is recipient
				if (t3lib_div::inList($row['recipient'], $GLOBALS['TSFE']->fe_user->user['uid'])) $recipient = true;
				
				// user is sender
				if ($row['sender'] == $GLOBALS['TSFE']->fe_user->user['uid']) $sender = true;
				
				
				if ( ($this->piVars['mode'] == 'outbox' && !$sender) || ($this->piVars['mode'] == 'inbox' && !$recipient) )   {
					$content = $this->cObj->getSubpart($this->templateCode,'###NO_ACCESS###');
					$content = $this->cObj->substituteMarker($content,'###ERRORMESSAGE###',$this->pi_getLL('no_access'));
					$content = $this->cObj->substituteMarker($content,'###LOGINPAGE###',$loginpagelink);
					return $content;
				}
			}
		}
		// return empty if user is logged in
		return '';
	}
	
	/**
	 * sanitizeData
	 *
	 * @param string $data
	 * @access public
	 * @return string
	 */
	public function sanitizeData($data='') {
		return htmlspecialchars($data, ENT_QUOTES, $GLOBALS['TSFE']->renderCharset);
	}
	
	
	/*
	*Check if recipient has already read a message
	*
	* @param int 	uid of message
	* @return bool	true if message read, otherwise false
	*/
	function messageReadByRecipient($messageUid, $recipientUser='') {
		
		// check given user or check current user if not set
		$recipient = $recipientUser ? $recipientUser : $GLOBALS['TSFE']->fe_user->user['uid'];
		
		#debug($recipient);
		
		$fields = '*';
		$table = 'tx_keinsitemailbox_log';
		$where = 'message="'.intval($messageUid).'" AND recipient="'.intval($recipient).'" AND action="read" ';
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery($fields,$table,$where,$groupBy='',$orderBy='',$limit='');
		$anz = $GLOBALS['TYPO3_DB']->sql_num_rows($res);
		return $anz ? true : false;
	}
	
	/*
	* Marks a message as read by the current user
	*
	*@param int $uid
	*/
	function markMessageAsRead($messageUid) {
		
		// already marked as read?
		$where = 'message="'.intval($messageUid).'" ';
		$where .= 'AND recipient="'.intval($GLOBALS['TSFE']->fe_user->user['uid']).'" ';
		$where .= 'AND action="read" ';
 		$where .= $this->cObj->enableFields($this->logtable);
 		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*',$this->logtable,$where,$groupBy='',$orderBy='',$limit='');
 		$anz = $GLOBALS['TYPO3_DB']->sql_num_rows($res);
		
		// if not already marked as read by user
		if ($anz == 0) {
		
			// get message data from db
			$data = $this->getMessageData($messageUid);
			
			// if set by sender: set notification to sender that message is read by the/one recipient
			if ($data['notification_read'] == 1) $this->sendNotificationRead($data['uid'], $GLOBALS['TSFE']->fe_user->user['uid']);
		
			// mark message as read in the logtable
			$fields_values = array(
				'pid' => $this->conf['dataPid'],
				'message' => intval($messageUid),
				'recipient' => intval($GLOBALS['TSFE']->fe_user->user['uid']),
				'action' => 'read',
			);
			$GLOBALS['TYPO3_DB']->exec_INSERTquery($this->logtable,$fields_values,$no_quote_fields=FALSE);
		}
	}
	
	
	/**
 	* Sets a single message as deleted for a single recipient
	*
 	* @param int $messageUid	
 	*/ 
 	function markMessageAsDeleted($messageUid) {
		
		$fields_values = array(
			'pid' => $this->conf['dataPid'],
			'message' => intval($messageUid),
			'recipient' => intval($GLOBALS['TSFE']->fe_user->user['uid']),
			'action' => 'deleted',
		);
		$GLOBALS['TYPO3_DB']->exec_INSERTquery($this->logtable,$fields_values,$no_quote_fields=FALSE);
		
 	}
		
		
	/**
 	* Check if message is deleted by a recipient
 	*
	* @param int $messageUid
	* @return boolean		true if message is marked as deleted by user, otherwiese false
 	*/ 
 	function messageDeletedByRecipient($messageUid) {
		$fields = '*';
		$table = 'tx_keinsitemailbox_log';
		$where = 'message="'.intval($messageUid).'" '; 
		$where .= 'AND action="deleted" ';
		$where .= 'AND recipient="'.intval($GLOBALS['TSFE']->fe_user->user['uid']).'" ';
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery($fields,$table,$where,$groupBy='',$orderBy='',$limit='');
		$anz = $GLOBALS['TYPO3_DB']->sql_num_rows($res);
		return $anz ? true : false;
 	}
	
	
	/**
 	* Show login form
 	*
	* @return string		HTML login form
 	*/ 
 	function showLoginForm() {
		
		// loginform action
		$linkconf['parameter'] = $this->conf['loginpage'];
 		$loginFormAction = $this->cObj->typoLink_URL($linkconf);
		
		$markerArray = array(
			'loginform_action' => $loginFormAction,
			'loginform_title' => $this->pi_getLL('loginform_title'),
			'loginform_username' => $this->pi_getLL('loginform_username'),
			'loginform_password' => $this->pi_getLL('loginform_password'),
			'loginform_submit' => $this->pi_getLL('loginform_submit'),
			'loginform_pid' => $this->conf['userdata'],
			'loginform_redirect_url' => $GLOBALS['TSFE']->siteScript,
		);
		
		$content = $this->cObj->getSubpart($this->templateCode,'###LOGINFORM###');
		$content = $this->cObj->substituteMarkerArray($content,$markerArray,$wrap='###|###',$uppercase=1);
		
		
		
		return $content;    
 	}
	
	
	/**
 	* Generates links for different actions in inbox and outbox
 	*
	* @param string $action		action to perform
	* @param int $uid			uid of message to perform action on
	* @param boolean $text		show textlink? if not, only icon will be shown
	* @return string				HTML link
 	*/ 
 	function getActionLink($action, $uid, $text=false) {
		
		switch ($action) {
			
			case 'reply':
				$linkconf['parameter'] = $this->conf['formpage'];
				$linkconf['additionalParams'] = '&tx_keinsitemailbox_pi2[reply]='.intval($uid);
				$linktext = $this->replyIcon;
				$linktext .= $text ? $this->pi_getLL('reply') : '';
				$link = $this->cObj->typoLink($linktext,$linkconf);
				break;
			
			case 'forward' :
				$linkconf['parameter'] = $this->conf['formpage'];
				$linkconf['additionalParams'] = '&tx_keinsitemailbox_pi2[forward]='.intval($uid);
				$linktext = $this->forwardIcon;
				$linktext .= $text ? $this->pi_getLL('forward') : '';
				$link = $this->cObj->typoLink($linktext,$linkconf);
				break;
				
			case 'delete':
				$linkconf['parameter'] = $GLOBALS['TSFE']->id;
				$linkconf['additionalParams'] = '&tx_keinsitemailbox_pi1[delmessage]='.intval($uid);
				$linktext = $this->deleteIcon;
				$linktext .= $text ? $this->pi_getLL('delete') : '';
				$link = $this->cObj->typoLink($linktext,$linkconf);
				break;
				
			case 'markread':
				if (!$this->messageReadByRecipient($uid)) {
					$linkconf['parameter'] = $GLOBALS['TSFE']->id;
					$linkconf['additionalParams'] = '&tx_keinsitemailbox_pi1[markread]='.intval($uid);
					$linktext = $this->markreadIcon;
					$linktext .= $text ? $this->pi_getLL('markread') : '';
					$link = $this->cObj->typoLink($linktext,$linkconf);
				}
				else $link = '&nbsp;';
				break;

		}
		return $link;    
 	}
	
	
	/**
 	* check if a sent message was read by the recipient/s
 	*
	* @param string recipients uid(s) 
 	*/ 
 	function getReceiptStatus($messageUid, $recipients) {
		$recipients = explode(',', $recipients);
		#debug($recipients,'RECIPIENTS');
		$numRecipients = count($recipients);
		$numRead = 0;
		foreach ($recipients as $key => $val) {
			if ($this->messageReadByRecipient($messageUid, $val)) $numRead++; 
		}
		
		#debug($numRead.' / '.$numRecipients,1);
		
		// read by all recipients
		if ($numRead == $numRecipients) return $this->readByAllIcon;
		// read by some recipients
		else if ($numRead > 0 && $numRead < $numRecipients) return $this->readBySomeIcon;
		// read by no recipient
		else return $this->readByNoneIcon;
		
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
	
	
	/**
 	* Send message to single user
 	*
	* @param int $to	uid of recipient user
	* @param int $from	uid of sender user
	* @param string $subject	title of message
	* @param string $bodytext	content of message
	* @param int $notification_read		set to 1 if sender should be notificated when recipient reads the message
 	*/ 
 	function sendMessage($to, $bodytext, $from='', $subject='', $notification_read=0, $savePid='') {
		// if sender is not set, use current loggend in user as sender
		$sender = $from ? $from : $GLOBALS['TSFE']->fe_user->user['uid'];
		// set default subject if not defined
		$subj = $subject ? $subject : $this->config['no_subject'];
		// set pid to save messsage to if not defined
		$pid = $pid ? $pid : $this->conf['dataPid'];
		
		$fields_values = array(
			'pid' => $pid,
			'crdate' => time(),
			'tstamp' => time(),
			'cruser_id' => $sender,
			'sender' => $sender,
			'recipient' => $to,
			'bodytext' => $bodytext,
			'subject' => $this->sanitizeData($subj),
			'attachment' => '',
			'notification_read' => $notification_read,		
		);
		if ($GLOBALS['TYPO3_DB']->exec_INSERTquery($this->table,$fields_values,$no_quote_fields=FALSE)) return true;
		else return false;
 	}
	
	
	
	/**
 	* Description:
 	* Author: Andreas Kiefer (kiefer@kennziffer.com)
 	*
 	*/ 
	function sendNotificationRead($messageUid, $userID) {
		
		$data = $this->getMessageData($messageUid);
		
		$bodytext = 'gelesen';
		
		$this->sendMessage($data['sender'], $bodytext, $this->config['adminUser']);
		
		return $content;    
 	}
	
	
}



if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/ke_insitemailbox/pi1/class.tx_keinsitemailbox_pi1.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/ke_insitemailbox/pi1/class.tx_keinsitemailbox_pi1.php']);
}

?>