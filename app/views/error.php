<?php defined("APP_ENTRY") || exit(); ?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="theme-color" content="#201f30">
    <title><?= $status ?> · <?= $escape($title) ?> · กรมควบคุมโรค</title>
    <link rel="icon" href="<?= $escape($basePath) ?>assets/favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="<?= $escape($basePath) ?>assets/error-page.css?v=1">
</head>
<body>
    <header class="error-brand">
        <span class="brand-mark" aria-hidden="true">HR</span>
        <span>กลุ่มข้อมูลการบริหารงานบุคคล<small>กรมควบคุมโรค</small></span>
    </header>
    <main class="error-main">
        <section class="error-card" aria-labelledby="error-title">
            <div class="error-art" aria-hidden="true">
                <svg viewBox="0 0 320 240" focusable="false">
                    <circle cx="160" cy="119" r="105" fill="#efedff"/>
                    <circle cx="55" cy="61" r="7" fill="#c5bfff"/>
                    <circle cx="274" cy="164" r="10" fill="#d9d5ff"/>
                    <path d="M65 192h193" stroke="#cbc6ef" stroke-width="3" stroke-linecap="round"/>
                    <rect x="72" y="65" width="170" height="116" rx="12" fill="white" stroke="#a79fe2" stroke-width="3"/>
                    <path d="M73 93h167" stroke="#e5e2f6" stroke-width="3"/>
                    <circle cx="87" cy="79" r="3" fill="#b6afe5"/>
                    <circle cx="98" cy="79" r="3" fill="#b6afe5"/>
                    <circle cx="109" cy="79" r="3" fill="#b6afe5"/>
                    <path d="M112 120h72m-72 17h51m-51 17h32" stroke="#d6d1ee" stroke-width="7" stroke-linecap="round"/>
                    <circle cx="235" cy="67" r="33" fill="#6558db"/>
                    <?php if (in_array($status, [401, 403], true)): ?>
                        <rect x="221" y="65" width="28" height="21" rx="4" fill="white"/>
                        <path d="M226 65v-8a9 9 0 0 1 18 0v8" fill="none" stroke="white" stroke-width="4"/>
                    <?php else: ?>
                        <path d="M235 50v19" stroke="white" stroke-width="5" stroke-linecap="round"/>
                        <circle cx="235" cy="80" r="3" fill="white"/>
                    <?php endif; ?>
                    <path d="M169 184h41l9 12h-60z" fill="#a79fe2"/>
                </svg>
                <span class="error-code"><?= $status ?></span>
            </div>
            <div class="error-copy">
                <p class="error-eyebrow">ระบบบันทึกการลาศึกษาและฝึกอบรม</p>
                <h1 id="error-title"><?= $escape($title) ?></h1>
                <p class="error-message"><?= $escape($message) ?></p>
                <p class="error-hint"><?= $escape($hint) ?></p>
                <nav class="error-actions" aria-label="ทางเลือกเมื่อพบข้อผิดพลาด">
                    <a class="error-button primary" href="<?= $escape($home) ?>">กลับหน้าหลัก</a>
                    <?php if (in_array($status, [401, 403], true)): ?>
                        <a class="error-button secondary" href="<?= $escape($login) ?>">เปิดหน้าเข้าสู่ระบบ</a>
                    <?php endif; ?>
                </nav>
            </div>
        </section>
    </main>
    <footer class="error-footer">กลุ่มข้อมูลการบริหารงานบุคคล · กองบริหารทรัพยากรบุคคล</footer>
</body>
</html>
