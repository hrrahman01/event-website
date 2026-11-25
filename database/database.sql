-- =====================================================
-- Event Management System: database schema
-- Safe to re-import: tables use IF NOT EXISTS and seed data uses INSERT IGNORE
-- =====================================================
CREATE DATABASE IF NOT EXISTS event_management
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE event_management;

-- ---------- admins ----------
CREATE TABLE IF NOT EXISTS admins (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  full_name     VARCHAR(100) NOT NULL,
  username      VARCHAR(50)  NOT NULL,
  email         VARCHAR(120) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role          ENUM('super_admin','admin') NOT NULL DEFAULT 'admin',
  last_login    DATETIME NULL,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uqAdminUsername (username),
  UNIQUE KEY uqAdminEmail (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- registrations ----------
CREATE TABLE IF NOT EXISTS registrations (
  id                    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  registration_id       VARCHAR(30)  NOT NULL,
  title                 VARCHAR(20)  NOT NULL,
  first_name            VARCHAR(60)  NOT NULL,
  last_name             VARCHAR(60)  NOT NULL,
  email                 VARCHAR(120) NOT NULL,
  phone                 VARCHAR(30)  NOT NULL,
  country               VARCHAR(80)  NOT NULL,
  organization          VARCHAR(150) NOT NULL,
  designation           VARCHAR(100) NOT NULL,
  registration_category VARCHAR(40)  NOT NULL,
  registration_tier     VARCHAR(12) DEFAULT NULL,
  participant_count      SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  participation_type    ENUM('In-person','Virtual') NOT NULL,
  dietary_requirements  VARCHAR(150) NULL,
  message               TEXT NULL,
  consent_at            DATETIME NOT NULL,
  status                ENUM('Pending','Confirmed','Rejected') NOT NULL DEFAULT 'Pending',
  created_at            TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at            TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uqRegistrationId (registration_id),
  KEY idxRegEmail (email),
  KEY idxRegStatus (status),
  KEY idxRegCountry (country),
  KEY idxRegCategory (registration_category),
  KEY idxRegCreated (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- abstracts ----------
CREATE TABLE IF NOT EXISTS abstracts (
  id                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  abstract_id          VARCHAR(30)  NOT NULL,
  title                VARCHAR(20)  NOT NULL,
  first_name           VARCHAR(60)  NOT NULL,
  last_name            VARCHAR(60)  NOT NULL,
  email                VARCHAR(120) NOT NULL,
  phone                VARCHAR(30)  NOT NULL,
  country              VARCHAR(80)  NOT NULL,
  organization         VARCHAR(150) NOT NULL,
  designation          VARCHAR(100) NOT NULL,
  abstract_title       VARCHAR(200) NOT NULL,
  research_track       VARCHAR(80)  NOT NULL,
  presentation_type    VARCHAR(40)  NOT NULL,
  co_authors           TEXT NULL,
  abstract_description MEDIUMTEXT NOT NULL,
  keywords             VARCHAR(200) NOT NULL,
  original_filename    VARCHAR(255) NOT NULL,
  stored_filename      VARCHAR(100) NOT NULL,
  file_mime_type       VARCHAR(100) NOT NULL,
  file_size            INT UNSIGNED NOT NULL,
  reviewer_notes       TEXT NULL,
  consent_at           DATETIME NOT NULL,
  status               ENUM('Pending','Under Review','Accepted','Rejected','Revision Required') NOT NULL DEFAULT 'Pending',
  created_at           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uqAbstractId (abstract_id),
  UNIQUE KEY uqStoredFilename (stored_filename),
  KEY idxAbsEmail (email),
  KEY idxAbsStatus (status),
  KEY idxAbsTrack (research_track),
  KEY idxAbsCreated (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- event_settings ----------
CREATE TABLE IF NOT EXISTS event_settings (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  setting_key   VARCHAR(60) NOT NULL,
  setting_value TEXT NULL,
  updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uqSettingKey (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- hero_slides ----------
CREATE TABLE IF NOT EXISTS hero_slides (
  id                    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  heading               VARCHAR(150) NOT NULL,
  subheading            VARCHAR(200) NULL,
  description           TEXT NULL,
  image_path            VARCHAR(255) NULL,
  primary_button_text   VARCHAR(50) NULL,
  secondary_button_text VARCHAR(50) NULL,
  display_order         TINYINT UNSIGNED NOT NULL DEFAULT 1,
  is_active             TINYINT(1) NOT NULL DEFAULT 1,
  created_at            TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at            TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idxSlideOrder (display_order, is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- activity_logs ----------
CREATE TABLE IF NOT EXISTS activity_logs (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  admin_id    INT UNSIGNED NULL,
  action      VARCHAR(60)  NOT NULL,
  entity_type VARCHAR(40)  NOT NULL,
  entity_id   VARCHAR(40)  NULL,
  description VARCHAR(255) NULL,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idxLogAdmin (admin_id),
  KEY idxLogCreated (created_at),
  CONSTRAINT fkLogAdmin FOREIGN KEY (admin_id) REFERENCES admins (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- rate_limits (login and public form throttling) ----------
CREATE TABLE IF NOT EXISTS rate_limits (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  rate_key   VARCHAR(100) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idxRateKey (rate_key, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Default content (editable later from the admin panel)
-- =====================================================
INSERT IGNORE INTO event_settings (setting_key, setting_value) VALUES
  ('event_name',            'Global Science Conference 2027'),
  ('event_theme',           'Innovation, Collaboration and the Future of Research'),
  ('event_logo',            ''),
  ('event_start_date',      '2027-03-12'),
  ('event_end_date',        '2027-03-14'),
  ('venue',                 'Venue Name'),
  ('city_country',          'City, Country'),
  ('registration_deadline', '2027-02-28'),
  ('abstract_deadline',     '2026-12-31'),
  ('contact_email',         'info@example.com'),
  ('contact_phone',         '+00 000 000 0000'),
  ('registration_open',     '1'),
  ('abstract_open',         '1'),
  ('abstract_word_limit',   '300');

INSERT IGNORE INTO hero_slides
  (id, heading, subheading, description, image_path, primary_button_text, secondary_button_text, display_order, is_active) VALUES
  (1, 'Global Science Conference 2027', 'International Conference',
      'Theme: Innovation, Collaboration and the Future of Research',
      'assets/images/hero/slide-1.webp', 'Register Now', 'Submit Abstract', 1, 1),
  (2, 'Share Your Research With the World', 'Call for Abstracts',
      'Present your findings to leading scientists and researchers. Oral, poster and virtual presentations are welcome.',
      'assets/images/hero/slide-2.webp', 'Submit Your Abstract', '', 2, 1),
  (3, 'Join the Global Conference', 'Registration Open',
      'Connect with peers, attend keynote sessions and be part of an international scientific community.',
      'assets/images/hero/slide-3.webp', 'Register Now', '', 3, 1);
