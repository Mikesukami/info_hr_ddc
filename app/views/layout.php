<?php defined("APP_ENTRY") || exit();
$titles = [
    "registry" => "ทะเบียนการลา",
    "ledger" => "ทะเบียนแบบ Excel",
    "overview" => "ภาพรวม",
    "reports" => "รายงานและสถิติ",
    "users" => "ผู้ใช้งานและสิทธิ์",
    "profile" => "ข้อมูลส่วนตัว",
    "user_profile" => "แก้ไขข้อมูลผู้ใช้งาน",
    "settings" => "ข้อมูลอ้างอิง",
    "audit" => "ประวัติการทำรายการ",
    "help" => "คู่มือการใช้งาน",
    "case_form" => "บันทึกแฟ้ม",
    "detail" => "แฟ้มประวัติ",
    "period_form" => "บันทึกช่วงการลา",
    "return_form" => "รายงานตัวกลับ",
    "cancel_form" => "ยกเลิกเรื่อง",
    "password" => "เปลี่ยนรหัสผ่าน",
    "login" => "เข้าสู่ระบบ",
    "register" => "ลงทะเบียน",
    "forgot" => "ลืมรหัสผ่าน",
    "reset" => "ตั้งรหัสผ่านใหม่",
];
$title = $titles[$page] ?? "ระบบลาศึกษาและฝึกอบรม";
// หน้าก่อนเข้าสู่ระบบที่ใช้ธีมและฉาก 3D ร่วมกัน
$useAuthTheme = !$user && in_array($page, ["login", "register", "forgot"], true);
$workspaceLinks = [
    "overview" => "layout-dashboard",
    "registry" => "files",
    "ledger" => "file-text",
    "reports" => "chart-no-axes-combined",
];
$adminLinks = [
    "users" => "users",
    "settings" => "sliders-horizontal",
    "audit" => "history",
];
$pendingUserRequests = pending_user_requests();
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="theme-color" content="#201f30">
    <title><?= h($title) ?> · กรมควบคุมโรค</title>
    <link rel="icon" href="assets/favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="assets/app.css">
    <link rel="stylesheet" href="assets/server.css?v=2">
    <link rel="stylesheet" href="assets/ui.css?v=4">
    <link rel="stylesheet" href="assets/navigation.css?v=2">
    <script src="assets/navigation.js?v=3" defer></script>
    <script src="assets/app.js?v=4" defer></script>
    <script src="assets/ui.js?v=9" defer></script>
    <link rel="stylesheet" href="assets/notifications.css?v=1">
    <script src="assets/notifications.js?v=1" defer></script>
    <?php if (in_array($page, ["registry", "ledger"], true)): ?>
        <link rel="stylesheet" href="assets/record-pagination.css?v=1">
        <script src="assets/record-pagination.js?v=1" defer></script>
    <?php endif; ?>
    <link rel="stylesheet" href="assets/be-datepicker.css?v=1">
    <script src="assets/be-datepicker.js?v=2" defer></script>
    <link rel="stylesheet" href="assets/select-search.css?v=1">
    <script src="assets/select-search.js?v=2" defer></script>
    <?php if ($useAuthTheme): ?>
        <link rel="stylesheet" href="assets/login.css?v=10">
    <?php endif; ?>
    <?php if ($page === "ledger"): ?>
        <link rel="stylesheet" href="assets/ledger-display.css?v=3">
        <script src="assets/ledger-display.js?v=2" defer></script>
    <?php endif; ?>
    <?php if ($page === "audit"): ?>
        <link rel="stylesheet" href="assets/audit.css?v=2">
    <?php endif; ?>
    <link rel="stylesheet" href="assets/dropdown.css?v=2">
    <?php if ($page === "settings"): ?>
        <link rel="stylesheet" href="assets/reference-settings.css?v=2">
    <?php endif; ?>
    <?php if (in_array($page, ["case_form", "period_form", "return_form", "cancel_form"], true)): ?>
        <link rel="stylesheet" href="assets/leave-fields.css?v=4">
        <script src="assets/leave-fields.js?v=3" defer></script>
    <?php endif; ?>
    <!-- ขนาดพื้นที่ทำงานและแสงช่องกรอก โหลดหลังรูปแบบเฉพาะหน้า -->
    <link rel="stylesheet" href="assets/density.css?v=1">
</head>
<body<?= $useAuthTheme ? ' class="login-page' . ($page === "register" ? ' register-page' : '') . '"' : ($page === "settings" ? ' class="settings-page"' : "") ?>>
<a class="skip" href="#main">ข้ามไปยังเนื้อหา</a>

<?php if ($useAuthTheme): ?>
    <!-- ฉาก CSS 3D: ลูกบาศก์ขอบคมและทรงกระบอก ไม่รับคลิกหรืออ่านโดยโปรแกรมอ่านหน้าจอ -->
    <div class="login-art" aria-hidden="true">
        <div class="login-scene">
            <?php foreach (["upper", "lower"] as $cylinder): ?>
                <div class="cylinder-position cylinder-position--<?= $cylinder ?>">
                    <div class="login-cylinder">
                        <?php foreach (range(0, 19) as $segment): ?>
                            <div class="cylinder-side cylinder-side--<?= $segment ?>"></div>
                        <?php endforeach; ?>
                        <div class="cylinder-cap cylinder-cap--top"></div>
                        <div class="cylinder-cap cylinder-cap--bottom"></div>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php foreach (["front", "back", "small"] as $cube): ?>
                <div class="cube-position cube-position--<?= $cube ?>">
                    <div class="login-cube">
                        <?php foreach (["front", "back", "right", "left", "top", "bottom"] as $face): ?>
                            <div class="cube-face cube-face--<?= $face ?>"></div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
            <div class="login-orb login-orb--top"></div>
            <div class="login-orb login-orb--bottom"></div>
            <div class="login-art-shadow"></div>
        </div>
    </div>
<?php endif; ?>

<?php if ($user): ?>
    <div class="shell">
        <div class="mobile-shade" id="shade"></div>
        <aside class="sidebar" id="sidebar" aria-label="เมนูหลัก">
            <div class="brand">
                <div class="brand-mark"><?= icon("book-open") ?></div>
                <div>
                    <strong>กลุ่มข้อมูลการบริหารงานบุคคล</strong>
                    <small>DEPARTMENT OF DISEASE CONTROL</small>
                </div>
            </div>

            <details class="nav-group" data-nav-group="workspace" open>
            <summary>พื้นที่ทำงาน<span class="nav-chevron" aria-hidden="true"></span></summary>
            <nav class="nav">
                <?php foreach ($workspaceLinks as $key => $glyph): ?>
                    <a href="<?= h(url($key)) ?>"
                       <?= $page === $key ? 'class="active" aria-current="page"' : "" ?>>
                        <?= icon($glyph) . h($titles[$key]) ?>
                    </a>
                <?php endforeach; ?>
            </nav>
            </details>

            <?php if (is_admin()): ?>
                <details class="nav-group" data-nav-group="admin" open>
                <summary>จัดการระบบ
                    <?php if ($pendingUserRequests > 0): ?>
                        <span class="request-count group-request-count" aria-label="คำขอรอดำเนินการ <?= $pendingUserRequests ?> รายการ"><?= number_format($pendingUserRequests) ?></span>
                    <?php endif; ?>
                    <span class="nav-chevron" aria-hidden="true"></span>
                </summary>
                <nav class="nav">
                    <?php foreach ($adminLinks as $key => $glyph): ?>
                        <a href="<?= h(url($key)) ?>"
                           <?= $page === $key ? 'class="active" aria-current="page"' : "" ?>>
                            <?= icon($glyph) . h($titles[$key]) ?>
                            <?php if ($key === "users" && $pendingUserRequests > 0): ?>
                                <span class="request-count"
                                      aria-label="คำขอรอดำเนินการ <?= $pendingUserRequests ?> รายการ"
                                      title="คำขอลงทะเบียนและรีเซ็ตรหัสผ่านที่รอดำเนินการ">
                                    <?= number_format($pendingUserRequests) ?>
                                </span>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </nav>
                </details>
            <?php endif; ?>

            <div class="sidebar-bottom">
                <nav class="nav">
                    <a href="<?= h(url("help")) ?>">
                        <?= icon("circle-help") ?>คู่มือการใช้งาน
                    </a>
                </nav>
                <div class="edition">
                    <b>STUDY LEAVE & TRAINING</b>
                    กองบริหารทรัพยากรบุคคล<br>กรมควบคุมโรค
                </div>
            </div>
        </aside>

        <div class="workspace">
            <header class="topbar">
                <button type="button"
                        class="icon-button menu-toggle"
                        id="menuButton"
                        aria-label="ซ่อนหรือแสดงเมนู"
                        aria-controls="sidebar"
                        aria-expanded="true">
                    <?= icon("menu") ?>
                </button>
                <div class="breadcrumb"><?= h($title) ?></div>
                <div class="top-actions">
                    <details class="account-menu" id="accountMenu">
                    <summary class="account" aria-label="เมนูบัญชีของ <?= h($user["name"]) ?>">
                        <span class="avatar"><?= h(mb_substr($user["name"], 0, 2)) ?></span>
                        <div class="account-copy">
                            <strong><?= h($user["name"]) ?></strong>
                            <small><?= h(ROLE_LABELS[$user["role"]]) ?></small>
                        </div>
                        <span class="nav-chevron" aria-hidden="true"></span>
                    </summary>
                    <div class="account-dropdown">
                        <div class="account-menu-heading">บัญชีของฉัน</div>
                        <nav aria-label="เมนูบัญชี">
                            <a href="<?= h(url("profile")) ?>" <?= $page === "profile" ? 'aria-current="page"' : "" ?>><?= icon("users") ?>ข้อมูลส่วนตัว</a>
                            <a href="<?= h(url("password")) ?>" <?= $page === "password" ? 'aria-current="page"' : "" ?>><?= icon("shield-check") ?>เปลี่ยนรหัสผ่าน</a>
                        </nav>
                        <form method="post" action="index.php">
                        <?= csrf() . hidden("action", "logout") ?>
                        <button class="logout" type="submit">ออกจากระบบ</button>
                    </form>
                    </div>
                    </details>
                </div>
            </header>
            <main class="main" id="main">
<?php else: ?>
    <main class="auth-main" id="main">
        <div class="auth-brand">
            <?= icon("book-open") ?> กรมควบคุมโรค · กลุ่มข้อมูลการบริหารงานบุคคล
        </div>
<?php endif; ?>

<?php if (isset($_SESSION["flash"])): ?>
    <?php
    $f = $_SESSION["flash"];
    unset($_SESSION["flash"]);
    $notificationError = $f["kind"] === "error";
    ?>
    <div class="notice flash-notice <?= $notificationError ? "error-notice" : "success-notice" ?>"
         role="<?= $notificationError ? "alert" : "status" ?>" aria-atomic="true">
        <span class="notification-icon" aria-hidden="true"><?= $notificationError ? "!" : icon("check") ?></span>
        <div class="notification-copy">
            <strong><?= $notificationError ? "ทำรายการไม่สำเร็จ" : "ดำเนินการเรียบร้อย" ?></strong>
            <p><?= h($f["text"]) ?></p>
        </div>
        <button type="button" class="notification-close" aria-label="ปิดการแจ้งเตือน" hidden>×</button>
    </div>
<?php endif; ?>

<?php render_content($page); ?>

<footer class="footnote">
    <span>ระบบบันทึกการลาศึกษาและฝึกอบรม</span>
    <span>กองบริหารทรัพยากรบุคคล / กรมควบคุมโรค</span>
</footer>
</main>

<?php if ($user): ?>
        </div>
    </div>
<?php endif; ?>

<!-- ใช้ร่วมกันทุกหน้า: ui.js เปิดเฉพาะก่อนยืนยันรายการสำคัญ -->
<dialog id="confirmDialog" class="confirm-dialog"
        aria-labelledby="confirmTitle" aria-describedby="confirmDescription">
    <div class="dialog-header">
        <h2 id="confirmTitle">ยืนยันการทำรายการ</h2>
    </div>
    <div class="dialog-body">
        <p id="confirmDescription"></p>
    </div>
    <div class="dialog-footer">
        <button type="button" id="confirmCancel" autofocus>กลับไปตรวจสอบ</button>
        <button type="button" id="confirmAccept" class="primary">ยืนยัน</button>
    </div>
</dialog>
</body>
</html>
