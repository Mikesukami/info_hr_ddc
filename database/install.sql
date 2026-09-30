-- Fresh installation only. Import this ONE file using phpMyAdmin.
CREATE DATABASE IF NOT EXISTS study_leave CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE study_leave;
-- Select/create the database with utf8mb4_general_ci before importing this file.
-- Schema v2: org hierarchy and country references. Fresh installation only; never import over existing tables.
SET NAMES utf8mb4 COLLATE utf8mb4_general_ci;

CREATE TABLE users (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 username VARCHAR(80) NOT NULL UNIQUE,
 name VARCHAR(200) NOT NULL,
 position VARCHAR(200) NOT NULL,
 phone VARCHAR(30) NOT NULL,
 password_hash VARCHAR(255) NOT NULL,
 role ENUM('superAdmin','admin','officer') NOT NULL DEFAULT 'officer',
 state ENUM('pending','approved','rejected','disabled') NOT NULL DEFAULT 'pending',
 must_change_password TINYINT(1) NOT NULL DEFAULT 0,
 session_version INT UNSIGNED NOT NULL DEFAULT 1,
 approved_by BIGINT UNSIGNED NULL,
 approved_at DATETIME NULL,
 created_by BIGINT UNSIGNED NULL,
 updated_by BIGINT UNSIGNED NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(created_by) REFERENCES users(id),
 FOREIGN KEY(updated_by) REFERENCES users(id),
 FOREIGN KEY(approved_by) REFERENCES users(id),
 INDEX ix_users_state(state,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE activity_types (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(120) NOT NULL UNIQUE,
 form_kind ENUM('study','course') NOT NULL,
 active TINYINT(1) NOT NULL DEFAULT 1,
 created_by BIGINT UNSIGNED NULL, updated_by BIGINT UNSIGNED NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(created_by) REFERENCES users(id), FOREIGN KEY(updated_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE reference_values (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 group_name VARCHAR(50) NOT NULL,
 label VARCHAR(200) NOT NULL,
 parent_id BIGINT UNSIGNED NULL COMMENT 'หน่วยงานแม่ เฉพาะกลุ่ม org; NULL คือสำนัก/กอง',
 org_depth TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0=สำนัก/กอง, 1-5=ระดับหน่วยงานย่อย',
 scope_parent_id BIGINT UNSIGNED GENERATED ALWAYS AS (COALESCE(parent_id,0)) STORED,
 active TINYINT(1) NOT NULL DEFAULT 1,
 created_by BIGINT UNSIGNED NULL, updated_by BIGINT UNSIGNED NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_reference(group_name,scope_parent_id,label),
 FOREIGN KEY(parent_id) REFERENCES reference_values(id),
 CHECK ((parent_id IS NULL AND org_depth=0) OR (group_name='org' AND parent_id IS NOT NULL AND org_depth BETWEEN 1 AND 5)),
 INDEX ix_reference_parent(parent_id,active),
 FOREIGN KEY(created_by) REFERENCES users(id), FOREIGN KEY(updated_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE leave_cases (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 type_id BIGINT UNSIGNED NOT NULL,
 person_name VARCHAR(200) NOT NULL,
 org_id BIGINT UNSIGNED NOT NULL,
 personnel_type_id BIGINT UNSIGNED NOT NULL,
 position_id BIGINT UNSIGNED NOT NULL,
 level_id BIGINT UNSIGNED NOT NULL,
 destination ENUM('domestic','foreign') NOT NULL,
 country_id BIGINT UNSIGNED NULL,
 fund VARCHAR(200) NOT NULL DEFAULT '',
 degree VARCHAR(200) NOT NULL DEFAULT '',
 branch VARCHAR(200) NOT NULL DEFAULT '',
 faculty VARCHAR(200) NOT NULL DEFAULT '',
 institution VARCHAR(200) NOT NULL DEFAULT '',
 course VARCHAR(200) NOT NULL DEFAULT '',
 location VARCHAR(200) NOT NULL DEFAULT '',
 note TEXT NOT NULL,
 revision INT UNSIGNED NOT NULL DEFAULT 1,
 created_by BIGINT UNSIGNED NOT NULL, updated_by BIGINT UNSIGNED NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(type_id) REFERENCES activity_types(id),
 FOREIGN KEY(org_id) REFERENCES reference_values(id),
 FOREIGN KEY(country_id) REFERENCES reference_values(id),
 FOREIGN KEY(personnel_type_id) REFERENCES reference_values(id),
 FOREIGN KEY(position_id) REFERENCES reference_values(id),
 FOREIGN KEY(level_id) REFERENCES reference_values(id),
 FOREIGN KEY(created_by) REFERENCES users(id), FOREIGN KEY(updated_by) REFERENCES users(id),
 CHECK (destination <> 'foreign' OR country_id IS NOT NULL),
 INDEX ix_cases_person(person_name), INDEX ix_cases_created(created_at,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE record_entries (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'เลขลำดับแถวทะเบียน No. ระบบออกให้ ไม่รับจากฟอร์ม',
 case_id BIGINT UNSIGNED NOT NULL,
 kind ENUM('period','return','cancel') NOT NULL,
 ordinal INT UNSIGNED NULL,
 fiscal_year SMALLINT UNSIGNED NOT NULL,
 start_date DATE NULL,
 end_date DATE NULL,
 action_date DATE NULL,
 document_no VARCHAR(200) NOT NULL,
 document_date DATE NOT NULL,
 received_date DATE NOT NULL,
 sender VARCHAR(200) NOT NULL,
 note TEXT NOT NULL,
 terminal_case_id BIGINT UNSIGNED GENERATED ALWAYS AS (CASE WHEN kind IN ('return','cancel') THEN case_id ELSE NULL END) STORED,
 created_by BIGINT UNSIGNED NOT NULL, updated_by BIGINT UNSIGNED NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(case_id) REFERENCES leave_cases(id),
 FOREIGN KEY(created_by) REFERENCES users(id), FOREIGN KEY(updated_by) REFERENCES users(id),
 UNIQUE KEY uq_period_ordinal(case_id,ordinal),
 UNIQUE KEY uq_terminal(terminal_case_id),
 CHECK(fiscal_year BETWEEN 2400 AND 2800),
 CHECK((kind='period' AND ordinal IS NOT NULL AND start_date IS NOT NULL AND end_date IS NOT NULL AND start_date<=end_date AND action_date IS NULL) OR (kind IN('return','cancel') AND ordinal IS NULL AND action_date IS NOT NULL AND start_date IS NULL AND end_date IS NULL)),
 INDEX ix_entries_year(fiscal_year,id), INDEX ix_entries_document(document_no), INDEX ix_entries_received(received_date,id), INDEX ix_entries_end(end_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE audit_logs (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 action VARCHAR(100) NOT NULL,
 entity_type VARCHAR(60) NOT NULL,
 entity_id BIGINT UNSIGNED NULL,
 description VARCHAR(500) NOT NULL,
 before_data LONGTEXT NULL,
 after_data LONGTEXT NULL,
 ip_address VARCHAR(45) NOT NULL,
 created_by BIGINT UNSIGNED NULL, updated_by BIGINT UNSIGNED NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(created_by) REFERENCES users(id), FOREIGN KEY(updated_by) REFERENCES users(id),
 INDEX ix_audit_time(created_at,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE auth_limits (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 bucket_hash CHAR(64) NOT NULL UNIQUE,
 hits INT UNSIGNED NOT NULL DEFAULT 1,
 window_start DATETIME NOT NULL,
 created_by BIGINT UNSIGNED NULL, updated_by BIGINT UNSIGNED NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(created_by) REFERENCES users(id), FOREIGN KEY(updated_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE password_reset_requests (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id BIGINT UNSIGNED NOT NULL,
 state ENUM('pending','issued','closed') NOT NULL DEFAULT 'pending',
 created_by BIGINT UNSIGNED NULL, updated_by BIGINT UNSIGNED NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(user_id) REFERENCES users(id),
 FOREIGN KEY(created_by) REFERENCES users(id), FOREIGN KEY(updated_by) REFERENCES users(id),
 INDEX ix_reset_request(state,user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE password_reset_tokens (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id BIGINT UNSIGNED NOT NULL,
 token_hash CHAR(64) NOT NULL UNIQUE,
 expires_at DATETIME NOT NULL,
 used_at DATETIME NULL,
 created_by BIGINT UNSIGNED NULL, updated_by BIGINT UNSIGNED NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(user_id) REFERENCES users(id),
 FOREIGN KEY(created_by) REFERENCES users(id), FOREIGN KEY(updated_by) REFERENCES users(id),
 INDEX ix_reset_user(user_id,used_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

START TRANSACTION;
INSERT INTO users(id,username,name,position,phone,password_hash,role,state,must_change_password) VALUES(1,'superadmin','ผู้ดูแลระบบสูงสุด','ผู้ดูแลระบบ','','$2y$10$RVujC83aL.Ft3bWSVQ/25eue3JBlnf0cBdJhI1aTEFM1N5FxReOmS','superAdmin','approved',1);
UPDATE users SET created_by=1,updated_by=1,approved_by=1,approved_at=NOW() WHERE id=1;
INSERT INTO activity_types(name,form_kind,created_by,updated_by) VALUES('ลาศึกษา','study',1,1),('ฝึกอบรม','course',1,1);
INSERT INTO reference_values(group_name,label,created_by,updated_by) VALUES('org','สำนักงานเลขานุการกรม',1,1);
INSERT INTO reference_values(group_name,label,created_by,updated_by) VALUES('org','สำนักงานคณะกรรมการผู้ทรงคุณวุฒิ',1,1);
INSERT INTO reference_values(group_name,label,created_by,updated_by) VALUES('org','สำนักงานความร่วมมือระหว่างประเทศ',1,1);
INSERT INTO reference_values(group_name,label,created_by,updated_by) VALUES('org','สำนักงานบริหารโครงการกองทุนโลก',1,1);
INSERT INTO reference_values(group_name,label,created_by,updated_by) VALUES('org','สำนักงานเลขานุการคณะกรรมการโครงการพระราชดำริฯ',1,1);
INSERT INTO reference_values(group_name,label,created_by,updated_by) VALUES('org','กลุ่มงานจริยธรรม',1,1);
INSERT INTO reference_values(group_name,label,created_by,updated_by) VALUES('org','กองยุทธศาสตร์และแผนงาน',1,1);
INSERT INTO reference_values(group_name,label,created_by,updated_by) VALUES('org','กองบริหารทรัพยากรบุคคล',1,1);
INSERT INTO reference_values(group_name,label,created_by,updated_by) VALUES('org','กองบริหารการคลัง',1,1);
INSERT INTO reference_values(group_name,label,created_by,updated_by) VALUES('org','กองกฎหมาย',1,1);
INSERT INTO reference_values(group_name,label,created_by,updated_by) VALUES('org','กองระบาดวิทยา',1,1);
INSERT INTO reference_values(group_name,label,created_by,updated_by) VALUES('org','กองโรคติดต่อทั่วไป',1,1);
INSERT INTO reference_values(group_name,label,created_by,updated_by) VALUES('org','กองโรคไม่ติดต่อ',1,1);
INSERT INTO reference_values(group_name,label,created_by,updated_by) VALUES('org','กองโรคเอดส์และโรคติดต่อทางเพศสัมพันธ์',1,1);
INSERT INTO reference_values(group_name,label,created_by,updated_by) VALUES('org','กองโรคติดต่อนำโดยแมลง',1,1);
INSERT INTO reference_values(group_name,label,created_by,updated_by) VALUES('org','กองวัณโรค',1,1);
INSERT INTO reference_values(group_name,label,created_by,updated_by) VALUES('org','กองโรคจากการประกอบอาชีพและสิ่งแวดล้อม',1,1);
INSERT INTO reference_values(group_name,label,created_by,updated_by) VALUES('org','กองควบคุมโรคและภัยสุขภาพในภาวะฉุกเฉิน',1,1);
INSERT INTO reference_values(group_name,label,created_by,updated_by) VALUES('org','กองป้องกันการบาดเจ็บ',1,1);
INSERT INTO reference_values(group_name,label,created_by,updated_by) VALUES('org','กองดิจิทัลเพื่อการควบคุมโรค',1,1);
INSERT INTO reference_values(group_name,label,created_by,updated_by) VALUES('org','กองนวัตกรรมและวิจัย',1,1);
INSERT INTO reference_values(group_name,label,created_by,updated_by) VALUES('org','กองด่านควบคุมโรคติดต่อระหว่างประเทศและกักกันโรค',1,1);
INSERT INTO reference_values(group_name,label,created_by,updated_by) VALUES('org','กองสื่อสารความเสี่ยงและพัฒนาพฤติกรรมสุขภาพ',1,1);
INSERT INTO reference_values(group_name,label,created_by,updated_by) VALUES('org','กองงานคณะกรรมการควบคุมผลิตภัณฑ์ยาสูบ',1,1);
INSERT INTO reference_values(group_name,label,created_by,updated_by) VALUES('org','สำนักงานคณะกรรมการควบคุมเครื่องดื่มแอลกอฮอล์',1,1);
INSERT INTO reference_values(group_name,label,created_by,updated_by) VALUES('org','สถาบันบำราศนราดูร',1,1);
INSERT INTO reference_values(group_name,label,created_by,updated_by) VALUES('org','สถาบันป้องกันควบคุมโรคเขตเมือง',1,1);
INSERT INTO reference_values(group_name,label,created_by,updated_by) VALUES('org','สถาบันเวชศาสตร์ป้องกันศึกษา กรมควบคุมโรค',1,1);
INSERT INTO reference_values(group_name,label,created_by,updated_by) VALUES('org','สำนักงานป้องกันควบคุมโรคที่ 1 จังหวัดเชียงใหม่',1,1);
INSERT INTO reference_values(group_name,label,created_by,updated_by) VALUES('org','สำนักงานป้องกันควบคุมโรคที่ 2 จังหวัดพิษณุโลก',1,1);
INSERT INTO reference_values(group_name,label,created_by,updated_by) VALUES('org','สำนักงานป้องกันควบคุมโรคที่ 3 จังหวัดนครสวรรค์',1,1);
INSERT INTO reference_values(group_name,label,created_by,updated_by) VALUES('org','สำนักงานป้องกันควบคุมโรคที่ 4 จังหวัดสระบุรี',1,1);
INSERT INTO reference_values(group_name,label,created_by,updated_by) VALUES('org','สำนักงานป้องกันควบคุมโรคที่ 5 จังหวัดราชบุรี',1,1);
INSERT INTO reference_values(group_name,label,created_by,updated_by) VALUES('org','สำนักงานป้องกันควบคุมโรคที่ 6 จังหวัดชลบุรี',1,1);
INSERT INTO reference_values(group_name,label,created_by,updated_by) VALUES('org','สำนักงานป้องกันควบคุมโรคที่ 7 จังหวัดขอนแก่น',1,1);
INSERT INTO reference_values(group_name,label,created_by,updated_by) VALUES('org','สำนักงานป้องกันควบคุมโรคที่ 8 จังหวัดอุดรธานี',1,1);
INSERT INTO reference_values(group_name,label,created_by,updated_by) VALUES('org','สำนักงานป้องกันควบคุมโรคที่ 9 จังหวัดนครราชสีมา',1,1);
INSERT INTO reference_values(group_name,label,created_by,updated_by) VALUES('org','สำนักงานป้องกันควบคุมโรคที่ 10 จังหวัดอุบลราชธานี',1,1);
INSERT INTO reference_values(group_name,label,created_by,updated_by) VALUES('org','สำนักงานป้องกันควบคุมโรคที่ 11 จังหวัดนครศรีธรรมราช',1,1);
INSERT INTO reference_values(group_name,label,created_by,updated_by) VALUES('org','สำนักงานป้องกันควบคุมโรคที่ 12 จังหวัดสงขลา',1,1);
INSERT INTO reference_values(group_name,label,created_by,updated_by) VALUES('personnel','ข้าราชการพลเรือนสามัญ',1,1);
INSERT INTO reference_values(group_name,label,created_by,updated_by) VALUES('personnel','ลูกจ้างประจำ',1,1);
INSERT INTO reference_values(group_name,label,created_by,updated_by) VALUES('personnel','พนักงานราชการทั่วไป',1,1);
INSERT INTO reference_values(group_name,label,created_by,updated_by) VALUES('personnel','พนักงานราชการพิเศษ',1,1);
INSERT INTO reference_values(group_name,label,created_by,updated_by) VALUES('personnel','พนักงานกระทรวงสาธารณสุข',1,1);
INSERT INTO reference_values(group_name,label,created_by,updated_by) VALUES('personnel','ลูกจ้างชั่วคราว',1,1);
INSERT INTO reference_values(group_name,label,created_by,updated_by) VALUES('personnel','ลูกจ้างชั่วคราวเงินบำรุงกรมควบคุมโรค',1,1);
INSERT INTO reference_values(group_name,label,created_by,updated_by) VALUES('position','นักวิชาการสาธารณสุข',1,1);
INSERT INTO reference_values(group_name,label,created_by,updated_by) VALUES('position','นายแพทย์',1,1);
INSERT INTO reference_values(group_name,label,created_by,updated_by) VALUES('position','นักทรัพยากรบุคคล',1,1);
INSERT INTO reference_values(group_name,label,created_by,updated_by) VALUES('position','นักเทคนิคการแพทย์',1,1);
INSERT INTO reference_values(group_name,label,created_by,updated_by) VALUES('level','ปฏิบัติงาน',1,1);
INSERT INTO reference_values(group_name,label,created_by,updated_by) VALUES('level','ชำนาญงาน',1,1);
INSERT INTO reference_values(group_name,label,created_by,updated_by) VALUES('level','อาวุโส',1,1);
INSERT INTO reference_values(group_name,label,created_by,updated_by) VALUES('level','ปฏิบัติการ',1,1);
INSERT INTO reference_values(group_name,label,created_by,updated_by) VALUES('level','ชำนาญการ',1,1);
INSERT INTO reference_values(group_name,label,created_by,updated_by) VALUES('level','ชำนาญการพิเศษ',1,1);
INSERT INTO reference_values(group_name,label,created_by,updated_by) VALUES('level','เชี่ยวชาญ',1,1);
INSERT INTO reference_values(group_name,label,created_by,updated_by) VALUES('level','ทรงคุณวุฒิ',1,1);
INSERT INTO audit_logs(action,entity_type,entity_id,description,ip_address,created_by,updated_by) VALUES('system_installed','user',1,'ติดตั้งระบบและสร้างบัญชี Super Admin','SQL import',1,1);

-- ประเทศเริ่มต้น: ผู้ดูแลเพิ่ม/ปิด/เปิดได้จากหน้าข้อมูลอ้างอิง
INSERT INTO reference_values(group_name,label,created_by,updated_by) VALUES('country','ญี่ปุ่น',1,1);
INSERT INTO reference_values(group_name,label,created_by,updated_by) VALUES('country','สหรัฐอเมริกา',1,1);
INSERT INTO reference_values(group_name,label,created_by,updated_by) VALUES('country','สหราชอาณาจักร',1,1);
INSERT INTO reference_values(group_name,label,created_by,updated_by) VALUES('country','ออสเตรเลีย',1,1);
INSERT INTO reference_values(group_name,label,created_by,updated_by) VALUES('country','จีน',1,1);
INSERT INTO reference_values(group_name,label,created_by,updated_by) VALUES('country','สิงคโปร์',1,1);
INSERT INTO reference_values(group_name,label,created_by,updated_by) VALUES('country','เกาหลีใต้',1,1);
INSERT INTO reference_values(group_name,label,created_by,updated_by) VALUES('country','แคนาดา',1,1);
INSERT INTO reference_values(group_name,label,created_by,updated_by) VALUES('country','เยอรมนี',1,1);
INSERT INTO reference_values(group_name,label,created_by,updated_by) VALUES('country','ฝรั่งเศส',1,1);
INSERT INTO reference_values(group_name,label,created_by,updated_by) VALUES('country','มาเลเซีย',1,1);
INSERT INTO reference_values(group_name,label,created_by,updated_by) VALUES('country','อินโดนีเซีย',1,1);
COMMIT;
