<?php
// งานแฟ้มลา: ส่วนต้นเป็น SQL อ่าน/ค้นหา ส่วน *_input ตรวจฟอร์ม ส่วน *_action บันทึกและ redirect
declare(strict_types=1);
defined("APP_ENTRY") || exit();
const STATUS_LABELS = [
    "scheduled" => "ยังไม่เริ่ม",
    "active" => "ดำเนินการอยู่",
    "overdue" => "รอรายงานตัว",
    "closed" => "รายงานตัวแล้ว",
    "cancelled" => "ยกเลิก",
];
const ROLE_LABELS = [
    "superAdmin" => "Super Admin",
    "admin" => "Admin",
    "officer" => "เจ้าหน้าที่",
];

function case_select(): string
{
    return <<<'SQL'
    SELECT c.*,
    creator.name AS creator_name,
    editor.name AS editor_name,
    t.name AS type_name,
    t.form_kind,
    co.label AS country,
    u.label AS org_name,
    pt.label AS personnel_name,
    p.label AS position_name,
    l.label AS level_name,
    summary.first_date,
    summary.last_date,
    summary.period_count,
    terminal.kind AS terminal_kind,
    terminal.action_date AS terminal_date,
    CASE
    WHEN terminal.kind='cancel'
    THEN 'cancelled'
    WHEN terminal.kind='return'
    THEN 'closed'
    WHEN summary.first_date>CURDATE()
    THEN 'scheduled'
    WHEN summary.last_date<CURDATE()
    THEN 'overdue'
    ELSE 'active'
    END AS status
    FROM leave_cases c
    LEFT JOIN users creator ON creator.id=c.created_by
    LEFT JOIN users editor ON editor.id=c.updated_by
    JOIN activity_types t
    ON t.id=c.type_id
    JOIN reference_values u
    ON u.id=c.org_id
    LEFT
    JOIN reference_values co
    ON co.id=c.country_id
    JOIN reference_values pt
    ON pt.id=c.personnel_type_id
    JOIN reference_values p
    ON p.id=c.position_id
    JOIN reference_values l
    ON l.id=c.level_id
    JOIN (
        SELECT case_id,
        MIN(start_date) first_date,
        MAX(end_date) last_date,
        COUNT(*) period_count
        FROM record_entries
        WHERE kind='period'
        GROUP BY case_id) summary
    ON summary.case_id=c.id
    LEFT
    JOIN record_entries terminal
    ON terminal.terminal_case_id=c.id
    SQL;
}

function get_case(int $id): array
{
    $r = row(case_select() . " WHERE c.id=?", [$id]);
    if (!$r) {
        http_response_code(404);
        throw new ValidationException("ไม่พบแฟ้มที่ระบุ");
    }
    return $r;
}

function entries(int $caseId): array
{
    return rows(
        <<<'SQL'
        SELECT *
        FROM record_entries
        WHERE case_id=?
        ORDER BY kind='period' DESC,
        ordinal,
        id
        SQL,
        [$caseId],
    );
}
// ฟอร์มใหม่ใช้เฉพาะประเภท active; แฟ้มเก่าคงประเภทเดิมได้แม้ปิดใช้งาน

function types(?int $currentId = null): array
{
    return rows("SELECT * FROM activity_types WHERE active=1 OR id=? ORDER BY id", [
        $currentId ?? 0,
    ]);
}

// ตัวกรองทะเบียนต้องค้นหาเรื่องเก่าของประเภทที่ปิดแล้วได้ด้วย
function filter_types(): array
{
    return rows("SELECT id, name FROM activity_types ORDER BY id");
}

function case_code(array $r): string
{
    return "SL-" . str_pad((string) $r["id"], 6, "0", STR_PAD_LEFT);
}

function filters(bool $ledger = false): array
{
    $params = [];
    $clauses = [];
    $search = trim(is_string($_GET["q"] ?? null) ? $_GET["q"] : "");
    $search = mb_substr($search, 0, 200);
    if ($search !== "") {
        $term = "%" . str_replace(["!", "%", "_"], ["!!", "!%", "!_"], $search) . "%";
        $cols = [
            "s.person_name",
            "s.org_name",
            "s.branch",
            "s.course",
            "s.institution",
            "s.location",
            "s.country",
        ];
        if ($ledger) {
            $cols = array_merge($cols, ["e.document_no", "e.sender", "e.note"]);
        }
        $parts = [];
        foreach ($cols as $col) {
            $parts[] = "$col LIKE ? ESCAPE '!'";
            $params[] = $term;
        }
        if (!$ledger) {
            $parts[] =
                "EXISTS(SELECT 1 FROM record_entries se WHERE se.case_id=s.id AND se.docu" .
                'ment_no LIKE ? ESCAPE \'!\')';
            $params[] = $term;
        }
        $clauses[] = "(" . implode(" OR ", $parts) . ")";
    }
    if (
        !empty($_GET["type"]) &&
        is_scalar($_GET["type"]) &&
        ctype_digit((string) $_GET["type"])
    ) {
        $clauses[] = "s.type_id=?";
        $params[] = (int) $_GET["type"];
    }
    if (
        !empty($_GET["year"]) &&
        is_scalar($_GET["year"]) &&
        ctype_digit((string) $_GET["year"])
    ) {
        $clauses[] = $ledger
            ? "e.fiscal_year=?"
            : "EXISTS(SELECT 1 FROM record_entries sy WHERE sy.case_id=s.id AND sy.kind" .
                '=\'period\' AND sy.fiscal_year=?)';
        $params[] = (int) $_GET["year"];
    }
    if (
        isset($_GET["status"]) &&
        is_string($_GET["status"]) &&
        isset(STATUS_LABELS[$_GET["status"]])
    ) {
        $clauses[] = "s.status=?";
        $params[] = $_GET["status"];
    }
    return [$clauses ? " WHERE " . implode(" AND ", $clauses) : "", $params];
}

function page_data(
    string $from,
    string $where,
    array $params,
    string $select = "*",
    string $order = "id DESC",
    bool $recordSizes = false,
): array {
    $requestedSize = (int) (is_scalar($_GET["size"] ?? null) ? $_GET["size"] : 10);
    $choices = $recordSizes ? [5, 10, 25, 50, 100, 2000] : [10, 25, 50, 100];
    $size = in_array($requestedSize, $choices, true) ? $requestedSize : 10;
    $all = $recordSizes && ($_GET["size"] ?? null) === "all";
    $total = (int) query(
        "SELECT COUNT(*) FROM " . $from . $where,
        $params,
    )->fetchColumn();
    if ($all) {
        $size = max(1, $total);
    }
    $pages = max(1, (int) ceil($total / $size));
    $page = max(1, min($pages, (int) (is_scalar($_GET["p"] ?? null) ? $_GET["p"] : 1)));
    $offset = ($page - 1) * $size;
    return [
        "items" => rows(
            "SELECT $select FROM $from$where ORDER BY $order LIMIT $size OFFSET $offset",
            $params,
        ),
        "total" => $total,
        "page" => $page,
        "pages" => $pages,
        "size" => $size,
        "offset" => $offset,
        "size_choice" => $all ? "all" : $size,
    ];
}

function record_listing(bool $ledger = false): array
{
    [$where, $params] = filters($ledger);
    $from = "(" . case_select() . ") s";
    if ($ledger) {
        $from .= " JOIN record_entries e ON e.case_id=s.id";
    }
    return page_data(
        $from,
        $where,
        $params,
        $ledger
            ? "s.*,e.id AS entry_id,e.kind,e.ordinal,e.fiscal_year,e.start_date,e.end_d" .
                "ate,e.action_date,e.document_no,e.document_date,e.received_date,e.sender" .
                ",e.note AS entry_note"
            : "s.*",
        $ledger ? "e.received_date DESC,e.id DESC" : "s.id DESC",
        true,
    );
}

function entry_subject(array $r): string
{
    $title =
        $r["form_kind"] === "study"
            ? implode(
                " · ",
                array_filter([
                    $r["degree"],
                    $r["branch"],
                    $r["faculty"],
                    $r["institution"],
                ]),
            )
            : $r["course"] . " · " . $r["location"];
    $prefix = match ($r["kind"]) {
        "return" => "รายงานตัวกลับ " . be_date($r["action_date"]),
        "cancel" => "ยกเลิกเรื่อง " . be_date($r["action_date"]),
        default => "ช่วงที่ " .
            $r["ordinal"] .
            " " .
            be_date($r["start_date"]) .
            " – " .
            be_date($r["end_date"]),
    };
    return $prefix . " · " . $title . ($r["country"] ? " · " . $r["country"] : "");
}
/**
 * แปลง POST เป็นข้อมูลแฟ้ม และตรวจกลุ่ม/สถานะอ้างอิงฝั่งเซิร์ฟเวอร์
 * $existing มาจากฐานข้อมูลที่ล็อกแล้วเท่านั้น ไม่เชื่อ id เดิมที่ผู้ใช้ส่งมา
 */

function metadata_input(array $existing = []): array
{
    $typeId = positive_id($_POST["type_id"] ?? null);
    $type = row("SELECT * FROM activity_types WHERE id = ?", [$typeId]);
    $sameType = isset($existing["type_id"]) && (int) $existing["type_id"] === $typeId;
    if (!$type || (!$type["active"] && !$sameType)) {
        throw new ValidationException("กรุณาเลือกประเภทการบันทึกที่เปิดใช้งาน");
    }
    $data = ["type_id" => $typeId, "person_name" => text_input("person_name")];
    $groups = [
        "org_id" => "org",
        "personnel_type_id" => "personnel",
        "position_id" => "position",
        "level_id" => "level",
    ];
    foreach ($groups as $field => $group) {
        $data[$field] = reference_input($field, $group, $existing);
    }
    $data["destination"] = text_input("destination", 20);
    if (!in_array($data["destination"], ["domestic", "foreign"], true)) {
        throw new ValidationException("ขอบเขตไม่ถูกต้อง");
    }
    $data["country_id"] =
        $data["destination"] === "foreign"
            ? reference_input("country_id", "country", $existing)
            : null;
    $data["fund"] = text_input("fund", 200, false);
    foreach (
        ["degree", "branch", "faculty", "institution", "course", "location"]
        as $key
    ) {
        $needed =
            $type["form_kind"] === "study"
                ? in_array($key, ["degree", "branch", "faculty", "institution"], true)
                : in_array($key, ["course", "location"], true);
        $data[$key] = !$needed
            ? ""
            : ($key === "course"
                ? course_reference_input($existing)
                : text_input($key));
    }
    $data["note"] = text_input("note", 2000, false);
    return $data;
}

function entry_input(string $kind): array
{
    $d = [
        "kind" => $kind,
        "fiscal_year" => fiscal_input(),
        "document_no" => text_input("document_no"),
        "document_date" => date_input("document_date"),
        "received_date" => date_input("received_date"),
        "sender" => text_input("sender"),
        "note" => text_input("entry_note", 2000, $kind === "cancel"),
    ];
    if ($kind === "period") {
        $d["start_date"] = date_input("start_date");
        $d["end_date"] = date_input("end_date");
        $d["action_date"] = null;
        if ($d["end_date"] < $d["start_date"]) {
            throw new ValidationException("วันที่สิ้นสุดต้องไม่ก่อนวันที่เริ่ม");
        }
    } else {
        $d["start_date"] = null;
        $d["end_date"] = null;
        $d["action_date"] = date_input("action_date");
        if ($d["action_date"] > date("Y-m-d")) {
            throw new ValidationException("วันที่รายงานตัวหรือยกเลิกต้องไม่เกินวันนี้");
        }
    }
    return $d;
}

function insert_data(string $table, array $data): int
{
    // All table/column identifiers originate exclusively from service code, never from request parameters.
    $cols = implode(",", array_keys($data));
    query(
        "INSERT INTO $table($cols) VALUES(" .
            implode(",", array_fill(0, count($data), "?")) .
            ")",
        array_values($data),
    );
    return (int) db()->lastInsertId();
}

function update_data(string $table, int $id, array $data): void
{
    query(
        "UPDATE $table SET " .
            implode(",", array_map(fn($k) => "$k=?", array_keys($data))) .
            ",updated_at=NOW() WHERE id=?",
        [...array_values($data), $id],
    );
}

function lock_case(int $id): array
{
    $c = row("SELECT * FROM leave_cases WHERE id=? FOR UPDATE", [$id]);
    if (!$c) {
        throw new ValidationException("ไม่พบแฟ้ม");
    }
    if ((int) text_input("revision", 12) !== (int) $c["revision"]) {
        throw new ValidationException(
            "มีผู้อื่นแก้ไขแฟ้มนี้แล้ว กรุณาเปิดข้อมูลล่าสุดก่อนบันทึกใหม่",
        );
    }
    return $c;
}

function touch_case(int $id): void
{
    query(
        <<<'SQL'
        UPDATE leave_cases
        SET revision=revision+1,
        updated_by=?,
        updated_at=NOW()
        WHERE id=?
        SQL,
        [actor(), $id],
    );
}
/**
 * บันทึกแฟ้มพร้อมช่วงแรกใน transaction เดียวกัน
 * เมื่อแก้ไข จะตรวจ revision ก่อน เพื่อไม่เขียนทับงานของอีกคน
 */

function case_save_action(): void
{
    $id = empty($_POST["id"]) ? null : positive_id($_POST["id"]);
    $id = transaction(function () use ($id): int {
        $old = $id ? lock_case($id) : [];
        if (
            $id &&
            row("SELECT id FROM record_entries WHERE terminal_case_id = ?", [$id])
        ) {
            allowed(["admin", "superAdmin"]);
        }
        $data = metadata_input($old);
        if ($id) {
            if ((int) $old["type_id"] !== $data["type_id"]) {
                throw new ValidationException(
                    "ไม่สามารถเปลี่ยนประเภทของเรื่องที่สร้างแล้ว",
                );
            }
            update_data("leave_cases", $id, $data + ["updated_by" => actor()]);
            touch_case($id);
            audit("case_updated", "case", $id, "แก้ไขแฟ้มเรื่อง", $old, $data);
        } else {
            $period = entry_input("period");
            $id = insert_data(
                "leave_cases",
                $data + ["created_by" => actor(), "updated_by" => actor()],
            );
            $entryId = insert_data(
                "record_entries",
                $period + [
                    "case_id" => $id,
                    "ordinal" => 1,
                    "created_by" => actor(),
                    "updated_by" => actor(),
                ],
            );
            audit(
                "case_created",
                "case",
                $id,
                "สร้างแฟ้มและช่วงแรก No. " . $entryId,
                null,
                $data + ["entry" => $period, "entry_id" => $entryId],
            );
        }
        return $id;
    });
    flash("บันทึกแฟ้มแล้ว");
    redirect("detail", ["id" => $id]);
}

function period_save_action(): void
{
    $caseId = positive_id($_POST["case_id"] ?? null);
    $entryId = empty($_POST["entry_id"]) ? null : positive_id($_POST["entry_id"]);
    $data = entry_input("period");
    transaction(function () use ($caseId, $entryId, $data) {
        lock_case($caseId);
        $terminal = row("SELECT * FROM record_entries WHERE terminal_case_id=?", [
            $caseId,
        ]);
        if ($terminal && !$entryId) {
            throw new ValidationException("เรื่องที่ปิดหรือยกเลิกแล้วเพิ่มช่วงไม่ได้");
        }
        if ($terminal) {
            allowed(["admin", "superAdmin"]);
        }
        $old = $entryId
            ? row(
                <<<'SQL'
                SELECT *
                FROM record_entries
                WHERE id=?
                AND case_id=?
                AND kind='period'
                SQL,
                [$entryId, $caseId],
            )
            : null;
        if ($entryId && !$old) {
            throw new ValidationException("ช่วงการลาไม่อยู่ในแฟ้มนี้");
        }
        $ordinal = $old
            ? (int) $old["ordinal"]
            : (int) query(
                <<<'SQL'
                SELECT COALESCE(MAX(ordinal),
                    0)+1
                FROM record_entries
                WHERE case_id=?
                AND kind='period'
                SQL,
                [$caseId],
            )->fetchColumn();
        $previous = row(
            <<<'SQL'
            SELECT end_date
            FROM record_entries
            WHERE case_id=?
            AND kind='period'
            AND ordinal<?
            ORDER BY ordinal DESC
            LIMIT 1
            SQL,
            [$caseId, $ordinal],
        );
        $next = row(
            <<<'SQL'
            SELECT start_date
            FROM record_entries
            WHERE case_id=?
            AND kind='period'
            AND ordinal>?
            ORDER BY ordinal
            LIMIT 1
            SQL,
            [$caseId, $ordinal],
        );
        if (
            ($previous && $data["start_date"] <= $previous["end_date"]) ||
            ($next && $data["end_date"] >= $next["start_date"])
        ) {
            throw new ValidationException(
                "ช่วงการลาทับซ้อนหรือสลับลำดับกับช่วงก่อนหน้าหรือถัดไป",
            );
        }
        if (
            $terminal &&
            $terminal["kind"] === "return" &&
            $data["start_date"] > $terminal["action_date"]
        ) {
            throw new ValidationException("วันเริ่มช่วงต้องไม่หลังวันรายงานตัวกลับ");
        }
        if ($entryId) {
            update_data("record_entries", $entryId, $data + ["updated_by" => actor()]);
        } else {
            $entryId = insert_data(
                "record_entries",
                $data + [
                    "case_id" => $caseId,
                    "ordinal" => $ordinal,
                    "created_by" => actor(),
                    "updated_by" => actor(),
                ],
            );
        }
        touch_case($caseId);
        audit(
            $old ? "period_updated" : "period_created",
            "entry",
            $entryId,
            "ช่วงที่ " . $ordinal . " ของแฟ้ม " . $caseId,
            $old,
            $data,
        );
    });
    flash("บันทึกช่วงการลาแล้ว");
    redirect("detail", ["id" => $caseId]);
}

function terminal_save_action(string $kind): void
{
    $caseId = positive_id($_POST["case_id"] ?? null);
    $entryId = empty($_POST["entry_id"]) ? null : positive_id($_POST["entry_id"]);
    if ($entryId) {
        allowed(["admin", "superAdmin"]);
        if ($kind !== "return") {
            throw new ValidationException("แก้ไขได้เฉพาะรายการรายงานตัวกลับ");
        }
    }
    $data = entry_input($kind);
    transaction(function () use ($caseId, $data, $kind, $entryId) {
        lock_case($caseId);
        $terminal = row("SELECT * FROM record_entries WHERE terminal_case_id=?", [
            $caseId,
        ]);
        if (
            $entryId &&
            (!$terminal ||
                (int) $terminal["id"] !== $entryId ||
                $terminal["kind"] !== "return")
        ) {
            throw new ValidationException("ไม่พบรายการรายงานตัวกลับในแฟ้มนี้");
        }
        if (!$entryId && $terminal) {
            throw new ValidationException("เรื่องนี้รายงานตัวหรือยกเลิกแล้ว");
        }
        $latest = row(
            <<<'SQL'
            SELECT start_date
            FROM record_entries
            WHERE case_id=?
            AND kind='period'
            ORDER BY ordinal DESC
            LIMIT 1
            SQL,
            [$caseId],
        );
        if (
            $kind === "return" &&
            (!$latest || $data["action_date"] < $latest["start_date"])
        ) {
            throw new ValidationException("วันรายงานตัวต้องไม่ก่อนวันเริ่มช่วงล่าสุด");
        }
        if ($entryId) {
            $id = $entryId;
            update_data("record_entries", $id, $data + ["updated_by" => actor()]);
        } else {
            $id = insert_data(
                "record_entries",
                $data + [
                    "case_id" => $caseId,
                    "ordinal" => null,
                    "created_by" => actor(),
                    "updated_by" => actor(),
                ],
            );
        }
        touch_case($caseId);
        audit(
            $entryId
                ? "return_updated"
                : ($kind === "return"
                    ? "case_returned"
                    : "case_cancelled"),
            "entry",
            $id,
            $entryId
                ? "แก้ไขรายงานตัวกลับ"
                : ($kind === "return"
                    ? "รายงานตัวกลับ"
                    : "ยกเลิกเรื่อง"),
            $entryId ? $terminal : null,
            $data,
        );
    });
    flash(
        $kind === "return"
            ? "บันทึกรายงานตัวกลับแล้ว"
            : "ยกเลิกเรื่องแล้ว ประวัติเดิมยังอยู่",
    );
    redirect("detail", ["id" => $caseId]);
}

function type_save_action(): void
{
    allowed(["admin", "superAdmin"]);
    $name = text_input("name", 120);
    $kind = text_input("form_kind", 10);
    if (!in_array($kind, ["study", "course"], true)) {
        throw new ValidationException("ชนิดแบบฟอร์มไม่ถูกต้อง");
    }
    transaction(function () use ($name, $kind) {
        if (row("SELECT id FROM activity_types WHERE name=?", [$name])) {
            throw new ValidationException("ชื่อประเภทซ้ำ");
        }
        $id = insert_data("activity_types", [
            "name" => $name,
            "form_kind" => $kind,
            "created_by" => actor(),
            "updated_by" => actor(),
        ]);
        audit("type_created", "activity_type", $id, "เพิ่มประเภท " . $name);
    });
    flash("เพิ่มประเภทแล้ว");
    redirect("settings", ["group" => "activity"]);
}
