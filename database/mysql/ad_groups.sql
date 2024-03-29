CREATE TABLE IF NOT EXISTS `adusrgrp` (
	`adusrgrpid`             bigint unsigned                           NOT NULL,
	`name`                   varchar(64)     DEFAULT ''                NOT NULL,
	`user_type`              integer         DEFAULT '1'               NOT NULL,
	PRIMARY KEY (adusrgrpid)
) ENGINE=InnoDB;
CREATE UNIQUE INDEX `adusrgrp_1` ON `adusrgrp` (`name`);
CREATE TABLE IF NOT EXISTS `adgroups_groups` (
	`id`                     bigint unsigned                           NOT NULL,
	`usrgrpid`               bigint unsigned                           NOT NULL,
	`adusrgrpid`             bigint unsigned                           NOT NULL,
	PRIMARY KEY (id)
) ENGINE=InnoDB;
CREATE UNIQUE INDEX `adgroups_groups_1` ON `adgroups_groups` (`usrgrpid`,`adusrgrpid`);
CREATE INDEX `adgroups_groups_2` ON `adgroups_groups` (`adusrgrpid`);
ALTER TABLE `adgroups_groups` ADD CONSTRAINT `c_adgroups_groups_1` FOREIGN KEY (`usrgrpid`) REFERENCES `usrgrp` (`usrgrpid`) ON DELETE CASCADE;
ALTER TABLE `adgroups_groups` ADD CONSTRAINT `c_adgroups_groups_2` FOREIGN KEY (`adusrgrpid`) REFERENCES `adusrgrp` (`adusrgrpid`) ON DELETE CASCADE;
