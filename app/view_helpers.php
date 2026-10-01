<?php
// ตัวช่วย HTML: h() escape อยู่ bootstrap; *_field สร้างช่องกรอก; form_start ใส่ CSRF ให้อัตโนมัติ
declare(strict_types=1);
defined("APP_ENTRY") || exit();

function icon(string $name): string
{
    static $icons;
    $icons ??= require __DIR__ . "/icons.php";
    return '<svg class="icon" viewBox="0 0 24 24" aria-hidden="true">' .
        ($icons[$name] ?? $icons["file-text"]) .
        "</svg>";
}

function selected(mixed $a, mixed $b): string
{
    return (string) $a === (string) $b ? " selected" : "";
}

function old(string $key, mixed $default = ""): mixed
{
    return $GLOBALS["old"][$key] ?? $default;
}

function input_field(
    string $label,
    string $name,
    mixed $value = "",
    string $type = "text",
    bool $required = true,
    int $max = 200,
): string {
    $value = old($name, $value);
    $hint = "";
    $attrs = "";
    if ($type === "date") {
        $type = "text";
        $value =
            is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)
                ? be_date($value)
                : $value;
        $attrs =
            ' data-be-date inputmode="numeric" pattern="[0-9]{2}/[0-9]{2}/[0-9]{4}" placeholder="วว/ดด/พ.ศ."';
        $max = 10;
        $hint = '<span class="field-hint">วว/ดด/พ.ศ. เช่น 01/07/2569</span>';
    }
    if ($type === "password") {
        $value = "";
        $attrs = ' autocomplete="new-password"';
        $max = 72;
    }
    return "<label>" .
        h($label) .
        ($required ? ' <span class="required">*</span>' : "") .
        '<input type="' .
        h($type) .
        '" name="' .
        h($name) .
        '" value="' .
        h($value) .
        '" maxlength="' .
        $max .
        '"' .
        ($required ? " required" : "") .
        $attrs .
        ">" .
        $hint .
        "</label>";
}

function select_field(
    string $label,
    string $name,
    array $choices,
    mixed $value = "",
    bool $searchable = false,
): string {
    $value = old($name, $value);
    $html =
        "<label>" .
        h($label) .
        ' <span class="required">*</span><select name="' .
        h($name) .
        '" required' .
        ($searchable ? " data-searchable" : "") .
        ">";
    foreach ($choices as $key => $text) {
        $html .=
            '<option value="' .
            h($key) .
            '"' .
            selected($key, $value) .
            ">" .
            h($text) .
            "</option>";
    }
    return $html . "</select></label>";
}
// ค่าเดิมที่ปิดแล้วแสดงพร้อมคำกำกับ แต่ไม่เพิ่มเป็นตัวเลือกให้เรื่องใหม่

function reference_field(
    string $label,
    string $name,
    string $group,
    mixed $value = "",
): string {
    $choices = ["" => "เลือก" . $label];
    foreach (
        references($group, $value !== "" && $value !== null ? (int) $value : null)
        as $item
    ) {
        $choices[$item["id"]] =
            $item["label"] . ($item["active"] ? "" : " (ปิดใช้งาน — ค่าเดิม)");
    }
    return select_field($label, $name, $choices, $value, true);
}

function course_reference_field(string $current = ""): string
{
    $choices = ["" => "เลือกหลักสูตร / เรื่อง"];
    $selected = "";
    foreach (
        rows(
            "SELECT id,label,active FROM reference_values
         WHERE group_name='course' AND (active=1 OR label=?) ORDER BY label,id",
            [$current],
        )
        as $item
    ) {
        $choices[$item["id"]] =
            $item["label"] . ($item["active"] ? "" : " (ปิดใช้งาน — ค่าเดิม)");
        if ($item["label"] === $current) {
            $selected = $item["id"];
        }
    }
    if ($current !== "" && $selected === "") {
        $choices["legacy"] = $current . " (ชื่อเดิมในแฟ้ม)";
        $selected = "legacy";
    }
    return select_field("ชื่อหลักสูตร / เรื่อง", "course_id", $choices, $selected, true) .
        '<p class="field-hint full">ไม่พบหลักสูตรที่ต้องการ? ให้ Admin หรือ Super Admin เพิ่มที่ข้อมูลอ้างอิง → หลักสูตรฝึกอบรม / เรื่อง</p>';
}

function textarea_field(
    string $label,
    string $name,
    mixed $value = "",
    bool $required = false,
): string {
    return '<label class="full">' .
        h($label) .
        ($required ? ' <span class="required">*</span>' : "") .
        '<textarea name="' .
        h($name) .
        '" maxlength="2000"' .
        ($required ? " required" : "") .
        ">" .
        h(old($name, $value)) .
        "</textarea></label>";
}

function hidden(string $name, mixed $value): string
{
    return '<input type="hidden" name="' . h($name) . '" value="' . h($value) . '">';
}

function form_start(string $action): string
{
    return '<form method="post" action="index.php" class="app-form" data-today="' .
        h(be_date(date("Y-m-d"))) .
        '">' .
        csrf() .
        hidden("action", $action);
}

function form_end(string $submit, string $back = "registry", array $params = []): string
{
    return '<div class="form-actions"><a class="button" href="' .
        h(url($back, $params)) .
        '">ย้อนกลับ</a><button class="primary" type="submit">' .
        icon("check") .
        h($submit) .
        "</button></div></form>";
}

function heading(string $title, string $description, string $actions = ""): void
{
    echo '<section class="page-intro"><div><div class="eyebrow">PERSONNEL INFORMATION</div><h1>' .
        h($title) .
        "</h1><p>" .
        h($description) .
        '</p></div><div class="actions">' .
        $actions .
        "</div></section>";
}

function badge_html(string $status): string
{
    return '<span class="badge ' .
        match ($status) {
            "cancelled" => "cancelled",
            "overdue" => "warn",
            "closed" => "success",
            "scheduled" => "neutral",
            default => "",
        } .
        '">' .
        h(STATUS_LABELS[$status]) .
        "</span>";
}

// ป้ายข้อมูลอ้างอิง: ใช้ทั้งสีและข้อความ ไม่ใช้สีเพียงอย่างเดียว
function entry_subject_html(array $entry): string
{
    $heading = match ($entry["kind"]) {
        "return" => "รายงานตัวกลับ",
        "cancel" => "ยกเลิกเรื่อง",
        default => "ช่วงที่ " . $entry["ordinal"],
    };
    // แยกวันที่และรายละเอียดออกจากตัวหนา โดยคงข้อความเดิมครบถ้วน
    $details = substr(entry_subject($entry), strlen($heading));
    return "<strong>" .
        h($heading) .
        "</strong>" .
        h($details);
}

function active_badge(bool $active): string
{
    return '<span class="badge ' .
        ($active ? "success" : "neutral") .
        '">' .
        ($active ? "เปิดใช้งาน" : "ปิดใช้งาน") .
        "</span>";
}

function paginate(array $data): void
{
    echo '<nav class="table-footer" aria-label="แบ่งหน้า"><span>แสดง ' .
        ($data["total"] ? $data["offset"] + 1 : 0) .
        "–" .
        min($data["offset"] + $data["size"], $data["total"]) .
        " จาก " .
        number_format($data["total"]) .
        ' รายการ</span><div class="pagination">';
    $base = array_filter($_GET, fn($v) => is_scalar($v));
    unset($base["page"], $base["p"]);
    if ($data["page"] > 1) {
        echo '<a class="button" href="' .
            h(url($GLOBALS["page"], $base + ["p" => $data["page"] - 1])) .
            '" aria-label="หน้าก่อนหน้า">‹</a>';
    }
    $numbers = array_unique([
        1,
        ...range(max(1, $data["page"] - 2), min($data["pages"], $data["page"] + 2)),
        $data["pages"],
    ]);
    $last = 0;
    foreach ($numbers as $n) {
        if ($last && $n > $last + 1) {
            echo "<span>…</span>";
        }
        echo '<a class="button ' .
            ($n === $data["page"] ? "current" : "") .
            '" href="' .
            h(url($GLOBALS["page"], $base + ["p" => $n])) .
            '"' .
            ($n === $data["page"] ? ' aria-current="page"' : "") .
            ">" .
            $n .
            "</a>";
        $last = $n;
    }
    if ($data["page"] < $data["pages"]) {
        echo '<a class="button" href="' .
            h(url($GLOBALS["page"], $base + ["p" => $data["page"] + 1])) .
            '" aria-label="หน้าถัดไป">›</a>';
    }
    echo "</div></nav>";
}

function filter_form(string $page): void
{
    $values = array_filter($_GET, fn($v) => is_scalar($v));
    echo '<form method="get" class="ledger-filters filter-form">' .
        hidden("page", $page) .
        '<label>ค้นหา<input type="search" name="q" value="' .
        h($values["q"] ?? "") .
        ('" placeholder="ชื่อ หน่วยงาน หลักสูตร เลขหนังสือ" maxlength="200"></labe' .
            'l><label>ประเภท<select name="type"><option value="">ทุกประเภท</option>');
    foreach (filter_types() as $t) {
        echo '<option value="' .
            $t["id"] .
            '"' .
            selected($t["id"], $values["type"] ?? "") .
            ">" .
            h($t["name"]) .
            "</option>";
    }
    echo '</select></label><label>ปีงบประมาณ<select name="year"><option value="">ทุกปีงบประมาณ</option>';
    foreach (
        rows(
            <<<'SQL'
            SELECT DISTINCT fiscal_year
            FROM record_entries
            ORDER BY fiscal_year DESC
            SQL,
        )
        as $y
    ) {
        echo "<option" .
            selected($y["fiscal_year"], $values["year"] ?? "") .
            ">" .
            $y["fiscal_year"] .
            "</option>";
    }
    echo '</select></label><label>สถานะ<select name="status"><option value="">ทุกสถานะ</option>';
    foreach (STATUS_LABELS as $key => $label) {
        echo '<option value="' .
            $key .
            '"' .
            selected($key, $values["status"] ?? "") .
            ">" .
            h($label) .
            "</option>";
    }
    echo '</select></label><label>จำนวนต่อหน้า<select name="size">';
    foreach ([10, 25, 50, 100] as $size) {
        echo "<option" .
            selected($size, $values["size"] ?? 10) .
            ">" .
            $size .
            "</option>";
    }
    echo '</select></label><div class="filter-actions"><button class="primary">' .
        icon("search") .
        'ค้นหา</button><a class="button" href="' .
        h(url($page)) .
        '">ล้าง</a></div></form>';
}

function entry_fields(array $entry = [], string $kind = "period"): void
{
    echo '<fieldset class="form-section"><legend>ระยะเวลาและสารบรรณ</legend><p class="section-copy">' .
        (isset($entry["id"])
            ? "เลขลำดับรายการ No. " . (int) $entry["id"]
            : "เลขลำดับรายการจะออกให้อัตโนมัติเมื่อบันทึก") .
        '</p><div class="form-grid">';
    echo input_field(
        "ปีงบประมาณ (พ.ศ.)",
        "fiscal_year",
        $entry["fiscal_year"] ?? fiscal_now(),
        "text",
        true,
        4,
    );
    if ($kind === "period") {
        echo input_field(
            "วันที่เริ่ม (พ.ศ.)",
            "start_date",
            $entry["start_date"] ?? "",
            "date",
        ) .
            input_field(
                "วันที่สิ้นสุด (พ.ศ.)",
                "end_date",
                $entry["end_date"] ?? "",
                "date",
            );
    } else {
        echo input_field(
            $kind === "return" ? "วันที่รายงานตัวกลับ (พ.ศ.)" : "วันที่ยกเลิก (พ.ศ.)",
            "action_date",
            $entry["action_date"] ?? date("Y-m-d"),
            "date",
        );
    }
    echo input_field("เลขหนังสือสารบรรณ", "document_no", $entry["document_no"] ?? "") .
        input_field(
            "วันที่หนังสือ (พ.ศ.)",
            "document_date",
            $entry["document_date"] ?? "",
            "date",
        ) .
        input_field(
            "วันที่รับเรื่อง (พ.ศ.)",
            "received_date",
            $entry["received_date"] ?? date("Y-m-d"),
            "date",
        ) .
        input_field("ชื่อผู้ส่งเรื่อง", "sender", $entry["sender"] ?? "");
    echo textarea_field(
        $kind === "cancel" ? "เหตุผลการยกเลิก" : "หมายเหตุเฉพาะช่วง / การคืนเรื่อง",
        "entry_note",
        $entry["note"] ?? "",
        $kind === "cancel",
    ) . "</div></fieldset>";
}
