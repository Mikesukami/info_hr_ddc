<?php
defined("APP_ENTRY") || exit();
function auth_view(string $page): void
{
    global $user;
    $titles = [
        "login" => "เข้าสู่ระบบ",
        "register" => "ลงทะเบียนผู้ใช้งาน",
        "forgot" => "ขอรีเซ็ตรหัสผ่าน",
        "reset" => "ตั้งรหัสผ่านใหม่",
        "password" => "เปลี่ยนรหัสผ่าน",
    ];
    heading(
        $titles[$page],
        $page === "register"
            ? "บัญชีใหม่ต้องได้รับอนุมัติจาก Super Admin ก่อนใช้งาน"
            : "ระบบลาศึกษาและฝึกอบรม กรมควบคุมโรค",
    );
    echo '<section class="panel auth-panel">';
    if ($page === "password" && $user["must_change_password"]) {
        echo '<div class="notice">กรุณาเปลี่ยนรหัสผ่านเริ่มต้นก่อนเข้าใช้งานระบบ</div>';
    }
    echo form_start($page) . '<div class="form-grid">';
    if (in_array($page, ["login", "register", "forgot"], true)) {
        echo input_field("ชื่อผู้ใช้", "username", "", "text", true, 80);
    }
    if ($page === "register") {
        echo input_field("ชื่อ–นามสกุล", "name") .
            input_field("ตำแหน่ง", "position") .
            input_field("เบอร์โทร", "phone", "", "tel", true, 30);
    }
    if ($page === "password") {
        echo '<label class="full">รหัสผ่านปัจจุบัน <span class="required">*</span><inp' .
            'ut type="password" name="current_password" required autocomplete="curren' .
            't-password" maxlength="200"></label>';
    }
    if ($page === "login") {
        echo '<label>รหัสผ่าน <span class="required">*</span><input type="password" na' .
            'me="password" required autocomplete="current-password" maxlength="200"><' .
            "/label>";
    } elseif ($page !== "forgot") {
        echo input_field("รหัสผ่านใหม่", "password", "", "password") .
            input_field("ยืนยันรหัสผ่านใหม่", "password_confirm", "", "password");
    }
    if ($page === "reset") {
        echo hidden("token", is_string($_GET["token"] ?? null) ? $_GET["token"] : "");
    }
    echo "</div>";
    if (in_array($page, ["register", "reset", "password"], true)) {
        echo '<p class="section-copy">รหัสผ่านอย่างน้อย 12 ตัวอักษร สูงสุด 72 ไบต์ แนะ' .
            "นำวลีรหัสผ่านยาวที่ไม่ซ้ำกับระบบอื่น</p>";
    }
    if ($page === "forgot") {
        echo '<p class="section-copy">ส่งคำขอแล้วติดต่อ Super Admin เพื่อยืนยันตัวตน ผ' .
            "ู้ดูแลจะออกลิงก์ตั้งรหัสผ่านใหม่ที่ใช้ได้ครั้งเดียว อายุ 30 นาที</p>";
    }
    echo '<div class="form-actions"><button class="primary" type="submit">' .
        h($titles[$page]) .
        "</button></div></form>";
    if (!$user) {
        echo '<div class="auth-links"><a href="' .
            h(url("login")) .
            '">เข้าสู่ระบบ</a><a href="' .
            h(url("register")) .
            '">ลงทะเบียน</a><a href="' .
            h(url("forgot")) .
            '">ลืมรหัสผ่าน</a></div>';
    }
    echo "</section>";
}
