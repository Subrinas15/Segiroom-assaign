-- DATABASE USE
CREATE DATABASE IF NOT EXISTS segi_room_assign;
USE segi_room_assign;

-- 1) Buildings
CREATE TABLE buildings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  code VARCHAR(20) NOT NULL
);

-- 2) Rooms
CREATE TABLE rooms (
  id INT AUTO_INCREMENT PRIMARY KEY,
  building_id INT NOT NULL,
  level VARCHAR(10) NOT NULL,
  room_label VARCHAR(50) NOT NULL,  -- e.g. 'Lab 4D'
  capacity INT NOT NULL DEFAULT 30,
  room_type ENUM('Lecture','Lab','Tutorial') DEFAULT 'Lecture',
  FOREIGN KEY (building_id) REFERENCES buildings(id)
);

-- 3) Bookings (ekhane clash check hobe)
CREATE TABLE bookings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  room_id INT NOT NULL,
  date DATE NOT NULL,
  start_time TIME NOT NULL,
  end_time TIME NOT NULL,
  title VARCHAR(150) NOT NULL,      -- class name / subject
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (room_id) REFERENCES rooms(id)
);

-- Sample data (just test er jonno)
INSERT INTO buildings (name, code) VALUES
('Main Building', 'MAIN'),
('North Block', 'NORTH');

INSERT INTO rooms (building_id, level, room_label, capacity, room_type) VALUES
(1, 'L4', 'Lab 4A', 35, 'Lab'),
(1, 'L4', 'Lab 4B', 35, 'Lab'),
(1, 'L4', 'Lab 4C', 35, 'Lab'),
(1, 'L4', 'Lab 4D', 35, 'Lab'),      -- ei ta tumi example dilo
(2, 'L2', 'Room 2A', 40, 'Lecture'),
(2, 'L2', 'Room 2B', 40, 'Lecture');
