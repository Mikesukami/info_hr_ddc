-- สำหรับฐานเดิมเท่านั้น: สำรองก่อน แล้วเลือกฐานที่ใช้งานใน phpMyAdmin
-- Import ไฟล์นี้ครั้งเดียว ไม่ต้องลบฐาน และไม่ต้องรัน install.sql ซ้ำ
SET NAMES utf8mb4 COLLATE utf8mb4_general_ci;

ALTER TABLE users
 ADD COLUMN title VARCHAR(50) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' AFTER name,
 ADD COLUMN first_name VARCHAR(80) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' AFTER title,
 ADD COLUMN last_name VARCHAR(80) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' AFTER first_name,
 ADD COLUMN national_id CHAR(13) COLLATE utf8mb4_general_ci NULL AFTER last_name,
 ADD COLUMN email VARCHAR(254) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' AFTER national_id,
 ADD COLUMN org_id BIGINT UNSIGNED NULL AFTER email,
 ADD COLUMN profile_version INT UNSIGNED NOT NULL DEFAULT 1 AFTER org_id,
 ADD UNIQUE KEY uq_users_national_id(national_id),
 ADD CONSTRAINT fk_users_org FOREIGN KEY(org_id) REFERENCES reference_values(id);

-- ไม่เดาแยกชื่อเดิม: บัญชีเดิมเข้าได้ตามปกติ แล้วกรอกข้อมูลผ่านเมนูข้อมูลส่วนตัว
-- ค่า national_id เดิมเป็น NULL จึงไม่ขัดกับ UNIQUE และไม่สร้างเลขบัตรสมมติ
