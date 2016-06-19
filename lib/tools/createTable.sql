DROP TABLE IF EXISTS `%TABLE_NAME%`;
CREATE TABLE `%TABLE_NAME%` (
  `sourcePageId` varchar(255) NOT NULL,
  `destinationPageId` varchar(255) NOT NULL,
  `references` int(10) unsigned NOT NULL,
  PRIMARY KEY  (`sourcePageId`(100),`destinationPageId`(100))
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
