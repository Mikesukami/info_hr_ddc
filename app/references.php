<?php
declare(strict_types=1);
defined("APP_ENTRY") || exit();

/**
 * ข้อมูลอ้างอิง: อ่านฐานข้อมูลและตรวจเงื่อนไขก่อนบันทึก
 * หน้าจออยู่ app/views/settings.php; index.php เป็นผู้ตรวจสิทธิ์และเรียก action
 * org ใช้ต้นไม้เดียวกันสำหรับสังกัดตามกฎหมาย/ตามปฏิบัติในอนาคต
 */
const REFERENCE_GROUPS = [
    "org" => "สำนัก/กองและหน่วยงานย่อย",
    "personnel" => "ประเภทบุคลากร",
    "position" => "ตำแหน่ง",
    "level" => "ระดับตำแหน่ง",
    "country" => "ประเทศ",
    "course" => "หลักสูตรฝึกอบรม / เรื่อง",
];

/** หลักสูตรเก็บชื่อในแฟ้มเป็น snapshot เพื่อไม่เปลี่ยนรายงานและประวัติเดิม */
function course_reference_input(array $existing): string
{
    $choice = $_POST["course_id"] ?? null;
    // ค่า legacy ใช้ได้เฉพาะชื่อเดิมที่อ่านจากฐานข้อมูล ไม่รับชื่อจาก POST
    if ($choice === "legacy" && !empty($existing["course"])) {
        return $existing["course"];
    }
    $id = positive_id($choice);
    $course = row("SELECT * FROM reference_values WHERE id=?", [$id]);
    if (
        !$course ||
        $course["group_name"] !== "course" ||
        (!$course["active"] && $course["label"] !== ($existing["course"] ?? ""))
    ) {
        throw new ValidationException("กรุณาเลือกหลักสูตรที่เปิดใช้งานจากข้อมูลอ้างอิง");
    }
    return $course["label"];
}

function reference_group(mixed $value): string
{
    if (!is_string($value) || !isset(REFERENCE_GROUPS[$value])) {
        throw new ValidationException("กลุ่มข้อมูลอ้างอิงไม่ถูกต้อง");
    }
    return $value;
}

/** ตัวเลือกในฟอร์ม: ใช้เฉพาะ active; เรื่องเก่าแสดงค่าเดิมที่ปิดแล้วได้ */
function references(string $group, ?int $currentId = null): array
{
    reference_group($group);
    $rootOnly = $group === "org" ? " AND parent_id IS NULL" : "";
    return rows(
        "SELECT id, label, active FROM reference_values
         WHERE group_name = ? AND (active = 1 OR id = ?) $rootOnly
         ORDER BY label, id",
        [$group, $currentId ?? 0],
    );
}

/** ตรวจ id กับกลุ่มเสมอ เพื่อกันการส่ง id ประเทศมาใส่ช่องสำนัก/กอง */
function reference_input(string $field, string $group, array $existing): int
{
    $id = positive_id($_POST[$field] ?? null);
    $value = row("SELECT * FROM reference_values WHERE id = ?", [$id]);
    $unchanged = isset($existing[$field]) && (int) $existing[$field] === $id;

    if (
        !$value ||
        $value["group_name"] !== $group ||
        (!$value["active"] && !$unchanged) ||
        ($group === "org" && $value["parent_id"] !== null)
    ) {
        throw new ValidationException(
            "กรุณาเลือกข้อมูลอ้างอิงที่เปิดใช้งานและตรงกับช่องที่กรอก",
        );
    }
    return $id;
}

/** อ่านต้นไม้ให้เป็นรายการพร้อมเส้นทาง เพื่อให้ชื่อกลุ่มซ้ำต่างกองแยกกันได้ */
function organization_tree(): array
{
    $all = rows(
        <<<'SQL'
        SELECT *
        FROM reference_values
        WHERE group_name = 'org'
        ORDER BY label,
        id
        SQL,
    );
    $children = [];
    foreach ($all as $item) {
        $children[(int) ($item["parent_id"] ?? 0)][] = $item;
    }
    $result = [];
    $walk = function (int $parent, string $path, bool $ancestorsActive) use (
        &$walk,
        &$result,
        $children,
    ): void {
        foreach ($children[$parent] ?? [] as $item) {
            $item["path"] =
                $path === "" ? $item["label"] : $path . " / " . $item["label"];
            $item["available"] = $ancestorsActive && (bool) $item["active"];
            $result[] = $item;
            $walk((int) $item["id"], $item["path"], $item["available"]);
        }
    };
    $walk(0, "", true);
    return $result;
}

/** เพิ่มเท่านั้น: ไม่ย้ายพ่อแม่หรือเปลี่ยนชื่อที่ถูกใช้อ้างอิงในเรื่องเก่า */
function reference_create_action(): void
{
    allowed(["admin", "superAdmin"]);
    $group = reference_group($_POST["group"] ?? null);
    $label = text_input("label", 200);
    $parentId = empty($_POST["parent_id"]) ? null : positive_id($_POST["parent_id"]);

    try {
        transaction(function () use ($group, $label, $parentId): void {
            $depth = 0;
            if ($parentId !== null) {
                if ($group !== "org") {
                    throw new ValidationException(
                        "เฉพาะหน่วยงานเท่านั้นที่มีหน่วยงานแม่ได้",
                    );
                }
                // ล็อกแม่ตัวเดียวกับการปิดใช้งาน ป้องกันเพิ่มลูกพร้อมปิดแม่
                $parent = row("SELECT * FROM reference_values WHERE id = ? FOR UPDATE", [
                    $parentId,
                ]);
                if (!$parent || $parent["group_name"] !== "org" || !$parent["active"]) {
                    throw new ValidationException("กรุณาเลือกหน่วยงานแม่ที่เปิดใช้งาน");
                }
                $depth = (int) $parent["org_depth"] + 1;
                if ($depth > 5) {
                    throw new ValidationException(
                        "รองรับต่ำกว่าสำนัก/กองไม่เกิน 5 ระดับ",
                    );
                }
            }
            $duplicate = row(
                <<<'SQL'
                SELECT id
                FROM reference_values
                WHERE group_name = ?
                AND scope_parent_id = ?
                AND label = ?
                SQL,
                [$group, $parentId ?? 0, $label],
            );
            if ($duplicate) {
                throw new ValidationException(
                    "ชื่อนี้มีอยู่แล้วในกลุ่มและหน่วยงานแม่เดียวกัน รวมรายการที่ปิดใช้งาน",
                );
            }
            $data = [
                "group_name" => $group,
                "label" => $label,
                "parent_id" => $parentId,
                "org_depth" => $depth,
                "active" => 1,
                "created_by" => actor(),
                "updated_by" => actor(),
            ];
            $id = insert_data("reference_values", $data);
            audit(
                "reference_created",
                "reference",
                $id,
                "เพิ่ม" . REFERENCE_GROUPS[$group] . ": " . $label,
                null,
                $data,
            );
        });
    } catch (PDOException $error) {
        // UNIQUE constraint จัดการกรณีผู้ดูแลเพิ่มชื่อเดียวกันในเวลาเดียวกัน
        if (($error->errorInfo[1] ?? null) === 1062) {
            throw new ValidationException("ชื่อซ้ำในกลุ่มและหน่วยงานแม่เดียวกัน");
        }
        throw $error;
    }
    flash("เพิ่มข้อมูลอ้างอิงแล้ว");
    redirect("settings", ["group" => $group]);
}

/** เปิด/ปิดโดยไม่ลบแถว เพื่อรักษา Foreign Key และประวัติเรื่องเดิม */
function reference_active_action(): void
{
    allowed(["admin", "superAdmin"]);
    $id = positive_id($_POST["id"] ?? null);
    $active = text_input("active", 1);
    if (!in_array($active, ["0", "1"], true)) {
        throw new ValidationException("สถานะไม่ถูกต้อง");
    }
    $group = transaction(function () use ($id, $active): string {
        $value = row("SELECT * FROM reference_values WHERE id = ? FOR UPDATE", [$id]);
        if (!$value) {
            throw new ValidationException("ไม่พบข้อมูลอ้างอิง");
        }
        reference_group($value["group_name"]);
        if ($value["group_name"] === "org") {
            if (
                $active === "0" &&
                row(
                    <<<'SQL'
                    SELECT id
                    FROM reference_values
                    WHERE parent_id = ?
                    AND active = 1 FOR UPDATE
                    SQL,
                    [$id],
                )
            ) {
                throw new ValidationException(
                    "กรุณาปิดหน่วยงานย่อยที่เปิดอยู่ก่อนปิดหน่วยงานแม่",
                );
            }
            if ($active === "1" && $value["parent_id"] !== null) {
                $parent = row(
                    "SELECT active FROM reference_values WHERE id = ? FOR UPDATE",
                    [$value["parent_id"]],
                );
                if (!$parent || !$parent["active"]) {
                    throw new ValidationException(
                        "กรุณาเปิดหน่วยงานแม่ก่อนเปิดหน่วยงานย่อย",
                    );
                }
            }
        }
        update_data("reference_values", $id, [
            "active" => (int) $active,
            "updated_by" => actor(),
        ]);
        audit(
            "reference_active_changed",
            "reference",
            $id,
            "เปลี่ยนสถานะข้อมูลอ้างอิง: " . $value["label"],
            ["active" => (int) $value["active"]],
            ["active" => (int) $active],
        );
        return $value["group_name"];
    });
    flash(
        $active === "1" ? "เปิดใช้งานแล้ว" : "ปิดใช้งานแล้ว ข้อมูลในเรื่องเดิมยังคงอยู่",
    );
    redirect("settings", ["group" => $group]);
}

function type_active_action(): void
{
    allowed(["admin", "superAdmin"]);
    $id = positive_id($_POST["id"] ?? null);
    $active = text_input("active", 1);
    if (!in_array($active, ["0", "1"], true)) {
        throw new ValidationException("สถานะไม่ถูกต้อง");
    }
    transaction(function () use ($id, $active): void {
        $type = row("SELECT * FROM activity_types WHERE id = ? FOR UPDATE", [$id]);
        if (!$type) {
            throw new ValidationException("ไม่พบประเภทการบันทึก");
        }
        update_data("activity_types", $id, [
            "active" => (int) $active,
            "updated_by" => actor(),
        ]);
        audit(
            "type_active_changed",
            "activity_type",
            $id,
            "เปลี่ยนสถานะประเภท: " . $type["name"],
            ["active" => (int) $type["active"]],
            ["active" => (int) $active],
        );
    });
    flash("เปลี่ยนสถานะประเภทแล้ว");
    redirect("settings", ["group" => "activity"]);
}
