<?php
// เลือกหน้าจอตามชื่อ page ที่ index.php ตรวจสิทธิ์แล้ว; ไฟล์ views มีหน้าที่แสดง HTML
defined("APP_ENTRY") || exit();
require __DIR__ . "/auth.php";
require __DIR__ . "/records.php";
require __DIR__ . "/admin.php";
require __DIR__ . "/settings.php";

// ชื่อ page ต้องอยู่ใน app/routes.php และผ่านการตรวจสิทธิ์ใน index.php ก่อน
function render_content(string $page): void
{
    match ($page) {
        "login", "register", "forgot", "reset", "password" => auth_view($page),
        "registry" => registry_view(false),
        "ledger" => registry_view(true),
        "overview" => overview_view(),
        "detail" => detail_view(),
        "case_form" => case_form_view(),
        "period_form" => period_form_view("period"),
        "return_form" => period_form_view("return"),
        "cancel_form" => period_form_view("cancel"),
        "reports" => reports_view(),
        "users" => users_view(),
        "settings" => settings_view(),
        "audit" => audit_view(),
        "help" => help_view(),
    };
}

function help_view(): void
{
    heading("คู่มือการใช้งาน", "ขั้นตอนบันทึกทะเบียนและการดูแลบัญชี");
    echo '<section class="panel">';
    $steps = [
        "เพิ่มรายการ" =>
            "เลือกประเภท กรอกข้อมูลบุคลากรและสารบรรณ เลขลำดับ No. ระบบออกให้เอง วันที่กรอกเป็น วว/ดด/พ.ศ.",
        "เพิ่มหรือแก้ไขช่วง" =>
            "เปิดแฟ้มแล้วเพิ่มช่วงใหม่ หรือเลือกแก้ไขช่วงที่ต้องการ ระบบตรวจวันที่ไม่" .
            "ให้ทับซ้อนและตรวจการแก้ไขพร้อมกัน",
        "รายงานตัวหรือยกเลิก" =>
            "กรอกวันที่ สารบรรณ และหมายเหตุ เมื่อปิดเรื่องแล้วจะเพิ่มช่วงไม่ได้ ผู้ดู" .
            "แลระบบแก้ไขข้อมูลย้อนหลังได้พร้อม Audit log",
        "ทะเบียนแบบ Excel" =>
            "แสดงหนึ่งแถวต่อรายการ เลข No. ไม่เปลี่ยนเมื่อค้นหา เรียงลำดับ หรือแบ่งหน" .
            "้า ส่งออกข้อมูลทั้งหมดที่ตรงตัวกรองได้",
        "จัดการผู้ใช้" =>
            "ผู้สมัครใหม่เป็นเจ้าหน้าที่และรอ Super Admin อนุมัติ Admin และ Super Adm" .
            "in เข้าหน้าจัดการระบบได้ การอนุมัติและออกลิงก์รีเซ็ตเป็นสิทธิ์ของ Super " .
            "Admin",
        "ลืมรหัสผ่าน" =>
            "กดลืมรหัสผ่านที่หน้าเข้าสู่ระบบและติดต่อ Super Admin เพื่อยืนยันตัวตน ลิ" .
            "งก์ตั้งรหัสผ่านใหม่มีอายุ 30 นาที ใช้ได้ครั้งเดียว",
        "พื้นที่ทำงาน" =>
            "ปุ่มเมนูด้านบนซ่อนหรือแสดงแถบด้านข้างได้ทั้งคอมพิวเตอร์และโทรศัพท์",
    ];
    foreach ($steps as $title => $body) {
        echo '<article class="help-step"><div>' .
            icon("check") .
            "</div><div><h2>" .
            h($title) .
            "</h2><p>" .
            h($body) .
            "</p></div></article>";
    }
    echo "</section>";
}
