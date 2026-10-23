CREATE DATABASE IF NOT EXISTS ecoboard_sti CHARACTER SET utf8mb4;
USE ecoboard_sti;
CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(80) NOT NULL, email VARCHAR(120) NOT NULL UNIQUE,
  student_no VARCHAR(40) NULL, password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin','technician','student') NOT NULL DEFAULT 'student', active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE labs (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(80) NOT NULL UNIQUE);
CREATE TABLE assets (
  id INT AUTO_INCREMENT PRIMARY KEY, tag VARCHAR(40) NOT NULL UNIQUE,
  type ENUM('desktop','laptop','monitor','keyboard','mouse','printer','network','projector','other') NOT NULL,
  make_model VARCHAR(100), lab_id INT NULL,
  status ENUM('in_use','needs_repair','in_repair','stored','donated_reused','recycled','disposed') NOT NULL DEFAULT 'in_use',
  condition_score TINYINT NOT NULL DEFAULT 5, purchase_date DATE NULL, lifespan_years DECIMAL(4,1) NOT NULL DEFAULT 5,
  power_w DECIMAL(7,1) NOT NULL DEFAULT 0, hours_per_day DECIMAL(4,1) NOT NULL DEFAULT 4, weight_kg DECIMAL(7,2) NOT NULL DEFAULT 0,
  embodied_kg_co2e DECIMAL(8,1) NULL, notes VARCHAR(255), created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (lab_id) REFERENCES labs(id) ON DELETE SET NULL);
CREATE TABLE asset_events (
  id INT AUTO_INCREMENT PRIMARY KEY, asset_id INT NOT NULL, status VARCHAR(30), condition_score TINYINT,
  note VARCHAR(255), user_id INT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE CASCADE);
CREATE TABLE reports (
  id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, type ENUM('ewaste','issue','plastic') NOT NULL,
  title VARCHAR(120) NOT NULL, lab_id INT NULL, asset_id INT NULL, quantity INT NOT NULL DEFAULT 1, description TEXT,
  status ENUM('open','in_review','resolved','rejected') NOT NULL DEFAULT 'open', response TEXT NULL, handled_by INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, resolved_at TIMESTAMP NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (lab_id) REFERENCES labs(id) ON DELETE SET NULL,
  FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE SET NULL);
CREATE TABLE feedback (
  id INT AUTO_INCREMENT PRIMARY KEY, report_id INT NOT NULL UNIQUE, user_id INT NOT NULL, rating TINYINT NOT NULL,
  comment VARCHAR(500), created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (report_id) REFERENCES reports(id) ON DELETE CASCADE);
CREATE TABLE plastic_log (
  id INT AUTO_INCREMENT PRIMARY KEY, item ENUM('bottle_caps','bottles','other') NOT NULL, qty INT NOT NULL,
  weight_kg DECIMAL(8,3) NOT NULL DEFAULT 0, lab_id INT NULL, note VARCHAR(150), user_id INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE board_messages (id INT AUTO_INCREMENT PRIMARY KEY, message VARCHAR(60) NOT NULL, active TINYINT(1) DEFAULT 1, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE interactions (id INT AUTO_INCREMENT PRIMARY KEY, type ENUM('touch','voice','chat','qr') NOT NULL, detail VARCHAR(255), created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP);

INSERT INTO labs (name) VALUES ('Computer Lab 1'),('Computer Lab 2'),('Storage Room'),('Library');
INSERT INTO board_messages (message) VALUES ('REDUCE E-WASTE'),('CAPS TO ART'),('REUSE REPAIR RECYCLE');
INSERT INTO assets (tag,type,make_model,lab_id,status,condition_score,purchase_date,lifespan_years,power_w,hours_per_day,weight_kg) VALUES
('PC-001','desktop','Dell OptiPlex 3020',1,'in_use',4,'2020-02-10',6,120,5,9.5),
('PC-002','desktop','HP ProDesk 400',1,'needs_repair',2,'2018-09-01',6,120,5,9.0),
('PC-003','desktop','Lenovo ThinkCentre',2,'in_use',3,'2017-05-20',6,120,5,8.5),
('MON-001','monitor','Dell 19in',1,'in_use',4,'2020-02-10',8,30,5,4.0),
('MON-002','monitor','LG 19in CRT',3,'stored',1,'2008-03-01',8,80,0,14.0),
('KB-014','keyboard','Generic USB',1,'recycled',1,'2016-01-15',5,0,0,0.6),
('LT-005','laptop','Acer Aspire',4,'donated_reused',3,'2019-07-01',5,45,3,2.2);
INSERT INTO plastic_log (item,qty,weight_kg,lab_id,note) VALUES ('bottle_caps',420,0.84,NULL,'Campus cafe drive'),('bottles',60,1.5,NULL,'Hostel collection');
