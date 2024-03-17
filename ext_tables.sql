CREATE TABLE `tx_videoprocessing_task`
(
    `uid`           int(11)        NOT NULL AUTO_INCREMENT,
    `pid`           int(11)        NOT NULL DEFAULT '0',
    `tstamp`        int(11)        NOT NULL DEFAULT '0',
    `crdate`        int(11)        NOT NULL DEFAULT '0',
    `file`          int(11)        NOT NULL,
    `configuration` varbinary(767) NOT NULL,
    `status`        varchar(15)    NOT NULL DEFAULT 'new',
    `progress`      json                    DEFAULT NULL,
    `priority`      int(11)        NOT NULL DEFAULT '0',
    PRIMARY KEY (`uid`),
    KEY `task` (`file`, `configuration`, `status`),
);

#[depricated]
#CREATE TABLE `tx_videoprocessing_cloudconvert_process`
#(
#  `uid`     int(11)        NOT NULL AUTO_INCREMENT,
#  `pid`     int(11)        NOT NULL DEFAULT '0',
#  `tstamp`  int(11)        NOT NULL DEFAULT '0',
#  `crdate`  int(11)        NOT NULL DEFAULT '0',
#  `file`    int(11)        NOT NULL,
#  `mode`    varchar(7)     NOT NULL,
#  `options` varbinary(767) NOT NULL,
#  `status`  text                    DEFAULT NULL,
#  `failed`  tinyint(1)     NOT NULL DEFAULT '0',
#  PRIMARY KEY (`uid`),
#  UNIQUE KEY `process` (`file`, `mode`, `options`),
#);

CREATE TABLE `sys_file_processedfile`
(
    `mime_type` varchar(255) NOT NULL DEFAULT '',
);

CREATE TABLE `sys_file_metadata`
(
    `video_metadata_extraction_tried` tinyint(1) NOT NULL DEFAULT '0',
);

#
# Table structure for table 'tx_examples_log'
#
# The KEY on request_id is optional
#
CREATE TABLE tx_videoprocessing_log
(
    request_id varchar(13) DEFAULT '' NOT NULL,
    time_micro double(16, 4) NOT NULL default '0.0000',
    component varchar(255) DEFAULT '' NOT NULL,
    level tinyint(1) unsigned DEFAULT '0' NOT NULL,
    message text,
    data text,

    KEY request (request_id)
);



--
-- Table structure for table 'tt_content'
--
CREATE TABLE tt_content (
    background_media int(11) unsigned DEFAULT '0'
);