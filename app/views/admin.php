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
    echo '<section class="panel users-panel"><div class="server-table-scroll" ' .
        'tabindex="0" role="region" aria-label="ตารางผู้ใช้งาน เลื่อนซ้ายขวาได้">' .
        '<table class="server-table users-table"><thead><tr>' .
        '<th scope="col">ผู้ใช้งาน</th><th scope="col">ตำแหน่ง / โทร</th>' .
        '<th scope="col">บทบาท / สถานะ</th><th scope="col">จัดการบัญชี</th>' .
        "</tr></thead><tbody>";
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
            '</small></td><td><div class="user-row-actions">';
        if ($user["role"] === "superAdmin") {
            echo '<a class="button" href="' . h(url("user_profile", ["id" => $u["id"]])) . '">แก้ข้อมูลส่วนตัว</a>';
        }
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
                '" class="sr-only">สถานะบัญชี</label>' .
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
        echo "</div></td></tr>";
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
            SQL
            ,
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

/** อ่านตัวกรองจาก URL และสร้างเงื่อนไข SQL แบบมี parameters */
function audit_filters(): array
{
    $values = [];
    $errors = [];
    foreach (["actor_name", "from", "to"] as $key) {
        $raw = $_GET[$key] ?? "";
        if (!is_string($raw)) {
            $errors[$key] = "รูปแบบตัวกรองไม่ถูกต้อง";
        }
        $values[$key] = is_string($raw) ? trim($raw) : "";
    }
    if (mb_strlen($values["actor_name"]) > 200) {
        $errors["actor_name"] = "ชื่อผู้ดำเนินการต้องไม่เกิน 200 ตัวอักษร";
    }

    $dates = [];
    foreach (["from" => "วันที่เริ่มต้น", "to" => "วันที่สิ้นสุด"] as $key => $label) {
        if ($values[$key] === "") {
            continue;
        }
        $valid = preg_match('~^(\d{2})/(\d{2})/(\d{4})$~', $values[$key], $parts);
        if (
            !$valid ||
            (int) $parts[3] < 2400 ||
            (int) $parts[3] > 2800 ||
            !checkdate((int) $parts[2], (int) $parts[1], (int) $parts[3] - 543)
        ) {
            $errors[$key] = "$label ต้องเป็นวันที่จริง รูปแบบ วว/ดด/พ.ศ. (2400–2800)";
            continue;
        }
        $dates[$key] = sprintf(
            "%04d-%02d-%02d",
            (int) $parts[3] - 543,
            (int) $parts[2],
            (int) $parts[1],
        );
    }
    if (isset($dates["from"], $dates["to"]) && $dates["from"] > $dates["to"]) {
        $errors["to"] = "วันที่สิ้นสุดต้องไม่ก่อนวันที่เริ่มต้น";
    }

    $clauses = [];
    $params = [];
    if ($values["actor_name"] !== "" && !$errors) {
        // % และ _ ที่ผู้ใช้พิมพ์ให้ค้นหาเป็นข้อความ ไม่ใช่ wildcard ของ SQL
        $clauses[] = "u.name LIKE ? ESCAPE '!'";
        $params[] =
            "%" .
            str_replace(["!", "%", "_"], ["!!", "!%", "!_"], $values["actor_name"]) .
            "%";
    }
    if (isset($dates["from"])) {
        $clauses[] = "a.created_at >= ?";
        $params[] = $dates["from"] . " 00:00:00";
    }
    if (isset($dates["to"])) {
        // ใช้ต้นวันถัดไป เพื่อรวมทุกรายการในวันสิ้นสุดและใช้ดัชนีวันเวลาได้
        $clauses[] = "a.created_at < ?";
        $endDate = new DateTimeImmutable($dates["to"]);
        $params[] = $endDate->modify("+1 day")
            ->format("Y-m-d 00:00:00");
    }
    $where = $errors
        ? " WHERE 1=0"
        : ($clauses
            ? " WHERE " . implode(" AND ", $clauses)
            : "");
    return [$values, $errors, $where, $errors ? [] : $params];
}

function audit_view(): void
{
    heading("ประวัติการทำรายการ", "บันทึกผู้ดำเนินการ วันเวลา และการเปลี่ยนแปลง");
    [$values, $errors, $where, $params] = audit_filters();
    $data = page_data(
        "audit_logs a LEFT JOIN users u ON u.id=a.created_by",
        $where,
        $params,
        "a.*,u.name AS actor_name",
        "a.id DESC",
    );
    echo '<section class="panel audit-panel">';
    if ($errors) {
        echo '<div class="notice error-notice" role="alert">กรุณาตรวจสอบตัวกรองด้านล่าง</div>';
    }
    echo '<form method="get" action="index.php" class="filter-form audit-filters">' .
        hidden("page", "audit");
    foreach (
        [
            "actor_name" => "ชื่อผู้ดำเนินการ",
            "from" => "ตั้งแต่วันที่ (พ.ศ.)",
            "to" => "ถึงวันที่ (พ.ศ.)",
        ]
        as $key => $label
    ) {
        $error = $errors[$key] ?? "";
        $date = $key !== "actor_name";
        echo "<label>" .
            h($label) .
            '<input name="' .
            $key .
            '" type="' .
            ($date ? "text" : "search") .
            '" value="' .
            h($values[$key]) .
            '"' .
            ($date
                ? ' data-be-date inputmode="numeric" maxlength="10" pattern="[0-9]{2}/[0-9]{2}/[0-9]{4}" placeholder="วว/ดด/พ.ศ."'
                : ' maxlength="200" placeholder="พิมพ์ชื่อหรือบางส่วนของชื่อ"') .
            ($error
                ? ' aria-invalid="true" aria-describedby="audit-' . $key . '-error"'
                : "") .
            ">";
        if ($error) {
            echo '<span class="field-error" id="audit-' .
                $key .
                '-error">' .
                h($error) .
                "</span>";
        }
        echo "</label>";
    }
    echo '<label>จำนวนต่อหน้า<select name="size">';
    foreach ([10, 25, 50, 100] as $size) {
        echo '<option value="' .
            $size .
            '"' .
            selected($size, $data["size"]) .
            ">" .
            $size .
            "</option>";
    }
    echo '</select></label><div class="filter-actions"><button class="primary" type="submit">' .
        icon("search") .
        'ค้นหา</button><a class="button" href="' .
        h(url("audit")) .
        '">ล้าง</a></div></form>';
    echo '<p class="audit-filter-hint">เว้นวันที่ว่างได้ · รวมรายการทั้งวันของวันที่สิ้นสุด</p>';
    echo '<div class="server-table-scroll"><table class="server-table audit-table"><thead><tr><th>วันเวลา (พ.ศ.)</th><th>รายการ</th><th>ผู้ดำเน' .
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
    if (!$data["items"]) {
        echo '<tr><td colspan="3" class="audit-empty">' .
            ($errors
                ? "แก้ไขตัวกรองแล้วกดค้นหาอีกครั้ง"
                : "ไม่พบประวัติการทำรายการตามเงื่อนไขที่เลือก") .
            "</td></tr>";
    }
    echo "</tbody></table></div>";
    paginate($data);
    echo "</section>";
}
