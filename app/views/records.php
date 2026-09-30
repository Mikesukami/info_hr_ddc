<?php
defined("APP_ENTRY") || exit();

function registry_view(bool $ledger): void
{
    $page = $ledger ? "ledger" : "registry";
    $data = record_listing($ledger);
    $filter = array_filter($_GET, fn($v) => is_scalar($v));
    unset($filter["page"], $filter["p"]);
    heading(
        $ledger ? "ทะเบียนแบบ Excel" : "ทะเบียนการลา",
        $ledger
            ? "หนึ่งแถวต่อช่วงการลา รายงานตัวกลับ หรือยกเลิกเรื่อง · No. ออกให้อัตโนมัติ"
            : "หนึ่งเรื่อง หนึ่งแฟ้มประวัติ พร้อมช่วงการลาต่อเนื่อง",
        '<a class="button" href="' .
            h(url("export", $filter + ["format" => $ledger ? "ledger" : "cases"])) .
            '">' .
            icon("download") .
            'ส่งออกตามตัวกรอง</a><a class="button primary" href="' .
            h(url("case_form")) .
            '">' .
            icon("plus") .
            "เพิ่มรายการ</a>",
    );
    if (!$ledger) {
        metrics_view();
    }
    echo '<section class="panel listing-panel">';
    filter_form($page);
    if (!$data["items"]) {
        echo '<div class="empty"><h2>ยังไม่มีรายการที่ตรงกับเงื่อนไข</h2><p>เพิ่มเรื่อ' .
            "งใหม่ หรือปรับคำค้นและตัวกรอง</p></div>";
        paginate($data);
        echo "</section>";
        return;
    }
    echo '<div class="' .
        ($ledger ? "ledger-scroll" : "server-table-scroll") .
        '" tabindex="0" role="region" aria-label="ตารางทะเบียน เลื่อนซ้ายขวาได้"><table class="' .
        ($ledger ? "ledger-table" : "server-table") .
        '"><thead><tr>';
    $headers = $ledger
        ? [
            "No.",
            "ว ด ป / ชื่อผู้ส่ง",
            "สารบรรณ No. / ลงวันที่",
            "เรื่อง (โดยสังเขป)",
            "ชื่อ–สกุล / หน่วยงาน",
            "ประเภท / ทุน",
            "หมายเหตุ / คืนเรื่อง",
            "ปีงบ",
            "ประเทศ",
            "สถานะ",
            "จัดการ",
        ]
        : ["บุคลากร / หน่วยงาน", "เรื่อง / สถานที่", "ระยะเวลา", "สถานะ", "จัดการ"];
    foreach ($headers as $label) {
        echo '<th scope="col">' . h($label) . "</th>";
    }
    echo "</tr></thead><tbody>";
    foreach ($data["items"] as $r) {
        $detail =
            '<a class="person" href="' .
            h(url("detail", ["id" => $r["id"]])) .
            '">' .
            h($r["person_name"]) .
            '</a><span class="subline">' .
            h($r["org_name"]) .
            "</span>";
        echo "<tr>";
        if ($ledger) {
            echo "<td>" .
                (int) $r["entry_id"] .
                '<span class="subline">' .
                h(case_code($r)) .
                "</span></td><td>" .
                h(be_date($r["received_date"])) .
                '<span class="subline">' .
                h($r["sender"]) .
                "</span></td><td>" .
                h($r["document_no"]) .
                '<span class="subline">' .
                h(be_date($r["document_date"])) .
                "</span></td><td>" .
                h(entry_subject($r)) .
                "</td><td>" .
                $detail .
                "</td><td>" .
                h($r["type_name"]) .
                '<span class="subline">' .
                h($r["fund"]) .
                '</span></td><td class="preserve-lines">' .
                h($r["entry_note"]) .
                "</td><td>" .
                $r["fiscal_year"] .
                "</td><td>" .
                h($r["country"] ?: "ไทย") .
                "</td>";
        } else {
            echo '<td><span class="record-id">' .
                h(case_code($r)) .
                "</span>" .
                $detail .
                '</td><td><span class="type-label">' .
                h($r["type_name"]) .
                " · " .
                ($r["destination"] === "foreign" ? "ต่างประเทศ" : "ในประเทศ") .
                "</span><div>" .
                h($r["form_kind"] === "study" ? $r["branch"] : $r["course"]) .
                '</div><span class="subline">' .
                h($r["form_kind"] === "study" ? $r["institution"] : $r["location"]) .
                "</span></td><td>" .
                h(be_date($r["first_date"])) .
                '<span class="subline">ถึง ' .
                h(be_date($r["last_date"])) .
                '</span><span class="subline">' .
                $r["period_count"] .
                " ช่วงการลา</span></td>";
        }
        echo "<td>" . badge_html($r["status"]) . "</td><td>";
        if ($ledger && $r["kind"] === "period" && (!$r["terminal_kind"] || is_admin())) {
            echo '<a class="button compact" href="' .
                h(url("period_form", ["id" => $r["id"], "entry_id" => $r["entry_id"]])) .
                '">แก้ไขช่วง</a>';
        } else {
            echo '<a class="button compact" href="' .
                h(url("detail", ["id" => $r["id"]])) .
                '">เปิดแฟ้ม</a>';
        }
        echo "</td></tr>";
    }
    echo "</tbody></table></div>";
    paginate($data);
    echo "</section>";
}

function metrics_view(): void
{
    $stats = rows(
        "SELECT status,COUNT(*) total FROM (" . case_select() . ") s GROUP BY status",
    );
    $counts = array_fill_keys(array_keys(STATUS_LABELS), 0);
    foreach ($stats as $s) {
        $counts[$s["status"]] = (int) $s["total"];
    }
    echo '<section class="metrics" aria-label="ภาพรวมทุกปีงบ">';
    foreach (
        [
            "ทั้งหมด" => array_sum($counts),
            "ดำเนินการอยู่" => $counts["active"],
            "รอรายงานตัว" => $counts["overdue"],
            "รายงานตัวแล้ว" => $counts["closed"],
        ]
        as $label => $count
    ) {
        echo '<div class="metric"><div class="metric-label">' .
            h($label) .
            '</div><div class="metric-value">' .
            $count .
            "<span>เรื่อง</span></div><small>รวมทุกปีงบประมาณ</small></div>";
    }
    echo "</section>";
}

function overview_view(): void
{
    heading("ภาพรวม", "ติดตามทะเบียนและกำหนดรายงานตัวกลับ");
    metrics_view();
    $due = rows(
        "SELECT * FROM (" .
            case_select() .
            (") s WHERE terminal_kind IS NULL AND last_date<=DATE_ADD(CURDATE(),INTERV" .
                "AL 30 DAY) ORDER BY last_date LIMIT 10"),
    );
    echo '<section class="panel"><h2>กำหนดกลับภายใน 30 วันและเกินกำหนด</h2>';
    foreach ($due as $r) {
        echo '<div class="report-row"><div><a class="person" href="' .
            h(url("detail", ["id" => $r["id"]])) .
            '">' .
            h($r["person_name"]) .
            "</a><small>" .
            h($r["type_name"]) .
            " · สิ้นสุด " .
            h(be_date($r["last_date"])) .
            "</small></div>" .
            badge_html($r["status"]) .
            "</div>";
    }
    if (!$due) {
        echo '<p class="section-copy">ไม่มีรายการที่ต้องติดตามในช่วงนี้</p>';
    }
    echo "</section>";
}

function detail_view(): void
{
    $r = get_case(positive_id($_GET["id"] ?? null));
    $terminal = (bool) $r["terminal_kind"];
    $actions = '<a class="button" href="' . h(url("registry")) . '">กลับทะเบียน</a>';
    if (!$terminal || is_admin()) {
        $actions .=
            '<a class="button" href="' .
            h(url("case_form", ["id" => $r["id"]])) .
            '">' .
            icon("pencil") .
            "แก้ไขข้อมูล</a>";
    }
    if (!$terminal) {
        $actions .=
            '<a class="button" href="' .
            h(url("period_form", ["id" => $r["id"]])) .
            '">เพิ่มช่วงการลา</a><a class="button primary" href="' .
            h(url("return_form", ["id" => $r["id"]])) .
            '">รายงานตัวกลับ</a><a class="button danger-quiet" href="' .
            h(url("cancel_form", ["id" => $r["id"]])) .
            '">ยกเลิกเรื่อง</a>';
    }
    heading($r["person_name"], case_code($r) . " · " . $r["type_name"], $actions);
    echo '<section class="panel">' .
        badge_html($r["status"]) .
        '<dl class="detail-grid">';
    $fields = [
        "หน่วยงาน" => $r["org_name"],
        "ประเภทบุคลากร" => $r["personnel_name"],
        "ตำแหน่ง / ระดับ" => $r["position_name"] . " " . $r["level_name"],
        "ขอบเขต" =>
            $r["destination"] === "foreign"
                ? "ต่างประเทศ · " . $r["country"]
                : "ในประเทศ",
        "ทุน" => $r["fund"],
    ];
    if ($r["form_kind"] === "study") {
        $fields += [
            "วุฒิ / สาขา" => $r["degree"] . " · " . $r["branch"],
            "คณะ / มหาวิทยาลัย" => $r["faculty"] . " · " . $r["institution"],
        ];
    } else {
        $fields += ["หลักสูตร" => $r["course"], "สถานที่" => $r["location"]];
    }
    $fields["หมายเหตุ"] = $r["note"];
    foreach ($fields as $key => $value) {
        echo "<div><dt>" . h($key) . "</dt><dd>" . h($value ?: "—") . "</dd></div>";
    }
    echo '</dl><h2>ประวัติการลา</h2><div class="timeline">';
    foreach (entries((int) $r["id"]) as $e) {
        echo '<article class="timeline-item"><div class="period-heading"><strong>No. ' .
            $e["id"] .
            " · " .
            match ($e["kind"]) {
                "return" => "รายงานตัวกลับ",
                "cancel" => "ยกเลิกเรื่อง",
                default => "ช่วงที่ " . $e["ordinal"],
            } .
            " · ปีงบ " .
            $e["fiscal_year"] .
            "</strong>";
        if ($e["kind"] === "period" && (!$terminal || is_admin())) {
            echo '<a class="button compact" href="' .
                h(url("period_form", ["id" => $r["id"], "entry_id" => $e["id"]])) .
                '">แก้ไขช่วงนี้</a>';
        }
        echo "</div><p>" .
            ($e["kind"] === "period"
                ? h(be_date($e["start_date"]) . " – " . be_date($e["end_date"]))
                : h(be_date($e["action_date"]))) .
            "</p><p>" .
            h($e["document_no"]) .
            " · ลงวันที่ " .
            h(be_date($e["document_date"])) .
            "</p><small>รับเรื่อง " .
            h(be_date($e["received_date"])) .
            " · ผู้ส่ง " .
            h($e["sender"]) .
            '</small><p class="preserve-lines">' .
            h($e["note"]) .
            "</p></article>";
    }
    echo "</div><small>สร้าง " .
        h(be_time($r["created_at"])) .
        " · แก้ไขล่าสุด " .
        h(be_time($r["updated_at"])) .
        "</small></section>";
}

function case_form_view(): void
{
    $r = empty($_GET["id"]) ? null : get_case(positive_id($_GET["id"]));
    if ($r && $r["terminal_kind"]) {
        allowed(["admin", "superAdmin"]);
    }
    heading(
        $r ? "แก้ไขข้อมูลแฟ้ม" : "เพิ่มรายการใหม่",
        "เลขลำดับรายการออกให้อัตโนมัติ ไม่ต้องกรอกเอง",
    );
    echo '<section class="panel form-page">' . form_start("case_save");
    if ($r) {
        echo hidden("id", $r["id"]) . hidden("revision", $r["revision"]);
    }
    echo '<fieldset class="form-section"><legend>01 / ประเภทและบุคลากร</legend><div class="form-grid">';
    if ($r) {
        echo hidden("type_id", $r["type_id"]) .
            "<p>ประเภท: " .
            h($r["type_name"]) .
            "</p>";
    } else {
        echo select_field(
            "ประเภทการบันทึก",
            "type_id",
            ["" => "เลือกประเภท"] + array_column(types(), "name", "id"),
        );
    }
    echo select_field(
        "ขอบเขต",
        "destination",
        ["domestic" => "ในประเทศ", "foreign" => "ต่างประเทศ"],
        $r["destination"] ?? "domestic",
    );
    echo '<div id="countryField">' .
        reference_field("ประเทศ", "country_id", "country", $r["country_id"] ?? "") .
        "</div>";
    echo input_field(
        "ชื่อ–นามสกุล พร้อมคำนำหน้า",
        "person_name",
        $r["person_name"] ?? "",
    );
    echo reference_field("สำนัก/กอง", "org_id", "org", $r["org_id"] ?? "") .
        reference_field(
            "ประเภทบุคลากร",
            "personnel_type_id",
            "personnel",
            $r["personnel_type_id"] ?? "",
        ) .
        reference_field("ตำแหน่ง", "position_id", "position", $r["position_id"] ?? "") .
        reference_field("ระดับตำแหน่ง", "level_id", "level", $r["level_id"] ?? "") .
        input_field("ประเภททุน", "fund", $r["fund"] ?? "", "text", false);
    echo '</div></fieldset><fieldset class="form-section" id="studyFields"><legend' .
        '>02 / รายละเอียดการศึกษา</legend><div class="form-grid">';
    foreach (
        [
            "degree" => "วุฒิการศึกษา",
            "branch" => "สาขา",
            "faculty" => "คณะ",
            "institution" => "มหาวิทยาลัย / สถานศึกษา",
        ]
        as $key => $label
    ) {
        echo input_field($label, $key, $r[$key] ?? "");
    }
    echo '</div></fieldset><fieldset class="form-section" id="courseFields"><legen' .
        'd>02 / รายละเอียดหลักสูตร</legend><div class="form-grid">' .
        course_reference_field($r["course"] ?? "") .
        input_field("สถานที่", "location", $r["location"] ?? "") .
        "</div></fieldset>";
    echo '<div id="typeKinds" hidden>';
    foreach (types($r ? (int) $r["type_id"] : null) as $t) {
        echo '<span data-type-id="' .
            $t["id"] .
            '" data-form-kind="' .
            h($t["form_kind"]) .
            '"></span>';
    }
    echo "</div>";
    if (!$r) {
        entry_fields();
    }
    echo textarea_field("หมายเหตุแฟ้ม", "note", $r["note"] ?? "");
    echo form_end(
        "บันทึกแฟ้ม",
        $r ? "detail" : "registry",
        $r ? ["id" => $r["id"]] : [],
    ) . "</section>";
}

function period_form_view(string $kind): void
{
    $r = get_case(positive_id($_GET["id"] ?? null));
    $entry = null;
    if (!empty($_GET["entry_id"])) {
        $entry = row(
            <<<'SQL'
            SELECT *
            FROM record_entries
            WHERE id=?
            AND case_id=?
            AND kind='period'
            SQL,
            [positive_id($_GET["entry_id"]), $r["id"]],
        );
        if (!$entry) {
            throw new ValidationException("ไม่พบช่วงในแฟ้มนี้");
        }
    }
    if ($r["terminal_kind"]) {
        if (!$entry) {
            throw new ValidationException("เรื่องนี้ปิดแล้ว ไม่สามารถเพิ่มรายการได้");
        }
        allowed(["admin", "superAdmin"]);
    }
    $title =
        $kind === "period"
            ? ($entry
                ? "แก้ไขช่วงการลา"
                : "เพิ่มช่วงการลา")
            : ($kind === "return"
                ? "รายงานตัวกลับ"
                : "ยกเลิกเรื่อง");
    heading($title, $r["person_name"] . " · " . case_code($r));
    echo '<section class="panel form-page">' .
        form_start($kind === "period" ? "period_save" : $kind . "_save") .
        hidden("case_id", $r["id"]) .
        hidden("revision", $r["revision"]);
    if ($entry) {
        echo hidden("entry_id", $entry["id"]);
    }
    if ($kind !== "period") {
        echo '<div class="notice">เมื่อบันทึกแล้ว เรื่องนี้จะปิดและเพิ่มช่วงใหม่ไม่ได้' .
            " ประวัติเดิมยังคงอยู่</div>";
    }
    entry_fields($entry ?? [], $kind);
    echo form_end("บันทึก" . $title, "detail", ["id" => $r["id"]]) . "</section>";
}

function reports_view(): void
{
    heading("รายงานและสถิติ", "นับแฟ้มเรื่องต่อปีงบประมาณ เฉพาะช่วงการลา");
    $from =
        "(SELECT e.fiscal_year,COUNT(DISTINCT e.case_id) case_count,COUNT(*) entr" .
        'y_count FROM record_entries e WHERE e.kind=\'period\' GROUP BY e.fiscal_ye' .
        "ar) report";
    $data = page_data($from, "", [], "*", "fiscal_year DESC");
    echo '<div class="notice">หนึ่งเรื่องอาจปรากฏหลายปีงบประมาณ ยอดรวมรายปีจึงไม่ใ' .
        'ช่จำนวนบุคลากรที่ไม่ซ้ำ</div><section class="panel"><div class="server-t' .
        'able-scroll"><table class="server-table"><thead><tr><th>ปีงบประมาณ</th><' .
        "th>จำนวนเรื่อง</th><th>จำนวนช่วงการลา</th></tr></thead><tbody>";
    foreach ($data["items"] as $r) {
        echo "<tr><td>" .
            $r["fiscal_year"] .
            "</td><td>" .
            $r["case_count"] .
            "</td><td>" .
            $r["entry_count"] .
            "</td></tr>";
    }
    echo "</tbody></table></div>";
    paginate($data);
    echo "</section>";
}
