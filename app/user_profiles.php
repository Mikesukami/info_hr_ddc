<?php
declare(strict_types=1);
defined("APP_ENTRY") || exit();

/** ข้อมูลส่วนบุคคลเท่านั้น: ไม่รับ username, role, state หรือ password จากฟอร์มนี้ */
function user_profile_input(array $existing = []): array
{
    $data = [
        "title" => text_input("title", 50),
        "first_name" => text_input("first_name", 80),
        "last_name" => text_input("last_name", 80),
        "national_id" => text_input("national_id", 13),
        "email" => text_input("email", 254),
        "position" => text_input("position", 200),
        "phone" => text_input("phone", 30),
        "org_id" => reference_input("org_id", "org", $existing),
    ];
    // เก็บเป็นข้อความ เพื่อคงเลขศูนย์นำหน้า; ตรวจรูปแบบ ไม่ใช่การยืนยันตัวตน
    if (!preg_match('/^[0-9]{13}$/D', $data["national_id"])) {
        throw new ValidationException(
            "เลขบัตรประชาชนต้องเป็นตัวเลข 13 หลัก ไม่เว้นวรรคหรือใส่ขีด",
        );
    }
    if (!filter_var($data["email"], FILTER_VALIDATE_EMAIL)) {
        throw new ValidationException("รูปแบบอีเมลไม่ถูกต้อง");
    }
    $data["name"] = $data["title"] . " " . $data["first_name"] . " " . $data["last_name"];
    if (mb_strlen($data["name"]) > 200) {
        throw new ValidationException(
            "คำนำหน้า ชื่อ และนามสกุลรวมกันต้องไม่เกิน 200 ตัวอักษร",
        );
    }
    return $data;
}

/** ใช้ก่อนบันทึกทุกครั้ง; UNIQUE ใน DB ป้องกันกรณีสองคำขอพร้อมกันด้วย */
function ensure_national_id_available(string $nationalId, int $excludeId = 0): void
{
    if (
        row("SELECT id FROM users WHERE national_id=? AND id<>?", [
            $nationalId,
            $excludeId,
        ])
    ) {
        throw new ValidationException(
            "ไม่สามารถใช้เลขบัตรประชาชนนี้ได้ กรุณาตรวจสอบหรือติดต่อ Super Admin",
        );
    }
}

function profile_save_action(bool $editOther = false): void
{
    allowed($editOther ? ["superAdmin"] : ["officer", "admin", "superAdmin"]);
    // หน้าข้อมูลตัวเองใช้ id จาก session เท่านั้น แม้มีผู้ส่ง id ของคนอื่นมา
    $id = $editOther ? positive_id($_POST["id"] ?? null) : actor();
    $version = positive_id($_POST["profile_version"] ?? null);
    try {
        transaction(function () use ($id, $version) {
            $existing = row("SELECT * FROM users WHERE id=? FOR UPDATE", [$id]);
            if (!$existing) {
                throw new ValidationException("ไม่พบบัญชีผู้ใช้");
            }
            if ((int) $existing["profile_version"] !== $version) {
                throw new ValidationException(
                    "ข้อมูลถูกแก้ไขไปแล้ว กรุณาเปิดหน้านี้ใหม่และตรวจข้อมูลล่าสุดก่อนบันทึก",
                );
            }
            $data = user_profile_input($existing);
            ensure_national_id_available($data["national_id"], $id);
            query(
                <<<'SQL'
                UPDATE users
                SET title=?, first_name=?, last_name=?, national_id=?, email=?,
                    position=?, phone=?, org_id=?, name=?,
                    profile_version=profile_version+1, updated_by=?, updated_at=NOW()
                WHERE id=?
                SQL,
                [
                    $data["title"],
                    $data["first_name"],
                    $data["last_name"],
                    $data["national_id"],
                    $data["email"],
                    $data["position"],
                    $data["phone"],
                    $data["org_id"],
                    $data["name"],
                    actor(),
                    $id,
                ],
            );
            // ประวัติเก็บว่าช่องใดเปลี่ยน ไม่ทำสำเนาเลขบัตร/อีเมลลง audit log
            $changed = [];
            foreach ($data as $key => $value) {
                if ((string) ($existing[$key] ?? "") !== (string) $value) {
                    $changed[] = $key;
                }
            }
            audit("user_profile_updated", "user", $id, "ปรับปรุงข้อมูลผู้ใช้", null, [
                "changed_fields" => $changed,
            ]);
        });
    } catch (PDOException $e) {
        if (($e->errorInfo[1] ?? null) === 1062) {
            throw new ValidationException(
                "ไม่สามารถใช้เลขบัตรประชาชนนี้ได้ กรุณาตรวจสอบหรือติดต่อ Super Admin",
            );
        }
        throw $e;
    }
    flash("บันทึกข้อมูลผู้ใช้แล้ว");
    redirect($editOther ? "user_profile" : "profile", $editOther ? ["id" => $id] : []);
}
