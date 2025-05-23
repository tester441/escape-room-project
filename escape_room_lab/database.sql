-- Create database
CREATE DATABASE IF NOT EXISTS escape_room_lab;
USE escape_room_lab;

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    is_admin TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Teams table
CREATE TABLE IF NOT EXISTS teams (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    created_by INT NOT NULL,
    escape_time INT NULL,
    start_time INT NULL,
    current_room INT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
);

-- Team members table
CREATE TABLE IF NOT EXISTS team_members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    team_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE
);

-- Rooms table
CREATE TABLE IF NOT EXISTS rooms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT NOT NULL,
    background_image VARCHAR(255) NOT NULL,
    order_num INT NOT NULL DEFAULT 1
);

-- Questions table
CREATE TABLE IF NOT EXISTS questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_id INT NOT NULL,
    question TEXT NOT NULL,
    answer VARCHAR(255) NOT NULL,
    hint TEXT NULL,
    order_num INT NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE
);

-- Solved Questions table
CREATE TABLE IF NOT EXISTS solved_questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    team_id INT NOT NULL,
    question_id INT NOT NULL,
    solved_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_solve (team_id, question_id),
    FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE
);

-- Insert default admin user (password: admin123 without hashing)
INSERT INTO users (username, password, email, is_admin) VALUES 
('admin', 'admin123', 'admin@example.com', 1);

-- Insert rooms
INSERT INTO rooms (name, description, background_image, order_num) VALUES
('Entreehal', 'De entree van het mysterieuze laboratorium. Je hebt de deur achter je horen dichtslaan en op slot gaan. Probeer een manier te vinden om verder te komen.', 'https://images.unsplash.com/photo-1580587771525-78b9dba3b914?ixlib=rb-1.2.1&auto=format&fit=crop&w=1350&q=80', 1),
('Chemisch Lab', 'Een ruimte vol met kolven, reageerbuizen en vreemde vloeistoffen. Wees voorzichtig met wat je aanraakt!', 'https://images.unsplash.com/photo-1532094349884-543bc11b234d?ixlib=rb-1.2.1&auto=format&fit=crop&w=1350&q=80', 2),
('Medische Kamer', 'Een kamer met medische apparatuur, microscopen en vreemde specimens op sterk water.', 'https://images.unsplash.com/photo-1516549655669-94e804e149e6?ixlib=rb-1.2.1&auto=format&fit=crop&w=1350&q=80', 3),
('Controleruimte', 'De centrale besturingsruimte van het laboratorium met computers en monitoren. Hier moet je de uitgang kunnen activeren.', 'https://images.unsplash.com/photo-1558494949-ef010cbdcc31?ixlib=rb-1.2.1&auto=format&fit=crop&w=1350&q=80', 4);

-- Insert sample questions
INSERT INTO questions (room_id, question, answer, hint, order_num) VALUES
-- Entreehal vragen
(1, 'Op de muur staat een raadsel: "Ik heb steden, maar geen huizen. Ik heb bossen, maar geen bomen. Ik heb rivieren, maar geen water. Wat ben ik?" Wat is het antwoord?', 'Kaart', 'Je gebruikt het om je weg te vinden.', 1),
(1, 'Je vindt een kluis met een cijferslot. Op de muur staat "Wortel van 144 + 5". Welk getal moet je invoeren?', '17', 'Wortel van 144 is 12, plus 5 is 17.', 2),

-- Chemisch lab vragen
(2, 'Op een whiteboard staan chemische elementen: "Na + Cl". Welke stof wordt hiermee bedoeld?', 'Zout', 'Het is iets wat je dagelijks gebruikt bij het koken.', 1),
(2, 'Je moet de juiste kleurcode invoeren om een kast te openen. Op een notitie staat "De kleur van water + de kleur van bloed". Wat is het antwoord?', 'Blauwrood', 'Denk aan de basiskleuren van deze substanties.', 2),

-- Medische kamer vragen
(3, 'Op een medicijnfles staat "Wat is het grootste orgaan van het menselijk lichaam?" Wat is het antwoord?', 'Huid', 'Het bedekt je hele lichaam.', 1),
(3, 'Je moet de hartslag van een patiënt invoeren om toegang te krijgen tot een medicijnkast. Op een notitie staat "Normale hartslag in rust van een volwassene". Welk getal voer je in?', '70', 'Het ligt meestal tussen 60 en 80 slagen per minuut.', 2),

-- Controleruimte vragen
(4, 'Om het systeem te ontgrendelen moet je het wachtwoord invoeren. Een hint op het scherm zegt "De achternaam van de beroemde wetenschapper die de relativiteitstheorie formuleerde".', 'Einstein', 'E=mc²', 1),
(4, 'Je hebt alle codes verzameld. Nu moet je de sleutelcode invoeren om de deur te ontgrendelen. De code is het jaartal waarin het periodiek systeem der elementen werd gepubliceerd door Dmitri Mendelejev.', '1869', 'Het was in de tweede helft van de 19e eeuw.', 2);
