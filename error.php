<?php
// จุดแสดง error ของเว็บเซิร์ฟเวอร์ แยกจาก index.php เพื่อไม่ต้องเชื่อม DB
declare(strict_types=1);
define("APP_ENTRY", true);
require __DIR__ . "/app/error_page.php";
$redirectStatus = (int) ($_SERVER["REDIRECT_STATUS"] ?? 0);
$status = $redirectStatus >= 400
    ? $redirectStatus
    : (int) ($_GET["status"] ?? 404);
render_error_page($status);
