-- Schema and seed data for the ATC Flight Strip Console.
-- The MySQL entrypoint runs this file the first time the data volume is empty.

CREATE DATABASE IF NOT EXISTS atcdb
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE atcdb;

CREATE TABLE IF NOT EXISTS controllers (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  username      VARCHAR(50)  NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  full_name     VARCHAR(100) NOT NULL,
  reg_no        VARCHAR(30)  NOT NULL,
  position      VARCHAR(40)  NOT NULL,
  licence_no    VARCHAR(30)  NOT NULL,
  created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS flight_strips (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  controller_id INT          NOT NULL,
  callsign      VARCHAR(12)  NOT NULL,
  aircraft_type VARCHAR(12)      NULL,
  origin        CHAR(4)      NOT NULL,
  destination   CHAR(4)      NOT NULL,
  flight_level  SMALLINT         NULL,
  status        ENUM('SCHEDULED','INBOUND','HOLDING','CLEARED','DEPARTED','LANDED')
                NOT NULL DEFAULT 'SCHEDULED',
  remarks       VARCHAR(255)     NULL,
  filed_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_strip_controller FOREIGN KEY (controller_id)
    REFERENCES controllers (id) ON DELETE CASCADE,
  INDEX idx_strip_callsign (callsign),
  INDEX idx_strip_status (status)
) ENGINE=InnoDB;

-- Seeded positions. Passwords are stored as SHA-256 hashes.
INSERT IGNORE INTO controllers
  (id, username, password_hash, full_name, reg_no, position, licence_no) VALUES
  (1, 'huzaima', SHA2('Fa23Bds017', 256), 'Huzaima Imtiaz', 'FA23-BDS-017',
      'Tower (TWR)', 'PK-ATC-4417'),
  (2, 'qasim',   SHA2('Devops2026', 256), 'Qasim Malik',    'INSTRUCTOR',
      'Watch Supervisor', 'PK-ATC-0001');

-- Sample traffic. All of this is fictional.
INSERT IGNORE INTO flight_strips
  (id, controller_id, callsign, aircraft_type, origin, destination,
   flight_level, status, remarks) VALUES
  (1, 1, 'PK309',  'A320', 'OPIS', 'OPKC', 350, 'CLEARED',
      'Cleared direct BAKAR, expect runway 28L'),
  (2, 1, 'EK614',  'B777', 'OMDB', 'OPIS', 380, 'INBOUND',
      'Descend FL180, contact approach at 40 miles'),
  (3, 1, 'QR632',  'A350', 'OTHH', 'OPLA', 370, 'HOLDING',
      'Holding at SAMAR due to traffic sequencing'),
  (4, 2, 'ABQ201', 'AT72', 'OPIS', 'OPPS', 160, 'DEPARTED',
      'Airborne 0412Z, handed to area control'),
  (5, 1, 'PA402',  'B738', 'OPKC', 'OPIS', 330, 'SCHEDULED',
      'Slot 0630Z, strip filed by tower'),
  (6, 1, 'TK710',  'B789', 'LTFM', 'OPIS', 400, 'LANDED',
      'Vacated via taxiway C, stand 14');

-- Application user (also created from MYSQL_USER). mysql_native_password is
-- used so that older MySQL clients can connect without a plugin error.
CREATE USER IF NOT EXISTS 'atcapp'@'%'
  IDENTIFIED WITH mysql_native_password BY 'Atcapp_2026';
ALTER USER 'atcapp'@'%'
  IDENTIFIED WITH mysql_native_password BY 'Atcapp_2026';
GRANT SELECT, INSERT, UPDATE, DELETE ON atcdb.* TO 'atcapp'@'%';

-- Read-only user for verification of the database integration.
CREATE USER IF NOT EXISTS 'readonly'@'%'
  IDENTIFIED WITH mysql_native_password BY 'Readonly_2026';
ALTER USER 'readonly'@'%'
  IDENTIFIED WITH mysql_native_password BY 'Readonly_2026';
GRANT SELECT ON atcdb.* TO 'readonly'@'%';

FLUSH PRIVILEGES;
