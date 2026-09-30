<?php
// ส่วนกลาง: เชื่อม PDO ด้วย db(), อ่านด้วย row()/rows(), เขียนด้วย query(); รวม session, CSRF และ validation
declare(strict_types=1);
defined("APP_ENTRY") || exit();
date_default_timezone_set("Asia/Bangkok");
ini_set("display_errors", "0");
ini_set("log_errors", "1");
ini_set("error_log", __DIR__ . "/../storage/app.log");
$configFile = __DIR__ . "/../config/local.php";
if (!is_file($configFile)) {
    http_response_code(503);
    exit("ไม่พบไฟล์ตั้งค่า config/local.php กรุณาตรวจไฟล์ระบบตามคู่มือ README.md");
}
$config = require $configFile;
header("Content-Type: text/html; charset=utf-8");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("Referrer-Policy: no-referrer");
header("Cache-Control: no-store, private");
header(
    'Content-Security-Policy: default-src \'self\'; script-src \'self\'; style-sr' .
        'c \'self\'; img-src \'self\' data:; font-src \'self\' data:; connect-src \'self' .
        '\'; frame-ancestors \'none\'; base-uri \'none\'; form-action \'self\'; object-s' .
        'rc \'none\'',
);
header("Permissions-Policy: camera=(), microphone=(), geolocation=()");
if (
    PHP_SAPI !== "cli" &&
    $config["secure_cookies"] &&
    ($_SERVER["HTTPS"] ?? "") !== "on"
) {
    http_response_code(503);
    exit("ระบบนี้กำหนดให้เข้าใช้งานผ่าน HTTPS เท่านั้น");
}
if ($config["secure_cookies"]) {
    header("Strict-Transport-Security: max-age=31536000");
}
ini_set("session.use_strict_mode", "1");
ini_set("session.use_only_cookies", "1");
session_name("study_leave_session");
session_set_cookie_params([
    "lifetime" => 0,
    "path" => parse_url($config["base_url"], PHP_URL_PATH) ?: "/",
    "secure" => (bool) $config["secure_cookies"],
    "httponly" => true,
    "samesite" => "Lax",
]);
$sessionPath = __DIR__ . "/../storage/sessions";
if (!is_dir($sessionPath) && !mkdir($sessionPath, 0700, true)) {
    throw new RuntimeException("Session storage unavailable");
}
ini_set("session.save_path", $sessionPath);
ini_set("session.gc_maxlifetime", (string) $config["session_max_seconds"]);
if (!session_start()) {
    throw new RuntimeException("Session initialization failed");
}
$_SESSION["csrf"] ??= bin2hex(random_bytes(32));
class ValidationException extends RuntimeException {}

function db(): PDO
{
    static $pdo;
    if (!$pdo) {
        global $config;
        $d = $config["db"];
        $pdo = new PDO(
            "mysql:host={$d["host"]};port={$d["port"]};dbname={$d["name"]};charset=utf8mb4",
            $d["user"],
            $d["password"],
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_STRINGIFY_FETCHES => false,
            ],
        );
        $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_general_ci");
        $pdo->exec("SET time_zone = '+07:00'");
    }
    return $pdo;
}

function query(string $sql, array $params = []): PDOStatement
{
    $q = db()->prepare($sql);
    $q->execute($params);
    return $q;
}

function row(string $sql, array $params = []): ?array
{
    return query($sql, $params)->fetch() ?: null;
}

function rows(string $sql, array $params = []): array
{
    return query($sql, $params)->fetchAll();
}

function h(mixed $value): string
{
    return htmlspecialchars(
        (string) ($value ?? ""),
        ENT_QUOTES | ENT_SUBSTITUTE,
        "UTF-8",
    );
}

function url(string $page = "registry", array $params = []): string
{
    return "index.php?" . http_build_query(["page" => $page] + $params);
}

function redirect(string $page, array $params = []): never
{
    header("Location: " . url($page, $params), true, 303);
    exit();
}

function flash(string $text, string $kind = "success"): void
{
    $_SESSION["flash"] = ["text" => $text, "kind" => $kind];
}

function csrf(): string
{
    return '<input type="hidden" name="csrf" value="' . h($_SESSION["csrf"]) . '">';
}

function verify_csrf(): void
{
    if (
        !is_string($_POST["csrf"] ?? null) ||
        !hash_equals($_SESSION["csrf"], $_POST["csrf"])
    ) {
        http_response_code(403);
        exit("คำขอไม่ถูกต้องหรือหมดอายุ กรุณาเปิดหน้าใหม่");
    }
}

function actor(): ?int
{
    return isset($GLOBALS["user"]["id"]) ? (int) $GLOBALS["user"]["id"] : null;
}

function is_admin(): bool
{
    return in_array($GLOBALS["user"]["role"] ?? "", ["admin", "superAdmin"], true);
}

function allowed(array $roles): void
{
    if (!in_array($GLOBALS["user"]["role"] ?? "", $roles, true)) {
        http_response_code(403);
        exit("403 — คุณไม่มีสิทธิ์เข้าถึงหน้านี้หรือทำรายการนี้");
    }
}

/**
 * ตรวจเวลาจากคำขอที่มาถึง PHP ไม่ใช่จากการเลื่อนหน้าหรือการพิมพ์ในเบราว์เซอร์
 * แยกเป็นฟังก์ชันเพื่อให้อ่านและทดสอบกรณีเวลาหมดได้ง่าย
 */
function session_expired(array $session, int $now): bool
{
    global $config;

    $idleSeconds = $now - ($session["last_seen"] ?? 0);
    $totalSeconds = $now - ($session["login_at"] ?? 0);

    $idleExpired = $idleSeconds > $config["session_idle_seconds"];
    $totalExpired = $totalSeconds > $config["session_max_seconds"];

    return $idleExpired || $totalExpired;
}

function current_user(): ?array
{
    global $config;
    if (empty($_SESSION["user_id"])) {
        return null;
    }
    $u = row("SELECT * FROM users WHERE id=?", [(int) $_SESSION["user_id"]]);
    if (
        !$u ||
        $u["state"] !== "approved" ||
        (int) $u["session_version"] !== ($_SESSION["version"] ?? 0) ||
        session_expired($_SESSION, time())
    ) {
        $_SESSION = [];
        session_regenerate_id(true);
        $_SESSION["csrf"] = bin2hex(random_bytes(32));
        return null;
    }
    // ต่อเฉพาะเวลาว่าง; login_at ไม่เปลี่ยน จึงยังจำกัดอายุรวมได้
    $_SESSION["last_seen"] = time();
    return $u;
}

function audit(
    string $action,
    string $entity,
    ?int $id,
    string $description,
    ?array $before = null,
    ?array $after = null,
    ?int $by = null,
): void {
    $by ??= actor();
    foreach (["password_hash", "token_hash", "password", "csrf", "token"] as $key) {
        if ($before) {
            unset($before[$key]);
        }
        if ($after) {
            unset($after[$key]);
        }
    }
    query(
        <<<'SQL'
        INSERT INTO audit_logs(action,
            entity_type,
            entity_id,
            description,
            before_data,
            after_data,
            ip_address,
            created_by,
            updated_by)
        VALUES(?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?)
        SQL,
        [
            $action,
            $entity,
            $id,
            mb_substr($description, 0, 500),
            $before ? json_encode($before, JSON_UNESCAPED_UNICODE) : null,
            $after ? json_encode($after, JSON_UNESCAPED_UNICODE) : null,
            $_SERVER["REMOTE_ADDR"] ?? "CLI",
            $by,
            $by,
        ],
    );
}

function transaction(callable $callback): mixed
{
    db()->beginTransaction();
    try {
        $result = $callback();
        db()->commit();
        return $result;
    } catch (Throwable $e) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }
        throw $e;
    }
}

function text_input(string $key, int $max = 200, bool $required = true): string
{
    $v = $_POST[$key] ?? "";
    if (!is_string($v)) {
        throw new ValidationException("รูปแบบข้อมูลไม่ถูกต้อง");
    }
    $v = trim($v);
    if (($required && $v === "") || mb_strlen($v) > $max || str_contains($v, "\0")) {
        throw new ValidationException("กรุณากรอกข้อมูลที่จำเป็นให้ครบและไม่ยาวเกินกำหนด");
    }
    return $v;
}

function positive_id(mixed $value): int
{
    if (!is_scalar($value) || !preg_match('/^[1-9][0-9]{0,17}$/', (string) $value)) {
        throw new ValidationException("รหัสรายการไม่ถูกต้อง");
    }
    return (int) $value;
}

function be_date(?string $iso): string
{
    if (!$iso) {
        return "";
    }
    return substr($iso, 8, 2) .
        "/" .
        substr($iso, 5, 2) .
        "/" .
        ((int) substr($iso, 0, 4) + 543);
}

function be_time(?string $value): string
{
    return $value
        ? be_date(substr($value, 0, 10)) . " " . substr($value, 11, 5) . " น."
        : "—";
}

function date_input(string $key): string
{
    $s = text_input($key, 10);
    if (!preg_match('~^(\d{2})/(\d{2})/(\d{4})$~', $s, $m)) {
        throw new ValidationException("วันที่ต้องเป็น วว/ดด/พ.ศ. เช่น 01/07/2569");
    }
    $year = (int) $m[3];
    if (
        $year < 2400 ||
        $year > 2800 ||
        !checkdate((int) $m[2], (int) $m[1], $year - 543)
    ) {
        throw new ValidationException("วันที่ไม่มีอยู่จริง หรือปีไม่ใช่ พ.ศ. 2400–2800");
    }
    return sprintf("%04d-%02d-%02d", $year - 543, (int) $m[2], (int) $m[1]);
}

function fiscal_input(): int
{
    $v = text_input("fiscal_year", 4);
    if (!ctype_digit($v) || (int) $v < 2400 || (int) $v > 2800) {
        throw new ValidationException("ปีงบประมาณต้องเป็น พ.ศ. 4 หลัก");
    }
    return (int) $v;
}

function fiscal_now(): int
{
    return (int) date("Y") + 543 + ((int) date("n") >= 10 ? 1 : 0);
}

function password_input(): string
{
    $password = $_POST["password"] ?? "";
    $confirm = $_POST["password_confirm"] ?? "";
    if (
        !is_string($password) ||
        mb_strlen($password) < 12 ||
        strlen($password) > 72 ||
        str_contains($password, "\0")
    ) {
        throw new ValidationException(
            "รหัสผ่านต้องยาวอย่างน้อย 12 ตัวอักษร และไม่เกิน 72 ไบต์",
        );
    }
    if (!is_string($confirm) || !hash_equals($password, $confirm)) {
        throw new ValidationException("รหัสผ่านทั้งสองช่องไม่ตรงกัน");
    }
    return $password;
}

function throttle(string $bucket, int $limit): void
{
    // bucket คือกลุ่มที่นับ เช่น login-user:somying หรือ login-ip:127.0.0.1
    // hash ทำให้ได้ key ยาวคงที่ ไม่ใช่ hash รหัสผ่าน และไม่ใช่การเข้ารหัสย้อนกลับ
    $hash = hash("sha256", $bucket);
    // เกิน 15 นาทีเริ่มนับใหม่เป็น 1; ภายในช่วงเดิมเพิ่ม hits ทีละ 1
    query(
        <<<'SQL'
        INSERT INTO auth_limits (bucket_hash, hits, window_start)
        VALUES (?, 1, NOW())
        ON DUPLICATE KEY UPDATE
            hits = IF(
                window_start < DATE_SUB(NOW(), INTERVAL 15 MINUTE),
                1,
                hits + 1
            ),
            window_start = IF(
                window_start < DATE_SUB(NOW(), INTERVAL 15 MINUTE),
                NOW(),
                window_start
            ),
            updated_at = NOW()
        SQL,
        [$hash],
    );
    if (
        (int) row("SELECT hits FROM auth_limits WHERE bucket_hash=?", [$hash])["hits"] >
        $limit
    ) {
        http_response_code(429);
        throw new ValidationException(
            "ทำรายการหลายครั้งเกินไป กรุณารอ 15 นาทีแล้วลองใหม่",
        );
    }
}

function request_ip(): string
{
    return $_SERVER["REMOTE_ADDR"] ?? "local";
}
