-- Create database (optional)
CREATE DATABASE IF NOT EXISTS smartwell;
USE smartwell;

-- =========================
-- Table: user
-- =========================
CREATE TABLE `user` (
  `UserID` varchar(5) NOT NULL,
  `UserName` varchar(100) NOT NULL,
  `Password` varchar(255) NOT NULL,
  `Email` varchar(100) NOT NULL,
  `DateOfBirth` date NOT NULL,
  `Gender` varchar(10) NOT NULL,
  `ProfilePic` longtext DEFAULT NULL,
  `PhoneNo` varchar(20) NOT NULL,
  `CreatedAt` datetime NOT NULL,
  `qr_token` varchar(255) DEFAULT NULL,
  `reset_token_hash` varchar(64) DEFAULT NULL,
  `reset_token_expires_at` datetime DEFAULT NULL,
  `Role` varchar(10) DEFAULT 'user',
  `qr_code_path` longblob DEFAULT NULL,
  `RememberMe` varchar(255) DEFAULT NULL,
  `last_login` datetime DEFAULT NULL,
  PRIMARY KEY (`UserID`),
  UNIQUE KEY `UserName` (`UserName`),
  UNIQUE KEY `Email` (`Email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================
-- Table: bmi_record
-- =========================
CREATE TABLE `bmi_record` (
  `bmiID` varchar(5) NOT NULL,
  `UserID` varchar(5) NOT NULL,
  `weight` double NOT NULL,
  `height` double NOT NULL,
  `bmi` double NOT NULL,
  `detectedAt` datetime NOT NULL,
  PRIMARY KEY (`bmiID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;

-- =========================
-- Table: health_record
-- =========================
CREATE TABLE `health_record` (
  `RecordID` varchar(5) NOT NULL,
  `UserID` varchar(5) NOT NULL,
  `riskLevel` varchar(255) NOT NULL,
  `DetectedAt` datetime NOT NULL,
  PRIMARY KEY (`RecordID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;

-- =========================
-- Table: product_record
-- =========================
CREATE TABLE `product_record` (
  `ProductID` varchar(5) NOT NULL,
  `UserID` varchar(5) NOT NULL,
  `ProductScore` varchar(255) NOT NULL,
  `DetectedAt` datetime NOT NULL,
  `ProductImage` longtext NOT NULL,
  PRIMARY KEY (`ProductID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;

-- =========================
-- Table: qr_login_requests
-- =========================
CREATE TABLE `qr_login_requests` (
  `id` int NOT NULL AUTO_INCREMENT,
  `qr_token` varchar(64) NOT NULL,
  `user_id` varchar(5) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `confirmed` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- =========================
-- Table: remembertoken
-- =========================
CREATE TABLE `remembertoken` (
  `token` varchar(255) NOT NULL,
  `UserID` varchar(5) NOT NULL,
  `Expires` datetime NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;
