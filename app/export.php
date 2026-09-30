<?php
// ส่งออก CSV: อ่านชุดข้อมูลเดียวกับทะเบียน ใช้ตัวกรองเดียวกัน และป้องกันข้อความถูกตีความเป็นสูตร
defined("APP_ENTRY") || exit();

function export_view(): never
{
    $ledger = ($_GET["format"] ?? "cases") === "ledger";
    [$where, $params] = filters($ledger);
    $from = "(" . case_select() . ") s JOIN record_entries e ON e.case_id=s.id";
    if (!$ledger) {
        $where .= ($where ? " AND " : " WHERE ") . "e.kind='period'";
        if (
            !empty($_GET["year"]) &&
            is_scalar($_GET["year"]) &&
            ctype_digit((string) $_GET["year"])
        ) {
            $where .= " AND e.fiscal_year=?";
            $params[] = (int) $_GET["year"];
        }
    }
    $stmt = query(
        <<<'SQL'
        SELECT s.*,
        e.id AS entry_id,
        e.kind,
        e.ordinal,
        e.fiscal_year,
        e.start_date,
        e.end_date,
        e.action_date,
        e.document_no,
        e.document_date,
        e.received_date,
        e.sender,
        e.note AS entry_note
        FROM
        SQL
        .
            $from .
            $where .
            " ORDER BY e.received_date DESC,e.id DESC",
        $params,
    );
    header("Content-Type: text/csv; charset=utf-8");
    header(
        'Content-Disposition: attachment; filename="study-leave-' .
            ((int) date("Y") + 543) .
            date("-m-d") .
            '.csv"',
    );
    $out = fopen("php://output", "w");
    fwrite($out, "\xEF\xBB\xBF");
    $head = [
        "No.",
        "รหัสแฟ้ม",
        "วันที่รับเรื่อง (พ.ศ.)",
        "ผู้ส่ง",
        "เลขหนังสือสารบรรณ",
        "วันที่หนังสือ (พ.ศ.)",
        "เรื่อง (โดยสังเขป)",
        "ชื่อ–สกุล",
        "หน่วยงาน",
        "ประเภท",
        "ทุน",
        "หมายเหตุ",
        "ปีงบประมาณ",
        "ประเทศ",
        "สถานะ",
        "วันเริ่ม (พ.ศ.)",
        "วันสิ้นสุด (พ.ศ.)",
        "วันรายงานตัว / ยกเลิก (พ.ศ.)",
    ];
    fputcsv($out, $head, ",", '"', "");
    while ($r = $stmt->fetch()) {
        $cells = [
            $r["entry_id"],
            case_code($r),
            be_date($r["received_date"]),
            $r["sender"],
            $r["document_no"],
            be_date($r["document_date"]),
            entry_subject($r),
            $r["person_name"],
            $r["org_name"],
            $r["type_name"],
            $r["fund"],
            $r["entry_note"],
            $r["fiscal_year"],
            $r["country"] ?: "ไทย",
            STATUS_LABELS[$r["status"]],
            be_date($r["start_date"]),
            be_date($r["end_date"]),
            be_date($r["action_date"]),
        ];
        $cells = array_map(function ($v) {
            $s = (string) ($v ?? "");
            return preg_match("/^\s*[=+@-]/u", $s) ? "'" . $s : $s;
        }, $cells);
        fputcsv($out, $cells, ",", '"', "");
    }
    fclose($out);
    exit();
}
