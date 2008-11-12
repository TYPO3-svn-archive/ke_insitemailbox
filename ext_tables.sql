#
# Table structure for table 'tx_keinsitemailbox_messages'
#
CREATE TABLE tx_keinsitemailbox_messages (
	uid int(11) NOT NULL auto_increment,
	pid int(11) DEFAULT '0' NOT NULL,
	tstamp int(11) DEFAULT '0' NOT NULL,
	crdate int(11) DEFAULT '0' NOT NULL,
	cruser_id int(11) DEFAULT '0' NOT NULL,
	deleted tinyint(4) DEFAULT '0' NOT NULL,
	hidden tinyint(4) DEFAULT '0' NOT NULL,
	subject tinytext NOT NULL,
	sender blob NOT NULL,
	recipient blob NOT NULL,
	bodytext text NOT NULL,
	attachment blob NOT NULL,
	
	PRIMARY KEY (uid),
	KEY parent (pid)
);



#
# Table structure for table 'tx_keinsitemailbox_log'
#
CREATE TABLE tx_keinsitemailbox_log (
	uid int(11) NOT NULL auto_increment,
	pid int(11) DEFAULT '0' NOT NULL,
	tstamp int(11) DEFAULT '0' NOT NULL,
	crdate int(11) DEFAULT '0' NOT NULL,
	cruser_id int(11) DEFAULT '0' NOT NULL,
	deleted tinyint(4) DEFAULT '0' NOT NULL,
	hidden tinyint(4) DEFAULT '0' NOT NULL,
	message blob NOT NULL,
	recipient blob NOT NULL,
	action tinytext NOT NULL,
	
	PRIMARY KEY (uid),
	KEY parent (pid)
);