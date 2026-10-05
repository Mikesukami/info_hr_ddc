"use strict";

// หัวข้อเลื่อนขึ้นบนขอบช่อง เฉพาะฟอร์มลา: คง label, input, ชื่อและค่าเดิมทั้งหมด
(() => {
    const syncFields = [];
    document.querySelectorAll(".form-page label").forEach((label) => {
        const field = label.querySelector(
            ":scope > input:not([type=hidden]):not([type=checkbox]), :scope > select, :scope > textarea, :scope > .be-date-field > input",
        );
        if (!field) return;

        const caption = document.createElement("span");
        caption.className = "leave-floating-caption";
        // ย้ายเฉพาะหัวข้อและเครื่องหมาย * ข้อความช่วยเหลือกับข้อผิดพลาดคงอยู่ใต้ช่อง
        [...label.childNodes].forEach((node) => {
            if (
                (node.nodeType === Node.TEXT_NODE && node.textContent.trim()) ||
                (node.nodeType === Node.ELEMENT_NODE &&
                    node.classList.contains("required"))
            ) {
                caption.append(node);
            }
        });
        if (!caption.textContent.trim()) return;
        label.prepend(caption);
        label.classList.add("leave-floating-label");
        const select = field.tagName === "SELECT";
        label.classList.toggle("is-select", select);
        label.classList.toggle("is-textarea", field.tagName === "TEXTAREA");

        function sync() {
            label.classList.toggle(
                "is-raised",
                select || field.value !== "" || label.contains(document.activeElement),
            );
            label.classList.toggle(
                "is-invalid",
                field.getAttribute("aria-invalid") === "true",
            );
            label.classList.toggle("is-disabled", field.disabled);
        }
        field.addEventListener("input", sync);
        field.addEventListener("change", sync);
        label.addEventListener("focusin", sync);
        label.addEventListener("focusout", () => queueMicrotask(sync));
        new MutationObserver(sync).observe(field, {
            attributes: true,
            attributeFilter: ["aria-invalid", "disabled", "value"],
        });
        syncFields.push(sync);
        sync();
    });
    window.addEventListener("pageshow", () => syncFields.forEach((sync) => sync()));
    document.querySelectorAll(".form-page form").forEach((form) => {
        form.addEventListener("reset", () =>
            setTimeout(() => syncFields.forEach((sync) => sync()), 0),
        );
    });
})();
