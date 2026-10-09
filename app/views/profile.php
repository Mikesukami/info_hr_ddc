<?php
defined("APP_ENTRY") || exit();

/** ฟอร์มชุดเดียวกันสำหรับลงทะเบียนและแก้ไขข้อมูลผู้ใช้ */
function user_profile_fields(array $values = []): string
{
    $nationalId = input_field(
        "เลขบัตรประชาชน",
        "national_id",
        $values["national_id"] ?? "",
        "text",
        true,
        13,
    );
    $nationalId = str_replace(
        ' maxlength="13"',
        ' inputmode="numeric" pattern="[0-9]{13}" autocomplete="off" maxlength="13"',
        $nationalId,
    );
    return input_field("คำนำหน้า", "title", $values["title"] ?? "", "text", true, 50) .
        input_field("ชื่อ", "first_name", $values["first_name"] ?? "", "text", true, 80) .
        input_field(
            "นามสกุล",
            "last_name",
            $values["last_name"] ?? "",
            "text",
            true,
            80,
        ) .
        $nationalId .
        input_field("อีเมล", "email", $values["email"] ?? "", "email", true, 254) .
        input_field("เบอร์โทร", "phone", $values["phone"] ?? "", "tel", true, 30) .
        input_field("ตำแหน่ง", "position", $values["position"] ?? "") .
        reference_field("สำนัก/กอง", "org_id", "org", $values["org_id"] ?? "");
}

function profile_view(bool $editOther = false): void
{
    allowed($editOther ? ["superAdmin"] : ["officer", "admin", "superAdmin"]);
    $id = $editOther ? positive_id($_GET["id"] ?? null) : actor();
    $profile = row("SELECT * FROM users WHERE id=?", [$id]);
    if (!$profile) {
        http_response_code(404);
        throw new ValidationException("ไม่พบบัญชีผู้ใช้");
    }
    heading(
        $editOther ? "แก้ไขข้อมูลผู้ใช้งาน" : "ข้อมูลส่วนตัว",
        "บัญชี: " .
            $profile["username"] .
            " · การแก้ข้อมูลส่วนตัวไม่เปลี่ยนสิทธิ์หรือรหัสผ่าน",
    );
    echo '<section class="panel profile-panel">';
    if (empty($profile["first_name"]) || empty($profile["last_name"])) {
        echo '<p class="section-copy">ชื่อเดิม: ' .
            h($profile["name"]) .
            " · กรุณากรอกคำนำหน้า ชื่อ และนามสกุลแยกช่องให้ครบ</p>";
    }
    echo form_start($editOther ? "user_profile_save" : "profile_save") .
        hidden("profile_version", old("profile_version", $profile["profile_version"]));
    if ($editOther) {
        echo hidden("id", $id);
    }
    echo '<div class="form-grid">' .
        user_profile_fields($profile) .
        "</div>" .
        '<p class="field-hint">เลขบัตรเป็นตัวเลข 13 หลัก ไม่ใส่ขีดหรือเว้นวรรค · หน่วยงานใช้ระดับสำนัก/กอง</p>' .
        form_end("บันทึกข้อมูล", $editOther ? "users" : "registry") .
        "</section>";
}
