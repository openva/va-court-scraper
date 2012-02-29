SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;

--
-- Database: `vacode`
--

-- --------------------------------------------------------

--
-- Table structure for table `court_decisions`
--

CREATE TABLE IF NOT EXISTS `court_decisions` (
  `id` smallint(5) unsigned NOT NULL auto_increment,
  `type` enum('appeals','supreme') collate utf8_bin NOT NULL,
  `record_number` varchar(7) collate utf8_bin NOT NULL,
  `name` varchar(150) collate utf8_bin NOT NULL,
  `date` date NOT NULL,
  `abstract` mediumtext collate utf8_bin NOT NULL,
  `decision` text collate utf8_bin NOT NULL,
  `date_created` datetime NOT NULL,
  `date_modified` timestamp NOT NULL default CURRENT_TIMESTAMP,
  PRIMARY KEY  (`id`),
  KEY `type` (`type`,`record_number`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

-- --------------------------------------------------------

--
-- Table structure for table `court_decision_laws`
--

CREATE TABLE IF NOT EXISTS `court_decision_laws` (
  `id` mediumint(8) unsigned NOT NULL auto_increment,
  `court_decision_id` smallint(5) unsigned NOT NULL,
  `law_id` int(10) unsigned default NULL,
  `law_section` varchar(16) collate utf8_bin NOT NULL,
  `mentions` tinyint(4) NOT NULL,
  `date_created` datetime NOT NULL,
  `date_modified` timestamp NOT NULL default CURRENT_TIMESTAMP,
  PRIMARY KEY  (`id`),
  KEY `court_decision_id` (`court_decision_id`,`law_id`,`law_section`),
  KEY `law_section` (`law_section`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_bin COMMENT='Xref of court decisions to laws mentioned within';

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
