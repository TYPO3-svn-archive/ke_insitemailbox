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


class insiteMailbox extends tslib_pibase {
	var $extKey        = 'ke_insitemailbox';	// The extension key.
	var $table 		= 'tx_keinsitemailbox_messages'; 	// main data table where messages are stored
	var $logtable 	= 'tx_keinsitemailbox_log'; 	// table where read messages are stored
	var $uploadsFolder = 'uploads/tx_keinsitemailbox/'; 	// path where attachments are stored
	var $cObj; 
	
	
	function init() {
		$this->cObj = t3lib_div::makeInstance('tslib_cObj');
		
		$this->defaultTemplateFile = t3lib_extMgm::siteRelPath($this->extKey).'pi1/template_keinsitemailbox_pi1.html';
		$this->templateFile = $this->conf['templateFile'] ? $this->conf['templateFile'] : $this->defaultTemplateFile;
		$this->templateCode = $this->cObj->fileResource($this->templateFile);
		
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
	function messageReadByRecipient($uid) {
		$fields = '*';
		$table = 'tx_keinsitemailbox_log';
		$where = 'message="'.intval($uid).'" AND recipient="'.intval($GLOBALS['TSFE']->fe_user->user['uid']).'" AND action="read" ';
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery($fields,$table,$where,$groupBy='',$orderBy='',$limit='');
		$anz = $GLOBALS['TYPO3_DB']->sql_num_rows($res);
		return $anz ? true : false;
	}
	
	/*
	* Marks a message as read by the current user
	*
	*@param int $uid 	uid of message
	*/
	function markMessageAsRead($uid) {
		
		$fields_values = array(
			'pid' => $this->conf['dataPid'],
			'message' => intval($uid),
			'recipient' => intval($GLOBALS['TSFE']->fe_user->user['uid']),
			'action' => 'read',
		);
		$GLOBALS['TYPO3_DB']->exec_INSERTquery($this->logtable,$fields_values,$no_quote_fields=FALSE);
	
	}
	
	
	/**
 	* Sets a single message as deleted for a single recipient
	*
 	* @param int $uid		uid of message
 	*/ 
 	function markMessageAsDeleted($uid) {
		
		$fields_values = array(
			'pid' => $this->conf['dataPid'],
			'message' => intval($uid),
			'recipient' => intval($GLOBALS['TSFE']->fe_user->user['uid']),
			'action' => 'deleted',
		);
		$GLOBALS['TYPO3_DB']->exec_INSERTquery($this->logtable,$fields_values,$no_quote_fields=FALSE);
		
		return $content;
 	}
		
		
	/**
 	* Check if message is deleted by a recipient
 	*
	* param int $uid		uid of message
 	*/ 
 	function messageDeletedByRecipient($uid) {
		$fields = '*';
		$table = 'tx_keinsitemailbox_log';
		$where = 'message="'.intval($uid).'" AND recipient="'.intval($GLOBALS['TSFE']->fe_user->user['uid']).'" AND action="deleted" ';
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery($fields,$table,$where,$groupBy='',$orderBy='',$limit='');
		$anz = $GLOBALS['TYPO3_DB']->sql_num_rows($res);
		return $anz ? true : false;
 	}
	
	
}



if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/ke_insitemailbox/pi1/class.tx_keinsitemailbox_pi1.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/ke_insitemailbox/pi1/class.tx_keinsitemailbox_pi1.php']);
}

?>