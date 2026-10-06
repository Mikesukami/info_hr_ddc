"use strict";

// หมวดเมนูจำสถานะที่ผู้ใช้เลือก แต่หมวดของหน้าปัจจุบันจะเปิดเสมอ
(() => {
    document.querySelectorAll("[data-nav-group]").forEach((group) => {
        const key = "study-leave-nav-" + group.dataset.navGroup;
        const active = Boolean(group.querySelector('[aria-current="page"]'));
        let saved = null;
        try {
            saved = localStorage.getItem(key);
        } catch {}
        group.open = active || (saved !== null ? saved === "open" : innerHeight >= 650);
        group.addEventListener("toggle", () => {
            try {
                localStorage.setItem(key, group.open ? "open" : "closed");
            } catch {}
        });
    });

    // details/summary ใช้ Enter/Space และ Tab ได้ตามมาตรฐานของเบราว์เซอร์
    const account = document.getElementById("accountMenu");
    if (!account) return;
    const trigger = account.querySelector("summary");
    function close(restoreFocus = false) {
        account.open = false;
        if (restoreFocus) trigger.focus();
    }
    document.addEventListener("click", (event) => {
        if (account.open && !account.contains(event.target)) close();
    });
    document.addEventListener("keydown", (event) => {
        if (event.key === "Escape" && account.open) {
            event.preventDefault();
            close(true);
        }
    });
    account.addEventListener("focusout", (event) => {
        // relatedTarget คือปลายทางโฟกัสจริง ไม่อ่าน activeElement ระหว่าง blur/focus
        // เพราะช่วงนั้นอาจเป็น body และทำให้เมนูปิดก่อนลิงก์/ปุ่มรับ click
        // ถ้าไม่ทราบปลายทาง ให้ click ภายนอกหรือ Escape เป็นผู้ปิดแทน
        if (event.relatedTarget && !account.contains(event.relatedTarget)) close();
    });
})();
