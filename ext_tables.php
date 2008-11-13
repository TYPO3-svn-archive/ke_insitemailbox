<?php
if (!defined ('TYPO3_MODE')) 	die ('Access denied.');
$TCA["tx_keinsitemailbox_messages"] = array (
	"ctrl" => array (
		'title'     => 'LLL:EXT:ke_insitemailbox/locallang_db.xml:tx_keinsitemailbox_messages',		
		'label'     => 'subject',	
		'tstamp'    => 'tstamp',
		'crdate'    => 'crdate',
		'cruser_id' => 'cruser_id',
		'default_sortby' => "ORDER BY crdate",	
		'delete' => 'deleted',	
		'enablecolumns' => array (		
			'disabled' => 'hidden',
		),
		'dynamicConfigFile' => t3lib_extMgm::extPath($_EXTKEY).'tca.php',
		'iconfile'          => t3lib_extMgm::extRelPath($_EXTKEY).'icon_tx_keinsitemailbox_messages.gif',
	),
	"feInterface" => array (
		"fe_admin_fieldList" => "hidden, subject, sender, recipient, bodytext, attachment, notification_read",
	)
);

$TCA["tx_keinsitemailbox_log"] = array (
	"ctrl" => array (
		'title'     => 'LLL:EXT:ke_insitemailbox/locallang_db.xml:tx_keinsitemailbox_log',		
		'label'     => 'message',	
		'tstamp'    => 'tstamp',
		'crdate'    => 'crdate',
		'cruser_id' => 'cruser_id',
		'default_sortby' => "ORDER BY crdate",	
		'delete' => 'deleted',	
		'enablecolumns' => array (		
			'disabled' => 'hidden',
		),
		'dynamicConfigFile' => t3lib_extMgm::extPath($_EXTKEY).'tca.php',
		'iconfile'          => t3lib_extMgm::extRelPath($_EXTKEY).'icon_tx_keinsitemailbox_log.gif',
	),
	"feInterface" => array (
		"fe_admin_fieldList" => "hidden, message, recipient, action",
	)
);


t3lib_div::loadTCA('tt_content');
$TCA['tt_content']['types']['list']['subtypes_excludelist'][$_EXTKEY.'_pi1']='layout,select_key';


t3lib_extMgm::addPlugin(array('LLL:EXT:ke_insitemailbox/locallang_db.xml:tt_content.list_type_pi1', $_EXTKEY.'_pi1'),'list_type');


t3lib_extMgm::addStaticFile($_EXTKEY,'pi1/static/','Show Insite Mailbox');


if (TYPO3_MODE=="BE")	$TBE_MODULES_EXT["xMOD_db_new_content_el"]["addElClasses"]["tx_keinsitemailbox_pi1_wizicon"] = t3lib_extMgm::extPath($_EXTKEY).'pi1/class.tx_keinsitemailbox_pi1_wizicon.php';


t3lib_div::loadTCA('tt_content');
$TCA['tt_content']['types']['list']['subtypes_excludelist'][$_EXTKEY.'_pi2']='layout,select_key';


t3lib_extMgm::addPlugin(array('LLL:EXT:ke_insitemailbox/locallang_db.xml:tt_content.list_type_pi2', $_EXTKEY.'_pi2'),'list_type');


t3lib_extMgm::addStaticFile($_EXTKEY,"pi2/static/","Insite Mails Form");


if (TYPO3_MODE=="BE")	$TBE_MODULES_EXT["xMOD_db_new_content_el"]["addElClasses"]["tx_keinsitemailbox_pi2_wizicon"] = t3lib_extMgm::extPath($_EXTKEY).'pi2/class.tx_keinsitemailbox_pi2_wizicon.php';
?>