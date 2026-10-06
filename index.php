<?php
declare(strict_types=1);
define("APP_ENTRY", true);
// จุดเริ่มของทุกหน้า: โหลดฟังก์ชัน -> ตรวจผู้ใช้ -> แยก GET/POST -> แสดงผล
try {
    // 1. ตั้งค่า PDO/session และโหลดส่วนทำงาน (ยังไม่แสดง HTML)
    require __DIR__ . "/app/bootstrap.php";
    require __DIR__ . "/app/auth.php";
    require __DIR__ . "/app/records.php";
    require __DIR__ . "/app/references.php";
    require __DIR__ . "/app/user_profiles.php";
    require __DIR__ . "/app/view_helpers.php";
    require __DIR__ . "/app/views/content.php";
    require __DIR__ . "/app/export.php";
    // 2. อ่านบัญชีจากฐานข้อมูลใหม่ทุก request เพื่อใช้สิทธิ์ล่าสุด
    $user = current_user();
    // อ่านสิทธิ์จากที่เดียว: การซ่อนเมนูไม่ใช่การป้องกันหลัก
    $routes = require __DIR__ . "/app/routes.php";
    $public = $routes["public"];
    $roles = $routes["pages"];
    $actions = $routes["actions"];
    // 3. POST คือการเปลี่ยนข้อมูล: ตรวจสิทธิ์ + CSRF ก่อนเรียก service
    if ($_SERVER["REQUEST_METHOD"] === "POST") {
        $action = is_string($_POST["action"] ?? null) ? $_POST["action"] : "";
        if (!array_key_exists($action, $actions)) {
            http_response_code(404);
            exit("ไม่พบการทำรายการนี้");
        }
        if ($actions[$action] !== null) {
            if (!$user) {
                redirect("login");
            }
            allowed($actions[$action]);
        }
        verify_csrf();
        if (
            $user &&
            $user["must_change_password"] &&
            !in_array($action, ["password", "logout"], true)
        ) {
            redirect("password");
        }
        try {
            switch ($action) {
                case "login":
                    login_action();
                    break;
                case "register":
                    register_action();
                    break;
                case "forgot":
                    forgot_action();
                    break;
                case "reset":
                    reset_action();
                    break;
                case "password":
                    change_password_action();
                    break;
                case "profile_save":
                    profile_save_action();
                    break;
                case "user_profile_save":
                    profile_save_action(true);
                    break;
                case "logout":
                    audit("logout", "user", actor(), "ออกจากระบบ");
                    $_SESSION = [];
                    session_regenerate_id(true);
                    $_SESSION["csrf"] = bin2hex(random_bytes(32));
                    redirect("login");
                case "case_save":
                    case_save_action();
                    break;
                case "period_save":
                    period_save_action();
                    break;
                case "return_save":
                    terminal_save_action("return");
                    break;
                case "cancel_save":
                    terminal_save_action("cancel");
                    break;
                case "type_save":
                    type_save_action();
                    break;
                case "reference_create":
                    reference_create_action();
                    break;
                case "reference_active":
                    reference_active_action();
                    break;
                case "type_active":
                    type_active_action();
                    break;
                case "user_decision":
                    user_decision_action();
                    break;
                case "user_state":
                    user_state_action();
                    break;
                case "issue_reset":
                    if (($_POST["identity_confirmed"] ?? "") !== "1") {
                        throw new ValidationException(
                            "ต้องยืนยันตัวตนของเจ้าของบัญชีก่อน",
                        );
                    }
                    $id = positive_id($_POST["id"] ?? null);
                    if ($id === actor()) {
                        throw new ValidationException(
                            "เปลี่ยนรหัสผ่านของตนเองได้ที่เมนูเปลี่ยนรหัสผ่าน",
                        );
                    }
                    $target = row("SELECT name FROM users WHERE id=?", [$id]);
                    $token = issue_reset($id);
                    $_SESSION["reset_link"] = [
                        "name" => $target["name"],
                        "url" =>
                            rtrim($config["base_url"], "/") .
                            "/" .
                            url("reset", ["token" => $token]),
                    ];
                    redirect("users");
            }
        } catch (ValidationException $e) {
            if (http_response_code() === 429) {
                header("Retry-After: 900");
                throw $e;
            }
            $old = $_POST;
            foreach (
                [
                    "password",
                    "password_confirm",
                    "current_password",
                    "csrf",
                    "token",
                    "action",
                ]
                as $secret
            ) {
                unset($old[$secret]);
            }
            $_SESSION["old"] = array_filter($old, fn($v) => is_scalar($v));
            flash($e->getMessage(), "error");
            $back = match ($action) {
                "profile_save" => "profile",
                "user_profile_save" => "user_profile",
                "case_save" => "case_form",
                "period_save" => "period_form",
                "return_save" => "return_form",
                "cancel_save" => "cancel_form",
                "type_save",
                "type_active",
                "reference_create",
                "reference_active"
                    => "settings",
                "user_decision", "user_state", "issue_reset" => "users",
                default => $action,
            };
            $params = [];
            if ($action === "user_profile_save" && is_scalar($_POST["id"] ?? null)) {
                $params["id"] = (int) $_POST["id"];
            }
            if (
                in_array($action, ["reference_create", "reference_active"], true) &&
                is_string($_POST["group"] ?? null) &&
                isset(REFERENCE_GROUPS[$_POST["group"]])
            ) {
                $params["group"] = $_POST["group"];
            }
            if (in_array($action, ["type_save", "type_active"], true)) {
                $params["group"] = "activity";
            }
            if (
                $action === "case_save" &&
                !empty($_POST["id"]) &&
                is_scalar($_POST["id"])
            ) {
                $params["id"] = (int) $_POST["id"];
            }
            if (
                in_array($action, ["period_save", "return_save", "cancel_save"], true) &&
                is_scalar($_POST["case_id"] ?? null)
            ) {
                $params["id"] = (int) $_POST["case_id"];
            }
            if (
                in_array($action, ["period_save", "return_save"], true) &&
                !empty($_POST["entry_id"]) &&
                is_scalar($_POST["entry_id"])
            ) {
                $params["entry_id"] = (int) $_POST["entry_id"];
            }
            if (
                $action === "reset" &&
                is_string($_POST["token"] ?? null) &&
                preg_match('/^[a-f0-9]{64}$/', $_POST["token"])
            ) {
                $params["token"] = $_POST["token"];
            }
            redirect($back, $params);
        }
    }
    // 4. GET คือเปิดหน้า: ตรวจสิทธิ์ก่อนเรียก view หรือส่งออก CSV
    if ($_SERVER["REQUEST_METHOD"] !== "GET") {
        http_response_code(405);
        header("Allow: GET, POST");
        exit();
    }
    $page = is_string($_GET["page"] ?? null)
        ? $_GET["page"]
        : ($user
            ? "registry"
            : "login");
    if (!in_array($page, $public, true) && !isset($roles[$page])) {
        http_response_code(404);
        exit("ไม่พบหน้าที่ระบุ");
    }
    if (isset($roles[$page])) {
        if (!$user) {
            redirect("login");
        }
        allowed($roles[$page]);
    } elseif ($user && $page !== "reset") {
        redirect("registry");
    }
    if ($user && $user["must_change_password"] && $page !== "password") {
        redirect("password");
    }
    if ($page === "export") {
        export_view();
    }
    $old = $_SESSION["old"] ?? [];
    unset($_SESSION["old"]);
    // 5. layout.php วาดกรอบเว็บ และเรียก render_content() เพื่อเลือกหน้าจอ
    ob_start();
    require __DIR__ . "/app/views/layout.php";
    ob_end_flush();
} catch (ValidationException $e) {
    if (ob_get_level()) {
        ob_end_clean();
    }
    if (http_response_code() === 200) {
        http_response_code(422);
    }
    echo '<!doctype html><html lang="th"><meta charset="utf-8"><title>ไม่สามารถเปิดรายการ</title><p>' .
        htmlspecialchars($e->getMessage(), ENT_QUOTES, "UTF-8") .
        '</p><a href="index.php">กลับหน้าหลัก</a></html>';
} catch (Throwable $e) {
    if (ob_get_level()) {
        ob_end_clean();
    }
    error_log("Application failure: " . get_class($e) . " code " . $e->getCode());
    http_response_code(503);
    echo '<!doctype html><html lang="th"><meta charset="utf-8"><title>ระบบยังไม่พร' .
        "้อม</title><p>ระบบไม่สามารถทำรายการได้ในขณะนี้ กรุณาติดต่อผู้ดูแลเพื่อตร" .
        'วจการติดตั้งหรือฐานข้อมูล</p><a href="index.php">ลองใหม่</a></html>';
}
