-- ClubConnect database schema and fictional sample data
-- Compatible with MySQL/MariaDB in XAMPP/phpMyAdmin.

CREATE DATABASE IF NOT EXISTS clubconnect
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE clubconnect;

CREATE TABLE IF NOT EXISTS users (
  user_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  first_name VARCHAR(80) NOT NULL,
  last_name VARCHAR(80) NOT NULL,
  email VARCHAR(190) NOT NULL,
  student_id VARCHAR(40) NULL,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('student', 'admin') NOT NULL DEFAULT 'student',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id),
  UNIQUE KEY uq_users_email (email),
  UNIQUE KEY uq_users_student_id (student_id),
  KEY idx_users_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS clubs (
  club_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(150) NOT NULL,
  category VARCHAR(80) NOT NULL,
  description TEXT NOT NULL,
  contact_email VARCHAR(190) NULL,
  contact_phone VARCHAR(30) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (club_id),
  UNIQUE KEY uq_clubs_name (name),
  KEY idx_clubs_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS events (
  event_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  club_id BIGINT UNSIGNED NOT NULL,
  title VARCHAR(180) NOT NULL,
  description TEXT NOT NULL,
  event_date DATE NOT NULL,
  event_time TIME NOT NULL,
  venue VARCHAR(180) NOT NULL,
  capacity SMALLINT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (event_id),
  UNIQUE KEY uq_events_club_title_date (club_id, title, event_date),
  KEY idx_events_club_id (club_id),
  KEY idx_events_date (event_date),
  CONSTRAINT fk_events_club
    FOREIGN KEY (club_id) REFERENCES clubs (club_id)
    ON UPDATE CASCADE
    ON DELETE RESTRICT,
  CONSTRAINT chk_events_capacity CHECK (capacity > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS club_members (
  club_member_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  club_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  joined_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (club_member_id),
  UNIQUE KEY uq_club_membership (club_id, user_id),
  KEY idx_club_members_user_id (user_id),
  CONSTRAINT fk_club_members_club
    FOREIGN KEY (club_id) REFERENCES clubs (club_id)
    ON UPDATE CASCADE
    ON DELETE CASCADE,
  CONSTRAINT fk_club_members_user
    FOREIGN KEY (user_id) REFERENCES users (user_id)
    ON UPDATE CASCADE
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS event_registrations (
  registration_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  event_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  status ENUM('registered', 'attended', 'cancelled') NOT NULL DEFAULT 'registered',
  registered_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (registration_id),
  UNIQUE KEY uq_event_registration (event_id, user_id),
  KEY idx_event_registrations_user_id (user_id),
  KEY idx_event_registrations_status (status),
  CONSTRAINT fk_event_registrations_event
    FOREIGN KEY (event_id) REFERENCES events (event_id)
    ON UPDATE CASCADE
    ON DELETE CASCADE,
  CONSTRAINT fk_event_registrations_user
    FOREIGN KEY (user_id) REFERENCES users (user_id)
    ON UPDATE CASCADE
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Fictional sample users. Passwords are stored only as PHP password_hash() bcrypt values.
INSERT INTO users
  (first_name, last_name, email, student_id, password_hash, role)
VALUES
  ('Alex', 'Mwangi', 'alex.mwangi@example.test', 'CC-1042', '$2y$10$DS6fwDixEQauQYPrgZsJUu4EoileWzrQsCJGwZe7FOlMNagWlFAvm', 'student'),
  ('Lisa', 'Otieno', 'lisa.otieno@example.test', 'CC-1043', '$2y$10$DS6fwDixEQauQYPrgZsJUu4EoileWzrQsCJGwZe7FOlMNagWlFAvm', 'student'),
  ('Sarah', 'Njeri', 'sarah.njeri@example.test', NULL, '$2y$10$8EyQVoP/uTd2nuDD2tCUKuPUYIr13OoXV6HIYNm5QP65mjeCXQKFG', 'admin')
ON DUPLICATE KEY UPDATE
  first_name = VALUES(first_name),
  last_name = VALUES(last_name),
  student_id = VALUES(student_id),
  password_hash = VALUES(password_hash),
  role = VALUES(role);

INSERT INTO clubs
  (name, category, description, contact_email, contact_phone)
VALUES
  ('Creative Minds', 'Creative', 'A welcoming space for storytellers, artists and imaginative thinkers.', 'creative.minds@example.test', '+254700000101'),
  ('Tech Innovators', 'Academic', 'Building practical solutions for tomorrow''s challenges.', 'tech.innovators@example.test', '+254700000102'),
  ('Wellness Circle', 'Social', 'A kinder campus starts with taking care of ourselves.', 'wellness.circle@example.test', '+254700000103'),
  ('Debate Society', 'Academic', 'A forum for research, public speaking and respectful argument.', 'debate.society@example.test', '+254700000104')
ON DUPLICATE KEY UPDATE
  category = VALUES(category),
  description = VALUES(description),
  contact_email = VALUES(contact_email),
  contact_phone = VALUES(contact_phone);

INSERT INTO events
  (club_id, title, description, event_date, event_time, venue, capacity)
SELECT c.club_id, sample.title, sample.description, sample.event_date, sample.event_time, sample.venue, sample.capacity
FROM clubs AS c
JOIN (
  SELECT 'Debate Society' AS club_name, 'Interfaculty Debate Night' AS title,
         'An evening of lively debate, new perspectives and thoughtful conversation.' AS description,
         '2026-10-10' AS event_date, '17:30:00' AS event_time, 'Main Auditorium' AS venue, 104 AS capacity
  UNION ALL
  SELECT 'Tech Innovators', 'Design Thinking Workshop',
         'Turn a real campus challenge into a human-centred solution.',
         '2026-10-12', '14:00:00', 'Innovation Hub', 30
  UNION ALL
  SELECT 'Wellness Circle', 'Saturday Football Social',
         'Good teams, good energy and no experience required.',
         '2026-10-17', '10:00:00', 'Sports Grounds', 60
) AS sample ON sample.club_name = c.name
ON DUPLICATE KEY UPDATE
  description = VALUES(description),
  event_date = VALUES(event_date),
  event_time = VALUES(event_time),
  venue = VALUES(venue),
  capacity = VALUES(capacity);

INSERT IGNORE INTO club_members (club_id, user_id)
SELECT c.club_id, u.user_id
FROM clubs AS c
JOIN users AS u ON u.email = 'alex.mwangi@example.test'
WHERE c.name = 'Creative Minds';

INSERT IGNORE INTO club_members (club_id, user_id)
SELECT c.club_id, u.user_id
FROM clubs AS c
JOIN users AS u ON u.email = 'lisa.otieno@example.test'
WHERE c.name = 'Tech Innovators';

INSERT IGNORE INTO event_registrations (event_id, user_id, status)
SELECT e.event_id, u.user_id, sample.status
FROM events AS e
JOIN users AS u ON u.email = sample.email
JOIN (
  SELECT 'Interfaculty Debate Night' AS title, 'alex.mwangi@example.test' AS email, 'registered' AS status
  UNION ALL
  SELECT 'Design Thinking Workshop', 'lisa.otieno@example.test', 'registered'
  UNION ALL
  SELECT 'Saturday Football Social', 'alex.mwangi@example.test', 'attended'
) AS sample ON sample.title = e.title;
