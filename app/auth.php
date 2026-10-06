<?php
// ระบบบัญชี: action รับ POST จาก index.php แล้วทำงานภายใน transaction; password ใช้ hash ไม่เก็บข้อความจริง
declare(strict_types=1);
defined("APP_ENTRY") || exit();

function login_action(): void
{
    throttle("login-ip:" . request_ip(), 60);
    $username = text_input("username", 80);
    $password = $_POST["password"] ?? "";
    throttle("login-user:" . strtolower($username), 12);
    if (!is_string($password) || strlen($password) > 200) {
        throw new ValidationException(
            "ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง หรือบัญชียังไม่ได้รับอนุมัติ",
        );
    }
    $u = row("SELECT * FROM users WHERE username=?", [$username]);
    $dummy = '$2y$12$JpeykBTOkzxliszbKmOMdu.nNrHVyvC1GFwyPDxNShLroBxpdx/uy';
    $valid = password_verify($password, $u["password_hash"] ?? $dummy);
    if (!$u || !$valid || $u["state"] !== "approved") {
        throw new ValidationException(
            "ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง หรือบัญชียังไม่ได้รับอนุมัติ",
        );
    }
    if (password_needs_rehash($u["password_hash"], PASSWORD_DEFAULT)) {
        query(
            <<<'SQL'
            UPDATE users
            SET password_hash=?,
            updated_at=NOW(),
            updated_by=?
            WHERE id=?
            SQL,
            [password_hash($password, PASSWORD_DEFAULT), $u["id"], $u["id"]],
        );
    }
    session_regenerate_id(true);
    $_SESSION = [
        "user_id" => (int) $u["id"],
        "version" => (int) $u["session_version"],
        "csrf" => bin2hex(random_bytes(32)),
        "last_seen" => time(),
        "login_at" => time(),
    ];
    audit("login", "user", (int) $u["id"], "เข้าสู่ระบบ", null, null, (int) $u["id"]);
    redirect($u["must_change_password"] ? "password" : "registry");
}

function register_action(): void
{
    throttle("register:" . request_ip(), 5);
    $username = text_input("username", 80);
    if (!preg_match('/^[a-zA-Z0-9_.-]{3,80}$/', $username)) {
        throw new ValidationException(
            "ชื่อผู้ใช้ใช้ตัวอักษรอังกฤษ ตัวเลข จุด ขีดกลางหรือขีดล่าง 3–80 ตัว",
        );
    }
    $profile = user_profile_input();
    $password = password_input();
    try {
        transaction(function () use ($username, $profile, $password) {
            if (row("SELECT id FROM users WHERE username=?", [$username])) {
                throw new ValidationException(
                    "ชื่อผู้ใช้นี้ไม่สามารถใช้ได้ กรุณาเลือกชื่ออื่น",
                );
            }
            ensure_national_id_available($profile["national_id"]);
            query(
                <<<'SQL'
                INSERT INTO users(username, title, first_name, last_name, national_id,
                    email, position, phone, org_id, name, password_hash, role, state)
                VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'officer', 'pending')
                SQL,
                [
                    $username,
                    $profile["title"],
                    $profile["first_name"],
                    $profile["last_name"],
                    $profile["national_id"],
                    $profile["email"],
                    $profile["position"],
                    $profile["phone"],
                    $profile["org_id"],
                    $profile["name"],
                    password_hash($password, PASSWORD_DEFAULT),
                ],
            );
            $id = (int) db()->lastInsertId();
            query("UPDATE users SET created_by=?,updated_by=? WHERE id=?", [
                $id,
                $id,
                $id,
            ]);
            audit(
                "register",
                "user",
                $id,
                "ลงทะเบียนรออนุมัติ",
                null,
                ["username" => $username],
                $id,
            );
        });
    } catch (PDOException $e) {
        if (($e->errorInfo[1] ?? null) === 1062) {
            throw new ValidationException(
                "ข้อมูลบัญชีนี้ไม่สามารถใช้ได้ กรุณาตรวจสอบหรือติดต่อ Super Admin",
            );
        }
        throw $e;
    }
    flash("ลงทะเบียนแล้ว กรุณารอ Super Admin อนุมัติ");
    redirect("login");
}

function forgot_action(): void
{
    throttle("forgot:" . request_ip(), 5);
    $username = text_input("username", 80);
    transaction(function () use ($username) {
        $u = row(
            "SELECT id FROM users WHERE username=? AND state='approved' FOR UPDATE",
            [$username],
        );
        if (
            $u &&
            !row(
                <<<'SQL'
                SELECT id
                FROM password_reset_requests
                WHERE user_id=?
                AND state='pending'
                SQL,
                [$u["id"]],
            )
        ) {
            query("INSERT INTO password_reset_requests(user_id) VALUES(?)", [$u["id"]]);
            audit(
                "password_reset_requested",
                "user",
                (int) $u["id"],
                "มีคำขอรีเซ็ตรหัสผ่าน (ยังไม่ได้ยืนยันตัวบุคคล)",
            );
        }
    });
    flash(
        "รับคำขอแล้ว หากมีบัญชีที่ใช้งานได้ กรุณาติดต่อ Super Admin เพื่อยืนยันตั" .
            "วตนและรับลิงก์ตั้งรหัสผ่านใหม่",
    );
    redirect("login");
}

function issue_reset(int $id): string
{
    return transaction(function () use ($id) {
        $u = row("SELECT * FROM users WHERE id=? AND state='approved' FOR UPDATE", [$id]);
        if (!$u) {
            throw new ValidationException("ไม่พบบัญชีที่อนุมัติแล้ว");
        }
        query(
            <<<'SQL'
            UPDATE password_reset_tokens
            SET used_at=NOW(),
            updated_at=NOW(),
            updated_by=?
            WHERE user_id=?
            AND used_at IS NULL
            SQL,
            [actor(), $id],
        );
        $token = bin2hex(random_bytes(32));
        query(
            <<<'SQL'
            INSERT INTO password_reset_tokens(user_id,
                token_hash,
                expires_at,
                created_by,
                updated_by)
            VALUES(?,
                ?,
                DATE_ADD(NOW(),
                    INTERVAL 30 MINUTE),
                ?,
                ?)
            SQL,
            [$id, hash("sha256", $token), actor(), actor()],
        );
        query(
            <<<'SQL'
            UPDATE password_reset_requests
            SET state='issued',
            updated_by=?,
            updated_at=NOW()
            WHERE user_id=?
            AND state='pending'
            SQL,
            [actor(), $id],
        );
        audit(
            "password_reset_issued",
            "user",
            $id,
            "ออกลิงก์รีเซ็ตรหัสผ่าน อายุ 30 นาที (หลังผู้ดูแลยืนยันตัวตน)",
        );
        return $token;
    });
}

function reset_action(): void
{
    throttle("reset:" . request_ip(), 10);
    $token = text_input("token", 64);
    $password = password_input();
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
        throw new ValidationException("ลิงก์ไม่ถูกต้อง หมดอายุ หรือถูกใช้แล้ว");
    }
    transaction(function () use ($token, $password) {
        // Lock user first, consistently with token issuance and password change, to avoid lock-order inversion.
        $lookup = row("SELECT user_id FROM password_reset_tokens WHERE token_hash=?", [
            hash("sha256", $token),
        ]);
        $u = $lookup
            ? row("SELECT * FROM users WHERE id=? AND state='approved' FOR UPDATE", [
                $lookup["user_id"],
            ])
            : null;
        $reset = $u
            ? row(
                <<<'SQL'
                SELECT *
                FROM password_reset_tokens
                WHERE token_hash=?
                AND used_at IS NULL
                AND expires_at>NOW() FOR UPDATE
                SQL,
                [hash("sha256", $token)],
            )
            : null;
        if (!$u || !$reset) {
            throw new ValidationException("ลิงก์ไม่ถูกต้อง หมดอายุ หรือถูกใช้แล้ว");
        }
        $id = (int) $u["id"];
        query(
            <<<'SQL'
            UPDATE users
            SET password_hash=?,
            must_change_password=0,
            session_version=session_version+1,
            updated_by=?,
            updated_at=NOW()
            WHERE id=?
            SQL,
            [password_hash($password, PASSWORD_DEFAULT), $id, $id],
        );
        query(
            <<<'SQL'
            UPDATE password_reset_tokens
            SET used_at=NOW(),
            updated_by=?,
            updated_at=NOW()
            WHERE user_id=?
            AND used_at IS NULL
            SQL,
            [$id, $id],
        );
        query(
            <<<'SQL'
            UPDATE password_reset_requests
            SET state='closed',
            updated_by=?,
            updated_at=NOW()
            WHERE user_id=?
            AND state<>'closed'
            SQL,
            [$id, $id],
        );
        audit(
            "password_reset_completed",
            "user",
            $id,
            "ตั้งรหัสผ่านใหม่และยกเลิกเซสชันเดิมทั้งหมด",
            null,
            null,
            $id,
        );
    });
    $_SESSION = [];
    session_regenerate_id(true);
    $_SESSION["csrf"] = bin2hex(random_bytes(32));
    flash("ตั้งรหัสผ่านใหม่แล้ว กรุณาเข้าสู่ระบบ");
    redirect("login");
}

function change_password_action(): void
{
    throttle("password:" . actor(), 10);
    $password = password_input();
    $old = $_POST["current_password"] ?? "";
    transaction(function () use ($password, $old) {
        $u = row("SELECT * FROM users WHERE id=? FOR UPDATE", [actor()]);
        if (!is_string($old) || !password_verify($old, $u["password_hash"])) {
            throw new ValidationException("รหัสผ่านปัจจุบันไม่ถูกต้อง");
        }
        if (password_verify($password, $u["password_hash"])) {
            throw new ValidationException("กรุณาใช้รหัสผ่านใหม่ที่ต่างจากเดิม");
        }
        query(
            <<<'SQL'
            UPDATE users
            SET password_hash=?,
            must_change_password=0,
            session_version=session_version+1,
            updated_by=?,
            updated_at=NOW()
            WHERE id=?
            SQL,
            [password_hash($password, PASSWORD_DEFAULT), actor(), actor()],
        );
        query(
            <<<'SQL'
            UPDATE password_reset_tokens
            SET used_at=NOW(),
            updated_by=?,
            updated_at=NOW()
            WHERE user_id=?
            AND used_at IS NULL
            SQL,
            [actor(), actor()],
        );
        audit("password_changed", "user", actor(), "เปลี่ยนรหัสผ่านและยกเลิกเซสชันอื่น");
        $_SESSION["version"] = (int) $u["session_version"] + 1;
    });
    session_regenerate_id(true);
    $_SESSION["csrf"] = bin2hex(random_bytes(32));
    flash("เปลี่ยนรหัสผ่านแล้ว");
    redirect("registry");
}

function user_decision_action(): void
{
    allowed(["superAdmin"]);
    $id = positive_id($_POST["id"] ?? null);
    $decision = text_input("decision", 20);
    $role = text_input("role", 20);
    if (
        !in_array($decision, ["approved", "rejected"], true) ||
        !in_array($role, ["admin", "officer"], true)
    ) {
        throw new ValidationException("ข้อมูลสิทธิ์หรือผลอนุมัติไม่ถูกต้อง");
    }
    transaction(function () use ($id, $decision, $role) {
        $u = row("SELECT * FROM users WHERE id=? FOR UPDATE", [$id]);
        if (!$u || $u["state"] !== "pending") {
            throw new ValidationException("คำขอนี้ถูกพิจารณาไปแล้ว");
        }
        query(
            <<<'SQL'
            UPDATE users
            SET state=?,
            role=?,
            approved_by=?,
            approved_at=NOW(),
            updated_by=?,
            updated_at=NOW(),
            session_version=session_version+1
            WHERE id=?
            SQL,
            [$decision, $role, actor(), actor(), $id],
        );
        audit(
            "user_" . $decision,
            "user",
            $id,
            "พิจารณาบัญชี " . $u["username"],
            ["state" => $u["state"], "role" => $u["role"]],
            ["state" => $decision, "role" => $role],
        );
    });
    flash("บันทึกผลการพิจารณาแล้ว");
    redirect("users");
}

/** จำนวนงานที่ Super Admin ยังดำเนินการได้ นับใหม่เมื่อเปิดหน้า */
function pending_user_requests(): int
{
    global $user;
    if (($user["role"] ?? "") !== "superAdmin") {
        return 0;
    }
    $counts = row(
        <<<'SQL'
        SELECT
            (SELECT COUNT(*) FROM users WHERE state='pending')
            +
            (SELECT COUNT(*)
             FROM password_reset_requests r
             JOIN users u ON u.id=r.user_id
             WHERE r.state='pending' AND u.state='approved') AS total
        SQL,
    );
    return (int) ($counts["total"] ?? 0);
}

/** เปลี่ยนสถานะบัญชีเดิม ไม่เปลี่ยนบทบาท และไม่ลบประวัติการทำรายการ */
function user_state_action(): void
{
    allowed(["superAdmin"]);
    $id = positive_id($_POST["id"] ?? null);
    $state = text_input("state", 20);
    $version = positive_id($_POST["version"] ?? null);
    if (!in_array($state, ["approved", "disabled", "rejected"], true)) {
        throw new ValidationException("สถานะบัญชีไม่ถูกต้อง");
    }
    transaction(function () use ($id, $state, $version) {
        $u = row("SELECT * FROM users WHERE id=? FOR UPDATE", [$id]);
        if (!$u) {
            throw new ValidationException("ไม่พบบัญชีผู้ใช้");
        }
        // ป้องกันการปิดบัญชีผู้ดูแลสูงสุดจนไม่มีผู้ดูแลเข้าใช้งาน
        if ($id === actor() || $u["role"] === "superAdmin") {
            throw new ValidationException(
                "ไม่สามารถเปลี่ยนสถานะบัญชีตนเองหรือ Super Admin จากหน้านี้",
            );
        }
        if ($u["state"] === "pending") {
            throw new ValidationException("กรุณาพิจารณาคำขอลงทะเบียนก่อนเปลี่ยนสถานะ");
        }
        if ((int) $u["session_version"] !== $version) {
            throw new ValidationException(
                "บัญชีนี้มีการเปลี่ยนแปลงแล้ว กรุณาตรวจสอบข้อมูลล่าสุดอีกครั้ง",
            );
        }
        if ($u["state"] === $state) {
            throw new ValidationException("กรุณาเลือกสถานะใหม่ที่ต่างจากเดิม");
        }
        query(
            <<<'SQL'
            UPDATE users
            SET state=?,
                session_version=session_version+1,
                updated_by=?,
                updated_at=NOW()
            WHERE id=?
            SQL,
            [$state, actor(), $id],
        );
        if ($state === "approved") {
            query("UPDATE users SET approved_by=?, approved_at=NOW() WHERE id=?", [
                actor(),
                $id,
            ]);
        }
        // ลิงก์รีเซ็ตเก่าต้องกลับมาใช้ไม่ได้แม้เปิดบัญชีอีกครั้งภายหลัง
        query(
            <<<'SQL'
            UPDATE password_reset_tokens
            SET used_at=NOW(), updated_by=?, updated_at=NOW()
            WHERE user_id=? AND used_at IS NULL
            SQL,
            [actor(), $id],
        );
        query(
            <<<'SQL'
            UPDATE password_reset_requests
            SET state='closed', updated_by=?, updated_at=NOW()
            WHERE user_id=? AND state<>'closed'
            SQL,
            [actor(), $id],
        );
        audit(
            "user_state_changed",
            "user",
            $id,
            "เปลี่ยนสถานะบัญชี " . $u["username"],
            ["state" => $u["state"]],
            ["state" => $state],
        );
    });
    flash(
        "เปลี่ยนสถานะบัญชีแล้ว ผู้ใช้ต้องเข้าสู่ระบบใหม่ และคำขอ/ลิงก์รีเซ็ตเดิมถูกยกเลิก",
    );
    redirect("users");
}
