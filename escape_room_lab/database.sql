-- Maak de database aan als deze nog niet bestaat
CREATE DATABASE IF NOT EXISTS escape_room_lab;

-- Selecteer de database
USE escape_room_lab;

-- Schakel foreign key checks uit
SET FOREIGN_KEY_CHECKS = 0;

-- Verwijder bestaande tabellen
DROP TABLE IF EXISTS solved_puzzles;
DROP TABLE IF EXISTS team_members;
DROP TABLE IF EXISTS puzzles;
DROP TABLE IF EXISTS rooms;
DROP TABLE IF EXISTS teams;
DROP TABLE IF EXISTS users;

-- Schakel foreign key checks weer in
SET FOREIGN_KEY_CHECKS = 1;

-- Maak users tabel
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) NOT NULL,
    role ENUM('user', 'admin') DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Voeg admin toe met PLAIN wachtwoord
INSERT INTO users (username, password, email, role) VALUES 
('admin', 'admin123', 'admin@voorbeeld.nl', 'admin');

-- Maak teams tabel
CREATE TABLE teams (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    created_by INT,
    start_time INT NULL,
    current_room INT NULL,
    escape_time INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Maak team_members tabel (ontbrekende tabel)
CREATE TABLE team_members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    team_id INT NOT NULL,
    user_id INT NOT NULL,
    is_captain BOOLEAN DEFAULT FALSE,
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_member (team_id, user_id)
);

-- Maak rooms tabel
CREATE TABLE rooms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT NOT NULL,
    room_style VARCHAR(20) NOT NULL,
    order_num INT NOT NULL DEFAULT 1
);

-- Maak puzzles tabel
CREATE TABLE puzzles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    emoji VARCHAR(10) NOT NULL,
    position_top INT NOT NULL,
    position_left INT NOT NULL,
    options TEXT NOT NULL,
    correct_answer VARCHAR(20) NOT NULL,
    max_attempts INT DEFAULT 2,
    order_num INT NOT NULL DEFAULT 1,
    FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE
);

-- Maak solved_puzzles tabel
CREATE TABLE solved_puzzles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    team_id INT NOT NULL,
    puzzle_id INT NOT NULL,
    attempts INT DEFAULT 1,
    solved BOOLEAN DEFAULT FALSE,
    solved_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_solve (team_id, puzzle_id),
    FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
    FOREIGN KEY (puzzle_id) REFERENCES puzzles(id) ON DELETE CASCADE
);

-- Voeg kamers toe
INSERT INTO rooms (id, name, description, room_style, order_num) VALUES
(1, 'Laboratorium', 'Een hightech laboratorium met diverse chemische stoffen en apparatuur. Ontdek de wetenschappelijke geheimen.', 'modern-lab', 1),
(2, 'Controleruimte', 'De centrale controleruimte met computersystemen. De uitgang is vergrendeld met wetenschappelijke codes.', 'control-room', 2);

-- Voeg wetenschappelijke puzzels toe
INSERT INTO puzzles (room_id, title, description, emoji, position_top, position_left, options, correct_answer, max_attempts, order_num) VALUES
(1, 'Chemische Test', 'Je ziet een reeks gekleurde vloeistoffen. Welke vloeistof geeft een groene kleur aan een vlam?', '🧪', 40, 20, '{"A":"Rood (Lithiumchloride)","B":"Groen (Koperchloride)","C":"Paars (Kaliumchloride)","D":"Geel (Natriumchloride)"}', 'B', 2, 1),
(1, 'Materiaalonderzoek', 'Op het computerscherm zie je een analyse van metalen. Welke vloeistof kan aluminium verzwakken bij kamertemperatuur?', '💻', 35, 65, '{"A":"Water","B":"Alcohol","C":"Gallium","D":"Azijnzuur"}', 'C', 2, 2),
(2, 'Chemische Reactie', 'Op een post-it bij de kluis staat: "Element dat heftig reageert met water". Welk element is dit?', '🔒', 60, 25, '{"A":"Natrium (Na)","B":"Zuurstof (O)","C":"Helium (He)","D":"IJzer (Fe)"}', 'A', 2, 1),
(2, 'Labwaarden', 'Het controlepaneel vraagt om de exacte temperatuur waarop water kookt op zeeniveau.', '🎛️', 30, 70, '{"A":"0°C","B":"100°C","C":"50°C","D":"200°C"}', 'B', 2, 2);
