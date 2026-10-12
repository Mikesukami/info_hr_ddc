-- สำหรับฐานข้อมูลเดิม: เลือกฐานข้อมูลที่ใช้งานใน phpMyAdmin ก่อน Import
-- สำรองฐานข้อมูลก่อน และรันไฟล์นี้เพียงครั้งเดียว ไม่ต้องลบข้อมูลเดิม
SET NAMES utf8mb4 COLLATE utf8mb4_general_ci;

ALTER TABLE leave_cases
 ADD COLUMN course_id BIGINT UNSIGNED NULL AFTER course,
 ADD COLUMN course_batch VARCHAR(50) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' AFTER course_id,
 ADD CONSTRAINT fk_cases_course FOREIGN KEY(course_id) REFERENCES reference_values(id);

-- เชื่อมเฉพาะชื่อที่ตรงกันทุกตัวอักษร ไม่เดาตัดคำว่า "รุ่น" ออกจากชื่อเดิม
UPDATE leave_cases c
 JOIN activity_types t ON t.id=c.type_id AND t.form_kind='course'
 JOIN reference_values r ON r.group_name='course' AND BINARY r.label=BINARY c.course
 SET c.course_id=r.id
 WHERE c.course_id IS NULL;

-- รายการที่ยังต้องตรวจจับคู่ด้วยตนเองผ่านหน้าแก้ไขแฟ้ม
SELECT c.id, c.person_name, c.course AS original_course
 FROM leave_cases c
 JOIN activity_types t ON t.id=c.type_id
 WHERE t.form_kind='course' AND c.course_id IS NULL
 ORDER BY c.id;
