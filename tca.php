<?php
if (!defined ('TYPO3_MODE')) 	die ('Access denied.');

$TCA["tx_keinsitemailbox_messages"] = array (
	"ctrl" => $TCA["tx_keinsitemailbox_messages"]["ctrl"],
	"interface" => array (
		"showRecordFieldList" => "hidden,subject,sender,recipient,bodytext,attachment,notification_read"
	),
	"feInterface" => $TCA["tx_keinsitemailbox_messages"]["feInterface"],
	"columns" => array (
		'hidden' => array (		
			'exclude' => 1,
			'label'   => 'LLL:EXT:lang/locallang_general.xml:LGL.hidden',
			'config'  => array (
				'type'    => 'check',
				'default' => '0'
			)
		),
		"subject" => Array (		
			"exclude" => 1,		
			"label" => "LLL:EXT:ke_insitemailbox/locallang_db.xml:tx_keinsitemailbox_messages.subject",		
			"config" => Array (
				"type" => "input",	
				"size" => "30",
			)
		),
		"sender" => Array (		
			"exclude" => 1,		
			"label" => "LLL:EXT:ke_insitemailbox/locallang_db.xml:tx_keinsitemailbox_messages.sender",		
			"config" => Array (
				"type" => "group",	
				"internal_type" => "db",	
				"allowed" => "fe_users",	
				"size" => 1,	
				"minitems" => 0,
				"maxitems" => 1,
			)
		),
		"recipient" => Array (		
			"exclude" => 1,		
			"label" => "LLL:EXT:ke_insitemailbox/locallang_db.xml:tx_keinsitemailbox_messages.recipient",		
			"config" => Array (
				"type" => "group",	
				"internal_type" => "db",	
				"allowed" => "fe_users",	
				"size" => 3,	
				"minitems" => 0,
				"maxitems" => 99,
			)
		),
		"bodytext" => Array (		
			"exclude" => 1,		
			"label" => "LLL:EXT:ke_insitemailbox/locallang_db.xml:tx_keinsitemailbox_messages.bodytext",		
			"config" => Array (
				"type" => "text",
				"cols" => "30",
				"rows" => "5",
			)
		),
		"attachment" => Array (		
			"exclude" => 1,		
			"label" => "LLL:EXT:ke_insitemailbox/locallang_db.xml:tx_keinsitemailbox_messages.attachment",		
			"config" => Array (
				"type" => "group",
				"internal_type" => "file",
				"allowed" => "",	
				"disallowed" => "php,php3",	
				"max_size" => 1000,	
				"uploadfolder" => "uploads/tx_keinsitemailbox",
				"show_thumbs" => 1,	
				"size" => 1,	
				"minitems" => 0,
				"maxitems" => 1,
			)
		),
		"notification_read" => Array (		
			"exclude" => 1,		
			"label" => "LLL:EXT:ke_insitemailbox/locallang_db.xml:tx_keinsitemailbox_messages.notification_read",		
			"config" => Array (
				"type" => "check",
			)
		),
	),
	"types" => array (
		"0" => array("showitem" => "hidden;;1;;1-1-1, subject, sender, recipient, bodytext;;;richtext[paste|bold|italic|underline|formatblock|class|left|center|right|orderedlist|unorderedlist|outdent|indent|link|image]:rte_transform[mode=ts], attachment, notification_read")
	),
	"palettes" => array (
		"1" => array("showitem" => "")
	)
);



$TCA["tx_keinsitemailbox_log"] = array (
	"ctrl" => $TCA["tx_keinsitemailbox_log"]["ctrl"],
	"interface" => array (
		"showRecordFieldList" => "hidden,message,recipient,action"
	),
	"feInterface" => $TCA["tx_keinsitemailbox_log"]["feInterface"],
	"columns" => array (
		'hidden' => array (		
			'exclude' => 1,
			'label'   => 'LLL:EXT:lang/locallang_general.xml:LGL.hidden',
			'config'  => array (
				'type'    => 'check',
				'default' => '0'
			)
		),
		"message" => Array (		
			"exclude" => 1,		
			"label" => "LLL:EXT:ke_insitemailbox/locallang_db.xml:tx_keinsitemailbox_log.message",		
			"config" => Array (
				"type" => "group",	
				"internal_type" => "db",	
				"allowed" => "tx_keinsitemailbox_messages",	
				"size" => 1,	
				"minitems" => 0,
				"maxitems" => 1,
			)
		),
		"recipient" => Array (		
			"exclude" => 1,		
			"label" => "LLL:EXT:ke_insitemailbox/locallang_db.xml:tx_keinsitemailbox_log.recipient",		
			"config" => Array (
				"type" => "group",	
				"internal_type" => "db",	
				"allowed" => "fe_users",	
				"size" => 1,	
				"minitems" => 0,
				"maxitems" => 1,
			)
		),
		"action" => Array (		
			"exclude" => 1,		
			"label" => "LLL:EXT:ke_insitemailbox/locallang_db.xml:tx_keinsitemailbox_log.action",		
			"config" => Array (
				"type" => "input",	
				"size" => "30",
			)
		),
	),
	"types" => array (
		"0" => array("showitem" => "hidden;;1;;1-1-1, message, recipient, action")
	),
	"palettes" => array (
		"1" => array("showitem" => "")
	)
);
?>