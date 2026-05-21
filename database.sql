-- EchoShield AI — full schema (import via phpMyAdmin)

CREATE DATABASE IF NOT EXISTS echoshield_ai;
USE echoshield_ai;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    phone VARCHAR(20) NOT NULL,
    password VARCHAR(255) NOT NULL,
    status VARCHAR(20) DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS alerts (
    alert_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    alert_message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS harassers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    harasser_name VARCHAR(100) NOT NULL UNIQUE,
    total_violations INT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS reports (
    report_id INT AUTO_INCREMENT PRIMARY KEY,
    victim_id INT NOT NULL,
    harasser_name VARCHAR(100) NOT NULL,
    harasser_phone VARCHAR(30) DEFAULT '',
    harmful_message TEXT NOT NULL,
    toxicity_score FLOAT DEFAULT 0,
    severity_level VARCHAR(20) NOT NULL DEFAULT 'SAFE',
    risk_level VARCHAR(20) NOT NULL DEFAULT 'SAFE',
    screenshot VARCHAR(255) DEFAULT '',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (victim_id) REFERENCES users(id) ON DELETE CASCADE
);
