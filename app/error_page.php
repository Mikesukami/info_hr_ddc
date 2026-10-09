<?php
declare(strict_types=1);
defined("APP_ENTRY") || exit();

/**
 * หน้า error กลาง ไม่ใช้ฐานข้อมูลหรือ Session จึงแสดงได้แม้ระบบเชื่อมต่อไม่ได้
 * ส่งเฉพาะข้อความที่เหมาะสำหรับผู้ใช้ ห้ามส่งข้อความ exception ภายในมาที่หน้านี้
 */
function render_error_page(int $status, string $message = ""): void
{
    $pages = [
        401 => ["กรุณาเข้าสู่ระบบ", "คุณต้องเข้าสู่ระบบก่อนเปิดหน้านี้", "เข้าสู่ระบบเพื่อใช้งานต่อ"],
        403 => ["ไม่สามารถทำรายการนี้ได้", "คุณไม่มีสิทธิ์เข้าถึงหน้านี้หรือทำรายการนี้", "หากคำขอหมดอายุ ให้เปิดหน้าใหม่แล้วลองอีกครั้ง"],
        404 => ["ไม่พบหน้าหรือรายการนี้", "ไม่พบหน้าหรือข้อมูลที่ระบุ", "รายการอาจถูกลบ หรือที่อยู่ของหน้าไม่ถูกต้อง"],
        405 => ["รูปแบบคำขอไม่รองรับ", "ระบบไม่รองรับวิธีส่งคำขอนี้", "กลับไปเปิดหน้าระบบและทำรายการผ่านฟอร์มอีกครั้ง"],
        422 => ["กรุณาตรวจสอบข้อมูล", "ข้อมูลที่ส่งมายังไม่ถูกต้องหรือไม่ครบถ้วน", "กลับไปตรวจสอบข้อมูลแล้วลองใหม่"],
        429 => ["ทำรายการถี่เกินไป", "ทำรายการหลายครั้งเกินไป กรุณารอ 15 นาทีแล้วลองใหม่", "ระบบจำกัดจำนวนคำขอเพื่อป้องกันการใช้งานผิดปกติ"],
        500 => ["เกิดข้อผิดพลาดในระบบ", "ระบบไม่สามารถทำรายการได้ในขณะนี้", "กรุณาลองใหม่ภายหลัง หรือติดต่อผู้ดูแลระบบ"],
        503 => ["ระบบยังไม่พร้อมใช้งาน", "ระบบไม่สามารถทำรายการได้ในขณะนี้ กรุณาติดต่อผู้ดูแลเพื่อตรวจการติดตั้งหรือฐานข้อมูล", "กรุณาลองใหม่ภายหลัง หรือติดต่อผู้ดูแลระบบ"],
    ];
    if (!isset($pages[$status])) {
        $status = 500;
    }
    [$title, $defaultMessage, $hint] = $pages[$status];
    $message = $message !== "" ? $message : $defaultMessage;
    $escape = static fn(string $value): string => htmlspecialchars(
        $value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        "UTF-8",
    );

    // ใช้ตำแหน่งไฟล์ที่รันจริง ไม่พึ่ง base_url ซึ่งอาจเป็นสาเหตุของ error เอง
    $script = str_replace("\\", "/", $_SERVER["SCRIPT_NAME"] ?? "/index.php");
    $basePath = rtrim(dirname($script), "/.") . "/";
    $home = $basePath . "index.php";
    $login = $home . "?page=login";
    http_response_code($status);
    header("Content-Type: text/html; charset=utf-8");
    header("Cache-Control: no-store, private");
    header("X-Content-Type-Options: nosniff");
    header("X-Frame-Options: DENY");
    header("Referrer-Policy: no-referrer");
    header("Content-Security-Policy: default-src 'none'; style-src 'self'; font-src 'self'; img-src 'self' data:; base-uri 'none'; frame-ancestors 'none'");
    if ($status === 405) {
        header("Allow: GET, POST");
    }
    if ($status === 429) {
        header("Retry-After: 900");
    }
    require __DIR__ . "/views/error.php";
}

/** ใช้แทน exit("ข้อความ") โดยยังคงรหัส HTTP เดิมและหยุดการทำรายการทันที */
function abort_page(int $status, string $message = ""): never
{
    render_error_page($status, $message);
    exit();
}
