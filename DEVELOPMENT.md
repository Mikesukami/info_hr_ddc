# แผนที่โค้ดสำหรับผู้เริ่มต้น

## ตั้งระยะเวลา Session

แก้เฉพาะ config/local.php ค่าสองตัวนี้เป็นวินาที:

    "session_idle_seconds" => 30 * 60,       // ว่างเกิน 30 นาที
    "session_max_seconds" => 8 * 60 * 60,    // อายุรวมเกิน 8 ชั่วโมง

ถ้าต้องการ 1 ชั่วโมงและ 12 ชั่วโมง ให้เปลี่ยนเป็น 60 * 60 และ 12 * 60 * 60
ค่าเดิมยังคงเป็น 30 นาที/8 ชั่วโมง ไม่ต้องแก้ DB และไม่ต้องนำเข้า SQL ใหม่

session_expired() ใน app/bootstrap.php ตรวจเวลาปัจจุบันลบ last_seen และ login_at
ทุกคำขอที่เข้าหน้าระบบ หากเกินอย่างใดอย่างหนึ่ง current_user() จะล้างการเข้าใช้งาน
และให้ login ใหม่ ไม่มีตัวนับถอยหลังคอยสั่งออกจากระบบในเบราว์เซอร์ตลอดเวลา
การเปิดหน้า/ค้นหา/บันทึกที่ส่งถึง PHP ต่อเวลา last_seen แต่ไม่เปลี่ยน login_at
การเลื่อนหน้าหรือพิมพ์ฟอร์มโดยยังไม่ส่งข้อมูลจึงไม่ต่อเวลา

ไฟล์ session อาจยังอยู่หลังหมดอายุ การลบไฟล์เก่าเป็นงานเก็บกวาดของ PHP
ส่วนการอนุญาตให้ใช้ระบบตรวจด้วยเวลาข้างต้น ไม่ได้รอให้ไฟล์ถูกลบ

## รูปแบบโค้ด

จัด PHP/JavaScript/CSS ให้แยกบล็อก เงื่อนไข และสมาชิก array ลงหลายบรรทัด
SQL ยาวแยกเป็นข้อความหลายบรรทัดแบบ nowdoc เพื่อแยกคำสั่ง SQL ออกจากค่าที่ส่งเข้าไป:

    query(
        <<<'SQL'
        SELECT id, name
        FROM users
        WHERE id = ?
        SQL,
        [$id]
    );

ส่วนระหว่าง SQL เป็นข้อความคำสั่ง ส่วน [$id] เป็นค่าที่ PDO ผูกเข้ากับ ?
การจัดบรรทัดไม่เปลี่ยนเงื่อนไขสิทธิ์หรือโครงสร้างฐานข้อมูล
ข้อมูลฟอนต์ที่ฝังใน assets/app.css เป็นข้อความ base64 ซึ่งยาวตามรูปแบบไฟล์

## เริ่มอ่านตรงไหน

เริ่มที่ **index.php** ซึ่งเป็นประตูของทุกหน้า PHP ทำงานใหม่ทุกครั้งที่เบราว์เซอร์ส่ง request เมื่อทำเสร็จจะส่ง HTML หรือ redirect กลับไป ต่างจาก Node.js ที่เราอาจเปิด server process และกำหนด route ใน Express เอง

ลำดับงาน:

    เบราว์เซอร์
      → index.php
      → bootstrap.php ตั้งค่า session / PDO / ตัวช่วย
      → routes.php ตรวจสิทธิ์ของหน้าและคำสั่ง
      → GET: views/layout.php → views/content.php → หน้าจอที่เลือก
      → POST: ฟังก์ชัน *_action → ตรวจข้อมูล → บันทึก DB → redirect

**GET** ใช้เปิดหน้า/ค้นหา เช่น index.php?page=settings&group=country
**POST** ใช้เปลี่ยนข้อมูล เช่น action=reference_create ไม่ใช้ GET เพื่อเปิด/ปิดหรือลบข้อมูล

## แต่ละไฟล์ทำอะไร

| ไฟล์ | หน้าที่ / จุดที่ควรหา |
|---|---|
| config/local.php | ข้อมูลเชื่อมต่อฐานและ URL ของเครื่องนี้ |
| index.php | รับ request ตรวจผู้ใช้/สิทธิ์/CSRF แล้วเรียกงานที่ตรง action |
| app/routes.php | รายชื่อหน้าและคำสั่งที่แต่ละบทบาทใช้ได้ |
| app/bootstrap.php | db, query, row, rows, transaction, session, h และ validation ทั่วไป |
| app/auth.php | สมัคร เข้าใช้ เปลี่ยนรหัสผ่าน อนุมัติ และ reset |
| app/references.php | กลุ่มอ้างอิง อ่านต้นไม้หน่วยงาน เพิ่ม/เปิด/ปิด ตรวจกลุ่ม id |
| app/records.php | SQL อ่านแฟ้ม ตัวกรอง แบ่งหน้า ตรวจฟอร์ม และบันทึกเรื่อง/ช่วง |
| app/view_helpers.php | สร้างช่องกรอก ปุ่ม แบบฟอร์ม CSRF และ pagination |
| app/views/layout.php | โครง HTML หลัก sidebar และ header |
| app/views/content.php | เลือกฟังก์ชันหน้าจอตาม page |
| app/views/settings.php | HTML หน้าข้อมูลอ้างอิงและประเภทการบันทึก |
| app/views/records.php | HTML ทะเบียน แฟ้ม แบบฟอร์ม และรายงาน |
| app/views/admin.php | HTML ผู้ใช้และประวัติ |
| app/views/auth.php | HTML login / register / password |
| app/export.php | CSV ใช้ตัวกรองจากทะเบียน |
| assets/app.js | พับเมนู สลับฟอร์มใน/ต่างประเทศ และรายละเอียดประเภท |
| assets/server.css | CSS ที่ปรับเพิ่มจากรูปแบบหลักใน app.css |
| database/install.sql | โครงสร้างและข้อมูลเริ่มต้น สำหรับติดตั้งใหม่ |

## ตัวอย่างการอ่านและบันทึก

ฟังก์ชันใน bootstrap.php ห่อ PDO ไว้:

    $item = row('SELECT * FROM reference_values WHERE id = ?', [$id]);
    $items = rows('SELECT * FROM reference_values WHERE group_name = ?', ['country']);
    query('UPDATE reference_values SET active = ? WHERE id = ?', [0, $id]);

row คืนหนึ่งแถวหรือ null, rows คืนรายการหลายแถว, query คืน PDOStatement ทุกค่าจากผู้ใช้ส่งผ่าน ? และ array ไม่ต่อชื่อหรือข้อความจาก POST ลง SQL เอง

ในงานจริงให้ใช้ฟังก์ชัน *_action ที่มีการตรวจสิทธิ์ ตรวจข้อมูล และ audit ครบ ตัวอย่าง UPDATE ข้างบนเป็นเพียงการอธิบาย PDO ไม่ใช่ action สำเร็จรูป

ตัวอย่างเพิ่มประเทศ:

1. settings.php สร้างฟอร์มด้วย form_start('reference_create') ซึ่งใส่ CSRF ให้
2. ฟอร์มส่ง group=country และ label ไป index.php
3. index.php ตรวจว่าบทบาทอยู่ใน routes.php และ CSRF ถูกต้อง
4. reference_create_action() ใน references.php ตรวจกลุ่ม ชื่อซ้ำ และหน่วยงานแม่
5. transaction() บันทึก reference_values และ audit_logs พร้อมกัน หากผิดพลาดจะ rollback
6. redirect กลับหน้า settings เพื่อป้องกันกด refresh แล้วบันทึกซ้ำ

ใน view ใช้ h($value) ก่อนแสดงข้อมูลของผู้ใช้ เพื่อไม่ให้ข้อความถูกตีความเป็น HTML/JavaScript ส่วน old() ช่วยคืนค่าฟอร์มเมื่อกรอกผิด

## โครงสร้างหน่วยงานและประเทศ

reference_values ใช้กลุ่ม org, personnel, position, level, country อยู่ตารางเดียวกัน

- parent_id: id หน่วยงานแม่ เฉพาะกลุ่ม org; NULL คือสำนัก/กอง
- org_depth: 0 = สำนัก/กอง, 1–5 = ต่ำกว่าสำนัก/กองตามจำนวนระดับ
- หน่วยงานลูกชื่อซ้ำกันต่างแม่ได้ แต่ชื่อซ้ำภายใต้แม่เดียวกันไม่ได้
- กลุ่มอื่นไม่มีแม่ และชื่อซ้ำในกลุ่มเดียวกันไม่ได้
- ไม่เปิดให้ย้ายแม่/เปลี่ยนชื่อในรุ่นนี้ เพื่อไม่เปลี่ยนความหมายของเรื่องที่อ้างอิงอยู่
- ปิดแม่ต้องปิดลูกก่อน เปิดลูกต้องเปิดแม่ก่อน ไม่ลบแถวที่มีคนอ้างอิง

ตัวอย่าง: สถาบันบำราศฯ (0) → กลุ่มการพยาบาล (1) → กลุ่มงานผู้ป่วยใน (2) → หอผู้ป่วย (3)

leave_cases.org_id ชี้เฉพาะสำนัก/กองระดับ 0 และ country_id ชี้รายการกลุ่ม country เมื่อไปต่างประเทศ ฟอร์มยังเป็นราย case มีชื่อบุคคลในแฟ้ม ไม่สร้างทะเบียนบุคคลหรือช่องวันเกิด/เลขที่ตำแหน่ง

**สำหรับอนาคต:** org ตามกฎหมายและ org_ass ตามปฏิบัติเป็นความสัมพันธ์สองแบบที่ชี้ต้นไม้หน่วยงานชุดเดียวกัน ไม่ต้องทำสำเนาสำนัก/กองสองชุด เมื่อเริ่มระบบบุคคลค่อยเพิ่มตารางประวัติการสังกัด เช่น person_id, org_id, org_ass_id, วันที่เริ่ม/สิ้นสุด โดยทั้งสอง id ชี้ระดับที่ต้องการได้ รุ่นนี้ยังไม่เพิ่มตารางหรือเก็บข้อมูลบุคคลเหล่านั้น

## การปิดใช้งานกับข้อมูลเก่า

ฟอร์มใหม่เลือกเฉพาะ active=1 ส่วนแฟ้มเก่าจะแสดงค่าเดิมที่ปิดแล้วพร้อมคำกำกับ และยอมให้คงค่านั้นไว้เมื่อแก้รายละเอียดอื่น หากเปลี่ยนค่าใหม่ต้องเลือกที่ active เท่านั้น เซิร์ฟเวอร์ตรวจ id/กลุ่ม/ระดับด้วย ไม่พึ่งรายการ dropdown อย่างเดียว

## เมื่อต้องเพิ่มฟีเจอร์

1. กำหนดสิทธิ์ใน routes.php ก่อน แล้วเพิ่มการเรียก action ใน index.php
2. แยกโค้ดฐานข้อมูลและกฎการบันทึกออกจากไฟล์ view เช่น references.php กับ views/settings.php
3. ใช้ validation, transaction, audit และ CSRF ของเดิม
4. หากเพิ่มกลุ่มอ้างอิงใหม่ ให้เพิ่มใน REFERENCE_GROUPS แล้วเชื่อมฟอร์มที่ใช้กลุ่มนั้น ไม่ต้องเพิ่มตารางสำหรับรายการชื่อทั่วไป
5. หลังรอบตั้งโครงสร้างนี้ การเปลี่ยน DB ให้ทำไฟล์ migration เฉพาะการเปลี่ยนแปลง ห้ามลบฐาน/import install.sql ซ้ำเมื่อมีข้อมูลจริง

การจัดบรรทัดไฟล์ PHP เดิมตรวจเทียบ token ก่อน/หลังเพื่อคงพฤติกรรมเดิม ส่วนงานใหม่มี comment อธิบายกฎที่สำคัญ ทดสอบแยกจากฐานใช้งานจริง

## ข้อมูลส่วนบุคคลของผู้ใช้

- app/user_profiles.php: ตรวจข้อมูลร่วมกับการสมัคร และบันทึกข้อมูลส่วนตัวใน transaction
- app/views/profile.php: สร้างชุดช่องกรอกเดียวกันสำหรับสมัคร/แก้ไขข้อมูล พร้อมหน้าข้อมูลส่วนตัว
- app/routes.php: profile/profile_save ใช้ได้ทุกบทบาท; user_profile/user_profile_save เฉพาะ Super Admin
- index.php: ตรวจสิทธิ์และ CSRF ก่อนเรียกบริการ และคืนฟอร์มพร้อมค่าที่กรอกเมื่อข้อมูลไม่ผ่าน
- database/upgrade_user_profiles.sql: ปรับฐานเดิมครั้งเดียวโดยไม่ลบข้อมูล

users เก็บ title, first_name, last_name, national_id, email, org_id และ profile_version เพิ่มจากเดิม
name ยังคงเป็นชื่อเต็มสำหรับจุดแสดงผลเดิมและประวัติ ทุกครั้งที่บันทึกโปรไฟล์จะประกอบจากสามช่องชื่อ
ผู้ใช้เดิมมีช่องแยกชื่อว่างไว้ก่อน ไม่เดาแยกจาก name เพราะคำนำหน้าและชื่ออาจมีหลายคำ
org_id อ้างอิง reference_values; บริการตรวจว่าตรงกลุ่ม org และเป็นระดับสำนัก/กองด้วย

การลบข้อมูลอ้างอิง: `reference_delete` และ `type_delete` ใน app/routes.php ให้เฉพาะ superAdmin
index.php ตรวจสิทธิ์และ CSRF แล้วเรียก reference_delete_action() ใน app/references.php
action ล็อกแถว ตรวจรายการที่ใช้งานและหน่วยงานลูก แล้ว DELETE พร้อม audit ใน transaction เดียว
หากเขียนประวัติไม่สำเร็จจะ rollback การลบ; Foreign Key ยังป้องกันการลบแถวที่ถูกใช้
หลักสูตรใน leave_cases เก็บชื่อเป็น snapshot จึงตรวจ course เทียบ label เพิ่มด้วย
ปุ่มอยู่ app/views/settings.php และใช้ modal ร่วมใน assets/ui.js ก่อนส่ง delete_confirmed=1
ค่านี้ยืนยันขั้นตอน UI เท่านั้น สิทธิ์และ CSRF ฝั่ง PHP เป็นตัวป้องกันจริง
profile_version ใช้ตรวจการแก้ไขพร้อมกัน แยกจาก session_version ซึ่งใช้ยกเลิก session
profile_save ใช้ id จากผู้ใช้ที่เข้าสู่ระบบเท่านั้น ไม่เชื่อ id ที่ส่งใน POST
UPDATE ระบุเฉพาะช่องส่วนตัว จึงไม่สามารถเปลี่ยนสิทธิ์/สถานะจากฟอร์มนี้
national_id มี UNIQUE ในฐานข้อมูล และบริการแปลงกรณีข้อมูลซ้ำเป็นข้อความที่ผู้ใช้เข้าใจได้
ประวัติ user_profile_updated เก็บ changed_fields เท่านั้น ไม่ทำสำเนาเลขบัตรหรือข้อมูลติดต่อ
# หน้าแจ้งข้อผิดพลาด

- `app/error_page.php`: ฟังก์ชัน `abort_page(status, message)` หยุดการทำงานและแสดงหน้า error; `render_error_page()` ใช้ใน catch
- `app/views/error.php` และ `assets/error-page.css`: HTML และรูปแบบของหน้า error ไม่เชื่อมฐานข้อมูล
- `error.php`: จุดรับ error จากเว็บเซิร์ฟเวอร์ รองรับ 401, 403, 404, 405, 422, 429, 500 และ 503
- `.htaccess`: เส้นทางที่ไม่มีไฟล์จริงส่งไปหน้า 404; กฎป้องกันไฟล์ระบบยังคงเดิม

กรณี Apache ปฏิเสธคำขอก่อนถึง PHP (เช่น เปิดไฟล์ config โดยตรง) หากต้องการใช้หน้าเดียวกัน ให้เพิ่ม ErrorDocument ใน `.htaccess` โดยใช้ path เว็บที่ติดตั้งจริง ตัวอย่างเมื่อโฟลเดอร์ชื่อ `study leave`:

```apache
ErrorDocument 401 /study%20leave/error.php
ErrorDocument 403 /study%20leave/error.php
ErrorDocument 404 /study%20leave/error.php
ErrorDocument 500 /study%20leave/error.php
ErrorDocument 503 /study%20leave/error.php
```

หากย้ายไป `info_hr_ddc` ให้เปลี่ยน prefix เป็น `/info_hr_ddc/`; หากติดตั้งที่ root domain ใช้ `/error.php` โดยตรง ไม่ใช้ URL เต็ม เพราะต้องรักษารหัส HTTP เดิม การล่มของ Apache/PHP เองต้องกำหนด error page ที่เว็บเซิร์ฟเวอร์เพิ่มเติม
