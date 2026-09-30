<?php
defined("APP_ENTRY") || exit();

/**
 * แผนที่สิทธิ์กลาง: index.php ใช้ตรวจทั้ง URL (pages) และ POST (actions)
 * null คือคำสั่งก่อน login แต่ยังต้องผ่านการตรวจ CSRF
 */
$staff = ["officer", "admin", "superAdmin"];
$admins = ["admin", "superAdmin"];
$superAdmin = ["superAdmin"];

return [
    "public" => ["login", "register", "forgot", "reset"],
    "pages" => [
        "overview" => $staff,
        "registry" => $staff,
        "ledger" => $staff,
        "reports" => $staff,
        "detail" => $staff,
        "case_form" => $staff,
        "period_form" => $staff,
        "return_form" => $staff,
        "cancel_form" => $staff,
        "help" => $staff,
        "password" => $staff,
        "export" => $staff,
        "users" => $admins,
        "settings" => $admins,
        "audit" => $admins,
    ],
    "actions" => [
        "login" => null,
        "register" => null,
        "forgot" => null,
        "reset" => null,
        "password" => $staff,
        "logout" => $staff,
        "case_save" => $staff,
        "period_save" => $staff,
        "return_save" => $staff,
        "cancel_save" => $staff,
        "type_save" => $admins,
        "type_active" => $admins,
        "reference_create" => $admins,
        "reference_active" => $admins,
        "user_decision" => $superAdmin,
        "user_state" => $superAdmin,
        "issue_reset" => $superAdmin,
    ],
];
