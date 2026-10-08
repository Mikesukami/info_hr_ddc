"use strict";

// ใช้ GET เดิมของทะเบียน: เปลี่ยนจำนวนแล้วเริ่มหน้า 1 พร้อมตัวกรองที่บันทึกไว้
document.querySelectorAll(".record-size-form").forEach((form) => {
    form.querySelector("select").addEventListener("change", () => form.requestSubmit());
});
