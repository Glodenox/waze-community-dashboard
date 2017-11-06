SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;


CREATE TABLE IF NOT EXISTS `dashboard_groups` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(200) COLLATE utf8_bin NOT NULL,
  `type` enum('Slack') COLLATE utf8_bin NOT NULL,
  `slack_team_id` varchar(12) COLLATE utf8_bin NOT NULL,
  PRIMARY KEY (`id`),
  KEY `slack_team_idx` (`slack_team_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

INSERT INTO `dashboard_groups` (`id`, `name`, `type`, `slack_team_id`) VALUES
(1, 'Waze BeNeLux', 'Slack', 'T037AT2KN');

CREATE TABLE IF NOT EXISTS `dashboard_reports` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `start_time` int(11) UNSIGNED DEFAULT NULL,
  `end_time` int(11) UNSIGNED DEFAULT NULL,
  `lon` decimal(18,15) NOT NULL,
  `lat` decimal(18,15) NOT NULL,
  `description` text COLLATE utf8_bin NOT NULL,
  `status` tinyint(3) UNSIGNED NOT NULL,
  `priority` tinyint(3) UNSIGNED NOT NULL DEFAULT '1',
  `required_editor_level` tinyint(4) NOT NULL DEFAULT '0',
  `source` smallint(5) UNSIGNED NOT NULL,
  `geojson` mediumtext COLLATE utf8_bin NOT NULL,
  `external_identifier` varchar(100) COLLATE utf8_bin NOT NULL,
  `external_data` text COLLATE utf8_bin NOT NULL,
  `last_modified` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `lon` (`lon`),
  KEY `lat` (`lat`),
  KEY `priority` (`priority`),
  KEY `status` (`status`) USING BTREE,
  KEY `source` (`source`) USING BTREE,
  KEY `editor_level` (`required_editor_level`) USING BTREE,
  KEY `start_time` (`start_time`),
  KEY `end_time` (`end_time`)
) ENGINE=InnoDB AUTO_INCREMENT=244754 DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE IF NOT EXISTS `dashboard_report_discussion` (
  `report_id` int(11) UNSIGNED NOT NULL,
  `user_id` int(11) UNSIGNED NOT NULL,
  `message` varchar(1000) COLLATE utf8_bin NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `report_id` (`report_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE IF NOT EXISTS `dashboard_report_filters` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `source_id` smallint(5) UNSIGNED NOT NULL,
  `name` varchar(300) COLLATE utf8_bin NOT NULL,
  `min_lon` decimal(9,6) NOT NULL,
  `max_lon` decimal(9,6) NOT NULL,
  `min_lat` decimal(9,6) NOT NULL,
  `max_lat` decimal(9,6) NOT NULL,
  `description` varchar(1000) COLLATE utf8_bin NOT NULL,
  PRIMARY KEY (`id`),
  KEY `source_id` (`source_id`)
) ENGINE=InnoDB AUTO_INCREMENT=79 DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE IF NOT EXISTS `dashboard_report_follow` (
  `report_id` int(11) UNSIGNED NOT NULL,
  `user_id` int(11) UNSIGNED NOT NULL,
  PRIMARY KEY (`report_id`,`user_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE IF NOT EXISTS `dashboard_report_history` (
  `report_id` int(11) UNSIGNED NOT NULL,
  `timestamp` int(11) UNSIGNED NOT NULL,
  `user_id` int(11) UNSIGNED DEFAULT NULL,
  `action_id` int(10) UNSIGNED NOT NULL,
  `value` smallint(5) UNSIGNED DEFAULT NULL COMMENT 'The new value caused by the action',
  `details` text COLLATE utf8_bin,
  KEY `report_id` (`report_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE IF NOT EXISTS `dashboard_sources` (
  `id` smallint(5) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(250) COLLATE utf8_bin NOT NULL,
  `description` varchar(1000) COLLATE utf8_bin NOT NULL,
  `url` varchar(200) COLLATE utf8_bin NOT NULL,
  `data_type` enum('Reports','News','Support Topics','Other') COLLATE utf8_bin NOT NULL,
  `update_cooldown` mediumint(8) UNSIGNED NOT NULL DEFAULT '10800' COMMENT 'Cooldown time before allowing next source update (default: 3 hours)',
  `notify` tinyint(1) NOT NULL,
  `state` enum('inactive','running','error') COLLATE utf8_bin NOT NULL DEFAULT 'inactive',
  `last_execution_result` varchar(2000) COLLATE utf8_bin NOT NULL,
  `last_update` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

INSERT INTO `dashboard_sources` (`id`, `name`, `description`, `url`, `data_type`, `update_cooldown`, `notify`, `state`, `last_execution_result`) VALUES
(1, 'GIPOD Work Assignments', 'Het Generiek Informatieplatform Openbaar Domein (GIPOD) brengt alle informatie over werken of manifestaties op het openbaar domein zoveel mogelijk samen.\nHet publiceren van informatie voor werkopdrachten en manifestaties gebeurt asynchroon.\nVier keer per dag worden de gegevens ter beschikking gesteld voor publicatie.\nNieuw aangemaakte werkopdrachten, wijzigingen aan werkopdrachten of het verwijderen van een werkopdracht, zullen pas na een synchronisatie (4x per dag) zichtbaar worden in de resultaten.', 'http://gipod.api.agiv.be/#!docs/technical.md', 'Reports', 5400, 1, 'inactive', '{\"received\":4029,\"new\":8,\"updated\":3,\"cancelled\":0,\"archived\":3,\"errors\":[]}'),
(2, 'GIPOD Manifestations', 'Het Generiek Informatieplatform Openbaar Domein (GIPOD) brengt alle informatie over werken of manifestaties op het openbaar domein zoveel mogelijk samen. Het publiceren van informatie voor werkopdrachten en manifestaties gebeurt asynchroon. Vier keer per dag worden de gegevens ter beschikking gesteld voor publicatie. Nieuw aangemaakte werkopdrachten, wijzigingen aan werkopdrachten of het verwijderen van een werkopdracht, zullen pas na een synchronisatie (4x per dag) zichtbaar worden in de resultaten.', 'http://gipod.api.agiv.be/#!docs/technical.md', 'Reports', 5400, 1, 'inactive', '{\"received\":5281,\"previous-count\":3889,\"new\":11,\"updated\":0,\"cancelled\":0,\"archived\":2,\"errors\":[]}'),
(3, 'Waze Forums', 'Searches various forums for topics that have yet to receive a first reply', 'https://www.waze.com/forum/index.php', 'Support Topics', 500, 0, 'inactive', '{\"received\":31,\"new\":0,\"archived\":0,\"errors\":[]}'),
(4, 'NDW wegwerkmeldingen (Wegstatus.nl)', 'Weergave van meldingen van wegafsluitingen zoals deze verzameld werden op wegstatus.nl', 'https://www.wegstatus.nl/wwinfo.php', 'Reports', 500, 0, 'inactive', '{\"received\":2378,\"previous-count\":2214,\"new\":120,\"updated\":60,\"cancelled\":0,\"archived\":4,\"errors\":[]}'),
(5, 'Unresolved Major Traffic Events', 'This source lists all created Major Traffic Events within the Benelux area that have not yet been submitted.', 'https://www.waze.com/events/unready', 'Reports', 2000, 0, 'inactive', '{\"received\":39,\"previous-count\":40,\"new\":1,\"updated\":0,\"archived\":2,\"errors\":[]}'),
(8, 'GRB Verschilbestand', 'GRB-verschilbestanden bevatten uitsluitend gegevens \r\nvan exemplaren die tussen 2 versies van het GRB Vlaanderen \r\nverwijderd werden of toegevoegd werden.', 'https://overheid.vlaanderen.be/producten-diensten/basiskaart-vlaanderen-grb', 'Reports', 0, 0, 'inactive', '{\"received\":480}');

CREATE TABLE IF NOT EXISTS `dashboard_support_forums` (
  `id` smallint(5) UNSIGNED NOT NULL,
  `name` varchar(150) COLLATE utf8_bin NOT NULL,
  `slack_team` varchar(20) COLLATE utf8_bin NOT NULL,
  `slack_channels` varchar(50) COLLATE utf8_bin NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE IF NOT EXISTS `dashboard_support_topics` (
  `id` int(11) UNSIGNED NOT NULL,
  `forum_id` smallint(5) UNSIGNED NOT NULL,
  `title` varchar(100) COLLATE utf8_bin NOT NULL,
  `timestamp` int(11) UNSIGNED NOT NULL,
  `status` smallint(5) UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  KEY `forum` (`forum_id`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE IF NOT EXISTS `dashboard_users` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `slack_user_id` varchar(12) COLLATE utf8_bin NOT NULL,
  `slack_access_token` char(50) COLLATE utf8_bin NOT NULL,
  `slack_team_id` varchar(12) COLLATE utf8_bin NOT NULL,
  `name` varchar(150) COLLATE utf8_bin NOT NULL,
  `avatar` varchar(200) COLLATE utf8_bin DEFAULT NULL,
  `editor_level` tinyint(3) UNSIGNED NOT NULL DEFAULT '1',
  `area_north` decimal(9,6) NOT NULL,
  `area_east` decimal(9,6) NOT NULL,
  `area_south` decimal(9,6) NOT NULL,
  `area_west` decimal(9,6) NOT NULL,
  `follow_bits` tinyint(3) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'Bits to indicate after which action the user wants to automatically follow a report',
  `notify_bits` smallint(6) NOT NULL DEFAULT '0' COMMENT 'Bits to indicate for which actions a user wants to receive notifications for followed reports',
  `claim_report` int(11) UNSIGNED DEFAULT NULL,
  `claim_time` int(11) UNSIGNED DEFAULT NULL,
  `process_auto_jump` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `slack_user_id` (`slack_user_id`),
  UNIQUE KEY `claim_report` (`claim_report`)
) ENGINE=InnoDB AUTO_INCREMENT=61 DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE IF NOT EXISTS `dashboard_user_sessions` (
  `session_id` char(40) COLLATE utf8_bin NOT NULL,
  `session_expires` int(10) UNSIGNED NOT NULL,
  `session_data` text COLLATE utf8_bin NOT NULL,
  PRIMARY KEY (`session_id`),
  KEY `expire_index` (`session_expires`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;


ALTER TABLE `dashboard_reports`
  ADD CONSTRAINT `dashboard_reports_ibfk_1` FOREIGN KEY (`source`) REFERENCES `dashboard_sources` (`id`);

ALTER TABLE `dashboard_report_discussion`
  ADD CONSTRAINT `dashboard_report_discussion_ibfk_1` FOREIGN KEY (`report_id`) REFERENCES `dashboard_reports` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `dashboard_report_discussion_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `dashboard_users` (`id`);

ALTER TABLE `dashboard_report_filters`
  ADD CONSTRAINT `dashboard_report_filters_ibfk_1` FOREIGN KEY (`source_id`) REFERENCES `dashboard_sources` (`id`) ON DELETE CASCADE;

ALTER TABLE `dashboard_report_follow`
  ADD CONSTRAINT `dashboard_report_follow_ibfk_1` FOREIGN KEY (`report_id`) REFERENCES `dashboard_reports` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `dashboard_report_follow_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `dashboard_users` (`id`);

ALTER TABLE `dashboard_report_history`
  ADD CONSTRAINT `history_report` FOREIGN KEY (`report_id`) REFERENCES `dashboard_reports` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `history_user` FOREIGN KEY (`user_id`) REFERENCES `dashboard_users` (`id`);

ALTER TABLE `dashboard_support_topics`
  ADD CONSTRAINT `forum_topic` FOREIGN KEY (`forum_id`) REFERENCES `dashboard_support_forums` (`id`);

ALTER TABLE `dashboard_users`
  ADD CONSTRAINT `claim_report` FOREIGN KEY (`claim_report`) REFERENCES `dashboard_reports` (`id`) ON DELETE SET NULL ON UPDATE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
