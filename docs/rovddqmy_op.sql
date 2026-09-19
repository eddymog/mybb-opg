-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Sep 19, 2026 at 08:47 AM
-- Server version: 5.7.44-48
-- PHP Version: 8.4.24

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `rovddqmy_op`
--

-- --------------------------------------------------------

--
-- Table structure for table `back_up_audit_recompensas`
--

CREATE TABLE `back_up_audit_recompensas` (
  `id` int(10) NOT NULL DEFAULT '0',
  `tiempo_completado` int(11) NOT NULL,
  `tiempo_nuevo` int(11) NOT NULL,
  `dia` int(11) NOT NULL,
  `season` int(11) NOT NULL,
  `uid` int(11) NOT NULL,
  `nombre` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `audit` varchar(255) COLLATE utf8_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `back_up_recompensas`
--

CREATE TABLE `back_up_recompensas` (
  `id` int(11) NOT NULL DEFAULT '0',
  `uid` int(11) NOT NULL,
  `nombre` varchar(255) CHARACTER SET utf8 NOT NULL,
  `dia` int(11) NOT NULL,
  `season` int(11) NOT NULL,
  `tiempo` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_adminlog`
--

CREATE TABLE `mybb_adminlog` (
  `uid` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `ipaddress` varbinary(16) NOT NULL DEFAULT '',
  `dateline` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `module` varchar(50) NOT NULL DEFAULT '',
  `action` varchar(50) NOT NULL DEFAULT '',
  `data` text NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_adminoptions`
--

CREATE TABLE `mybb_adminoptions` (
  `uid` int(11) NOT NULL DEFAULT '0',
  `cpstyle` varchar(50) NOT NULL DEFAULT '',
  `cplanguage` varchar(50) NOT NULL DEFAULT '',
  `codepress` tinyint(1) NOT NULL DEFAULT '1',
  `notes` text NOT NULL,
  `permissions` text NOT NULL,
  `defaultviews` text NOT NULL,
  `loginattempts` smallint(5) UNSIGNED NOT NULL DEFAULT '0',
  `loginlockoutexpiry` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `authsecret` varchar(16) NOT NULL DEFAULT '',
  `recovery_codes` varchar(177) NOT NULL DEFAULT ''
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_adminsessions`
--

CREATE TABLE `mybb_adminsessions` (
  `sid` varchar(32) NOT NULL DEFAULT '',
  `uid` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `loginkey` varchar(50) NOT NULL DEFAULT '',
  `ip` varbinary(16) NOT NULL DEFAULT '',
  `dateline` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `lastactive` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `data` text NOT NULL,
  `useragent` varchar(200) NOT NULL DEFAULT '',
  `authenticated` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_adminviews`
--

CREATE TABLE `mybb_adminviews` (
  `vid` int(10) UNSIGNED NOT NULL,
  `uid` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `title` varchar(100) NOT NULL DEFAULT '',
  `type` varchar(6) NOT NULL DEFAULT '',
  `visibility` tinyint(1) NOT NULL DEFAULT '0',
  `fields` text NOT NULL,
  `conditions` text NOT NULL,
  `custom_profile_fields` text NOT NULL,
  `sortby` varchar(20) NOT NULL DEFAULT '',
  `sortorder` varchar(4) NOT NULL DEFAULT '',
  `perpage` smallint(4) UNSIGNED NOT NULL DEFAULT '0',
  `view_type` varchar(6) NOT NULL DEFAULT ''
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_announcements`
--

CREATE TABLE `mybb_announcements` (
  `aid` int(10) UNSIGNED NOT NULL,
  `fid` int(11) NOT NULL DEFAULT '0',
  `uid` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `subject` varchar(120) NOT NULL DEFAULT '',
  `message` text NOT NULL,
  `startdate` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `enddate` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `allowhtml` tinyint(1) NOT NULL DEFAULT '0',
  `allowmycode` tinyint(1) NOT NULL DEFAULT '0',
  `allowsmilies` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_attachments`
--

CREATE TABLE `mybb_attachments` (
  `aid` int(10) UNSIGNED NOT NULL,
  `pid` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `posthash` varchar(50) NOT NULL DEFAULT '',
  `uid` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `filename` varchar(255) NOT NULL DEFAULT '',
  `filetype` varchar(120) NOT NULL DEFAULT '',
  `filesize` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `attachname` varchar(255) NOT NULL DEFAULT '',
  `downloads` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `dateuploaded` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `visible` tinyint(1) NOT NULL DEFAULT '0',
  `thumbnail` varchar(120) NOT NULL DEFAULT ''
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_attachtypes`
--

CREATE TABLE `mybb_attachtypes` (
  `atid` int(10) UNSIGNED NOT NULL,
  `name` varchar(120) NOT NULL DEFAULT '',
  `mimetype` varchar(120) NOT NULL DEFAULT '',
  `extension` varchar(10) NOT NULL DEFAULT '',
  `maxsize` int(15) UNSIGNED NOT NULL DEFAULT '0',
  `icon` varchar(100) NOT NULL DEFAULT '',
  `enabled` tinyint(1) NOT NULL DEFAULT '1',
  `forcedownload` tinyint(1) NOT NULL DEFAULT '0',
  `groups` text NOT NULL,
  `forums` text NOT NULL,
  `avatarfile` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_audit_op_fichas`
--

CREATE TABLE `mybb_audit_op_fichas` (
  `id` int(10) NOT NULL,
  `fid` int(10) NOT NULL,
  `nombre` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
  `apodo` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
  `faccion` varchar(20) COLLATE utf8_unicode_ci NOT NULL,
  `raza` varchar(20) COLLATE utf8_unicode_ci NOT NULL,
  `berries` int(11) NOT NULL DEFAULT '0',
  `puntos_estadistica` int(11) NOT NULL DEFAULT '50',
  `nivel` int(11) NOT NULL DEFAULT '1',
  `fuerza` int(3) NOT NULL DEFAULT '0',
  `fuerza_pasiva` int(3) NOT NULL DEFAULT '0',
  `resistencia` int(3) NOT NULL DEFAULT '0',
  `resistencia_pasiva` int(3) NOT NULL DEFAULT '0',
  `destreza` int(3) NOT NULL DEFAULT '0',
  `destreza_pasiva` int(3) NOT NULL DEFAULT '0',
  `voluntad` int(3) NOT NULL DEFAULT '0',
  `voluntad_pasiva` int(3) NOT NULL DEFAULT '0',
  `punteria` int(3) NOT NULL DEFAULT '0',
  `punteria_pasiva` int(3) NOT NULL DEFAULT '0',
  `agilidad` int(3) NOT NULL DEFAULT '0',
  `agilidad_pasiva` int(3) NOT NULL DEFAULT '0',
  `reflejos` int(3) NOT NULL DEFAULT '0',
  `reflejos_pasiva` int(3) NOT NULL DEFAULT '0',
  `control_akuma` int(3) NOT NULL DEFAULT '0',
  `control_akuma_pasiva` int(3) NOT NULL DEFAULT '0',
  `vitalidad` int(11) NOT NULL DEFAULT '0',
  `vitalidad_pasiva` int(10) NOT NULL DEFAULT '0',
  `energia` int(11) NOT NULL DEFAULT '0',
  `energia_pasiva` int(10) NOT NULL DEFAULT '0',
  `haki` int(11) NOT NULL DEFAULT '0',
  `haki_pasiva` int(10) NOT NULL DEFAULT '0',
  `nika` int(11) NOT NULL DEFAULT '0',
  `kuro` int(10) NOT NULL DEFAULT '0',
  `rasgos_positivos` text COLLATE utf8_unicode_ci NOT NULL,
  `rasgos_negativos` text COLLATE utf8_unicode_ci NOT NULL,
  `reputacion` int(11) NOT NULL DEFAULT '0',
  `reputacion_positiva` int(11) NOT NULL DEFAULT '0',
  `reputacion_negativa` int(11) NOT NULL DEFAULT '0',
  `rango` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'Novato',
  `fama` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'Desconocido',
  `belica1` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `belicas` json NOT NULL,
  `oficios` json NOT NULL,
  `belica2` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `belica3` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `belica4` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `belica5` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `oficio1` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `puntos_oficio` int(10) NOT NULL DEFAULT '0',
  `oficio2` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `estilo1` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'no_bloqueado',
  `estilo2` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'bloqueado',
  `estilo3` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'bloqueado',
  `estilos` json DEFAULT NULL,
  `akuma` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `akuma_subnombre` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `hao` int(5) NOT NULL DEFAULT '-1',
  `hao_chance` int(5) NOT NULL DEFAULT '1',
  `kenbun` int(5) NOT NULL DEFAULT '0',
  `buso` int(5) NOT NULL DEFAULT '0',
  `wanted_repu` int(11) NOT NULL DEFAULT '0',
  `muerto` int(10) NOT NULL DEFAULT '0',
  `Fecha` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `belica6` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `estilo4` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_audit_users`
--

CREATE TABLE `mybb_audit_users` (
  `id` int(10) NOT NULL,
  `uid` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `username` varchar(120) CHARACTER SET utf8 NOT NULL DEFAULT '',
  `newpoints` decimal(16,2) NOT NULL DEFAULT '0.00',
  `fecha` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_awaitingactivation`
--

CREATE TABLE `mybb_awaitingactivation` (
  `aid` int(10) UNSIGNED NOT NULL,
  `uid` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `dateline` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `code` varchar(100) NOT NULL DEFAULT '',
  `type` char(1) NOT NULL DEFAULT '',
  `validated` tinyint(1) NOT NULL DEFAULT '0',
  `misc` varchar(255) NOT NULL DEFAULT ''
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_badwords`
--

CREATE TABLE `mybb_badwords` (
  `bid` int(10) UNSIGNED NOT NULL,
  `badword` varchar(100) NOT NULL DEFAULT '',
  `regex` tinyint(1) NOT NULL DEFAULT '0',
  `replacement` varchar(100) NOT NULL DEFAULT ''
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_banfilters`
--

CREATE TABLE `mybb_banfilters` (
  `fid` int(10) UNSIGNED NOT NULL,
  `filter` varchar(200) NOT NULL DEFAULT '',
  `type` tinyint(1) NOT NULL DEFAULT '0',
  `lastuse` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `dateline` int(10) UNSIGNED NOT NULL DEFAULT '0'
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_banned`
--

CREATE TABLE `mybb_banned` (
  `uid` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `gid` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `oldgroup` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `oldadditionalgroups` text NOT NULL,
  `olddisplaygroup` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `admin` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `dateline` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `bantime` varchar(50) NOT NULL DEFAULT '',
  `lifted` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `reason` varchar(255) NOT NULL DEFAULT ''
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_buddyrequests`
--

CREATE TABLE `mybb_buddyrequests` (
  `id` int(10) UNSIGNED NOT NULL,
  `uid` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `touid` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `date` int(10) UNSIGNED NOT NULL DEFAULT '0'
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_calendarpermissions`
--

CREATE TABLE `mybb_calendarpermissions` (
  `cid` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `gid` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `canviewcalendar` tinyint(1) NOT NULL DEFAULT '0',
  `canaddevents` tinyint(1) NOT NULL DEFAULT '0',
  `canbypasseventmod` tinyint(1) NOT NULL DEFAULT '0',
  `canmoderateevents` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_calendars`
--

CREATE TABLE `mybb_calendars` (
  `cid` int(10) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL DEFAULT '',
  `disporder` smallint(5) UNSIGNED NOT NULL DEFAULT '0',
  `startofweek` tinyint(1) NOT NULL DEFAULT '0',
  `showbirthdays` tinyint(1) NOT NULL DEFAULT '0',
  `eventlimit` smallint(3) UNSIGNED NOT NULL DEFAULT '0',
  `moderation` tinyint(1) NOT NULL DEFAULT '0',
  `allowhtml` tinyint(1) NOT NULL DEFAULT '0',
  `allowmycode` tinyint(1) NOT NULL DEFAULT '0',
  `allowimgcode` tinyint(1) NOT NULL DEFAULT '0',
  `allowvideocode` tinyint(1) NOT NULL DEFAULT '0',
  `allowsmilies` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_captcha`
--

CREATE TABLE `mybb_captcha` (
  `imagehash` varchar(32) NOT NULL DEFAULT '',
  `imagestring` varchar(8) NOT NULL DEFAULT '',
  `dateline` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `used` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_datacache`
--

CREATE TABLE `mybb_datacache` (
  `title` varchar(50) NOT NULL DEFAULT '',
  `cache` mediumtext NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_delayedmoderation`
--

CREATE TABLE `mybb_delayedmoderation` (
  `did` int(10) UNSIGNED NOT NULL,
  `type` varchar(30) NOT NULL DEFAULT '',
  `delaydateline` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `uid` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `fid` smallint(5) UNSIGNED NOT NULL DEFAULT '0',
  `tids` text NOT NULL,
  `dateline` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `inputs` text NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_events`
--

CREATE TABLE `mybb_events` (
  `eid` int(10) UNSIGNED NOT NULL,
  `cid` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `uid` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `name` varchar(120) NOT NULL DEFAULT '',
  `description` text NOT NULL,
  `visible` tinyint(1) NOT NULL DEFAULT '0',
  `private` tinyint(1) NOT NULL DEFAULT '0',
  `dateline` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `starttime` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `endtime` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `timezone` varchar(5) NOT NULL DEFAULT '',
  `ignoretimezone` tinyint(1) NOT NULL DEFAULT '0',
  `usingtime` tinyint(1) NOT NULL DEFAULT '0',
  `repeats` text NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_forumpermissions`
--

CREATE TABLE `mybb_forumpermissions` (
  `pid` int(10) UNSIGNED NOT NULL,
  `fid` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `gid` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `canview` tinyint(1) NOT NULL DEFAULT '0',
  `canviewthreads` tinyint(1) NOT NULL DEFAULT '0',
  `canonlyviewownthreads` tinyint(1) NOT NULL DEFAULT '0',
  `candlattachments` tinyint(1) NOT NULL DEFAULT '0',
  `canpostthreads` tinyint(1) NOT NULL DEFAULT '0',
  `canpostreplys` tinyint(1) NOT NULL DEFAULT '0',
  `canonlyreplyownthreads` tinyint(1) NOT NULL DEFAULT '0',
  `canpostattachments` tinyint(1) NOT NULL DEFAULT '0',
  `canratethreads` tinyint(1) NOT NULL DEFAULT '0',
  `caneditposts` tinyint(1) NOT NULL DEFAULT '0',
  `candeleteposts` tinyint(1) NOT NULL DEFAULT '0',
  `candeletethreads` tinyint(1) NOT NULL DEFAULT '0',
  `caneditattachments` tinyint(1) NOT NULL DEFAULT '0',
  `canviewdeletionnotice` tinyint(1) NOT NULL DEFAULT '0',
  `modposts` tinyint(1) NOT NULL DEFAULT '0',
  `modthreads` tinyint(1) NOT NULL DEFAULT '0',
  `mod_edit_posts` tinyint(1) NOT NULL DEFAULT '0',
  `modattachments` tinyint(1) NOT NULL DEFAULT '0',
  `canpostpolls` tinyint(1) NOT NULL DEFAULT '0',
  `canvotepolls` tinyint(1) NOT NULL DEFAULT '0',
  `cansearch` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_forums`
--

CREATE TABLE `mybb_forums` (
  `fid` smallint(5) UNSIGNED NOT NULL,
  `name` varchar(120) NOT NULL DEFAULT '',
  `description` text NOT NULL,
  `linkto` varchar(180) NOT NULL DEFAULT '',
  `type` char(1) NOT NULL DEFAULT '',
  `pid` smallint(5) UNSIGNED NOT NULL DEFAULT '0',
  `parentlist` text NOT NULL,
  `disporder` smallint(5) UNSIGNED NOT NULL DEFAULT '0',
  `active` tinyint(1) NOT NULL DEFAULT '0',
  `open` tinyint(1) NOT NULL DEFAULT '0',
  `threads` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `posts` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `lastpost` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `lastposter` varchar(120) NOT NULL DEFAULT '',
  `lastposteruid` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `lastposttid` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `lastpostsubject` varchar(120) NOT NULL DEFAULT '',
  `allowhtml` tinyint(1) NOT NULL DEFAULT '0',
  `allowmycode` tinyint(1) NOT NULL DEFAULT '0',
  `allowsmilies` tinyint(1) NOT NULL DEFAULT '0',
  `allowimgcode` tinyint(1) NOT NULL DEFAULT '0',
  `allowvideocode` tinyint(1) NOT NULL DEFAULT '0',
  `allowpicons` tinyint(1) NOT NULL DEFAULT '0',
  `allowtratings` tinyint(1) NOT NULL DEFAULT '0',
  `usepostcounts` tinyint(1) NOT NULL DEFAULT '0',
  `usethreadcounts` tinyint(1) NOT NULL DEFAULT '0',
  `requireprefix` tinyint(1) NOT NULL DEFAULT '0',
  `password` varchar(50) NOT NULL DEFAULT '',
  `showinjump` tinyint(1) NOT NULL DEFAULT '0',
  `style` smallint(5) UNSIGNED NOT NULL DEFAULT '0',
  `overridestyle` tinyint(1) NOT NULL DEFAULT '0',
  `rulestype` tinyint(1) NOT NULL DEFAULT '0',
  `rulestitle` varchar(200) NOT NULL DEFAULT '',
  `rules` text NOT NULL,
  `unapprovedthreads` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `unapprovedposts` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `deletedthreads` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `deletedposts` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `defaultdatecut` smallint(4) UNSIGNED NOT NULL DEFAULT '0',
  `defaultsortby` varchar(10) NOT NULL DEFAULT '',
  `defaultsortorder` varchar(4) NOT NULL DEFAULT '',
  `oculto` int(10) NOT NULL DEFAULT '0',
  `subisla` int(10) NOT NULL DEFAULT '0',
  `parent_isla` int(10) NOT NULL DEFAULT '0',
  `isla_rol` int(10) NOT NULL DEFAULT '0'
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_forumsread`
--

CREATE TABLE `mybb_forumsread` (
  `fid` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `uid` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `dateline` int(10) UNSIGNED NOT NULL DEFAULT '0'
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_forumsubscriptions`
--

CREATE TABLE `mybb_forumsubscriptions` (
  `fsid` int(10) UNSIGNED NOT NULL,
  `fid` smallint(5) UNSIGNED NOT NULL DEFAULT '0',
  `uid` int(10) UNSIGNED NOT NULL DEFAULT '0'
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_groupleaders`
--

CREATE TABLE `mybb_groupleaders` (
  `lid` smallint(5) UNSIGNED NOT NULL,
  `gid` smallint(5) UNSIGNED NOT NULL DEFAULT '0',
  `uid` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `canmanagemembers` tinyint(1) NOT NULL DEFAULT '0',
  `canmanagerequests` tinyint(1) NOT NULL DEFAULT '0',
  `caninvitemembers` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_helpdocs`
--

CREATE TABLE `mybb_helpdocs` (
  `hid` smallint(5) UNSIGNED NOT NULL,
  `sid` smallint(5) UNSIGNED NOT NULL DEFAULT '0',
  `name` varchar(120) NOT NULL DEFAULT '',
  `description` text NOT NULL,
  `document` text NOT NULL,
  `usetranslation` tinyint(1) NOT NULL DEFAULT '0',
  `enabled` tinyint(1) NOT NULL DEFAULT '0',
  `disporder` smallint(5) UNSIGNED NOT NULL DEFAULT '0'
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_helpsections`
--

CREATE TABLE `mybb_helpsections` (
  `sid` smallint(5) UNSIGNED NOT NULL,
  `name` varchar(120) NOT NULL DEFAULT '',
  `description` text NOT NULL,
  `usetranslation` tinyint(1) NOT NULL DEFAULT '0',
  `enabled` tinyint(1) NOT NULL DEFAULT '0',
  `disporder` smallint(5) UNSIGNED NOT NULL DEFAULT '0'
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_icons`
--

CREATE TABLE `mybb_icons` (
  `iid` smallint(5) UNSIGNED NOT NULL,
  `name` varchar(120) NOT NULL DEFAULT '',
  `path` varchar(220) NOT NULL DEFAULT ''
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_joinrequests`
--

CREATE TABLE `mybb_joinrequests` (
  `rid` int(10) UNSIGNED NOT NULL,
  `uid` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `gid` smallint(5) UNSIGNED NOT NULL DEFAULT '0',
  `reason` varchar(250) NOT NULL DEFAULT '',
  `dateline` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `invite` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_mailerrors`
--

CREATE TABLE `mybb_mailerrors` (
  `eid` int(10) UNSIGNED NOT NULL,
  `subject` varchar(200) NOT NULL DEFAULT '',
  `message` text NOT NULL,
  `toaddress` varchar(150) NOT NULL DEFAULT '',
  `fromaddress` varchar(150) NOT NULL DEFAULT '',
  `dateline` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `error` text NOT NULL,
  `smtperror` varchar(200) NOT NULL DEFAULT '',
  `smtpcode` smallint(5) UNSIGNED NOT NULL DEFAULT '0'
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_maillogs`
--

CREATE TABLE `mybb_maillogs` (
  `mid` int(10) UNSIGNED NOT NULL,
  `subject` varchar(200) NOT NULL DEFAULT '',
  `message` text NOT NULL,
  `dateline` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `fromuid` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `fromemail` varchar(200) NOT NULL DEFAULT '',
  `touid` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `toemail` varchar(200) NOT NULL DEFAULT '',
  `tid` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `ipaddress` varbinary(16) NOT NULL DEFAULT '',
  `type` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_mailqueue`
--

CREATE TABLE `mybb_mailqueue` (
  `mid` int(10) UNSIGNED NOT NULL,
  `mailto` varchar(200) NOT NULL,
  `mailfrom` varchar(200) NOT NULL,
  `subject` varchar(200) NOT NULL,
  `message` text NOT NULL,
  `headers` text NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_massemails`
--

CREATE TABLE `mybb_massemails` (
  `mid` int(10) UNSIGNED NOT NULL,
  `uid` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `subject` varchar(200) NOT NULL DEFAULT '',
  `message` text NOT NULL,
  `htmlmessage` text NOT NULL,
  `type` tinyint(1) NOT NULL DEFAULT '0',
  `format` tinyint(1) NOT NULL DEFAULT '0',
  `dateline` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `senddate` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `status` tinyint(1) NOT NULL DEFAULT '0',
  `sentcount` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `totalcount` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `conditions` text NOT NULL,
  `perpage` smallint(4) UNSIGNED NOT NULL DEFAULT '50'
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_moderatorlog`
--

CREATE TABLE `mybb_moderatorlog` (
  `uid` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `dateline` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `fid` smallint(5) UNSIGNED NOT NULL DEFAULT '0',
  `tid` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `pid` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `action` text NOT NULL,
  `data` text NOT NULL,
  `ipaddress` varbinary(16) NOT NULL DEFAULT ''
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_moderators`
--

CREATE TABLE `mybb_moderators` (
  `mid` smallint(5) UNSIGNED NOT NULL,
  `fid` smallint(5) UNSIGNED NOT NULL DEFAULT '0',
  `id` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `isgroup` tinyint(1) UNSIGNED NOT NULL DEFAULT '0',
  `caneditposts` tinyint(1) NOT NULL DEFAULT '0',
  `cansoftdeleteposts` tinyint(1) NOT NULL DEFAULT '0',
  `canrestoreposts` tinyint(1) NOT NULL DEFAULT '0',
  `candeleteposts` tinyint(1) NOT NULL DEFAULT '0',
  `cansoftdeletethreads` tinyint(1) NOT NULL DEFAULT '0',
  `canrestorethreads` tinyint(1) NOT NULL DEFAULT '0',
  `candeletethreads` tinyint(1) NOT NULL DEFAULT '0',
  `canviewips` tinyint(1) NOT NULL DEFAULT '0',
  `canviewunapprove` tinyint(1) NOT NULL DEFAULT '0',
  `canviewdeleted` tinyint(1) NOT NULL DEFAULT '0',
  `canopenclosethreads` tinyint(1) NOT NULL DEFAULT '0',
  `canstickunstickthreads` tinyint(1) NOT NULL DEFAULT '0',
  `canapproveunapprovethreads` tinyint(1) NOT NULL DEFAULT '0',
  `canapproveunapproveposts` tinyint(1) NOT NULL DEFAULT '0',
  `canapproveunapproveattachs` tinyint(1) NOT NULL DEFAULT '0',
  `canmanagethreads` tinyint(1) NOT NULL DEFAULT '0',
  `canmanagepolls` tinyint(1) NOT NULL DEFAULT '0',
  `canpostclosedthreads` tinyint(1) NOT NULL DEFAULT '0',
  `canmovetononmodforum` tinyint(1) NOT NULL DEFAULT '0',
  `canusecustomtools` tinyint(1) NOT NULL DEFAULT '0',
  `canmanageannouncements` tinyint(1) NOT NULL DEFAULT '0',
  `canmanagereportedposts` tinyint(1) NOT NULL DEFAULT '0',
  `canviewmodlog` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_modtools`
--

CREATE TABLE `mybb_modtools` (
  `tid` smallint(5) UNSIGNED NOT NULL,
  `name` varchar(200) NOT NULL,
  `description` text NOT NULL,
  `forums` text NOT NULL,
  `groups` text NOT NULL,
  `type` char(1) NOT NULL DEFAULT '',
  `postoptions` text NOT NULL,
  `threadoptions` text NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_mycode`
--

CREATE TABLE `mybb_mycode` (
  `cid` int(10) UNSIGNED NOT NULL,
  `title` varchar(100) NOT NULL DEFAULT '',
  `description` text NOT NULL,
  `regex` text NOT NULL,
  `replacement` text NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '0',
  `parseorder` smallint(5) UNSIGNED NOT NULL DEFAULT '0'
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_newpoints_forumrules`
--

CREATE TABLE `mybb_newpoints_forumrules` (
  `rid` bigint(30) UNSIGNED NOT NULL,
  `fid` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `name` varchar(100) NOT NULL DEFAULT '',
  `description` text NOT NULL,
  `rate` float NOT NULL DEFAULT '1',
  `pointsview` decimal(16,2) NOT NULL DEFAULT '0.00',
  `pointspost` decimal(16,2) NOT NULL DEFAULT '0.00'
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_newpoints_grouprules`
--

CREATE TABLE `mybb_newpoints_grouprules` (
  `rid` bigint(30) UNSIGNED NOT NULL,
  `gid` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `name` varchar(100) NOT NULL DEFAULT '',
  `description` text NOT NULL,
  `rate` float NOT NULL DEFAULT '1',
  `pointsearn` decimal(16,2) UNSIGNED NOT NULL DEFAULT '0.00',
  `period` bigint(30) UNSIGNED NOT NULL DEFAULT '0',
  `lastpay` bigint(30) UNSIGNED NOT NULL DEFAULT '0'
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_newpoints_log`
--

CREATE TABLE `mybb_newpoints_log` (
  `lid` bigint(30) UNSIGNED NOT NULL,
  `action` varchar(100) NOT NULL DEFAULT '',
  `data` text NOT NULL,
  `date` bigint(30) UNSIGNED NOT NULL DEFAULT '0',
  `uid` bigint(30) UNSIGNED NOT NULL DEFAULT '0',
  `username` varchar(100) NOT NULL DEFAULT ''
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_newpoints_settings`
--

CREATE TABLE `mybb_newpoints_settings` (
  `sid` int(10) UNSIGNED NOT NULL,
  `plugin` varchar(100) NOT NULL DEFAULT '',
  `name` varchar(100) NOT NULL DEFAULT '',
  `title` varchar(100) NOT NULL DEFAULT '',
  `description` text NOT NULL,
  `type` text NOT NULL,
  `value` text NOT NULL,
  `disporder` smallint(5) UNSIGNED NOT NULL DEFAULT '0'
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_Objetos_Inframundo`
--

CREATE TABLE `mybb_Objetos_Inframundo` (
  `id` int(10) UNSIGNED NOT NULL,
  `objeto_id` varchar(255) NOT NULL,
  `vendedor_uid` int(11) NOT NULL,
  `ultimo_ofertante_uid` int(11) DEFAULT NULL,
  `comprador_uid` int(11) DEFAULT NULL,
  `precio_minimo` int(11) NOT NULL,
  `precio_compra` int(11) DEFAULT NULL,
  `fecha_final_subasta` datetime DEFAULT NULL,
  `estado` enum('activa','vendida','cancelada','finalizada_sin_venta') NOT NULL DEFAULT 'activa',
  `notas` varchar(255) DEFAULT NULL,
  `cantidad` int(11) NOT NULL DEFAULT '0',
  `fecha_creacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `disponible_desde` timestamp NULL DEFAULT NULL,
  `precio_actual` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_op_adviento_abiertos`
--

CREATE TABLE `mybb_op_adviento_abiertos` (
  `id` int(10) UNSIGNED NOT NULL,
  `uid` int(10) UNSIGNED NOT NULL,
  `dia` tinyint(3) UNSIGNED NOT NULL,
  `anio` smallint(5) UNSIGNED NOT NULL,
  `fecha_apertura` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_op_afiliados`
--

CREATE TABLE `mybb_op_afiliados` (
  `id` int(11) NOT NULL,
  `tipo` enum('hermano','grande','pequeno') COLLATE utf8_unicode_ci NOT NULL,
  `nombre` varchar(120) COLLATE utf8_unicode_ci NOT NULL,
  `url` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `imagen` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `descripcion` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `orden` int(11) NOT NULL DEFAULT '0',
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `agregado_por` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `tiempo` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_op_afiliados_rate_limit`
--

CREATE TABLE `mybb_op_afiliados_rate_limit` (
  `id` int(11) NOT NULL,
  `ip` varchar(45) COLLATE utf8_unicode_ci NOT NULL,
  `tiempo` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_op_akumas`
--

CREATE TABLE `mybb_op_akumas` (
  `akuma_id` varchar(20) COLLATE utf8_unicode_ci NOT NULL,
  `nombre` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
  `subnombre` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
  `categoria` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  `tier` int(5) NOT NULL,
  `descripcion` text COLLATE utf8_unicode_ci NOT NULL,
  `uid` varchar(20) COLLATE utf8_unicode_ci NOT NULL DEFAULT '1',
  `es_npc` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '0',
  `es_oculta` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '0',
  `ocupada` tinyint(1) NOT NULL DEFAULT '0',
  `imagen` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '/images/op/uploads/AkumaMisteriosa_One_Piece_Gaiden_Foro_Rol.png',
  `detalles` text COLLATE utf8_unicode_ci NOT NULL,
  `dominio1` text COLLATE utf8_unicode_ci NOT NULL,
  `dominio2` text COLLATE utf8_unicode_ci NOT NULL,
  `dominio3` text COLLATE utf8_unicode_ci NOT NULL,
  `pasiva1` text COLLATE utf8_unicode_ci NOT NULL,
  `pasiva2` text COLLATE utf8_unicode_ci NOT NULL,
  `pasiva3` text COLLATE utf8_unicode_ci NOT NULL,
  `reservas` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `reservasFecha` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_op_auditoria_posts_ia`
--

CREATE TABLE `mybb_op_auditoria_posts_ia` (
  `id` int(11) NOT NULL,
  `pid` int(11) NOT NULL COMMENT 'ID del post (mybb_posts.pid)',
  `tid` int(11) NOT NULL COMMENT 'ID del hilo',
  `fid_forum` int(11) NOT NULL DEFAULT '0' COMMENT 'ID del subforo',
  `uid` int(11) NOT NULL DEFAULT '0' COMMENT 'UID del autor',
  `username` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `mensaje_original` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Contenido del post en el momento de publicación',
  `indicadores` varchar(512) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'Lista de patrones IA detectados, separados por coma',
  `dateline` int(10) NOT NULL DEFAULT '0' COMMENT 'Timestamp Unix de publicación'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Auditoría de posts publicados con indicadores de prompt/uso de IA';

-- --------------------------------------------------------

--
-- Table structure for table `mybb_op_audit_consola_mod`
--

CREATE TABLE `mybb_op_audit_consola_mod` (
  `id` int(11) NOT NULL,
  `tiempo` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `staff` text COLLATE utf8_unicode_ci NOT NULL,
  `username` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  `razon` text COLLATE utf8_unicode_ci NOT NULL,
  `log` text COLLATE utf8_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_op_audit_consola_tec`
--

CREATE TABLE `mybb_op_audit_consola_tec` (
  `id` int(11) NOT NULL,
  `tiempo` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `staff` text COLLATE utf8_unicode_ci NOT NULL,
  `razon` text COLLATE utf8_unicode_ci NOT NULL,
  `log` text COLLATE utf8_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_op_audit_consola_tec_mod`
--

CREATE TABLE `mybb_op_audit_consola_tec_mod` (
  `id` int(11) NOT NULL,
  `tiempo` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `staff` text COLLATE utf8_unicode_ci NOT NULL,
  `razon` text COLLATE utf8_unicode_ci NOT NULL,
  `log` text COLLATE utf8_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_op_audit_crafteo`
--

CREATE TABLE `mybb_op_audit_crafteo` (
  `id` int(10) NOT NULL,
  `uid` int(10) NOT NULL,
  `nombre` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  `log` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `tiempo_completado` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_op_audit_creacion`
--

CREATE TABLE `mybb_op_audit_creacion` (
  `id` int(10) NOT NULL,
  `uid` int(10) NOT NULL,
  `nombre` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  `log` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `tiempo_completado` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_op_audit_descripcion`
--

CREATE TABLE `mybb_op_audit_descripcion` (
  `fid` int(10) NOT NULL,
  `tiempo_editado` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `apodo` text COLLATE utf8_unicode_ci NOT NULL,
  `frase` text COLLATE utf8_unicode_ci NOT NULL,
  `extra` text COLLATE utf8_unicode_ci NOT NULL,
  `fisico_de_pj` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `personalidad` mediumtext COLLATE utf8_unicode_ci NOT NULL,
  `apariencia` mediumtext COLLATE utf8_unicode_ci NOT NULL,
  `biografia` mediumtext COLLATE utf8_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_op_audit_entrenamientos`
--

CREATE TABLE `mybb_op_audit_entrenamientos` (
  `fid` int(10) NOT NULL,
  `nombre` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  `tiempo_completado` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `puntos_estadistica` int(10) NOT NULL DEFAULT '2',
  `pr` varchar(255) COLLATE utf8_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_op_audit_entrenamiento_tecnicas`
--

CREATE TABLE `mybb_op_audit_entrenamiento_tecnicas` (
  `fid` int(10) NOT NULL,
  `nombre` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  `tid` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `tiempo_completado` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `tiempo_iniciado` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `tiempo_finaliza` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `puntos_estadistica` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `pr` varchar(255) COLLATE utf8_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_op_audit_general`
--

CREATE TABLE `mybb_op_audit_general` (
  `id` int(11) NOT NULL,
  `tiempo` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `uid` text COLLATE utf8_unicode_ci NOT NULL,
  `username` text COLLATE utf8_unicode_ci NOT NULL,
  `user_uid` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '9999',
  `categoria` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `log` text COLLATE utf8_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_op_audit_oficios`
--

CREATE TABLE `mybb_op_audit_oficios` (
  `id` int(10) NOT NULL,
  `fid` int(10) NOT NULL,
  `nombre` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  `oficio` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  `tiempo_completado` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `progreso` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `experiencia` int(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_op_audit_recompensas`
--

CREATE TABLE `mybb_op_audit_recompensas` (
  `id` int(10) NOT NULL,
  `tiempo_completado` int(11) NOT NULL,
  `tiempo_nuevo` int(11) NOT NULL,
  `dia` int(11) NOT NULL,
  `season` int(11) NOT NULL,
  `uid` int(11) NOT NULL,
  `nombre` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `audit` varchar(255) COLLATE utf8_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_op_audit_stats`
--

CREATE TABLE `mybb_op_audit_stats` (
  `fid` int(10) NOT NULL,
  `tiempo_editado` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `puntos_estadistica` int(11) NOT NULL,
  `fuerza` int(3) NOT NULL,
  `resistencia` int(3) NOT NULL,
  `reflejos` int(3) NOT NULL,
  `precision` int(3) NOT NULL,
  `voluntad` int(3) NOT NULL,
  `agilidad` int(3) NOT NULL,
  `inteligencia` int(3) NOT NULL,
  `destreza` int(3) NOT NULL,
  `control_haki` int(3) NOT NULL,
  `control_akuma` int(3) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_op_avisos`
--

CREATE TABLE `mybb_op_avisos` (
  `id` int(11) NOT NULL,
  `uid` int(11) NOT NULL,
  `nombre` varchar(20) COLLATE utf8_unicode_ci NOT NULL,
  `categoria` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  `resumen` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `descripcion` text COLLATE utf8_unicode_ci NOT NULL,
  `url` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `resuelto` tinyint(1) NOT NULL DEFAULT '0',
  `enviado` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `mod_uid` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `mod_nombre` varchar(255) COLLATE utf8_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_op_avisosvpn`
--

CREATE TABLE `mybb_op_avisosvpn` (
  `usuario` text COLLATE utf8_unicode_ci NOT NULL,
  `uid` int(11) NOT NULL,
  `fechaConexion` datetime NOT NULL,
  `ip` text COLLATE utf8_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_op_bandas`
--

CREATE TABLE `mybb_op_bandas` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL DEFAULT '',
  `owner_uid` int(11) NOT NULL DEFAULT '0',
  `descripcion` text,
  `bandera` varchar(500) NOT NULL,
  `fecha` int(11) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_op_banda_invitaciones`
--

CREATE TABLE `mybb_op_banda_invitaciones` (
  `id` int(11) NOT NULL,
  `banda_id` int(11) NOT NULL,
  `owner_uid` int(11) NOT NULL DEFAULT '0',
  `miembro_uid` int(11) NOT NULL DEFAULT '0',
  `fecha` int(11) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_op_banda_miembros`
--

CREATE TABLE `mybb_op_banda_miembros` (
  `id` int(11) NOT NULL,
  `banda_id` int(11) NOT NULL,
  `owner_uid` int(11) NOT NULL DEFAULT '0',
  `miembro_uid` int(11) NOT NULL DEFAULT '0',
  `fecha` int(11) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_op_barcos`
--

CREATE TABLE `mybb_op_barcos` (
  `barco_id` varchar(80) COLLATE utf8_unicode_ci NOT NULL,
  `nombre_barco` varchar(80) CHARACTER SET utf8 NOT NULL,
  `vitalidad` int(11) NOT NULL,
  `espacios` int(11) NOT NULL,
  `velocidad` int(11) NOT NULL,
  `tiempo_viaje` int(11) NOT NULL,
  `resistencia` int(11) NOT NULL,
  `espacios_mejora` int(11) NOT NULL,
  `ruputura` int(11) NOT NULL,
  `mejora_tripulacion` tinyint(1) NOT NULL DEFAULT '0',
  `mejora_vitalidad` tinyint(1) NOT NULL DEFAULT '0',
  `mejora_resistencia` tinyint(1) NOT NULL DEFAULT '0',
  `mejora_ruptura` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_op_barco_acceso`
--

CREATE TABLE `mybb_op_barco_acceso` (
  `id` int(11) NOT NULL,
  `barco_id` varchar(80) NOT NULL DEFAULT '',
  `owner_uid` int(11) NOT NULL DEFAULT '0',
  `member_uid` int(11) NOT NULL DEFAULT '0',
  `rol` varchar(80) NOT NULL DEFAULT 'tripulante',
  `added_at` int(11) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_op_barco_cofre`
--

CREATE TABLE `mybb_op_barco_cofre` (
  `id` int(11) NOT NULL,
  `barco_id` varchar(80) NOT NULL DEFAULT '',
  `owner_uid` int(11) NOT NULL DEFAULT '0',
  `objeto_id` varchar(255) NOT NULL DEFAULT '',
  `cantidad` int(11) NOT NULL DEFAULT '1',
  `added_by` int(11) NOT NULL DEFAULT '0',
  `added_at` int(11) NOT NULL DEFAULT '0',
  `apodo` varchar(255) NOT NULL DEFAULT '',
  `imagen` varchar(255) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_op_barco_cofre_historial`
--

CREATE TABLE `mybb_op_barco_cofre_historial` (
  `id` int(11) NOT NULL,
  `barco_id` varchar(100) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `owner_uid` int(11) NOT NULL DEFAULT '0',
  `uid` int(11) NOT NULL DEFAULT '0',
  `tipo` varchar(10) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `clase` varchar(10) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `objeto_id` varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL,
  `nombre` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `cantidad` int(11) NOT NULL DEFAULT '0',
  `timestamp` int(11) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_op_barco_estado`
--

CREATE TABLE `mybb_op_barco_estado` (
  `barco_id` varchar(80) NOT NULL DEFAULT '',
  `owner_uid` int(11) NOT NULL DEFAULT '0',
  `berries` bigint(20) NOT NULL DEFAULT '0',
  `owner_rangos` varchar(100) NOT NULL DEFAULT '',
  `owner_ausente` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_op_barco_invitaciones`
--

CREATE TABLE `mybb_op_barco_invitaciones` (
  `id` int(11) NOT NULL,
  `barco_id` varchar(80) COLLATE utf8_unicode_ci NOT NULL,
  `owner_uid` int(11) NOT NULL,
  `miembro_uid` int(11) NOT NULL,
  `fecha` int(11) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_op_barco_npcs`
--

CREATE TABLE `mybb_op_barco_npcs` (
  `id` int(11) NOT NULL,
  `barco_id` varchar(80) COLLATE utf8_unicode_ci NOT NULL,
  `owner_uid` int(11) NOT NULL,
  `ref_id` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
  `tipo` varchar(5) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'npc',
  `rol` varchar(32) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `ausente` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_op_barco_salas`
--

CREATE TABLE `mybb_op_barco_salas` (
  `id` int(11) NOT NULL,
  `barco_id` varchar(80) NOT NULL DEFAULT '',
  `owner_uid` int(11) NOT NULL DEFAULT '0',
  `slot` int(11) NOT NULL DEFAULT '1',
  `nombre` varchar(255) NOT NULL DEFAULT '',
  `descripcion` text NOT NULL,
  `imagen` varchar(255) NOT NULL DEFAULT '',
  `tipo` varchar(50) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_op_barco_tripulacion`
--

CREATE TABLE `mybb_op_barco_tripulacion` (
  `id` int(11) NOT NULL,
  `barco_id` varchar(80) COLLATE utf8_unicode_ci NOT NULL,
  `owner_uid` int(11) NOT NULL,
  `miembro_uid` int(11) NOT NULL,
  `fecha` int(11) NOT NULL DEFAULT '0',
  `rango` varchar(100) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `ausente` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_op_cambioid`
--

CREATE TABLE `mybb_op_cambioid` (
  `id` int(10) NOT NULL,
  `objeto_id` varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
  `categoria` text NOT NULL,
  `subcategoria` text NOT NULL,
  `nombre` text NOT NULL,
  `tier` int(10) NOT NULL DEFAULT '0',
  `imagen_id` int(10) NOT NULL DEFAULT '0',
  `imagen_avatar` text NOT NULL,
  `berries` int(11) NOT NULL DEFAULT '0',
  `cantidadMaxima` int(4) NOT NULL DEFAULT '1',
  `dano` text NOT NULL,
  `bloqueo` text NOT NULL,
  `efecto` text NOT NULL,
  `alcance` text NOT NULL,
  `exclusivo` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Exclusivo de tienda',
  `invisible` int(11) NOT NULL DEFAULT '0' COMMENT 'invisible zona dp_cambioid\n',
  `espacios` int(10) NOT NULL DEFAULT '0',
  `imagen` text NOT NULL,
  `desbloquear` int(20) NOT NULL DEFAULT '10000',
  `desbloqueado` int(10) NOT NULL DEFAULT '1',
  `oficio` varchar(255) NOT NULL DEFAULT '',
  `nivel` int(10) NOT NULL DEFAULT '0',
  `tiempo_creacion` int(100) NOT NULL DEFAULT '10000',
  `requisitos` text NOT NULL,
  `escalado` text NOT NULL,
  `editable` int(10) NOT NULL DEFAULT '0',
  `custom` int(10) NOT NULL DEFAULT '0',
  `descripcion` text NOT NULL,
  `comerciable` int(1) NOT NULL DEFAULT '0',
  `crafteo_usuarios` varchar(255) NOT NULL DEFAULT '',
  `negro` int(10) NOT NULL DEFAULT '0',
  `negro_berries` int(100) NOT NULL DEFAULT '9999999',
  `fusion_tipo` varchar(255) NOT NULL DEFAULT '',
  `engastes` int(10) NOT NULL DEFAULT '0',
  `fecha_creacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `berriesCrafteo` bigint(20) NOT NULL DEFAULT '0'
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_op_codigos_admin`
--

CREATE TABLE `mybb_op_codigos_admin` (
  `id` int(100) NOT NULL,
  `codigo` varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
  `expiracion_codigo` int(100) NOT NULL,
  `duracion` int(100) NOT NULL,
  `categoria` varchar(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
  `uso_unico` tinyint(1) NOT NULL DEFAULT '0',
  `usado` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_op_codigos_usuarios`
--

CREATE TABLE `mybb_op_codigos_usuarios` (
  `id` int(100) NOT NULL,
  `uid` int(3) NOT NULL,
  `nombre` varchar(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `codigo` varchar(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
  `categoria` varchar(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
  `expiracion` int(100) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_op_cofres`
--

CREATE TABLE `mybb_op_cofres` (
  `id` int(11) NOT NULL,
  `cofre_id` varchar(255) NOT NULL,
  `objeto_id` varchar(255) NOT NULL,
  `nombre` varchar(255) NOT NULL,
  `tipo` varchar(255) NOT NULL,
  `peso` int(11) NOT NULL,
  `cantidad` int(11) NOT NULL DEFAULT '1'
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_op_consumir`
--

CREATE TABLE `mybb_op_consumir` (
  `id` int(10) UNSIGNED NOT NULL,
  `tid` int(11) NOT NULL,
  `pid` int(11) NOT NULL,
  `uid` int(11) NOT NULL,
  `counter` int(11) NOT NULL,
  `objeto_id` varchar(255) NOT NULL,
  `content` text CHARACTER SET utf8 NOT NULL,
  `tiempo_creacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_op_costes_akuma`
--

CREATE TABLE `mybb_op_costes_akuma` (
  `dominio` int(11) NOT NULL,
  `coste_normal` int(11) DEFAULT NULL,
  `coste_camino` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_op_costes_belica`
--

CREATE TABLE `mybb_op_costes_belica` (
  `slot` int(11) NOT NULL,
  `coste_slot` int(11) NOT NULL DEFAULT '0',
  `coste_espe1` int(11) NOT NULL DEFAULT '0',
  `coste_espe2` int(11) NOT NULL DEFAULT '0',
  `coste_up` int(11) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_op_costes_estilo`
--

CREATE TABLE `mybb_op_costes_estilo` (
  `slot` varchar(20) COLLATE utf8_unicode_ci NOT NULL,
  `coste_nuevo` int(11) DEFAULT NULL,
  `coste_reembolso` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_op_costes_haki`
--

CREATE TABLE `mybb_op_costes_haki` (
  `nivel` int(11) NOT NULL,
  `coste` int(11) NOT NULL DEFAULT '0',
  `coste_camino` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_op_costes_oficio`
--

CREATE TABLE `mybb_op_costes_oficio` (
  `clave` varchar(20) COLLATE utf8_unicode_ci NOT NULL,
  `coste_nikas` int(11) NOT NULL DEFAULT '0',
  `coste_puntos_oficio` int(11) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_op_crafteo_npcs`
--

CREATE TABLE `mybb_op_crafteo_npcs` (
  `id` int(11) NOT NULL,
  `uid` int(11) NOT NULL,
  `npc_id` varchar(64) NOT NULL,
  `objeto_id` varchar(64) NOT NULL,
  `nombre` varchar(255) NOT NULL,
  `material_id` varchar(64) DEFAULT NULL,
  `timestamp_end` int(11) NOT NULL,
  `duracion` int(11) NOT NULL,
  `costo` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_op_crafteo_usuarios`
--

CREATE TABLE `mybb_op_crafteo_usuarios` (
  `id` int(100) NOT NULL,
  `uid` int(3) NOT NULL,
  `objeto_id` varchar(100) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `material_id` varchar(255) NOT NULL DEFAULT '',
  `timestamp_end` int(100) NOT NULL,
  `duracion` int(100) NOT NULL,
  `costo` int(100) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_op_creacion_usuarios`
--

CREATE TABLE `mybb_op_creacion_usuarios` (
  `id` int(100) NOT NULL,
  `uid` int(3) NOT NULL,
  `ticket` varchar(100) NOT NULL,
  `nombre_ticket` varchar(100) NOT NULL,
  `timestamp_end` int(100) NOT NULL,
  `duracion` int(100) NOT NULL,
  `nikas_costo` int(100) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_op_dados`
--

CREATE TABLE `mybb_op_dados` (
  `did` int(10) UNSIGNED NOT NULL,
  `tid` int(11) NOT NULL,
  `pid` int(11) NOT NULL,
  `uid` int(11) NOT NULL,
  `dado_counter` int(11) NOT NULL,
  `dado_content` text CHARACTER SET utf8 NOT NULL,
  `tiempo_creacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_op_disciplinas`
--

CREATE TABLE `mybb_op_disciplinas` (
  `nombre` varchar(60) COLLATE utf8_unicode_ci NOT NULL,
  `camino1` varchar(60) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `camino2` varchar(60) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `orden` int(11) NOT NULL DEFAULT '0',
  `imagen` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_op_entrenamientos_usuarios`
--

CREATE TABLE `mybb_op_entrenamientos_usuarios` (
  `id` int(100) NOT NULL,
  `uid` int(3) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `timestamp_end` int(100) NOT NULL,
  `duracion` int(100) NOT NULL,
  `costo_pr` int(100) NOT NULL,
  `recompensa` int(100) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_op_equipamiento_personaje`
--

CREATE TABLE `mybb_op_equipamiento_personaje` (
  `id` int(10) NOT NULL,
  `tid` int(5) NOT NULL,
  `pid` int(5) NOT NULL,
  `uid` int(10) NOT NULL,
  `equipamiento` json NOT NULL DEFAULT 'null',
  `fecha` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_op_estilos_catalogo`
--

CREATE TABLE `mybb_op_estilos_catalogo` (
  `nombre` varchar(60) COLLATE utf8_unicode_ci NOT NULL,
  `imagen` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `orden` int(11) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_op_experiencia_limite`
--

CREATE TABLE `mybb_op_experiencia_limite` (
  `id` int(11) NOT NULL,
  `uid` int(10) NOT NULL,
  `semana` int(10) NOT NULL,
  `experiencia_semanal` float NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_op_facciones`
--

CREATE TABLE `mybb_op_facciones` (
  `nombre` varchar(20) COLLATE utf8_unicode_ci NOT NULL,
  `orden` int(11) NOT NULL DEFAULT '0',
  `usergroup` int(11) NOT NULL DEFAULT '0',
  `color_faccion` varchar(120) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `color_rombo` varchar(120) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `color_border_tag` varchar(120) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `color_rango` varchar(120) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `color_border` varchar(120) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `color_border_pill` varchar(120) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `color_texto` varchar(120) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `color_chat` varchar(120) COLLATE utf8_unicode_ci NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_op_facciones_rangos`
--

CREATE TABLE `mybb_op_facciones_rangos` (
  `id` int(11) NOT NULL,
  `faccion` varchar(20) COLLATE utf8_unicode_ci NOT NULL,
  `valor` varchar(60) COLLATE utf8_unicode_ci NOT NULL,
  `nombre_visible` varchar(100) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `imagen` varchar(100) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `orden` int(11) NOT NULL DEFAULT '0',
  `reputacion_min` int(11) DEFAULT NULL,
  `nivel_min` int(11) DEFAULT NULL,
  `sueldo_semanal` int(11) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_op_fama`
--

CREATE TABLE `mybb_op_fama` (
  `id` int(11) NOT NULL,
  `reputacion_min` int(11) NOT NULL DEFAULT '0',
  `perc_malo` int(11) NOT NULL DEFAULT '0',
  `perc_bueno` int(11) NOT NULL DEFAULT '0',
  `nombre_bueno` varchar(60) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `nombre_neutral` varchar(60) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `nombre_malo` varchar(60) COLLATE utf8_unicode_ci NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_op_fichas`
--

CREATE TABLE `mybb_op_fichas` (
  `fid` int(10) NOT NULL,
  `nombre` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
  `apodo` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
  `faccion` varchar(20) COLLATE utf8_unicode_ci NOT NULL,
  `raza` varchar(20) COLLATE utf8_unicode_ci NOT NULL,
  `berries` int(11) NOT NULL DEFAULT '0',
  `dia` int(10) NOT NULL DEFAULT '0',
  `edad` int(2) NOT NULL,
  `altura` int(4) NOT NULL,
  `peso` int(5) NOT NULL,
  `sexo` varchar(20) COLLATE utf8_unicode_ci NOT NULL,
  `temporada` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `puntos_estadistica` int(11) NOT NULL DEFAULT '60',
  `nivel` int(11) NOT NULL DEFAULT '1',
  `nivel_ajustado` tinyint(1) NOT NULL DEFAULT '0',
  `limite_nivel` int(20) NOT NULL DEFAULT '20',
  `fuerza` int(3) NOT NULL DEFAULT '0',
  `fuerza_pasiva` int(3) NOT NULL DEFAULT '0',
  `resistencia` int(3) NOT NULL DEFAULT '0',
  `resistencia_pasiva` int(3) NOT NULL DEFAULT '0',
  `destreza` int(3) NOT NULL DEFAULT '0',
  `destreza_pasiva` int(3) NOT NULL DEFAULT '0',
  `voluntad` int(3) NOT NULL DEFAULT '0',
  `voluntad_pasiva` int(3) NOT NULL DEFAULT '0',
  `punteria` int(3) NOT NULL DEFAULT '0',
  `punteria_pasiva` int(3) NOT NULL DEFAULT '0',
  `agilidad` int(3) NOT NULL DEFAULT '0',
  `agilidad_pasiva` int(3) NOT NULL DEFAULT '0',
  `reflejos` int(3) NOT NULL DEFAULT '0',
  `reflejos_pasiva` int(3) NOT NULL DEFAULT '0',
  `control_akuma` int(3) NOT NULL DEFAULT '0',
  `control_akuma_pasiva` int(3) NOT NULL DEFAULT '0',
  `vitalidad` int(11) NOT NULL DEFAULT '0',
  `vitalidad_pasiva` int(10) NOT NULL DEFAULT '0',
  `energia` int(11) NOT NULL DEFAULT '0',
  `energia_pasiva` int(10) NOT NULL DEFAULT '0',
  `haki` int(11) NOT NULL DEFAULT '0',
  `haki_pasiva` int(10) NOT NULL DEFAULT '0',
  `nika` int(11) NOT NULL DEFAULT '0',
  `kuro` int(10) NOT NULL DEFAULT '0',
  `apariencia` mediumtext COLLATE utf8_unicode_ci NOT NULL,
  `personalidad` mediumtext COLLATE utf8_unicode_ci NOT NULL,
  `historia` mediumtext COLLATE utf8_unicode_ci NOT NULL,
  `rasgos_positivos` text COLLATE utf8_unicode_ci NOT NULL,
  `rasgos_negativos` text COLLATE utf8_unicode_ci NOT NULL,
  `extra` text COLLATE utf8_unicode_ci NOT NULL,
  `frase` text COLLATE utf8_unicode_ci NOT NULL,
  `notas` text COLLATE utf8_unicode_ci NOT NULL,
  `reputacion` int(11) NOT NULL DEFAULT '0',
  `reputacion_positiva` int(11) NOT NULL DEFAULT '0',
  `reputacion_negativa` int(11) NOT NULL DEFAULT '0',
  `reputacion2` int(11) NOT NULL DEFAULT '0',
  `reputacion_positiva2` int(11) NOT NULL DEFAULT '0',
  `reputacion_negativa2` int(11) NOT NULL DEFAULT '0',
  `rango` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'Novato',
  `fama` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'Desconocido',
  `fisico_de_pj` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `origen_de_pj` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `banner` text COLLATE utf8_unicode_ci NOT NULL,
  `como_nos_conociste` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `orientacion` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `aprobada_por` varchar(20) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'sin_aprobar',
  `tiempo_creacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `belica1` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `belica2` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `belica3` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `belica4` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `belica5` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `belica6` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `belica7` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `belica8` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `belica9` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `belica10` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `belica11` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `belica12` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `belicas` json NOT NULL,
  `oficios` json NOT NULL,
  `oficio1` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `puntos_oficio` int(10) NOT NULL DEFAULT '0',
  `oficio2` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `oficio2exp` int(11) NOT NULL DEFAULT '0',
  `oficio1nivel` int(10) NOT NULL DEFAULT '1',
  `oficio2nivel` int(10) NOT NULL DEFAULT '0',
  `estilo1` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'bloqueado',
  `estilo2` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'bloqueado',
  `estilo3` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'bloqueado',
  `estilo4` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'bloqueado',
  `estilos` json DEFAULT NULL,
  `elementos` json NOT NULL,
  `sangre` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `akuma` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `akuma_subnombre` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `akuma_origen` varchar(20) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `dominio_akuma` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '0',
  `hao` int(5) NOT NULL DEFAULT '-1',
  `hao_chance` int(5) NOT NULL DEFAULT '1',
  `kenbun` int(5) NOT NULL DEFAULT '0',
  `buso` int(5) NOT NULL DEFAULT '0',
  `espacios` int(100) NOT NULL DEFAULT '0',
  `avatar1` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '/images/op/uploads/AvatarReputacion1_One_Piece_Gaiden_Foro_Rol.png',
  `avatar2` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '/images/op/uploads/AvatarReputacion2_One_Piece_Gaiden_Foro_Rol.png',
  `avatar3` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '/images/op/uploads/AvatarBiografia_One_Piece_Gaiden_Foro_Rol.png',
  `avatar4` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '/images/op/uploads/AvatarInventario_One_Piece_Gaiden_Foro_Rol.png',
  `wanted` int(10) NOT NULL DEFAULT '0',
  `wanted_repu` int(11) NOT NULL DEFAULT '0',
  `muerto` int(10) NOT NULL DEFAULT '0',
  `banda_sonora` varchar(500) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `camino` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `ranuras` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '0 / 6',
  `equipamiento_espacio` int(11) NOT NULL DEFAULT '5',
  `implantes` text COLLATE utf8_unicode_ci NOT NULL,
  `equipamiento` json NOT NULL,
  `secret1` int(11) NOT NULL DEFAULT '0',
  `rango_inframundo` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `cronologia` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `wantedGuardado` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `fx` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `movidoInframundo` bigint(20) NOT NULL DEFAULT '0',
  `aventurasActivas` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `slotAventuras` int(10) UNSIGNED NOT NULL DEFAULT '3',
  `expNarradorMensualActual` int(11) NOT NULL COMMENT '675 máximo',
  `nivelnarrador` varchar(80) CHARACTER SET utf8 NOT NULL DEFAULT 'Aprendiz',
  `wantedcustom` bigint(20) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_op_fichas_audit`
--

CREATE TABLE `mybb_op_fichas_audit` (
  `audit_id` int(11) NOT NULL,
  `changed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fid` int(10) NOT NULL,
  `nombre` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
  `apodo` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
  `faccion` varchar(20) COLLATE utf8_unicode_ci NOT NULL,
  `raza` varchar(20) COLLATE utf8_unicode_ci NOT NULL,
  `berries` int(11) NOT NULL DEFAULT '0',
  `edad` int(2) NOT NULL,
  `altura` int(4) NOT NULL,
  `peso` int(5) NOT NULL,
  `sexo` varchar(20) COLLATE utf8_unicode_ci NOT NULL,
  `temporada` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `puntos_estadistica` int(11) NOT NULL DEFAULT '43',
  `nivel` int(11) NOT NULL DEFAULT '1',
  `fuerza` int(3) NOT NULL DEFAULT '1',
  `fuerza_pasiva` int(3) NOT NULL DEFAULT '0',
  `resistencia` int(3) NOT NULL DEFAULT '1',
  `resistencia_pasiva` int(3) NOT NULL DEFAULT '0',
  `destreza` int(3) NOT NULL DEFAULT '1',
  `destreza_pasiva` int(3) NOT NULL DEFAULT '0',
  `voluntad` int(3) NOT NULL DEFAULT '1',
  `voluntad_pasiva` int(3) NOT NULL DEFAULT '0',
  `punteria` int(3) NOT NULL DEFAULT '1',
  `punteria_pasiva` int(3) NOT NULL DEFAULT '0',
  `agilidad` int(3) NOT NULL DEFAULT '1',
  `agilidad_pasiva` int(3) NOT NULL DEFAULT '0',
  `reflejos` int(3) NOT NULL DEFAULT '1',
  `reflejos_pasiva` int(3) NOT NULL DEFAULT '0',
  `control_akuma` int(3) NOT NULL DEFAULT '0',
  `control_akuma_pasiva` int(3) NOT NULL DEFAULT '0',
  `vitalidad` int(11) NOT NULL DEFAULT '20',
  `energia` int(11) NOT NULL DEFAULT '9',
  `haki` int(11) NOT NULL DEFAULT '5',
  `nika` int(11) NOT NULL DEFAULT '0',
  `apariencia` mediumtext COLLATE utf8_unicode_ci NOT NULL,
  `personalidad` mediumtext COLLATE utf8_unicode_ci NOT NULL,
  `historia` mediumtext COLLATE utf8_unicode_ci NOT NULL,
  `rasgos_positivos` text COLLATE utf8_unicode_ci NOT NULL,
  `rasgos_negativos` text COLLATE utf8_unicode_ci NOT NULL,
  `extra` text COLLATE utf8_unicode_ci NOT NULL,
  `frase` text COLLATE utf8_unicode_ci NOT NULL,
  `notas` text COLLATE utf8_unicode_ci NOT NULL,
  `reputacion` int(11) NOT NULL DEFAULT '0',
  `reputacion_positiva` int(11) NOT NULL DEFAULT '0',
  `reputacion_negativa` int(11) NOT NULL DEFAULT '0',
  `rango` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'Novato',
  `fama` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'Desconocido',
  `fisico_de_pj` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `origen_de_pj` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `banner` text COLLATE utf8_unicode_ci NOT NULL,
  `como_nos_conociste` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `orientacion` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `aprobada_por` varchar(20) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'sin_aprobar',
  `tiempo_creacion` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `belica1` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `belicas` json NOT NULL,
  `oficios` json NOT NULL,
  `belica2` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `belica1tier` int(11) NOT NULL DEFAULT '1',
  `belica2tier` int(11) NOT NULL DEFAULT '0',
  `oficio1` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `oficio1exp` int(10) NOT NULL DEFAULT '0',
  `oficio2` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `oficio2exp` int(11) NOT NULL DEFAULT '0',
  `oficio1nivel` int(10) NOT NULL DEFAULT '1',
  `oficio2nivel` int(10) NOT NULL DEFAULT '0',
  `sangre` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `akuma` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `hao` int(5) NOT NULL DEFAULT '0',
  `hao_chance` int(5) NOT NULL DEFAULT '1',
  `kenbun` int(5) NOT NULL DEFAULT '0',
  `buso` int(5) NOT NULL DEFAULT '0',
  `avatar1` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '/images/op/uploads/AvatarReputacion1_One_Piece_Gaiden_Foro_Rol.png',
  `avatar2` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '/images/op/uploads/AvatarReputacion2_One_Piece_Gaiden_Foro_Rol.png',
  `avatar3` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '/images/op/uploads/AvatarHabilidades_One_Piece_Gaiden_Foro_Rol.png',
  `avatar4` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '/images/op/uploads/AvatarInventario_One_Piece_Gaiden_Foro_Rol.png',
  `wanted` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `wanted_repu` int(11) NOT NULL DEFAULT '0',
  `muerto` int(10) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_op_fichas_guardar`
--

CREATE TABLE `mybb_op_fichas_guardar` (
  `fid` int(11) NOT NULL,
  `nombre` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `apodo` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `altura` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `peso` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `sexo` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `edad` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `raza` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `subraza` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `temporada` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `faccion` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `virtudes` varchar(500) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `oficio` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `disciplina` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `arma` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `apariencia` text COLLATE utf8_unicode_ci NOT NULL,
  `personalidad` text COLLATE utf8_unicode_ci NOT NULL,
  `extras` text COLLATE utf8_unicode_ci NOT NULL,
  `historia` text COLLATE utf8_unicode_ci NOT NULL,
  `fisico` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `origen` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `como_nos_conociste` varchar(500) COLLATE utf8_unicode_ci NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_op_fichas_reset`
--

CREATE TABLE `mybb_op_fichas_reset` (
  `id` int(10) UNSIGNED NOT NULL,
  `peticion_id` int(10) UNSIGNED NOT NULL,
  `uid` int(10) UNSIGNED NOT NULL,
  `backup_json` longtext NOT NULL,
  `propuesta_json` longtext NOT NULL,
  `estado` enum('pendiente','aplicado','rechazado') NOT NULL DEFAULT 'pendiente',
  `staff_uid` int(10) UNSIGNED DEFAULT NULL,
  `staff_nombre` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `applied_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_op_fichas_secret`
--

CREATE TABLE `mybb_op_fichas_secret` (
  `id` int(10) NOT NULL,
  `secret_number` int(10) NOT NULL DEFAULT '1',
  `fid` int(10) NOT NULL,
  `nombre` varchar(50) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'Sin Nombre',
  `apodo` varchar(50) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `faccion` varchar(20) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'Civil',
  `apariencia` mediumtext COLLATE utf8_unicode_ci NOT NULL,
  `personalidad` mediumtext COLLATE utf8_unicode_ci NOT NULL,
  `historia` mediumtext COLLATE utf8_unicode_ci NOT NULL,
  `extra` text COLLATE utf8_unicode_ci NOT NULL,
  `rango` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'Novato',
  `avatar1` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '/images/op/uploads/AvatarBiografia_One_Piece_Gaiden_Foro_Rol.png',
  `avatar2` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '/images/op/uploads/AvatarReputacion2_One_Piece_Gaiden_Foro_Rol.png',
  `es_visible` int(11) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_op_gacha_aniversario_gratis`
--

CREATE TABLE `mybb_op_gacha_aniversario_gratis` (
  `uid` int(10) UNSIGNED NOT NULL,
  `fecha` int(10) UNSIGNED NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_op_gacha_aniversario_gratis_2`
--

CREATE TABLE `mybb_op_gacha_aniversario_gratis_2` (
  `uid` int(10) UNSIGNED NOT NULL,
  `fecha` int(10) UNSIGNED NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_op_gacha_aniversario_log`
--

CREATE TABLE `mybb_op_gacha_aniversario_log` (
  `id` int(10) UNSIGNED NOT NULL,
  `uid` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `username` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `banner` enum('berries','kuros') COLLATE utf8mb4_unicode_ci NOT NULL,
  `premio_type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `premio_nombre` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `fecha` int(10) UNSIGNED NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_op_hentai`
--

CREATE TABLE `mybb_op_hentai` (
  `uid` int(10) NOT NULL,
  `enable_hentai` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_op_hide`
--

CREATE TABLE `mybb_op_hide` (
  `hid` int(10) UNSIGNED NOT NULL,
  `tid` int(11) NOT NULL,
  `pid` int(11) NOT NULL,
  `uid` int(11) NOT NULL,
  `hide_counter` int(11) NOT NULL,
  `show_hide` int(1) UNSIGNED NOT NULL DEFAULT '0',
  `hide_uids` varchar(500) CHARACTER SET utf8 NOT NULL DEFAULT '',
  `hide_content` text CHARACTER SET utf8 NOT NULL,
  `tiempo_creacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_op_intercambios`
--

CREATE TABLE `mybb_op_intercambios` (
  `id` int(10) NOT NULL,
  `uid` int(10) NOT NULL,
  `nombre` varchar(20) COLLATE utf8_unicode_ci NOT NULL,
  `faccion` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `r_uid` int(10) NOT NULL,
  `r_nombre` varchar(20) COLLATE utf8_unicode_ci NOT NULL,
  `r_faccion` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `tid` int(10) NOT NULL,
  `objetos` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `objetos_nombre` varchar(1000) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `razon` text COLLATE utf8_unicode_ci NOT NULL,
  `dinero` int(100) NOT NULL,
  `timestamp` int(100) NOT NULL,
  `tiempo_creacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_op_inventario`
--

CREATE TABLE `mybb_op_inventario` (
  `id` int(3) NOT NULL,
  `objeto_id` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `uid` int(10) NOT NULL,
  `cantidad` int(10) NOT NULL,
  `tiempo` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `imagen` varchar(500) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `apodo` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `autor` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `autor_uid` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `oficios` json DEFAULT NULL,
  `especial` int(10) NOT NULL DEFAULT '0',
  `editado` int(10) NOT NULL DEFAULT '0',
  `usado` int(11) NOT NULL DEFAULT '0',
  `vendidoReciente` timestamp(6) NOT NULL DEFAULT '0000-00-00 00:00:00.000000',
  `bautizado` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_op_inventario_crafteo`
--

CREATE TABLE `mybb_op_inventario_crafteo` (
  `id` int(3) NOT NULL,
  `objeto_id` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `nombre` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `uid` int(10) NOT NULL,
  `tiempo` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `desbloqueado` int(10) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_op_islas`
--

CREATE TABLE `mybb_op_islas` (
  `isla_id` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
  `nombre` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
  `gobierno` text COLLATE utf8_unicode_ci NOT NULL,
  `faccion` text COLLATE utf8_unicode_ci NOT NULL,
  `comercio` text COLLATE utf8_unicode_ci NOT NULL,
  `tamano` text COLLATE utf8_unicode_ci NOT NULL,
  `zonas` text COLLATE utf8_unicode_ci NOT NULL,
  `habitantes` text COLLATE utf8_unicode_ci NOT NULL,
  `facilities` text COLLATE utf8_unicode_ci NOT NULL COMMENT 'Listado de construcciones de los conquistadores',
  `lore` mediumtext COLLATE utf8_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_op_isla_eventos`
--

CREATE TABLE `mybb_op_isla_eventos` (
  `evento_id` int(10) UNSIGNED NOT NULL,
  `isla_id` int(10) UNSIGNED NOT NULL COMMENT 'ID de la isla (fid del foro)',
  `titulo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Título del evento',
  `descripcion` text COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Descripción del evento',
  `ano` int(11) NOT NULL COMMENT 'Año del evento',
  `estacion` enum('Primavera','Verano','Otoño','Invierno') COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Estación en que ocurrió',
  `dia` tinyint(2) UNSIGNED DEFAULT NULL COMMENT 'Día de la estación (1-90)',
  `staff_uid` int(10) UNSIGNED NOT NULL COMMENT 'UID del staff que creó el evento',
  `fecha_creacion` int(10) UNSIGNED NOT NULL COMMENT 'Timestamp de creación',
  `tema_url` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `narrador_uid` int(10) NOT NULL DEFAULT '0',
  `personajes` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `impacto` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Bajo'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Eventos históricos de las islas';

-- --------------------------------------------------------

--
-- Table structure for table `mybb_op_kuros`
--

CREATE TABLE `mybb_op_kuros` (
  `id` int(10) NOT NULL,
  `objeto_id` varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
  `categoria` text NOT NULL,
  `subcategoria` text NOT NULL,
  `nombre` text NOT NULL,
  `berries` int(11) NOT NULL DEFAULT '0',
  `cantidadMaxima` int(4) NOT NULL DEFAULT '1',
  `exclusivo` tinyint(1) NOT NULL DEFAULT '0',
  `imagen` text NOT NULL,
  `descripcion` text NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_op_leidos`
--

CREATE TABLE `mybb_op_leidos` (
  `id` int(10) NOT NULL,
  `pid` int(10) NOT NULL,
  `uid` int(10) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_op_likes`
--

CREATE TABLE `mybb_op_likes` (
  `id` int(100) NOT NULL,
  `pid` int(10) UNSIGNED NOT NULL,
  `tid` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `fid` smallint(5) UNSIGNED NOT NULL DEFAULT '0',
  `uid` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `username` varchar(80) NOT NULL DEFAULT '',
  `subject` varchar(120) NOT NULL DEFAULT '',
  `liked_by_uid` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `liked_by_username` varchar(80) NOT NULL DEFAULT '',
  `liked_by_timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_op_mantenidas_html`
--

CREATE TABLE `mybb_op_mantenidas_html` (
  `id` int(11) NOT NULL,
  `pid` int(11) NOT NULL,
  `html_content` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_op_mapa_posiciones`
--

CREATE TABLE `mybb_op_mapa_posiciones` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tid` int(10) UNSIGNED NOT NULL,
  `uid` int(10) UNSIGNED NOT NULL,
  `fid` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `x_percent` decimal(6,3) NOT NULL,
  `y_percent` decimal(6,3) NOT NULL,
  `created_at` int(10) UNSIGNED NOT NULL,
  `updated_at` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_op_mascotas`
--

CREATE TABLE `mybb_op_mascotas` (
  `id` int(10) NOT NULL,
  `npc_id` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
  `nombre` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
  `avatar1` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `usuario` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `etiqueta` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `tiempo_creacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `estilo1` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `estilo2` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `estilo3` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `estilo4` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `belicas` text COLLATE utf8_unicode_ci,
  `oficios` text COLLATE utf8_unicode_ci,
  `estilos` text COLLATE utf8_unicode_ci,
  `belicas_disponibles` text COLLATE utf8_unicode_ci,
  `belica1` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `belica2` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `belica3` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `belica4` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `belica5` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `belica6` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `belica7` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `belica8` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `oficio1` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `oficio2` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_op_mensajes`
--

CREATE TABLE `mybb_op_mensajes` (
  `id` int(11) NOT NULL,
  `uid_dest` int(11) NOT NULL,
  `remitente` varchar(100) NOT NULL DEFAULT 'Sistema',
  `titulo` varchar(255) NOT NULL,
  `cuerpo` text NOT NULL,
  `leida` tinyint(1) NOT NULL DEFAULT '0',
  `fecha` int(11) NOT NULL,
  `fecha_juego` varchar(100) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_op_misiones_lista`
--

CREATE TABLE `mybb_op_misiones_lista` (
  `id` int(100) NOT NULL,
  `cod` int(100) NOT NULL COMMENT 'codigo único de la mision',
  `rango` text NOT NULL COMMENT 'Rango de mision',
  `niv` int(100) NOT NULL COMMENT 'nivel requerido para realizar la misión',
  `title` text NOT NULL COMMENT 'título de la misión',
  `descripcion` text NOT NULL COMMENT 'descripción de la misión',
  `ryos` int(100) NOT NULL COMMENT 'ryos obtenidos al completar la misión',
  `expt` int(100) NOT NULL COMMENT 'puntos de experiencia ganados al terminar la misión',
  `time` int(100) NOT NULL COMMENT 'tiempo requerido para completar la misión',
  `coste` int(10) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_op_niveles`
--

CREATE TABLE `mybb_op_niveles` (
  `nivel` int(11) NOT NULL,
  `exp_min` int(11) NOT NULL DEFAULT '0',
  `exp_max` int(11) NOT NULL DEFAULT '0',
  `bono_puntos` int(11) DEFAULT NULL,
  `limite_temporal` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_op_niveles_requeridos`
--

CREATE TABLE `mybb_op_niveles_requeridos` (
  `clave` varchar(30) COLLATE utf8_unicode_ci NOT NULL,
  `nivel_min` int(11) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_op_npcs`
--

CREATE TABLE `mybb_op_npcs` (
  `npc_id` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
  `nombre` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
  `apodo` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
  `faccion` varchar(20) COLLATE utf8_unicode_ci NOT NULL,
  `raza` varchar(20) COLLATE utf8_unicode_ci NOT NULL,
  `edad` varchar(30) COLLATE utf8_unicode_ci NOT NULL,
  `altura` varchar(30) COLLATE utf8_unicode_ci NOT NULL,
  `peso` varchar(30) COLLATE utf8_unicode_ci NOT NULL,
  `sexo` varchar(20) COLLATE utf8_unicode_ci NOT NULL,
  `temporada` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `nivel` varchar(30) COLLATE utf8_unicode_ci NOT NULL DEFAULT '1',
  `fuerza` varchar(30) COLLATE utf8_unicode_ci NOT NULL DEFAULT '1',
  `resistencia` varchar(30) COLLATE utf8_unicode_ci NOT NULL DEFAULT '1',
  `destreza` varchar(30) COLLATE utf8_unicode_ci NOT NULL DEFAULT '1',
  `voluntad` varchar(30) COLLATE utf8_unicode_ci NOT NULL DEFAULT '1',
  `punteria` varchar(30) COLLATE utf8_unicode_ci NOT NULL DEFAULT '1',
  `agilidad` varchar(30) COLLATE utf8_unicode_ci NOT NULL DEFAULT '1',
  `reflejos` varchar(30) COLLATE utf8_unicode_ci NOT NULL DEFAULT '1',
  `control_akuma` varchar(30) COLLATE utf8_unicode_ci NOT NULL DEFAULT '1',
  `vitalidad` varchar(30) COLLATE utf8_unicode_ci NOT NULL DEFAULT '1',
  `energia` varchar(30) COLLATE utf8_unicode_ci NOT NULL DEFAULT '1',
  `haki` varchar(30) COLLATE utf8_unicode_ci NOT NULL DEFAULT '1',
  `apariencia` mediumtext COLLATE utf8_unicode_ci NOT NULL,
  `personalidad` mediumtext COLLATE utf8_unicode_ci NOT NULL,
  `historia1` mediumtext COLLATE utf8_unicode_ci NOT NULL,
  `historia2` mediumtext COLLATE utf8_unicode_ci NOT NULL,
  `historia3` mediumtext COLLATE utf8_unicode_ci NOT NULL,
  `extra` text COLLATE utf8_unicode_ci NOT NULL,
  `notas` text COLLATE utf8_unicode_ci NOT NULL,
  `rango` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `sangre` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `akuma` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `wanted` int(10) NOT NULL DEFAULT '0',
  `avatar1` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'https://cdn.discordapp.com/attachments/835254788756602941/1203405082956267620/AvatarOculto_One_Piece_Gaiden_Foro_Rol.png',
  `avatar2` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'https://cdn.discordapp.com/attachments/835254788756602941/1203422974796103760/WantePerfilOculto_One_Piece_Gaiden_Foro_Rol.png',
  `estilo_combate` text COLLATE utf8_unicode_ci NOT NULL,
  `oficio2` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `oficio1` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `belica8` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `belica7` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `belica6` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `belica5` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `belica4` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `belica3` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `belica2` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `belica1` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `belicas_disponibles` text COLLATE utf8_unicode_ci,
  `estilos` text COLLATE utf8_unicode_ci,
  `oficios` text COLLATE utf8_unicode_ci,
  `belicas` text COLLATE utf8_unicode_ci,
  `estilo4` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `estilo3` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `estilo2` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `estilo1` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `buso` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `kenbun` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `haoshoku` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `reputacion` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '0',
  `info` json NOT NULL,
  `etiqueta` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `tiempo_creacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_op_npcs_usuarios`
--

CREATE TABLE `mybb_op_npcs_usuarios` (
  `id` int(10) NOT NULL,
  `npc_id` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
  `nombre` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
  `avatar1` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `usuario` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `etiqueta` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `tiempo_creacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `estilo1` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `estilo2` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `estilo3` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `estilo4` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `belicas` text COLLATE utf8_unicode_ci,
  `oficios` text COLLATE utf8_unicode_ci,
  `estilos` text COLLATE utf8_unicode_ci,
  `belicas_disponibles` text COLLATE utf8_unicode_ci,
  `belica1` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `belica2` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `belica3` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `belica4` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `belica5` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `belica6` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `belica7` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `belica8` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `oficio1` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `oficio2` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_op_objetos`
--

CREATE TABLE `mybb_op_objetos` (
  `id` int(10) NOT NULL,
  `objeto_id` varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
  `categoria` text NOT NULL,
  `subcategoria` text NOT NULL,
  `nombre` text NOT NULL,
  `tier` int(10) NOT NULL DEFAULT '0',
  `imagen_id` int(10) NOT NULL DEFAULT '0',
  `imagen_avatar` text NOT NULL,
  `berries` int(11) NOT NULL DEFAULT '0',
  `cantidadMaxima` int(4) NOT NULL DEFAULT '1',
  `dano` text NOT NULL,
  `bloqueo` text NOT NULL,
  `efecto` text NOT NULL,
  `alcance` text NOT NULL,
  `exclusivo` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Exclusivo de tienda',
  `invisible` int(11) NOT NULL DEFAULT '0' COMMENT 'invisible zona de objetos',
  `espacios` int(10) NOT NULL DEFAULT '0',
  `imagen` text NOT NULL,
  `desbloquear` int(20) NOT NULL DEFAULT '10000',
  `desbloqueado` int(10) NOT NULL DEFAULT '1',
  `oficio` varchar(255) NOT NULL DEFAULT '',
  `nivel` int(10) NOT NULL DEFAULT '0',
  `tiempo_creacion` int(100) NOT NULL DEFAULT '10000',
  `requisitos` text NOT NULL,
  `escalado` text NOT NULL,
  `editable` int(10) NOT NULL DEFAULT '0',
  `custom` int(10) NOT NULL DEFAULT '0',
  `descripcion` text NOT NULL,
  `comerciable` int(1) NOT NULL DEFAULT '0',
  `crafteo_usuarios` varchar(255) NOT NULL DEFAULT '',
  `negro` int(10) NOT NULL DEFAULT '0',
  `negro_berries` int(100) NOT NULL DEFAULT '9999999',
  `fusion_tipo` varchar(255) NOT NULL DEFAULT '',
  `engastes` int(10) NOT NULL DEFAULT '0',
  `fecha_creacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `berriesCrafteo` bigint(20) NOT NULL DEFAULT '0'
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_op_oficios_catalogo`
--

CREATE TABLE `mybb_op_oficios_catalogo` (
  `nombre` varchar(60) COLLATE utf8_unicode_ci NOT NULL,
  `sub1` varchar(60) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `sub2` varchar(60) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `orden` int(11) NOT NULL DEFAULT '0',
  `imagen` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_op_oficios_usuarios`
--

CREATE TABLE `mybb_op_oficios_usuarios` (
  `id` int(100) NOT NULL,
  `uid` int(3) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `oficio` varchar(100) NOT NULL,
  `timestamp_end` int(100) NOT NULL,
  `duracion` int(100) NOT NULL,
  `experiencia` int(100) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_op_peticionAventuras`
--

CREATE TABLE `mybb_op_peticionAventuras` (
  `id` int(10) UNSIGNED NOT NULL,
  `uid` int(10) UNSIGNED NOT NULL,
  `narrador_uid` int(10) UNSIGNED DEFAULT NULL,
  `narrador_fid` int(10) UNSIGNED DEFAULT NULL,
  `narrador_nombre` varchar(80) DEFAULT NULL,
  `tier_seleccionado` tinyint(3) UNSIGNED NOT NULL,
  `nivel_promedio` tinyint(3) UNSIGNED NOT NULL DEFAULT '0',
  `num_jugadores` tinyint(3) UNSIGNED NOT NULL DEFAULT '0',
  `dificultad_texto` varchar(20) NOT NULL,
  `dificultad_color` varchar(7) NOT NULL,
  `ratio_poder` decimal(10,4) NOT NULL DEFAULT '0.0000',
  `descripcion_tier` text NOT NULL,
  `jugadores_json` mediumtext NOT NULL,
  `enemigos_json` mediumtext NOT NULL,
  `detalles_json` mediumtext,
  `created_at` int(10) UNSIGNED NOT NULL,
  `estado` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT '0=creado, 1=aprobado, 2=denegado, 3=finalizado, 4=pendiente narrador\r\n5=solicitud de borrado',
  `comentario_publico` text NOT NULL,
  `comentario_staff` text NOT NULL,
  `aventura_url` varchar(255) DEFAULT NULL,
  `inframundo` tinyint(1) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_op_peticionAventuras_meta`
--

CREATE TABLE `mybb_op_peticionAventuras_meta` (
  `peticion_id` int(10) UNSIGNED NOT NULL,
  `staff_note` text,
  `public_comment` text,
  `status` enum('pendiente','aprobada','denegada') NOT NULL DEFAULT 'pendiente',
  `updated_by` int(10) UNSIGNED DEFAULT NULL,
  `updated_at` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_op_peticiones`
--

CREATE TABLE `mybb_op_peticiones` (
  `id` int(11) NOT NULL,
  `uid` int(11) NOT NULL,
  `nombre` varchar(20) COLLATE utf8_unicode_ci NOT NULL,
  `categoria` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  `resumen` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `descripcion` text COLLATE utf8_unicode_ci NOT NULL,
  `url` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `resuelto` tinyint(1) NOT NULL DEFAULT '0',
  `enviado` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `mod_uid` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `mod_nombre` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `atendidoPor` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `notasMod` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_op_plugin_categories`
--

CREATE TABLE `mybb_op_plugin_categories` (
  `codename` varchar(64) COLLATE utf8_unicode_ci NOT NULL,
  `category` varchar(190) COLLATE utf8_unicode_ci NOT NULL,
  `subcategory` varchar(190) COLLATE utf8_unicode_ci NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_op_razas`
--

CREATE TABLE `mybb_op_razas` (
  `id` int(11) NOT NULL,
  `nombre` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `descripcion` text COLLATE utf8_unicode_ci NOT NULL,
  `caracteristicas` text COLLATE utf8_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_op_recompensas_usuarios`
--

CREATE TABLE `mybb_op_recompensas_usuarios` (
  `id` int(11) NOT NULL,
  `uid` int(11) NOT NULL,
  `nombre` varchar(255) NOT NULL,
  `dia` int(11) NOT NULL,
  `season` int(11) NOT NULL,
  `tiempo` int(11) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_op_sabiasque`
--

CREATE TABLE `mybb_op_sabiasque` (
  `id` int(10) NOT NULL,
  `tipo` int(3) NOT NULL,
  `texto` varchar(1000) COLLATE utf8_unicode_ci NOT NULL,
  `autor` varchar(100) COLLATE utf8_unicode_ci NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_op_tecnicas`
--

CREATE TABLE `mybb_op_tecnicas` (
  `tid` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `nombre` varchar(255) CHARACTER SET utf8 COLLATE utf8_spanish_ci NOT NULL,
  `estilo` varchar(255) CHARACTER SET utf8 COLLATE utf8_spanish_ci NOT NULL,
  `clase` varchar(255) CHARACTER SET utf8 COLLATE utf8_spanish_ci NOT NULL,
  `tier` varchar(255) CHARACTER SET utf8 COLLATE utf8_spanish_ci NOT NULL,
  `rama` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `tipo` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `exclusiva` tinyint(1) NOT NULL DEFAULT '0',
  `energia` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `energia_turno` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `haki` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `haki_turno` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `enfriamiento` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `efectos` varchar(511) CHARACTER SET utf8 COLLATE utf8_spanish_ci NOT NULL,
  `requisitos` varchar(255) CHARACTER SET utf8 COLLATE utf8_spanish_ci NOT NULL,
  `descripcion` mediumtext CHARACTER SET utf8 COLLATE utf8_spanish_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_op_tecnicas_mantenidas`
--

CREATE TABLE `mybb_op_tecnicas_mantenidas` (
  `id` int(11) NOT NULL,
  `uid` int(11) NOT NULL,
  `tid` int(11) NOT NULL,
  `tecnica_id` varchar(100) NOT NULL,
  `pid_inicio` int(11) NOT NULL,
  `turnos` int(11) DEFAULT '1',
  `activa` tinyint(1) DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_op_tecnicas_usuarios`
--

CREATE TABLE `mybb_op_tecnicas_usuarios` (
  `id` int(100) NOT NULL,
  `tid` varchar(255) NOT NULL,
  `uid` int(3) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `tiempo_iniciado` int(100) NOT NULL,
  `tiempo_finaliza` int(100) NOT NULL,
  `duracion` int(100) NOT NULL,
  `costo_pr` int(100) NOT NULL,
  `recompensa` int(100) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_op_tec_aprendidas`
--

CREATE TABLE `mybb_op_tec_aprendidas` (
  `id` int(10) NOT NULL,
  `tid` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `uid` int(3) NOT NULL,
  `tiempo` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_op_tec_para_aprender`
--

CREATE TABLE `mybb_op_tec_para_aprender` (
  `id` int(10) NOT NULL,
  `tid` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `uid` int(3) NOT NULL,
  `tiempo` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_op_thread_personaje`
--

CREATE TABLE `mybb_op_thread_personaje` (
  `id` int(10) NOT NULL,
  `tid` int(5) NOT NULL,
  `pid` int(5) NOT NULL,
  `uid` int(10) NOT NULL,
  `fuerza` int(3) NOT NULL,
  `resistencia` int(3) NOT NULL,
  `destreza` int(3) NOT NULL,
  `punteria` int(3) NOT NULL,
  `agilidad` int(3) NOT NULL,
  `reflejos` int(3) NOT NULL,
  `voluntad` int(3) NOT NULL,
  `control_akuma` int(3) NOT NULL,
  `fuerza_pasiva` int(3) NOT NULL,
  `resistencia_pasiva` int(3) NOT NULL,
  `destreza_pasiva` int(3) NOT NULL,
  `punteria_pasiva` int(3) NOT NULL,
  `agilidad_pasiva` int(3) NOT NULL,
  `reflejos_pasiva` int(3) NOT NULL,
  `voluntad_pasiva` int(3) NOT NULL,
  `control_akuma_pasiva` int(3) NOT NULL,
  `vitalidad` int(5) NOT NULL,
  `energia` int(5) NOT NULL,
  `haki` int(5) NOT NULL,
  `vitalidad_pasiva` int(10) NOT NULL,
  `energia_pasiva` int(10) NOT NULL,
  `haki_pasiva` int(10) NOT NULL,
  `nombre` text NOT NULL,
  `nivel` int(11) NOT NULL DEFAULT '0'
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_op_tiradanaval`
--

CREATE TABLE `mybb_op_tiradanaval` (
  `id` int(10) UNSIGNED NOT NULL,
  `tid` int(11) NOT NULL,
  `pid` int(11) NOT NULL,
  `uid` int(11) NOT NULL,
  `counter` int(11) NOT NULL,
  `content` text CHARACTER SET utf8 NOT NULL,
  `tiempo_creacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_op_tirada_akumas`
--

CREATE TABLE `mybb_op_tirada_akumas` (
  `id` int(10) NOT NULL,
  `uid` int(10) NOT NULL,
  `nombre` varchar(20) COLLATE utf8_unicode_ci NOT NULL,
  `tier` int(10) NOT NULL,
  `fruta` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
  `subnombre` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
  `real` int(10) NOT NULL,
  `timestamp` int(100) NOT NULL,
  `tiempo_creacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_op_tirada_cofre`
--

CREATE TABLE `mybb_op_tirada_cofre` (
  `id` int(10) NOT NULL,
  `uid` int(10) NOT NULL,
  `nombre` varchar(20) COLLATE utf8_unicode_ci NOT NULL,
  `tier` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
  `objeto` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
  `objeto_id` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
  `timestamp` int(100) NOT NULL,
  `tiempo_creacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `cofre_random` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_op_tirada_haki`
--

CREATE TABLE `mybb_op_tirada_haki` (
  `id` int(10) NOT NULL,
  `uid` int(10) NOT NULL,
  `nombre` varchar(20) COLLATE utf8_unicode_ci NOT NULL,
  `haki` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
  `subnombre` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
  `real` int(10) NOT NULL,
  `timestamp` int(100) NOT NULL,
  `tiempo_creacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_op_tirada_rey`
--

CREATE TABLE `mybb_op_tirada_rey` (
  `id` int(10) NOT NULL,
  `uid` int(10) NOT NULL,
  `nombre` varchar(20) COLLATE utf8_unicode_ci NOT NULL,
  `haki` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
  `subnombre` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
  `real` int(10) NOT NULL,
  `tirada_random` varchar(10) COLLATE utf8_unicode_ci NOT NULL DEFAULT '21',
  `timestamp` int(100) NOT NULL,
  `tiempo_creacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_op_viajes`
--

CREATE TABLE `mybb_op_viajes` (
  `id` int(10) NOT NULL,
  `uid_viaje` int(10) NOT NULL,
  `nombre` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
  `mar` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
  `partida` varchar(1000) COLLATE utf8_unicode_ci NOT NULL,
  `llegada` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
  `dificultad` varchar(1000) COLLATE utf8_unicode_ci NOT NULL,
  `modificador` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
  `horas` text COLLATE utf8_unicode_ci NOT NULL,
  `temporada` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `fecha_salida` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `fecha_llegada` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `viajeros` varchar(1000) COLLATE utf8_unicode_ci NOT NULL,
  `log` text COLLATE utf8_unicode_ci NOT NULL,
  `timestamp` int(100) NOT NULL,
  `tiempo_creacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `postViaje` text COLLATE utf8_unicode_ci NOT NULL,
  `dado_naval` int(11) NOT NULL DEFAULT '-1',
  `barco_id` varchar(64) COLLATE utf8_unicode_ci NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_op_virtudes`
--

CREATE TABLE `mybb_op_virtudes` (
  `virtud_id` varchar(30) COLLATE utf8_unicode_ci NOT NULL,
  `nombre` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
  `puntos` int(5) NOT NULL,
  `requisito` tinyint(1) NOT NULL DEFAULT '0',
  `descripcion` text COLLATE utf8_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_op_virtudes_usuarios`
--

CREATE TABLE `mybb_op_virtudes_usuarios` (
  `id` int(100) NOT NULL,
  `virtud_id` varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
  `uid` int(3) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_polls`
--

CREATE TABLE `mybb_polls` (
  `pid` int(10) UNSIGNED NOT NULL,
  `tid` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `question` varchar(200) NOT NULL DEFAULT '',
  `dateline` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `options` text NOT NULL,
  `votes` text NOT NULL,
  `numoptions` smallint(5) UNSIGNED NOT NULL DEFAULT '0',
  `numvotes` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `timeout` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `closed` tinyint(1) NOT NULL DEFAULT '0',
  `multiple` tinyint(1) NOT NULL DEFAULT '0',
  `public` tinyint(1) NOT NULL DEFAULT '0',
  `maxoptions` smallint(5) UNSIGNED NOT NULL DEFAULT '0'
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_pollvotes`
--

CREATE TABLE `mybb_pollvotes` (
  `vid` int(10) UNSIGNED NOT NULL,
  `pid` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `uid` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `voteoption` smallint(5) UNSIGNED NOT NULL DEFAULT '0',
  `dateline` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `ipaddress` varbinary(16) NOT NULL DEFAULT ''
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_posts`
--

CREATE TABLE `mybb_posts` (
  `pid` int(10) UNSIGNED NOT NULL,
  `tid` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `replyto` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `fid` smallint(5) UNSIGNED NOT NULL DEFAULT '0',
  `subject` varchar(120) NOT NULL DEFAULT '',
  `icon` smallint(5) UNSIGNED NOT NULL DEFAULT '0',
  `uid` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `username` varchar(80) NOT NULL DEFAULT '',
  `dateline` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `message` text NOT NULL,
  `ipaddress` varbinary(16) NOT NULL DEFAULT '',
  `includesig` tinyint(1) NOT NULL DEFAULT '0',
  `smilieoff` tinyint(1) NOT NULL DEFAULT '0',
  `edituid` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `edittime` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `editreason` varchar(150) NOT NULL DEFAULT '',
  `visible` tinyint(1) NOT NULL DEFAULT '0',
  `faccionsecreta` varchar(80) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_privatemessages`
--

CREATE TABLE `mybb_privatemessages` (
  `pmid` int(10) UNSIGNED NOT NULL,
  `uid` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `toid` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `fromid` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `recipients` text NOT NULL,
  `folder` smallint(5) UNSIGNED NOT NULL DEFAULT '1',
  `subject` varchar(120) NOT NULL DEFAULT '',
  `icon` smallint(5) UNSIGNED NOT NULL DEFAULT '0',
  `message` text NOT NULL,
  `dateline` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `deletetime` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `status` tinyint(1) NOT NULL DEFAULT '0',
  `statustime` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `includesig` tinyint(1) NOT NULL DEFAULT '0',
  `smilieoff` tinyint(1) NOT NULL DEFAULT '0',
  `receipt` tinyint(1) NOT NULL DEFAULT '0',
  `readtime` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `ipaddress` varbinary(16) NOT NULL DEFAULT ''
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_profilefields`
--

CREATE TABLE `mybb_profilefields` (
  `fid` smallint(5) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL DEFAULT '',
  `description` text NOT NULL,
  `disporder` smallint(5) UNSIGNED NOT NULL DEFAULT '0',
  `type` text NOT NULL,
  `regex` text NOT NULL,
  `length` smallint(5) UNSIGNED NOT NULL DEFAULT '0',
  `maxlength` smallint(5) UNSIGNED NOT NULL DEFAULT '0',
  `required` tinyint(1) NOT NULL DEFAULT '0',
  `registration` tinyint(1) NOT NULL DEFAULT '0',
  `profile` tinyint(1) NOT NULL DEFAULT '0',
  `postbit` tinyint(1) NOT NULL DEFAULT '0',
  `viewableby` text NOT NULL,
  `editableby` text NOT NULL,
  `postnum` smallint(5) UNSIGNED NOT NULL DEFAULT '0',
  `allowhtml` tinyint(1) NOT NULL DEFAULT '0',
  `allowmycode` tinyint(1) NOT NULL DEFAULT '0',
  `allowsmilies` tinyint(1) NOT NULL DEFAULT '0',
  `allowimgcode` tinyint(1) NOT NULL DEFAULT '0',
  `allowvideocode` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_promotionlogs`
--

CREATE TABLE `mybb_promotionlogs` (
  `plid` int(10) UNSIGNED NOT NULL,
  `pid` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `uid` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `oldusergroup` varchar(200) NOT NULL DEFAULT '0',
  `newusergroup` smallint(6) NOT NULL DEFAULT '0',
  `dateline` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `type` varchar(9) NOT NULL DEFAULT 'primary'
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_promotions`
--

CREATE TABLE `mybb_promotions` (
  `pid` int(10) UNSIGNED NOT NULL,
  `title` varchar(120) NOT NULL DEFAULT '',
  `description` text NOT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT '1',
  `logging` tinyint(1) NOT NULL DEFAULT '0',
  `posts` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `posttype` char(2) NOT NULL DEFAULT '',
  `threads` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `threadtype` char(2) NOT NULL DEFAULT '',
  `registered` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `registeredtype` varchar(20) NOT NULL DEFAULT '',
  `online` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `onlinetype` varchar(20) NOT NULL DEFAULT '',
  `reputations` int(11) NOT NULL DEFAULT '0',
  `reputationtype` char(2) NOT NULL DEFAULT '',
  `referrals` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `referralstype` char(2) NOT NULL DEFAULT '',
  `warnings` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `warningstype` char(2) NOT NULL DEFAULT '',
  `requirements` varchar(200) NOT NULL DEFAULT '',
  `originalusergroup` varchar(120) NOT NULL DEFAULT '0',
  `newusergroup` smallint(5) UNSIGNED NOT NULL DEFAULT '0',
  `usergrouptype` varchar(120) NOT NULL DEFAULT '0'
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_questions`
--

CREATE TABLE `mybb_questions` (
  `qid` int(10) UNSIGNED NOT NULL,
  `question` varchar(200) NOT NULL DEFAULT '',
  `answer` varchar(150) NOT NULL DEFAULT '',
  `shown` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `correct` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `incorrect` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `active` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_questionsessions`
--

CREATE TABLE `mybb_questionsessions` (
  `sid` varchar(32) NOT NULL DEFAULT '',
  `qid` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `dateline` int(10) UNSIGNED NOT NULL DEFAULT '0'
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_reportedcontent`
--

CREATE TABLE `mybb_reportedcontent` (
  `rid` int(10) UNSIGNED NOT NULL,
  `id` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `id2` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `id3` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `uid` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `reportstatus` tinyint(1) NOT NULL DEFAULT '0',
  `reasonid` smallint(5) UNSIGNED NOT NULL DEFAULT '0',
  `reason` varchar(250) NOT NULL DEFAULT '',
  `type` varchar(50) NOT NULL DEFAULT '',
  `reports` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `reporters` text NOT NULL,
  `dateline` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `lastreport` int(10) UNSIGNED NOT NULL DEFAULT '0'
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_reportreasons`
--

CREATE TABLE `mybb_reportreasons` (
  `rid` int(10) UNSIGNED NOT NULL,
  `title` varchar(250) NOT NULL DEFAULT '',
  `appliesto` varchar(250) NOT NULL DEFAULT '',
  `extra` tinyint(1) NOT NULL DEFAULT '0',
  `disporder` smallint(5) UNSIGNED NOT NULL DEFAULT '0'
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_reputation`
--

CREATE TABLE `mybb_reputation` (
  `rid` int(10) UNSIGNED NOT NULL,
  `uid` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `adduid` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `pid` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `reputation` smallint(6) NOT NULL DEFAULT '0',
  `dateline` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `comments` text NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_rtchat`
--

CREATE TABLE `mybb_rtchat` (
  `id` int(11) NOT NULL,
  `uid` int(11) DEFAULT NULL,
  `touid` int(11) DEFAULT NULL,
  `message` text COLLATE utf8_unicode_ci,
  `dateline` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_rtchat_bans`
--

CREATE TABLE `mybb_rtchat_bans` (
  `id` int(11) NOT NULL,
  `uid` int(11) DEFAULT NULL,
  `reason` text COLLATE utf8_unicode_ci,
  `dateline` int(11) DEFAULT NULL,
  `expires` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_rt_discord_webhooks`
--

CREATE TABLE `mybb_rt_discord_webhooks` (
  `id` int(11) NOT NULL,
  `webhook_url` text COLLATE utf8_unicode_ci,
  `webhook_name` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `webhook_embeds` tinyint(4) NOT NULL DEFAULT '0',
  `webhook_embeds_color` text COLLATE utf8_unicode_ci,
  `webhook_embeds_thumbnail` text COLLATE utf8_unicode_ci,
  `webhook_embeds_footer_text` text COLLATE utf8_unicode_ci,
  `webhook_embeds_footer_icon_url` text COLLATE utf8_unicode_ci,
  `bot_id` int(11) NOT NULL DEFAULT '0',
  `watch_new_threads` tinyint(4) NOT NULL DEFAULT '0',
  `watch_new_posts` tinyint(4) NOT NULL DEFAULT '0',
  `watch_edit_threads` tinyint(4) NOT NULL DEFAULT '0',
  `watch_edit_posts` tinyint(4) NOT NULL DEFAULT '0',
  `watch_delete_threads` tinyint(4) NOT NULL DEFAULT '0',
  `watch_delete_posts` tinyint(4) NOT NULL DEFAULT '0',
  `watch_new_registrations` tinyint(4) NOT NULL DEFAULT '0',
  `character_limit` int(11) NOT NULL DEFAULT '500',
  `allowed_mentions` tinyint(4) NOT NULL DEFAULT '0',
  `watch_usergroups` text COLLATE utf8_unicode_ci,
  `watch_forums` text COLLATE utf8_unicode_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_rt_discord_webhooks_logs`
--

CREATE TABLE `mybb_rt_discord_webhooks_logs` (
  `id` int(11) NOT NULL,
  `discord_message_id` text COLLATE utf8_unicode_ci,
  `discord_channel_id` text COLLATE utf8_unicode_ci,
  `webhook_id` text COLLATE utf8_unicode_ci,
  `tid` int(11) NOT NULL DEFAULT '0',
  `pid` int(11) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_searchlog`
--

CREATE TABLE `mybb_searchlog` (
  `sid` varchar(32) NOT NULL DEFAULT '',
  `uid` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `dateline` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `ipaddress` varbinary(16) NOT NULL DEFAULT '',
  `threads` longtext NOT NULL,
  `posts` longtext NOT NULL,
  `resulttype` varchar(10) NOT NULL DEFAULT '',
  `querycache` text NOT NULL,
  `keywords` text NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_sessions`
--

CREATE TABLE `mybb_sessions` (
  `sid` varchar(32) NOT NULL DEFAULT '',
  `uid` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `ip` varbinary(16) NOT NULL DEFAULT '',
  `time` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `location` varchar(150) NOT NULL DEFAULT '',
  `useragent` varchar(200) NOT NULL DEFAULT '',
  `anonymous` tinyint(1) NOT NULL DEFAULT '0',
  `nopermission` tinyint(1) NOT NULL DEFAULT '0',
  `location1` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `location2` int(10) UNSIGNED NOT NULL DEFAULT '0'
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_settinggroups`
--

CREATE TABLE `mybb_settinggroups` (
  `gid` smallint(5) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL DEFAULT '',
  `title` varchar(220) NOT NULL DEFAULT '',
  `description` text NOT NULL,
  `disporder` smallint(5) UNSIGNED NOT NULL DEFAULT '0',
  `isdefault` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_settings`
--

CREATE TABLE `mybb_settings` (
  `sid` smallint(5) UNSIGNED NOT NULL,
  `name` varchar(120) NOT NULL DEFAULT '',
  `title` varchar(120) NOT NULL DEFAULT '',
  `description` text NOT NULL,
  `optionscode` text NOT NULL,
  `value` text NOT NULL,
  `disporder` smallint(5) UNSIGNED NOT NULL DEFAULT '0',
  `gid` smallint(5) UNSIGNED NOT NULL DEFAULT '0',
  `isdefault` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_smilies`
--

CREATE TABLE `mybb_smilies` (
  `sid` smallint(5) UNSIGNED NOT NULL,
  `name` varchar(120) NOT NULL DEFAULT '',
  `find` text NOT NULL,
  `image` varchar(220) NOT NULL DEFAULT '',
  `disporder` smallint(5) UNSIGNED NOT NULL DEFAULT '0',
  `showclickable` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_spamlog`
--

CREATE TABLE `mybb_spamlog` (
  `sid` int(10) UNSIGNED NOT NULL,
  `username` varchar(120) NOT NULL DEFAULT '',
  `email` varchar(220) NOT NULL DEFAULT '',
  `ipaddress` varbinary(16) NOT NULL DEFAULT '',
  `dateline` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `data` text NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_spiders`
--

CREATE TABLE `mybb_spiders` (
  `sid` int(10) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL DEFAULT '',
  `theme` smallint(5) UNSIGNED NOT NULL DEFAULT '0',
  `language` varchar(20) NOT NULL DEFAULT '',
  `usergroup` smallint(5) UNSIGNED NOT NULL DEFAULT '0',
  `useragent` varchar(200) NOT NULL DEFAULT '',
  `lastvisit` int(10) UNSIGNED NOT NULL DEFAULT '0'
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_stats`
--

CREATE TABLE `mybb_stats` (
  `dateline` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `numusers` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `numthreads` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `numposts` int(10) UNSIGNED NOT NULL DEFAULT '0'
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_tasklog`
--

CREATE TABLE `mybb_tasklog` (
  `lid` int(10) UNSIGNED NOT NULL,
  `tid` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `dateline` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `data` text NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_tasks`
--

CREATE TABLE `mybb_tasks` (
  `tid` int(10) UNSIGNED NOT NULL,
  `title` varchar(120) NOT NULL DEFAULT '',
  `description` text NOT NULL,
  `file` varchar(30) NOT NULL DEFAULT '',
  `minute` varchar(200) NOT NULL DEFAULT '',
  `hour` varchar(200) NOT NULL DEFAULT '',
  `day` varchar(100) NOT NULL DEFAULT '',
  `month` varchar(30) NOT NULL DEFAULT '',
  `weekday` varchar(15) NOT NULL DEFAULT '',
  `nextrun` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `lastrun` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `enabled` tinyint(1) NOT NULL DEFAULT '1',
  `logging` tinyint(1) NOT NULL DEFAULT '0',
  `locked` int(10) UNSIGNED NOT NULL DEFAULT '0'
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_templategroups`
--

CREATE TABLE `mybb_templategroups` (
  `gid` int(10) UNSIGNED NOT NULL,
  `prefix` varchar(50) NOT NULL DEFAULT '',
  `title` varchar(100) NOT NULL DEFAULT '',
  `isdefault` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_templates`
--

CREATE TABLE `mybb_templates` (
  `tid` int(10) UNSIGNED NOT NULL,
  `title` varchar(120) NOT NULL DEFAULT '',
  `template` longtext NOT NULL,
  `sid` smallint(6) NOT NULL DEFAULT '0',
  `version` varchar(20) NOT NULL DEFAULT '0',
  `status` varchar(10) NOT NULL DEFAULT '',
  `dateline` int(10) UNSIGNED NOT NULL DEFAULT '0'
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_templatesets`
--

CREATE TABLE `mybb_templatesets` (
  `sid` smallint(5) UNSIGNED NOT NULL,
  `title` varchar(120) NOT NULL DEFAULT ''
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_themes`
--

CREATE TABLE `mybb_themes` (
  `tid` smallint(5) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL DEFAULT '',
  `pid` smallint(5) UNSIGNED NOT NULL DEFAULT '0',
  `def` tinyint(1) NOT NULL DEFAULT '0',
  `properties` text NOT NULL,
  `stylesheets` text NOT NULL,
  `allowedgroups` text NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_themestylesheets`
--

CREATE TABLE `mybb_themestylesheets` (
  `sid` int(10) UNSIGNED NOT NULL,
  `name` varchar(30) NOT NULL DEFAULT '',
  `tid` smallint(5) UNSIGNED NOT NULL DEFAULT '0',
  `attachedto` text NOT NULL,
  `stylesheet` longtext NOT NULL,
  `cachefile` varchar(100) NOT NULL DEFAULT '',
  `lastmodified` int(10) UNSIGNED NOT NULL DEFAULT '0'
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_threadprefixes`
--

CREATE TABLE `mybb_threadprefixes` (
  `pid` int(10) UNSIGNED NOT NULL,
  `prefix` varchar(120) NOT NULL DEFAULT '',
  `displaystyle` varchar(200) NOT NULL DEFAULT '',
  `forums` text NOT NULL,
  `groups` text NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_threadratings`
--

CREATE TABLE `mybb_threadratings` (
  `rid` int(10) UNSIGNED NOT NULL,
  `tid` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `uid` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `rating` tinyint(1) UNSIGNED NOT NULL DEFAULT '0',
  `ipaddress` varbinary(16) NOT NULL DEFAULT ''
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_threads`
--

CREATE TABLE `mybb_threads` (
  `tid` int(10) UNSIGNED NOT NULL,
  `fid` smallint(5) UNSIGNED NOT NULL DEFAULT '0',
  `subject` varchar(120) NOT NULL DEFAULT '',
  `prefix` smallint(5) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'Aventura = 3\r\nMT = 10\r\nComún = 1\r\nEvento = 6\r\nAutonarrada = 9\r\nRequerimiento = 14',
  `icon` smallint(5) UNSIGNED NOT NULL DEFAULT '0',
  `poll` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `uid` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `username` varchar(80) NOT NULL DEFAULT '',
  `dateline` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `firstpost` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `lastpost` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `lastposter` varchar(120) NOT NULL DEFAULT '',
  `lastposteruid` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `views` int(100) UNSIGNED NOT NULL DEFAULT '0',
  `replies` int(100) UNSIGNED NOT NULL DEFAULT '0',
  `closed` varchar(30) NOT NULL DEFAULT '',
  `sticky` tinyint(1) NOT NULL DEFAULT '0',
  `numratings` smallint(5) UNSIGNED NOT NULL DEFAULT '0',
  `totalratings` smallint(5) UNSIGNED NOT NULL DEFAULT '0',
  `notes` text NOT NULL,
  `visible` tinyint(1) NOT NULL DEFAULT '0',
  `unapprovedposts` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `deletedposts` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `attachmentcount` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `deletetime` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `year` varchar(255) NOT NULL DEFAULT '0',
  `estacion` varchar(255) NOT NULL DEFAULT '0',
  `day` varchar(255) NOT NULL DEFAULT '0'
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_threadsread`
--

CREATE TABLE `mybb_threadsread` (
  `tid` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `uid` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `dateline` int(10) UNSIGNED NOT NULL DEFAULT '0'
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_threadsubscriptions`
--

CREATE TABLE `mybb_threadsubscriptions` (
  `sid` int(10) UNSIGNED NOT NULL,
  `uid` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `tid` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `notification` tinyint(1) NOT NULL DEFAULT '0',
  `dateline` int(10) UNSIGNED NOT NULL DEFAULT '0'
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_threadviews`
--

CREATE TABLE `mybb_threadviews` (
  `tid` int(10) UNSIGNED NOT NULL DEFAULT '0'
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_userfields`
--

CREATE TABLE `mybb_userfields` (
  `ufid` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `fid1` text NOT NULL,
  `fid2` text NOT NULL,
  `fid3` text NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_usergroups`
--

CREATE TABLE `mybb_usergroups` (
  `gid` smallint(5) UNSIGNED NOT NULL,
  `type` tinyint(1) UNSIGNED NOT NULL DEFAULT '2',
  `title` varchar(120) NOT NULL DEFAULT '',
  `description` text NOT NULL,
  `namestyle` varchar(200) NOT NULL DEFAULT '{username}',
  `usertitle` varchar(120) NOT NULL DEFAULT '',
  `stars` smallint(4) UNSIGNED NOT NULL DEFAULT '0',
  `starimage` varchar(120) NOT NULL DEFAULT '',
  `image` varchar(120) NOT NULL DEFAULT '',
  `disporder` smallint(6) UNSIGNED NOT NULL,
  `isbannedgroup` tinyint(1) NOT NULL DEFAULT '0',
  `canview` tinyint(1) NOT NULL DEFAULT '0',
  `canviewthreads` tinyint(1) NOT NULL DEFAULT '0',
  `canviewprofiles` tinyint(1) NOT NULL DEFAULT '0',
  `candlattachments` tinyint(1) NOT NULL DEFAULT '0',
  `canviewboardclosed` tinyint(1) NOT NULL DEFAULT '0',
  `canpostthreads` tinyint(1) NOT NULL DEFAULT '0',
  `canpostreplys` tinyint(1) NOT NULL DEFAULT '0',
  `canpostattachments` tinyint(1) NOT NULL DEFAULT '0',
  `canratethreads` tinyint(1) NOT NULL DEFAULT '0',
  `modposts` tinyint(1) NOT NULL DEFAULT '0',
  `modthreads` tinyint(1) NOT NULL DEFAULT '0',
  `mod_edit_posts` tinyint(1) NOT NULL DEFAULT '0',
  `modattachments` tinyint(1) NOT NULL DEFAULT '0',
  `caneditposts` tinyint(1) NOT NULL DEFAULT '0',
  `candeleteposts` tinyint(1) NOT NULL DEFAULT '0',
  `candeletethreads` tinyint(1) NOT NULL DEFAULT '0',
  `caneditattachments` tinyint(1) NOT NULL DEFAULT '0',
  `canviewdeletionnotice` tinyint(1) NOT NULL DEFAULT '0',
  `canpostpolls` tinyint(1) NOT NULL DEFAULT '0',
  `canvotepolls` tinyint(1) NOT NULL DEFAULT '0',
  `canundovotes` tinyint(1) NOT NULL DEFAULT '0',
  `canusepms` tinyint(1) NOT NULL DEFAULT '0',
  `cansendpms` tinyint(1) NOT NULL DEFAULT '0',
  `cantrackpms` tinyint(1) NOT NULL DEFAULT '0',
  `candenypmreceipts` tinyint(1) NOT NULL DEFAULT '0',
  `pmquota` int(3) UNSIGNED NOT NULL DEFAULT '0',
  `maxpmrecipients` int(4) UNSIGNED NOT NULL DEFAULT '5',
  `cansendemail` tinyint(1) NOT NULL DEFAULT '0',
  `cansendemailoverride` tinyint(1) NOT NULL DEFAULT '0',
  `maxemails` int(3) UNSIGNED NOT NULL DEFAULT '5',
  `emailfloodtime` int(3) UNSIGNED NOT NULL DEFAULT '5',
  `canviewmemberlist` tinyint(1) NOT NULL DEFAULT '0',
  `canviewcalendar` tinyint(1) NOT NULL DEFAULT '0',
  `canaddevents` tinyint(1) NOT NULL DEFAULT '0',
  `canbypasseventmod` tinyint(1) NOT NULL DEFAULT '0',
  `canmoderateevents` tinyint(1) NOT NULL DEFAULT '0',
  `canviewonline` tinyint(1) NOT NULL DEFAULT '0',
  `canviewwolinvis` tinyint(1) NOT NULL DEFAULT '0',
  `canviewonlineips` tinyint(1) NOT NULL DEFAULT '0',
  `cancp` tinyint(1) NOT NULL DEFAULT '0',
  `issupermod` tinyint(1) NOT NULL DEFAULT '0',
  `cansearch` tinyint(1) NOT NULL DEFAULT '0',
  `canusercp` tinyint(1) NOT NULL DEFAULT '0',
  `canuploadavatars` tinyint(1) NOT NULL DEFAULT '0',
  `canratemembers` tinyint(1) NOT NULL DEFAULT '0',
  `canchangename` tinyint(1) NOT NULL DEFAULT '0',
  `canbereported` tinyint(1) NOT NULL DEFAULT '0',
  `canbeinvisible` tinyint(1) NOT NULL DEFAULT '1',
  `canchangewebsite` tinyint(1) NOT NULL DEFAULT '1',
  `showforumteam` tinyint(1) NOT NULL DEFAULT '0',
  `usereputationsystem` tinyint(1) NOT NULL DEFAULT '0',
  `cangivereputations` tinyint(1) NOT NULL DEFAULT '0',
  `candeletereputations` tinyint(1) NOT NULL DEFAULT '0',
  `reputationpower` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `maxreputationsday` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `maxreputationsperuser` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `maxreputationsperthread` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `candisplaygroup` tinyint(1) NOT NULL DEFAULT '0',
  `attachquota` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `cancustomtitle` tinyint(1) NOT NULL DEFAULT '0',
  `canwarnusers` tinyint(1) NOT NULL DEFAULT '0',
  `canreceivewarnings` tinyint(1) NOT NULL DEFAULT '0',
  `maxwarningsday` int(3) UNSIGNED NOT NULL DEFAULT '3',
  `canmodcp` tinyint(1) NOT NULL DEFAULT '0',
  `showinbirthdaylist` tinyint(1) NOT NULL DEFAULT '0',
  `canoverridepm` tinyint(1) NOT NULL DEFAULT '0',
  `canusesig` tinyint(1) NOT NULL DEFAULT '0',
  `canusesigxposts` smallint(5) UNSIGNED NOT NULL DEFAULT '0',
  `signofollow` tinyint(1) NOT NULL DEFAULT '0',
  `edittimelimit` int(4) UNSIGNED NOT NULL DEFAULT '0',
  `maxposts` int(4) UNSIGNED NOT NULL DEFAULT '0',
  `showmemberlist` tinyint(1) NOT NULL DEFAULT '1',
  `canmanageannounce` tinyint(1) NOT NULL DEFAULT '0',
  `canmanagemodqueue` tinyint(1) NOT NULL DEFAULT '0',
  `canmanagereportedcontent` tinyint(1) NOT NULL DEFAULT '0',
  `canviewmodlogs` tinyint(1) NOT NULL DEFAULT '0',
  `caneditprofiles` tinyint(1) NOT NULL DEFAULT '0',
  `canbanusers` tinyint(1) NOT NULL DEFAULT '0',
  `canviewwarnlogs` tinyint(1) NOT NULL DEFAULT '0',
  `canuseipsearch` tinyint(1) NOT NULL DEFAULT '0',
  `as_canswitch` int(1) NOT NULL DEFAULT '0',
  `as_limit` smallint(5) NOT NULL DEFAULT '0'
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_users`
--

CREATE TABLE `mybb_users` (
  `uid` int(10) UNSIGNED NOT NULL,
  `username` varchar(120) NOT NULL DEFAULT '',
  `password` varchar(120) NOT NULL DEFAULT '',
  `salt` varchar(10) NOT NULL DEFAULT '',
  `loginkey` varchar(50) NOT NULL DEFAULT '',
  `email` varchar(220) NOT NULL DEFAULT '',
  `postnum` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `threadnum` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `avatar` varchar(200) NOT NULL DEFAULT '/images/op/uploads/AvatarHabilidades_One_Piece_Gaiden_Foro_Rol.png',
  `avatar2` varchar(200) NOT NULL DEFAULT '/images/op/uploads/AvatarHabilidades_One_Piece_Gaiden_Foro_Rol.png',
  `avatardimensions` varchar(10) NOT NULL DEFAULT '',
  `avatartype` varchar(10) NOT NULL DEFAULT '0',
  `usergroup` smallint(5) UNSIGNED NOT NULL DEFAULT '0',
  `additionalgroups` varchar(200) NOT NULL DEFAULT '',
  `displaygroup` smallint(5) UNSIGNED NOT NULL DEFAULT '0',
  `usertitle` varchar(250) NOT NULL DEFAULT '',
  `regdate` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `lastactive` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `lastvisit` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `lastpost` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `website` varchar(200) NOT NULL DEFAULT '',
  `icq` varchar(10) NOT NULL DEFAULT '',
  `skype` varchar(75) NOT NULL DEFAULT '',
  `google` varchar(75) NOT NULL DEFAULT '',
  `birthday` varchar(15) NOT NULL DEFAULT '',
  `birthdayprivacy` varchar(4) NOT NULL DEFAULT 'all',
  `signature` text NOT NULL,
  `allownotices` tinyint(1) NOT NULL DEFAULT '0',
  `hideemail` tinyint(1) NOT NULL DEFAULT '0',
  `subscriptionmethod` tinyint(1) NOT NULL DEFAULT '0',
  `invisible` tinyint(1) NOT NULL DEFAULT '0',
  `receivepms` tinyint(1) NOT NULL DEFAULT '0',
  `receivefrombuddy` tinyint(1) NOT NULL DEFAULT '0',
  `pmnotice` tinyint(1) NOT NULL DEFAULT '0',
  `pmnotify` tinyint(1) NOT NULL DEFAULT '0',
  `buddyrequestspm` tinyint(1) NOT NULL DEFAULT '1',
  `buddyrequestsauto` tinyint(1) NOT NULL DEFAULT '0',
  `threadmode` varchar(8) NOT NULL DEFAULT '',
  `showimages` tinyint(1) NOT NULL DEFAULT '0',
  `showvideos` tinyint(1) NOT NULL DEFAULT '0',
  `showsigs` tinyint(1) NOT NULL DEFAULT '0',
  `showavatars` tinyint(1) NOT NULL DEFAULT '0',
  `showquickreply` tinyint(1) NOT NULL DEFAULT '0',
  `showredirect` tinyint(1) NOT NULL DEFAULT '0',
  `ppp` smallint(6) UNSIGNED NOT NULL DEFAULT '0',
  `tpp` smallint(6) UNSIGNED NOT NULL DEFAULT '0',
  `daysprune` smallint(6) UNSIGNED NOT NULL DEFAULT '0',
  `dateformat` varchar(4) NOT NULL DEFAULT '',
  `timeformat` varchar(4) NOT NULL DEFAULT '',
  `timezone` varchar(5) NOT NULL DEFAULT '',
  `dst` tinyint(1) NOT NULL DEFAULT '0',
  `dstcorrection` tinyint(1) NOT NULL DEFAULT '0',
  `buddylist` text NOT NULL,
  `ignorelist` text NOT NULL,
  `style` smallint(5) UNSIGNED NOT NULL DEFAULT '0',
  `away` tinyint(1) NOT NULL DEFAULT '0',
  `awaydate` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `returndate` varchar(15) NOT NULL DEFAULT '',
  `awayreason` varchar(200) NOT NULL DEFAULT '',
  `pmfolders` text NOT NULL,
  `notepad` text NOT NULL,
  `referrer` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `referrals` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `reputation` int(11) NOT NULL DEFAULT '0',
  `regip` varbinary(16) NOT NULL DEFAULT '',
  `lastip` varbinary(16) NOT NULL DEFAULT '',
  `language` varchar(50) NOT NULL DEFAULT '',
  `timeonline` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `showcodebuttons` tinyint(1) NOT NULL DEFAULT '1',
  `totalpms` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `unreadpms` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `warningpoints` int(3) UNSIGNED NOT NULL DEFAULT '0',
  `moderateposts` tinyint(1) NOT NULL DEFAULT '0',
  `moderationtime` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `suspendposting` tinyint(1) NOT NULL DEFAULT '0',
  `suspensiontime` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `suspendsignature` tinyint(1) NOT NULL DEFAULT '0',
  `suspendsigtime` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `coppauser` tinyint(1) NOT NULL DEFAULT '0',
  `classicpostbit` tinyint(1) NOT NULL DEFAULT '0',
  `loginattempts` smallint(2) UNSIGNED NOT NULL DEFAULT '0',
  `loginlockoutexpiry` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `usernotes` text NOT NULL,
  `sourceeditor` tinyint(1) NOT NULL DEFAULT '0',
  `newpoints` decimal(16,2) NOT NULL DEFAULT '0.00',
  `as_uid` int(11) NOT NULL DEFAULT '0',
  `as_share` int(1) NOT NULL DEFAULT '0',
  `as_shareuid` int(11) NOT NULL DEFAULT '0',
  `as_sec` int(1) NOT NULL DEFAULT '0',
  `as_privacy` int(1) NOT NULL DEFAULT '0',
  `as_buddyshare` int(1) NOT NULL DEFAULT '0',
  `recentthread_show` int(11) NOT NULL DEFAULT '1',
  `as_secreason` varchar(500) NOT NULL DEFAULT ''
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Triggers `mybb_users`
--
DELIMITER $$
CREATE TRIGGER `u_users_triggers` AFTER UPDATE ON `mybb_users` FOR EACH ROW BEGIN
   IF NEW.newpoints <> OLD.newpoints THEN
   INSERT INTO `mybb_audit_users` (uid, username, newpoints)
    values (
  NEW.`uid`  
  , NEW.username
  ,NEW.`newpoints`
   );
  END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_usertitles`
--

CREATE TABLE `mybb_usertitles` (
  `utid` smallint(5) UNSIGNED NOT NULL,
  `posts` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `title` varchar(250) NOT NULL DEFAULT '',
  `stars` smallint(4) UNSIGNED NOT NULL DEFAULT '0',
  `starimage` varchar(120) NOT NULL DEFAULT ''
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_warninglevels`
--

CREATE TABLE `mybb_warninglevels` (
  `lid` int(10) UNSIGNED NOT NULL,
  `percentage` smallint(3) UNSIGNED NOT NULL DEFAULT '0',
  `action` text NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_warnings`
--

CREATE TABLE `mybb_warnings` (
  `wid` int(10) UNSIGNED NOT NULL,
  `uid` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `tid` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `pid` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `title` varchar(120) NOT NULL DEFAULT '',
  `points` smallint(5) UNSIGNED NOT NULL DEFAULT '0',
  `dateline` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `issuedby` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `expires` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `expired` tinyint(1) NOT NULL DEFAULT '0',
  `daterevoked` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `revokedby` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `revokereason` text NOT NULL,
  `notes` text NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `mybb_warningtypes`
--

CREATE TABLE `mybb_warningtypes` (
  `tid` int(10) UNSIGNED NOT NULL,
  `title` varchar(120) NOT NULL DEFAULT '',
  `points` smallint(5) UNSIGNED NOT NULL DEFAULT '0',
  `expirationtime` int(10) UNSIGNED NOT NULL DEFAULT '0'
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `mybb_adminlog`
--
ALTER TABLE `mybb_adminlog`
  ADD KEY `module` (`module`,`action`),
  ADD KEY `uid` (`uid`);

--
-- Indexes for table `mybb_adminoptions`
--
ALTER TABLE `mybb_adminoptions`
  ADD PRIMARY KEY (`uid`);

--
-- Indexes for table `mybb_adminviews`
--
ALTER TABLE `mybb_adminviews`
  ADD PRIMARY KEY (`vid`);

--
-- Indexes for table `mybb_announcements`
--
ALTER TABLE `mybb_announcements`
  ADD PRIMARY KEY (`aid`),
  ADD KEY `fid` (`fid`);

--
-- Indexes for table `mybb_attachments`
--
ALTER TABLE `mybb_attachments`
  ADD PRIMARY KEY (`aid`),
  ADD KEY `pid` (`pid`,`visible`),
  ADD KEY `uid` (`uid`);

--
-- Indexes for table `mybb_attachtypes`
--
ALTER TABLE `mybb_attachtypes`
  ADD PRIMARY KEY (`atid`);

--
-- Indexes for table `mybb_audit_op_fichas`
--
ALTER TABLE `mybb_audit_op_fichas`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `mybb_audit_users`
--
ALTER TABLE `mybb_audit_users`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `mybb_awaitingactivation`
--
ALTER TABLE `mybb_awaitingactivation`
  ADD PRIMARY KEY (`aid`);

--
-- Indexes for table `mybb_badwords`
--
ALTER TABLE `mybb_badwords`
  ADD PRIMARY KEY (`bid`);

--
-- Indexes for table `mybb_banfilters`
--
ALTER TABLE `mybb_banfilters`
  ADD PRIMARY KEY (`fid`),
  ADD KEY `type` (`type`);

--
-- Indexes for table `mybb_banned`
--
ALTER TABLE `mybb_banned`
  ADD KEY `uid` (`uid`),
  ADD KEY `dateline` (`dateline`);

--
-- Indexes for table `mybb_buddyrequests`
--
ALTER TABLE `mybb_buddyrequests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `uid` (`uid`),
  ADD KEY `touid` (`touid`);

--
-- Indexes for table `mybb_calendars`
--
ALTER TABLE `mybb_calendars`
  ADD PRIMARY KEY (`cid`);

--
-- Indexes for table `mybb_captcha`
--
ALTER TABLE `mybb_captcha`
  ADD KEY `imagehash` (`imagehash`),
  ADD KEY `dateline` (`dateline`);

--
-- Indexes for table `mybb_datacache`
--
ALTER TABLE `mybb_datacache`
  ADD PRIMARY KEY (`title`);

--
-- Indexes for table `mybb_delayedmoderation`
--
ALTER TABLE `mybb_delayedmoderation`
  ADD PRIMARY KEY (`did`);

--
-- Indexes for table `mybb_events`
--
ALTER TABLE `mybb_events`
  ADD PRIMARY KEY (`eid`),
  ADD KEY `cid` (`cid`),
  ADD KEY `daterange` (`starttime`,`endtime`),
  ADD KEY `private` (`private`);

--
-- Indexes for table `mybb_forumpermissions`
--
ALTER TABLE `mybb_forumpermissions`
  ADD PRIMARY KEY (`pid`),
  ADD KEY `fid` (`fid`,`gid`);

--
-- Indexes for table `mybb_forums`
--
ALTER TABLE `mybb_forums`
  ADD PRIMARY KEY (`fid`);

--
-- Indexes for table `mybb_forumsread`
--
ALTER TABLE `mybb_forumsread`
  ADD UNIQUE KEY `fid` (`fid`,`uid`),
  ADD KEY `dateline` (`dateline`);

--
-- Indexes for table `mybb_forumsubscriptions`
--
ALTER TABLE `mybb_forumsubscriptions`
  ADD PRIMARY KEY (`fsid`),
  ADD KEY `uid` (`uid`);

--
-- Indexes for table `mybb_groupleaders`
--
ALTER TABLE `mybb_groupleaders`
  ADD PRIMARY KEY (`lid`);

--
-- Indexes for table `mybb_helpdocs`
--
ALTER TABLE `mybb_helpdocs`
  ADD PRIMARY KEY (`hid`);

--
-- Indexes for table `mybb_helpsections`
--
ALTER TABLE `mybb_helpsections`
  ADD PRIMARY KEY (`sid`);

--
-- Indexes for table `mybb_icons`
--
ALTER TABLE `mybb_icons`
  ADD PRIMARY KEY (`iid`);

--
-- Indexes for table `mybb_joinrequests`
--
ALTER TABLE `mybb_joinrequests`
  ADD PRIMARY KEY (`rid`);

--
-- Indexes for table `mybb_mailerrors`
--
ALTER TABLE `mybb_mailerrors`
  ADD PRIMARY KEY (`eid`);

--
-- Indexes for table `mybb_maillogs`
--
ALTER TABLE `mybb_maillogs`
  ADD PRIMARY KEY (`mid`);

--
-- Indexes for table `mybb_mailqueue`
--
ALTER TABLE `mybb_mailqueue`
  ADD PRIMARY KEY (`mid`);

--
-- Indexes for table `mybb_massemails`
--
ALTER TABLE `mybb_massemails`
  ADD PRIMARY KEY (`mid`);

--
-- Indexes for table `mybb_moderatorlog`
--
ALTER TABLE `mybb_moderatorlog`
  ADD KEY `uid` (`uid`),
  ADD KEY `fid` (`fid`),
  ADD KEY `tid` (`tid`);

--
-- Indexes for table `mybb_moderators`
--
ALTER TABLE `mybb_moderators`
  ADD PRIMARY KEY (`mid`),
  ADD KEY `uid` (`id`,`fid`);

--
-- Indexes for table `mybb_modtools`
--
ALTER TABLE `mybb_modtools`
  ADD PRIMARY KEY (`tid`);

--
-- Indexes for table `mybb_mycode`
--
ALTER TABLE `mybb_mycode`
  ADD PRIMARY KEY (`cid`);

--
-- Indexes for table `mybb_newpoints_forumrules`
--
ALTER TABLE `mybb_newpoints_forumrules`
  ADD PRIMARY KEY (`rid`);

--
-- Indexes for table `mybb_newpoints_grouprules`
--
ALTER TABLE `mybb_newpoints_grouprules`
  ADD PRIMARY KEY (`rid`);

--
-- Indexes for table `mybb_newpoints_log`
--
ALTER TABLE `mybb_newpoints_log`
  ADD PRIMARY KEY (`lid`);

--
-- Indexes for table `mybb_newpoints_settings`
--
ALTER TABLE `mybb_newpoints_settings`
  ADD PRIMARY KEY (`sid`);

--
-- Indexes for table `mybb_Objetos_Inframundo`
--
ALTER TABLE `mybb_Objetos_Inframundo`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `mybb_op_adviento_abiertos`
--
ALTER TABLE `mybb_op_adviento_abiertos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_day` (`uid`,`dia`,`anio`),
  ADD KEY `idx_uid` (`uid`),
  ADD KEY `idx_anio` (`anio`);

--
-- Indexes for table `mybb_op_afiliados`
--
ALTER TABLE `mybb_op_afiliados`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tipo_orden` (`tipo`,`orden`);

--
-- Indexes for table `mybb_op_afiliados_rate_limit`
--
ALTER TABLE `mybb_op_afiliados_rate_limit`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ip_tiempo` (`ip`,`tiempo`);

--
-- Indexes for table `mybb_op_akumas`
--
ALTER TABLE `mybb_op_akumas`
  ADD PRIMARY KEY (`akuma_id`);

--
-- Indexes for table `mybb_op_auditoria_posts_ia`
--
ALTER TABLE `mybb_op_auditoria_posts_ia`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_pid` (`pid`),
  ADD KEY `idx_uid` (`uid`),
  ADD KEY `idx_dateline` (`dateline`);

--
-- Indexes for table `mybb_op_audit_consola_mod`
--
ALTER TABLE `mybb_op_audit_consola_mod`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `mybb_op_audit_consola_tec`
--
ALTER TABLE `mybb_op_audit_consola_tec`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `mybb_op_audit_consola_tec_mod`
--
ALTER TABLE `mybb_op_audit_consola_tec_mod`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `mybb_op_audit_crafteo`
--
ALTER TABLE `mybb_op_audit_crafteo`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `mybb_op_audit_creacion`
--
ALTER TABLE `mybb_op_audit_creacion`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `mybb_op_audit_descripcion`
--
ALTER TABLE `mybb_op_audit_descripcion`
  ADD PRIMARY KEY (`fid`,`tiempo_editado`);

--
-- Indexes for table `mybb_op_audit_entrenamientos`
--
ALTER TABLE `mybb_op_audit_entrenamientos`
  ADD PRIMARY KEY (`fid`,`tiempo_completado`);

--
-- Indexes for table `mybb_op_audit_entrenamiento_tecnicas`
--
ALTER TABLE `mybb_op_audit_entrenamiento_tecnicas`
  ADD PRIMARY KEY (`fid`,`tiempo_completado`);

--
-- Indexes for table `mybb_op_audit_general`
--
ALTER TABLE `mybb_op_audit_general`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `mybb_op_audit_oficios`
--
ALTER TABLE `mybb_op_audit_oficios`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `mybb_op_audit_recompensas`
--
ALTER TABLE `mybb_op_audit_recompensas`
  ADD PRIMARY KEY (`id`) USING BTREE;

--
-- Indexes for table `mybb_op_audit_stats`
--
ALTER TABLE `mybb_op_audit_stats`
  ADD PRIMARY KEY (`fid`,`tiempo_editado`);

--
-- Indexes for table `mybb_op_avisos`
--
ALTER TABLE `mybb_op_avisos`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `mybb_op_bandas`
--
ALTER TABLE `mybb_op_bandas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nombre` (`nombre`),
  ADD KEY `owner_uid` (`owner_uid`);

--
-- Indexes for table `mybb_op_banda_invitaciones`
--
ALTER TABLE `mybb_op_banda_invitaciones`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_invite` (`banda_id`,`miembro_uid`),
  ADD KEY `miembro_uid` (`miembro_uid`);

--
-- Indexes for table `mybb_op_banda_miembros`
--
ALTER TABLE `mybb_op_banda_miembros`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_member` (`banda_id`,`miembro_uid`),
  ADD KEY `miembro_uid` (`miembro_uid`);

--
-- Indexes for table `mybb_op_barco_acceso`
--
ALTER TABLE `mybb_op_barco_acceso`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `barco_member` (`barco_id`,`owner_uid`,`member_uid`),
  ADD KEY `member_uid` (`member_uid`);

--
-- Indexes for table `mybb_op_barco_cofre`
--
ALTER TABLE `mybb_op_barco_cofre`
  ADD PRIMARY KEY (`id`),
  ADD KEY `barco_owner` (`barco_id`,`owner_uid`);

--
-- Indexes for table `mybb_op_barco_cofre_historial`
--
ALTER TABLE `mybb_op_barco_cofre_historial`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_barco` (`barco_id`(50),`owner_uid`);

--
-- Indexes for table `mybb_op_barco_estado`
--
ALTER TABLE `mybb_op_barco_estado`
  ADD PRIMARY KEY (`barco_id`,`owner_uid`);

--
-- Indexes for table `mybb_op_barco_invitaciones`
--
ALTER TABLE `mybb_op_barco_invitaciones`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unica` (`barco_id`,`owner_uid`,`miembro_uid`);

--
-- Indexes for table `mybb_op_barco_npcs`
--
ALTER TABLE `mybb_op_barco_npcs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unico_ref` (`barco_id`,`owner_uid`,`ref_id`);

--
-- Indexes for table `mybb_op_barco_salas`
--
ALTER TABLE `mybb_op_barco_salas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `barco_slot` (`barco_id`,`owner_uid`,`slot`);

--
-- Indexes for table `mybb_op_barco_tripulacion`
--
ALTER TABLE `mybb_op_barco_tripulacion`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unico_miembro` (`barco_id`,`owner_uid`,`miembro_uid`);

--
-- Indexes for table `mybb_op_cambioid`
--
ALTER TABLE `mybb_op_cambioid`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `id` (`id`);

--
-- Indexes for table `mybb_op_codigos_admin`
--
ALTER TABLE `mybb_op_codigos_admin`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `mybb_op_codigos_usuarios`
--
ALTER TABLE `mybb_op_codigos_usuarios`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `mybb_op_cofres`
--
ALTER TABLE `mybb_op_cofres`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `mybb_op_consumir`
--
ALTER TABLE `mybb_op_consumir`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `mybb_op_costes_akuma`
--
ALTER TABLE `mybb_op_costes_akuma`
  ADD PRIMARY KEY (`dominio`);

--
-- Indexes for table `mybb_op_costes_belica`
--
ALTER TABLE `mybb_op_costes_belica`
  ADD PRIMARY KEY (`slot`);

--
-- Indexes for table `mybb_op_costes_estilo`
--
ALTER TABLE `mybb_op_costes_estilo`
  ADD PRIMARY KEY (`slot`);

--
-- Indexes for table `mybb_op_costes_haki`
--
ALTER TABLE `mybb_op_costes_haki`
  ADD PRIMARY KEY (`nivel`);

--
-- Indexes for table `mybb_op_costes_oficio`
--
ALTER TABLE `mybb_op_costes_oficio`
  ADD PRIMARY KEY (`clave`);

--
-- Indexes for table `mybb_op_crafteo_npcs`
--
ALTER TABLE `mybb_op_crafteo_npcs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `uid` (`uid`),
  ADD KEY `npc_id` (`npc_id`);

--
-- Indexes for table `mybb_op_crafteo_usuarios`
--
ALTER TABLE `mybb_op_crafteo_usuarios`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `mybb_op_creacion_usuarios`
--
ALTER TABLE `mybb_op_creacion_usuarios`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `mybb_op_disciplinas`
--
ALTER TABLE `mybb_op_disciplinas`
  ADD PRIMARY KEY (`nombre`);

--
-- Indexes for table `mybb_op_entrenamientos_usuarios`
--
ALTER TABLE `mybb_op_entrenamientos_usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uid` (`uid`);

--
-- Indexes for table `mybb_op_equipamiento_personaje`
--
ALTER TABLE `mybb_op_equipamiento_personaje`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `mybb_op_estilos_catalogo`
--
ALTER TABLE `mybb_op_estilos_catalogo`
  ADD PRIMARY KEY (`nombre`);

--
-- Indexes for table `mybb_op_experiencia_limite`
--
ALTER TABLE `mybb_op_experiencia_limite`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `mybb_op_facciones`
--
ALTER TABLE `mybb_op_facciones`
  ADD PRIMARY KEY (`nombre`);

--
-- Indexes for table `mybb_op_facciones_rangos`
--
ALTER TABLE `mybb_op_facciones_rangos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `faccion` (`faccion`);

--
-- Indexes for table `mybb_op_fama`
--
ALTER TABLE `mybb_op_fama`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `mybb_op_fichas`
--
ALTER TABLE `mybb_op_fichas`
  ADD PRIMARY KEY (`fid`);

--
-- Indexes for table `mybb_op_fichas_audit`
--
ALTER TABLE `mybb_op_fichas_audit`
  ADD PRIMARY KEY (`audit_id`),
  ADD KEY `idx_fid` (`fid`),
  ADD KEY `idx_changed_at` (`changed_at`);

--
-- Indexes for table `mybb_op_fichas_reset`
--
ALTER TABLE `mybb_op_fichas_reset`
  ADD PRIMARY KEY (`id`),
  ADD KEY `uid` (`uid`),
  ADD KEY `peticion_id` (`peticion_id`),
  ADD KEY `estado` (`estado`);

--
-- Indexes for table `mybb_op_fichas_secret`
--
ALTER TABLE `mybb_op_fichas_secret`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `mybb_op_gacha_aniversario_gratis`
--
ALTER TABLE `mybb_op_gacha_aniversario_gratis`
  ADD PRIMARY KEY (`uid`);

--
-- Indexes for table `mybb_op_gacha_aniversario_gratis_2`
--
ALTER TABLE `mybb_op_gacha_aniversario_gratis_2`
  ADD PRIMARY KEY (`uid`);

--
-- Indexes for table `mybb_op_gacha_aniversario_log`
--
ALTER TABLE `mybb_op_gacha_aniversario_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_banner_id` (`banner`,`id`),
  ADD KEY `idx_uid_banner` (`uid`,`banner`);

--
-- Indexes for table `mybb_op_hentai`
--
ALTER TABLE `mybb_op_hentai`
  ADD PRIMARY KEY (`uid`);

--
-- Indexes for table `mybb_op_hide`
--
ALTER TABLE `mybb_op_hide`
  ADD PRIMARY KEY (`hid`);

--
-- Indexes for table `mybb_op_intercambios`
--
ALTER TABLE `mybb_op_intercambios`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `mybb_op_inventario`
--
ALTER TABLE `mybb_op_inventario`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `objeto_id` (`objeto_id`,`uid`);

--
-- Indexes for table `mybb_op_inventario_crafteo`
--
ALTER TABLE `mybb_op_inventario_crafteo`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `mybb_op_islas`
--
ALTER TABLE `mybb_op_islas`
  ADD PRIMARY KEY (`isla_id`);

--
-- Indexes for table `mybb_op_isla_eventos`
--
ALTER TABLE `mybb_op_isla_eventos`
  ADD PRIMARY KEY (`evento_id`),
  ADD KEY `isla_id` (`isla_id`),
  ADD KEY `staff_uid` (`staff_uid`),
  ADD KEY `cronologico` (`ano`,`estacion`,`dia`);

--
-- Indexes for table `mybb_op_kuros`
--
ALTER TABLE `mybb_op_kuros`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `mybb_op_leidos`
--
ALTER TABLE `mybb_op_leidos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `pid` (`pid`,`uid`);

--
-- Indexes for table `mybb_op_likes`
--
ALTER TABLE `mybb_op_likes`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `mybb_op_mantenidas_html`
--
ALTER TABLE `mybb_op_mantenidas_html`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `pid` (`pid`);

--
-- Indexes for table `mybb_op_mapa_posiciones`
--
ALTER TABLE `mybb_op_mapa_posiciones`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_tid_uid` (`tid`,`uid`),
  ADD KEY `idx_uid` (`uid`),
  ADD KEY `idx_fid` (`fid`);

--
-- Indexes for table `mybb_op_mascotas`
--
ALTER TABLE `mybb_op_mascotas`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `mybb_op_mensajes`
--
ALTER TABLE `mybb_op_mensajes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `uid_dest` (`uid_dest`);

--
-- Indexes for table `mybb_op_misiones_lista`
--
ALTER TABLE `mybb_op_misiones_lista`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `mybb_op_niveles`
--
ALTER TABLE `mybb_op_niveles`
  ADD PRIMARY KEY (`nivel`);

--
-- Indexes for table `mybb_op_niveles_requeridos`
--
ALTER TABLE `mybb_op_niveles_requeridos`
  ADD PRIMARY KEY (`clave`);

--
-- Indexes for table `mybb_op_npcs`
--
ALTER TABLE `mybb_op_npcs`
  ADD PRIMARY KEY (`npc_id`);

--
-- Indexes for table `mybb_op_npcs_usuarios`
--
ALTER TABLE `mybb_op_npcs_usuarios`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `mybb_op_objetos`
--
ALTER TABLE `mybb_op_objetos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `id` (`id`);

--
-- Indexes for table `mybb_op_oficios_catalogo`
--
ALTER TABLE `mybb_op_oficios_catalogo`
  ADD PRIMARY KEY (`nombre`);

--
-- Indexes for table `mybb_op_oficios_usuarios`
--
ALTER TABLE `mybb_op_oficios_usuarios`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `mybb_op_peticionAventuras`
--
ALTER TABLE `mybb_op_peticionAventuras`
  ADD PRIMARY KEY (`id`),
  ADD KEY `uid_created_at` (`uid`,`created_at`),
  ADD KEY `tier_idx` (`tier_seleccionado`),
  ADD KEY `narrador_idx` (`narrador_uid`,`narrador_fid`);

--
-- Indexes for table `mybb_op_peticionAventuras_meta`
--
ALTER TABLE `mybb_op_peticionAventuras_meta`
  ADD PRIMARY KEY (`peticion_id`);

--
-- Indexes for table `mybb_op_peticiones`
--
ALTER TABLE `mybb_op_peticiones`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `mybb_op_plugin_categories`
--
ALTER TABLE `mybb_op_plugin_categories`
  ADD PRIMARY KEY (`codename`);

--
-- Indexes for table `mybb_op_razas`
--
ALTER TABLE `mybb_op_razas`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `mybb_op_recompensas_usuarios`
--
ALTER TABLE `mybb_op_recompensas_usuarios`
  ADD PRIMARY KEY (`id`),
  ADD KEY `uid` (`uid`);

--
-- Indexes for table `mybb_op_sabiasque`
--
ALTER TABLE `mybb_op_sabiasque`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `mybb_op_tecnicas`
--
ALTER TABLE `mybb_op_tecnicas`
  ADD PRIMARY KEY (`tid`);

--
-- Indexes for table `mybb_op_tecnicas_mantenidas`
--
ALTER TABLE `mybb_op_tecnicas_mantenidas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `uid` (`uid`,`tid`,`activa`);

--
-- Indexes for table `mybb_op_tecnicas_usuarios`
--
ALTER TABLE `mybb_op_tecnicas_usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uid` (`uid`);

--
-- Indexes for table `mybb_op_tec_aprendidas`
--
ALTER TABLE `mybb_op_tec_aprendidas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `tid` (`tid`,`uid`);

--
-- Indexes for table `mybb_op_tec_para_aprender`
--
ALTER TABLE `mybb_op_tec_para_aprender`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `tid` (`tid`,`uid`);

--
-- Indexes for table `mybb_op_thread_personaje`
--
ALTER TABLE `mybb_op_thread_personaje`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `tid` (`tid`,`uid`);

--
-- Indexes for table `mybb_op_tiradanaval`
--
ALTER TABLE `mybb_op_tiradanaval`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `mybb_op_tirada_akumas`
--
ALTER TABLE `mybb_op_tirada_akumas`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `mybb_op_tirada_cofre`
--
ALTER TABLE `mybb_op_tirada_cofre`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `mybb_op_tirada_haki`
--
ALTER TABLE `mybb_op_tirada_haki`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `mybb_op_tirada_rey`
--
ALTER TABLE `mybb_op_tirada_rey`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `mybb_op_viajes`
--
ALTER TABLE `mybb_op_viajes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_barco_id` (`barco_id`);

--
-- Indexes for table `mybb_op_virtudes`
--
ALTER TABLE `mybb_op_virtudes`
  ADD PRIMARY KEY (`virtud_id`);

--
-- Indexes for table `mybb_op_virtudes_usuarios`
--
ALTER TABLE `mybb_op_virtudes_usuarios`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `mybb_polls`
--
ALTER TABLE `mybb_polls`
  ADD PRIMARY KEY (`pid`),
  ADD KEY `tid` (`tid`);

--
-- Indexes for table `mybb_pollvotes`
--
ALTER TABLE `mybb_pollvotes`
  ADD PRIMARY KEY (`vid`),
  ADD KEY `pid` (`pid`,`uid`);

--
-- Indexes for table `mybb_posts`
--
ALTER TABLE `mybb_posts`
  ADD PRIMARY KEY (`pid`),
  ADD KEY `tid` (`tid`,`uid`),
  ADD KEY `uid` (`uid`),
  ADD KEY `visible` (`visible`),
  ADD KEY `dateline` (`dateline`),
  ADD KEY `ipaddress` (`ipaddress`),
  ADD KEY `tiddate` (`tid`,`dateline`);
ALTER TABLE `mybb_posts` ADD FULLTEXT KEY `message` (`message`);

--
-- Indexes for table `mybb_privatemessages`
--
ALTER TABLE `mybb_privatemessages`
  ADD PRIMARY KEY (`pmid`),
  ADD KEY `uid` (`uid`,`folder`),
  ADD KEY `toid` (`toid`);

--
-- Indexes for table `mybb_profilefields`
--
ALTER TABLE `mybb_profilefields`
  ADD PRIMARY KEY (`fid`);

--
-- Indexes for table `mybb_promotionlogs`
--
ALTER TABLE `mybb_promotionlogs`
  ADD PRIMARY KEY (`plid`);

--
-- Indexes for table `mybb_promotions`
--
ALTER TABLE `mybb_promotions`
  ADD PRIMARY KEY (`pid`);

--
-- Indexes for table `mybb_questions`
--
ALTER TABLE `mybb_questions`
  ADD PRIMARY KEY (`qid`);

--
-- Indexes for table `mybb_questionsessions`
--
ALTER TABLE `mybb_questionsessions`
  ADD PRIMARY KEY (`sid`);

--
-- Indexes for table `mybb_reportedcontent`
--
ALTER TABLE `mybb_reportedcontent`
  ADD PRIMARY KEY (`rid`),
  ADD KEY `reportstatus` (`reportstatus`),
  ADD KEY `lastreport` (`lastreport`);

--
-- Indexes for table `mybb_reportreasons`
--
ALTER TABLE `mybb_reportreasons`
  ADD PRIMARY KEY (`rid`);

--
-- Indexes for table `mybb_reputation`
--
ALTER TABLE `mybb_reputation`
  ADD PRIMARY KEY (`rid`),
  ADD KEY `uid` (`uid`);

--
-- Indexes for table `mybb_rtchat`
--
ALTER TABLE `mybb_rtchat`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `mybb_rtchat_bans`
--
ALTER TABLE `mybb_rtchat_bans`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uid` (`uid`);

--
-- Indexes for table `mybb_rt_discord_webhooks`
--
ALTER TABLE `mybb_rt_discord_webhooks`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `mybb_rt_discord_webhooks_logs`
--
ALTER TABLE `mybb_rt_discord_webhooks_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `mybb_searchlog`
--
ALTER TABLE `mybb_searchlog`
  ADD PRIMARY KEY (`sid`);

--
-- Indexes for table `mybb_sessions`
--
ALTER TABLE `mybb_sessions`
  ADD PRIMARY KEY (`sid`),
  ADD KEY `location` (`location1`,`location2`),
  ADD KEY `time` (`time`),
  ADD KEY `uid` (`uid`),
  ADD KEY `ip` (`ip`);

--
-- Indexes for table `mybb_settinggroups`
--
ALTER TABLE `mybb_settinggroups`
  ADD PRIMARY KEY (`gid`);

--
-- Indexes for table `mybb_settings`
--
ALTER TABLE `mybb_settings`
  ADD PRIMARY KEY (`sid`),
  ADD KEY `gid` (`gid`);

--
-- Indexes for table `mybb_smilies`
--
ALTER TABLE `mybb_smilies`
  ADD PRIMARY KEY (`sid`);

--
-- Indexes for table `mybb_spamlog`
--
ALTER TABLE `mybb_spamlog`
  ADD PRIMARY KEY (`sid`);

--
-- Indexes for table `mybb_spiders`
--
ALTER TABLE `mybb_spiders`
  ADD PRIMARY KEY (`sid`);

--
-- Indexes for table `mybb_stats`
--
ALTER TABLE `mybb_stats`
  ADD PRIMARY KEY (`dateline`);

--
-- Indexes for table `mybb_tasklog`
--
ALTER TABLE `mybb_tasklog`
  ADD PRIMARY KEY (`lid`);

--
-- Indexes for table `mybb_tasks`
--
ALTER TABLE `mybb_tasks`
  ADD PRIMARY KEY (`tid`);

--
-- Indexes for table `mybb_templategroups`
--
ALTER TABLE `mybb_templategroups`
  ADD PRIMARY KEY (`gid`);

--
-- Indexes for table `mybb_templates`
--
ALTER TABLE `mybb_templates`
  ADD PRIMARY KEY (`tid`),
  ADD KEY `sid` (`sid`,`title`);

--
-- Indexes for table `mybb_templatesets`
--
ALTER TABLE `mybb_templatesets`
  ADD PRIMARY KEY (`sid`);

--
-- Indexes for table `mybb_themes`
--
ALTER TABLE `mybb_themes`
  ADD PRIMARY KEY (`tid`);

--
-- Indexes for table `mybb_themestylesheets`
--
ALTER TABLE `mybb_themestylesheets`
  ADD PRIMARY KEY (`sid`),
  ADD KEY `tid` (`tid`);

--
-- Indexes for table `mybb_threadprefixes`
--
ALTER TABLE `mybb_threadprefixes`
  ADD PRIMARY KEY (`pid`);

--
-- Indexes for table `mybb_threadratings`
--
ALTER TABLE `mybb_threadratings`
  ADD PRIMARY KEY (`rid`),
  ADD KEY `tid` (`tid`,`uid`);

--
-- Indexes for table `mybb_threads`
--
ALTER TABLE `mybb_threads`
  ADD PRIMARY KEY (`tid`),
  ADD KEY `fid` (`fid`,`visible`,`sticky`),
  ADD KEY `dateline` (`dateline`),
  ADD KEY `lastpost` (`lastpost`,`fid`),
  ADD KEY `firstpost` (`firstpost`),
  ADD KEY `uid` (`uid`);
ALTER TABLE `mybb_threads` ADD FULLTEXT KEY `subject` (`subject`);

--
-- Indexes for table `mybb_threadsread`
--
ALTER TABLE `mybb_threadsread`
  ADD UNIQUE KEY `tid` (`tid`,`uid`),
  ADD KEY `dateline` (`dateline`);

--
-- Indexes for table `mybb_threadsubscriptions`
--
ALTER TABLE `mybb_threadsubscriptions`
  ADD PRIMARY KEY (`sid`),
  ADD KEY `uid` (`uid`),
  ADD KEY `tid` (`tid`,`notification`);

--
-- Indexes for table `mybb_threadviews`
--
ALTER TABLE `mybb_threadviews`
  ADD KEY `tid` (`tid`);

--
-- Indexes for table `mybb_userfields`
--
ALTER TABLE `mybb_userfields`
  ADD PRIMARY KEY (`ufid`);

--
-- Indexes for table `mybb_usergroups`
--
ALTER TABLE `mybb_usergroups`
  ADD PRIMARY KEY (`gid`);

--
-- Indexes for table `mybb_users`
--
ALTER TABLE `mybb_users`
  ADD PRIMARY KEY (`uid`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `usergroup` (`usergroup`),
  ADD KEY `regip` (`regip`),
  ADD KEY `lastip` (`lastip`);

--
-- Indexes for table `mybb_usertitles`
--
ALTER TABLE `mybb_usertitles`
  ADD PRIMARY KEY (`utid`);

--
-- Indexes for table `mybb_warninglevels`
--
ALTER TABLE `mybb_warninglevels`
  ADD PRIMARY KEY (`lid`);

--
-- Indexes for table `mybb_warnings`
--
ALTER TABLE `mybb_warnings`
  ADD PRIMARY KEY (`wid`),
  ADD KEY `uid` (`uid`);

--
-- Indexes for table `mybb_warningtypes`
--
ALTER TABLE `mybb_warningtypes`
  ADD PRIMARY KEY (`tid`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `mybb_adminviews`
--
ALTER TABLE `mybb_adminviews`
  MODIFY `vid` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mybb_announcements`
--
ALTER TABLE `mybb_announcements`
  MODIFY `aid` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mybb_attachments`
--
ALTER TABLE `mybb_attachments`
  MODIFY `aid` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mybb_attachtypes`
--
ALTER TABLE `mybb_attachtypes`
  MODIFY `atid` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mybb_audit_op_fichas`
--
ALTER TABLE `mybb_audit_op_fichas`
  MODIFY `id` int(10) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mybb_audit_users`
--
ALTER TABLE `mybb_audit_users`
  MODIFY `id` int(10) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mybb_awaitingactivation`
--
ALTER TABLE `mybb_awaitingactivation`
  MODIFY `aid` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mybb_badwords`
--
ALTER TABLE `mybb_badwords`
  MODIFY `bid` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mybb_banfilters`
--
ALTER TABLE `mybb_banfilters`
  MODIFY `fid` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mybb_buddyrequests`
--
ALTER TABLE `mybb_buddyrequests`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mybb_calendars`
--
ALTER TABLE `mybb_calendars`
  MODIFY `cid` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mybb_delayedmoderation`
--
ALTER TABLE `mybb_delayedmoderation`
  MODIFY `did` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mybb_events`
--
ALTER TABLE `mybb_events`
  MODIFY `eid` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mybb_forumpermissions`
--
ALTER TABLE `mybb_forumpermissions`
  MODIFY `pid` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mybb_forums`
--
ALTER TABLE `mybb_forums`
  MODIFY `fid` smallint(5) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mybb_forumsubscriptions`
--
ALTER TABLE `mybb_forumsubscriptions`
  MODIFY `fsid` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mybb_groupleaders`
--
ALTER TABLE `mybb_groupleaders`
  MODIFY `lid` smallint(5) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mybb_helpdocs`
--
ALTER TABLE `mybb_helpdocs`
  MODIFY `hid` smallint(5) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mybb_helpsections`
--
ALTER TABLE `mybb_helpsections`
  MODIFY `sid` smallint(5) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mybb_icons`
--
ALTER TABLE `mybb_icons`
  MODIFY `iid` smallint(5) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mybb_joinrequests`
--
ALTER TABLE `mybb_joinrequests`
  MODIFY `rid` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mybb_mailerrors`
--
ALTER TABLE `mybb_mailerrors`
  MODIFY `eid` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mybb_maillogs`
--
ALTER TABLE `mybb_maillogs`
  MODIFY `mid` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mybb_mailqueue`
--
ALTER TABLE `mybb_mailqueue`
  MODIFY `mid` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mybb_massemails`
--
ALTER TABLE `mybb_massemails`
  MODIFY `mid` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mybb_moderators`
--
ALTER TABLE `mybb_moderators`
  MODIFY `mid` smallint(5) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mybb_modtools`
--
ALTER TABLE `mybb_modtools`
  MODIFY `tid` smallint(5) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mybb_mycode`
--
ALTER TABLE `mybb_mycode`
  MODIFY `cid` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mybb_newpoints_forumrules`
--
ALTER TABLE `mybb_newpoints_forumrules`
  MODIFY `rid` bigint(30) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mybb_newpoints_grouprules`
--
ALTER TABLE `mybb_newpoints_grouprules`
  MODIFY `rid` bigint(30) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mybb_newpoints_log`
--
ALTER TABLE `mybb_newpoints_log`
  MODIFY `lid` bigint(30) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mybb_newpoints_settings`
--
ALTER TABLE `mybb_newpoints_settings`
  MODIFY `sid` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mybb_Objetos_Inframundo`
--
ALTER TABLE `mybb_Objetos_Inframundo`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mybb_op_adviento_abiertos`
--
ALTER TABLE `mybb_op_adviento_abiertos`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mybb_op_afiliados`
--
ALTER TABLE `mybb_op_afiliados`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mybb_op_afiliados_rate_limit`
--
ALTER TABLE `mybb_op_afiliados_rate_limit`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mybb_op_auditoria_posts_ia`
--
ALTER TABLE `mybb_op_auditoria_posts_ia`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mybb_op_audit_consola_mod`
--
ALTER TABLE `mybb_op_audit_consola_mod`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mybb_op_audit_consola_tec`
--
ALTER TABLE `mybb_op_audit_consola_tec`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mybb_op_audit_consola_tec_mod`
--
ALTER TABLE `mybb_op_audit_consola_tec_mod`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mybb_op_audit_crafteo`
--
ALTER TABLE `mybb_op_audit_crafteo`
  MODIFY `id` int(10) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mybb_op_audit_creacion`
--
ALTER TABLE `mybb_op_audit_creacion`
  MODIFY `id` int(10) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mybb_op_audit_general`
--
ALTER TABLE `mybb_op_audit_general`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mybb_op_audit_oficios`
--
ALTER TABLE `mybb_op_audit_oficios`
  MODIFY `id` int(10) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mybb_op_audit_recompensas`
--
ALTER TABLE `mybb_op_audit_recompensas`
  MODIFY `id` int(10) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mybb_op_avisos`
--
ALTER TABLE `mybb_op_avisos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mybb_op_bandas`
--
ALTER TABLE `mybb_op_bandas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mybb_op_banda_invitaciones`
--
ALTER TABLE `mybb_op_banda_invitaciones`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mybb_op_banda_miembros`
--
ALTER TABLE `mybb_op_banda_miembros`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mybb_op_barco_acceso`
--
ALTER TABLE `mybb_op_barco_acceso`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mybb_op_barco_cofre`
--
ALTER TABLE `mybb_op_barco_cofre`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mybb_op_barco_cofre_historial`
--
ALTER TABLE `mybb_op_barco_cofre_historial`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mybb_op_barco_invitaciones`
--
ALTER TABLE `mybb_op_barco_invitaciones`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mybb_op_barco_npcs`
--
ALTER TABLE `mybb_op_barco_npcs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mybb_op_barco_salas`
--
ALTER TABLE `mybb_op_barco_salas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mybb_op_barco_tripulacion`
--
ALTER TABLE `mybb_op_barco_tripulacion`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mybb_op_cambioid`
--
ALTER TABLE `mybb_op_cambioid`
  MODIFY `id` int(10) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mybb_op_codigos_admin`
--
ALTER TABLE `mybb_op_codigos_admin`
  MODIFY `id` int(100) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mybb_op_codigos_usuarios`
--
ALTER TABLE `mybb_op_codigos_usuarios`
  MODIFY `id` int(100) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mybb_op_cofres`
--
ALTER TABLE `mybb_op_cofres`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mybb_op_consumir`
--
ALTER TABLE `mybb_op_consumir`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mybb_op_crafteo_npcs`
--
ALTER TABLE `mybb_op_crafteo_npcs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mybb_op_crafteo_usuarios`
--
ALTER TABLE `mybb_op_crafteo_usuarios`
  MODIFY `id` int(100) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mybb_op_creacion_usuarios`
--
ALTER TABLE `mybb_op_creacion_usuarios`
  MODIFY `id` int(100) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mybb_op_entrenamientos_usuarios`
--
ALTER TABLE `mybb_op_entrenamientos_usuarios`
  MODIFY `id` int(100) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mybb_op_equipamiento_personaje`
--
ALTER TABLE `mybb_op_equipamiento_personaje`
  MODIFY `id` int(10) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mybb_op_experiencia_limite`
--
ALTER TABLE `mybb_op_experiencia_limite`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mybb_op_facciones_rangos`
--
ALTER TABLE `mybb_op_facciones_rangos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mybb_op_fama`
--
ALTER TABLE `mybb_op_fama`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mybb_op_fichas_audit`
--
ALTER TABLE `mybb_op_fichas_audit`
  MODIFY `audit_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mybb_op_fichas_reset`
--
ALTER TABLE `mybb_op_fichas_reset`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mybb_op_fichas_secret`
--
ALTER TABLE `mybb_op_fichas_secret`
  MODIFY `id` int(10) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mybb_op_gacha_aniversario_log`
--
ALTER TABLE `mybb_op_gacha_aniversario_log`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mybb_op_hide`
--
ALTER TABLE `mybb_op_hide`
  MODIFY `hid` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mybb_op_intercambios`
--
ALTER TABLE `mybb_op_intercambios`
  MODIFY `id` int(10) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mybb_op_inventario`
--
ALTER TABLE `mybb_op_inventario`
  MODIFY `id` int(3) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mybb_op_inventario_crafteo`
--
ALTER TABLE `mybb_op_inventario_crafteo`
  MODIFY `id` int(3) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mybb_op_isla_eventos`
--
ALTER TABLE `mybb_op_isla_eventos`
  MODIFY `evento_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mybb_op_kuros`
--
ALTER TABLE `mybb_op_kuros`
  MODIFY `id` int(10) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mybb_op_leidos`
--
ALTER TABLE `mybb_op_leidos`
  MODIFY `id` int(10) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mybb_op_likes`
--
ALTER TABLE `mybb_op_likes`
  MODIFY `id` int(100) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mybb_op_mantenidas_html`
--
ALTER TABLE `mybb_op_mantenidas_html`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mybb_op_mapa_posiciones`
--
ALTER TABLE `mybb_op_mapa_posiciones`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mybb_op_mascotas`
--
ALTER TABLE `mybb_op_mascotas`
  MODIFY `id` int(10) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mybb_op_mensajes`
--
ALTER TABLE `mybb_op_mensajes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mybb_op_misiones_lista`
--
ALTER TABLE `mybb_op_misiones_lista`
  MODIFY `id` int(100) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mybb_op_npcs_usuarios`
--
ALTER TABLE `mybb_op_npcs_usuarios`
  MODIFY `id` int(10) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mybb_op_objetos`
--
ALTER TABLE `mybb_op_objetos`
  MODIFY `id` int(10) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mybb_op_oficios_usuarios`
--
ALTER TABLE `mybb_op_oficios_usuarios`
  MODIFY `id` int(100) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mybb_op_peticionAventuras`
--
ALTER TABLE `mybb_op_peticionAventuras`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mybb_op_peticiones`
--
ALTER TABLE `mybb_op_peticiones`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mybb_op_razas`
--
ALTER TABLE `mybb_op_razas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mybb_op_recompensas_usuarios`
--
ALTER TABLE `mybb_op_recompensas_usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mybb_op_sabiasque`
--
ALTER TABLE `mybb_op_sabiasque`
  MODIFY `id` int(10) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mybb_op_tecnicas_mantenidas`
--
ALTER TABLE `mybb_op_tecnicas_mantenidas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mybb_op_tecnicas_usuarios`
--
ALTER TABLE `mybb_op_tecnicas_usuarios`
  MODIFY `id` int(100) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mybb_op_tec_aprendidas`
--
ALTER TABLE `mybb_op_tec_aprendidas`
  MODIFY `id` int(10) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mybb_op_tec_para_aprender`
--
ALTER TABLE `mybb_op_tec_para_aprender`
  MODIFY `id` int(10) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mybb_op_thread_personaje`
--
ALTER TABLE `mybb_op_thread_personaje`
  MODIFY `id` int(10) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mybb_op_tiradanaval`
--
ALTER TABLE `mybb_op_tiradanaval`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mybb_op_tirada_akumas`
--
ALTER TABLE `mybb_op_tirada_akumas`
  MODIFY `id` int(10) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mybb_op_tirada_cofre`
--
ALTER TABLE `mybb_op_tirada_cofre`
  MODIFY `id` int(10) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mybb_op_tirada_haki`
--
ALTER TABLE `mybb_op_tirada_haki`
  MODIFY `id` int(10) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mybb_op_tirada_rey`
--
ALTER TABLE `mybb_op_tirada_rey`
  MODIFY `id` int(10) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mybb_op_viajes`
--
ALTER TABLE `mybb_op_viajes`
  MODIFY `id` int(10) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mybb_op_virtudes_usuarios`
--
ALTER TABLE `mybb_op_virtudes_usuarios`
  MODIFY `id` int(100) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mybb_polls`
--
ALTER TABLE `mybb_polls`
  MODIFY `pid` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mybb_pollvotes`
--
ALTER TABLE `mybb_pollvotes`
  MODIFY `vid` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mybb_posts`
--
ALTER TABLE `mybb_posts`
  MODIFY `pid` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mybb_privatemessages`
--
ALTER TABLE `mybb_privatemessages`
  MODIFY `pmid` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mybb_profilefields`
--
ALTER TABLE `mybb_profilefields`
  MODIFY `fid` smallint(5) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mybb_promotionlogs`
--
ALTER TABLE `mybb_promotionlogs`
  MODIFY `plid` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mybb_promotions`
--
ALTER TABLE `mybb_promotions`
  MODIFY `pid` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mybb_questions`
--
ALTER TABLE `mybb_questions`
  MODIFY `qid` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mybb_reportedcontent`
--
ALTER TABLE `mybb_reportedcontent`
  MODIFY `rid` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mybb_reportreasons`
--
ALTER TABLE `mybb_reportreasons`
  MODIFY `rid` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mybb_reputation`
--
ALTER TABLE `mybb_reputation`
  MODIFY `rid` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mybb_rtchat`
--
ALTER TABLE `mybb_rtchat`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mybb_rtchat_bans`
--
ALTER TABLE `mybb_rtchat_bans`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mybb_rt_discord_webhooks`
--
ALTER TABLE `mybb_rt_discord_webhooks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mybb_rt_discord_webhooks_logs`
--
ALTER TABLE `mybb_rt_discord_webhooks_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mybb_settinggroups`
--
ALTER TABLE `mybb_settinggroups`
  MODIFY `gid` smallint(5) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mybb_settings`
--
ALTER TABLE `mybb_settings`
  MODIFY `sid` smallint(5) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mybb_smilies`
--
ALTER TABLE `mybb_smilies`
  MODIFY `sid` smallint(5) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mybb_spamlog`
--
ALTER TABLE `mybb_spamlog`
  MODIFY `sid` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mybb_spiders`
--
ALTER TABLE `mybb_spiders`
  MODIFY `sid` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mybb_tasklog`
--
ALTER TABLE `mybb_tasklog`
  MODIFY `lid` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mybb_tasks`
--
ALTER TABLE `mybb_tasks`
  MODIFY `tid` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mybb_templategroups`
--
ALTER TABLE `mybb_templategroups`
  MODIFY `gid` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mybb_templates`
--
ALTER TABLE `mybb_templates`
  MODIFY `tid` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mybb_templatesets`
--
ALTER TABLE `mybb_templatesets`
  MODIFY `sid` smallint(5) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mybb_themes`
--
ALTER TABLE `mybb_themes`
  MODIFY `tid` smallint(5) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mybb_themestylesheets`
--
ALTER TABLE `mybb_themestylesheets`
  MODIFY `sid` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mybb_threadprefixes`
--
ALTER TABLE `mybb_threadprefixes`
  MODIFY `pid` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mybb_threadratings`
--
ALTER TABLE `mybb_threadratings`
  MODIFY `rid` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mybb_threads`
--
ALTER TABLE `mybb_threads`
  MODIFY `tid` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mybb_threadsubscriptions`
--
ALTER TABLE `mybb_threadsubscriptions`
  MODIFY `sid` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mybb_usergroups`
--
ALTER TABLE `mybb_usergroups`
  MODIFY `gid` smallint(5) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mybb_users`
--
ALTER TABLE `mybb_users`
  MODIFY `uid` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mybb_usertitles`
--
ALTER TABLE `mybb_usertitles`
  MODIFY `utid` smallint(5) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mybb_warninglevels`
--
ALTER TABLE `mybb_warninglevels`
  MODIFY `lid` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mybb_warnings`
--
ALTER TABLE `mybb_warnings`
  MODIFY `wid` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mybb_warningtypes`
--
ALTER TABLE `mybb_warningtypes`
  MODIFY `tid` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
