<?php
defined("APP_ENTRY") || exit();

function audit_display_values(mixed $value): mixed
{
    if (is_array($value)) {
        return array_map("audit_display_values", $value);
    }
    if (
        is_string($value) &&
        preg_match('/^\d{4}-\d{2}-\d{2}(?: \d{2}:\d{2}:\d{2})?$/D', $value)
    ) {
        return strlen($value) > 10 ? be_time($value) : be_date($value);
    }
    return $value;
}

function users_view(): void
{
    global $user, $config;
    heading("ผู้ใช้งานและสิทธิ์", "บัญชีใหม่รอ Super Admin อนุมัติ");
    if (isset($_SESSION["reset_link"])) {
        echo '<section class="panel"><h2>ลิงก์ตั้งรหัสผ่านใหม่</h2><p class="section-copy">สำหรับ ' .
            h($_SESSION["reset_link"]["name"]) .
            (" · อายุ 30 นาที ใช้ได้ครั้งเดียว ตรวจสอบตัวตนก่อนส่งให้เจ้าของบัญชี</p><" .
                'label>ลิงก์ (แสดงครั้งนี้เท่านั้น)<input readonly value="') .
            h($_SESSION["reset_link"]["url"]) .
            '" aria-label="ลิงก์ตั้งรหัสผ่านใหม่"></label></section>';
        unset($_SESSION["reset_link"]);
    }
    $data = page_data(
        "users",
        "",
        [],
        "id,username,name,position,phone,role,state,session_version,created_at",
        "state='pending' DESC,id DESC",
    );
    echo '<section class="panel"><div class="server-table-scroll"><table class="se' .
        'rver-table"><thead><tr><th>ผู้ใช้งาน</th><th>ตำแหน่ง / โทร</th><th>บทบาท' .
        " / สถานะ</th><th>จัดการ</th></tr></thead><tbody>";
    $states = [
        "pending" => "รออนุมัติ",
        "approved" => "อนุมัติแล้ว",
        "rejected" => "ไม่อนุมัติ",
        "disabled" => "ปิดใช้งาน",
    ];
    foreach ($data["items"] as $u) {
        echo "<tr><td>" .
            h($u["name"]) .
            '<small class="subline">' .
            h($u["username"]) .
            "</small></td><td>" .
            h($u["position"]) .
            '<small class="subline">' .
            h($u["phone"]) .
            "</small></td><td>" .
            h(ROLE_LABELS[$u["role"]]) .
            '<small class="subline">' .
            h($states[$u["state"]]) .
            "</small></td><td>";
        if ($user["role"] === "superAdmin" && $u["state"] === "pending") {
            echo form_start("user_decision") .
                hidden("id", $u["id"]) .
                '<label class="sr-only" for="role-' .
                $u["id"] .
                '">บทบาท</label><select id="role-' .
                $u["id"] .
                ('" name="role"><option value="officer">เจ้าหน้าที่</option><option value=' .
                    '"admin">Admin</option></select><div class="actions"><button name="decisi' .
                    'on" value="approved" class="primary">อนุมัติ</button><button name="decis' .
                    'ion" value="rejected">ไม่อนุมัติ</button></div></form>');
        } elseif (
            $user["role"] === "superAdmin" &&
            $u["state"] === "approved" &&
            (int) $u["id"] !== (int) $user["id"]
        ) {
            echo form_start("issue_reset") .
                hidden("id", $u["id"]) .
                ('<label class="check-label"><input type="checkbox" name="identity_confirm' .
                    'ed" value="1" required> ยืนยันตัวตนแล้ว</label><button type="submit">ออก' .
                    "ลิงก์รีเซ็ต</button></form>");
        } elseif ($user["role"] !== "superAdmin" || $u["role"] === "superAdmin") {
            echo "—";
        }
        if (
            $user["role"] === "superAdmin" &&
            $u["role"] !== "superAdmin" &&
            (int) $u["id"] !== (int) $user["id"] &&
            $u["state"] !== "pending"
        ) {
            echo '<div class="user-state-editor">' .
                form_start("user_state") .
                hidden("id", $u["id"]) .
                hidden("version", $u["session_version"]) .
                '<label for="user-state-' .
                (int) $u["id"] .
                '">สถานะบัญชี</label>' .
                '<select name="state" id="user-state-' .
                (int) $u["id"] .
                '" required>';
            foreach (["approved", "disabled", "rejected"] as $state) {
                echo '<option value="' .
                    h($state) .
                    '"' .
                    selected($state, $u["state"]) .
                    ">" .
                    h($states[$state]) .
                    "</option>";
            }
            echo '</select><button type="submit">บันทึกสถานะ</button></form></div>';
        }
        echo "</td></tr>";
    }
    echo "</tbody></table></div>";
    paginate($data);
    echo "</section>";
    if ($user["role"] === "superAdmin") {
        $requests = rows(
            <<<'SQL'
            SELECT r.id,
            r.created_at,
            u.name,
            u.username
            FROM password_reset_requests r
            JOIN users u
            ON u.id=r.user_id
            WHERE r.state='pending' AND u.state='approved'
            ORDER BY r.id DESC
            LIMIT 20
            SQL,
        );
        echo '<section class="panel"><h2>คำขอรีเซ็ตรหัสผ่านล่าสุด</h2><p class="sectio' .
            'n-copy">แสดงคำขอรอดำเนินการล่าสุดไม่เกิน 20 รายการ ออกลิงก์ให้บัญชีที่เก' .
            "ี่ยวข้องในตารางผู้ใช้ด้านบน</p>";
        foreach ($requests as $r) {
            echo '<div class="report-row"><span>' .
                h($r["name"]) .
                " · " .
                h($r["username"]) .
                "</span><small>" .
                h(be_time($r["created_at"])) .
                "</small></div>";
        }
        if (!$requests) {
            echo "<p>ไม่มีคำขอรอดำเนินการ</p>";
        }
        echo "</section>";
    }
}

function audit_view(): void
{
    heading("ประวัติการทำรายการ", "บันทึกผู้ดำเนินการ วันเวลา และการเปลี่ยนแปลง");
    $data = page_data(
        "audit_logs a LEFT JOIN users u ON u.id=a.created_by",
        "",
        [],
        "a.*,u.name AS actor_name",
        "a.id DESC",
    );
    echo '<section class="panel"><div class="server-table-scroll"><table class="se' .
        'rver-table"><thead><tr><th>วันเวลา (พ.ศ.)</th><th>รายการ</th><th>ผู้ดำเน' .
        "ินการ</th></tr></thead><tbody>";
    foreach ($data["items"] as $r) {
        echo "<tr><td>" .
            h(be_time($r["created_at"])) .
            "</td><td>" .
            h($r["description"]) .
            '<small class="subline">' .
            h($r["action"]) .
            " · " .
            h($r["entity_type"]) .
            " #" .
            h($r["entity_id"]) .
            "</small>";
        if ($r["before_data"] || $r["after_data"]) {
            echo '<details><summary>รายละเอียดก่อน / หลัง</summary><pre class="audit-json">' .
                h(
                    json_encode(
                        audit_display_values([
                            "ก่อน" => json_decode($r["before_data"] ?? "null", true),
                            "หลัง" => json_decode($r["after_data"] ?? "null", true),
                        ]),
                        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE,
                    ),
                ) .
                "</pre></details>";
        }
        echo "</td><td>" .
            h($r["actor_name"] ?? "คำขอที่ยังไม่ยืนยันตัวตน") .
            "</td></tr>";
    }
    echo "</tbody></table></div>";
    paginate($data);
    echo "</section>";
}
