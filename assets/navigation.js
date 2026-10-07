"use strict";

// หมวดเมนูจำสถานะที่ผู้ใช้เลือก แต่หมวดของหน้าปัจจุบันจะเปิดเสมอ
(() => {
    const reducedMotion = matchMedia("(prefers-reduced-motion: reduce)");
    document.querySelectorAll("[data-nav-group]").forEach((group) => {
        const key = "study-leave-nav-" + group.dataset.navGroup;
        const active = Boolean(group.querySelector('[aria-current="page"]'));
        let saved = null;
        try {
            saved = localStorage.getItem(key);
        } catch {}
        group.open = active || (saved !== null ? saved === "open" : innerHeight >= 650);
        const summary = group.querySelector(":scope > summary");
        const panel = group.querySelector(":scope > nav");
        if (!summary || !panel) return;
        let expanded = group.open;
        let animation = null;
        panel.id = "nav-panel-" + group.dataset.navGroup;
        summary.setAttribute("aria-controls", panel.id);

        function syncState() {
            group.dataset.navExpanded = String(expanded);
            summary.setAttribute("aria-expanded", String(expanded));
            panel.inert = !expanded;
        }
        function settle() {
            // ปิด details หลังหุบเสร็จ; เปิดเสร็จคืนความสูงอัตโนมัติรองรับเมนูใหม่
            group.open = expanded;
            animation?.cancel();
            animation = null;
            group.classList.remove("nav-animating");
        }
        summary.addEventListener("click", (event) => {
            event.preventDefault();
            const height = group.open ? panel.getBoundingClientRect().height : 0;
            const opacity = group.open ? getComputedStyle(panel).opacity : "0";
            animation?.cancel();
            animation = null;
            expanded = !expanded;
            if (!expanded && panel.contains(document.activeElement)) summary.focus();
            syncState();
            try {
                localStorage.setItem(key, expanded ? "open" : "closed");
            } catch {}
            if (reducedMotion.matches || !panel.animate) {
                settle();
                return;
            }
            // เก็บ details ไว้เปิดระหว่างเคลื่อนไหว เพื่อให้เบราว์เซอร์ยังวาดรายการได้
            group.open = true;
            group.classList.add("nav-animating");
            const current = panel.animate(
                [
                    { height: height + "px", opacity },
                    {
                        height: (expanded ? panel.scrollHeight : 0) + "px",
                        opacity: expanded ? 1 : 0,
                    },
                ],
                { duration: 260, easing: "cubic-bezier(0.2, 0, 0, 1)", fill: "both" },
            );
            animation = current;
            current.onfinish = () => {
                if (animation === current) settle();
            };
        });
        reducedMotion.addEventListener("change", () => {
            if (reducedMotion.matches && animation) settle();
        });
        syncState();
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
