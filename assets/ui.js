"use strict";

// ตัวช่วยหน้าจอเท่านั้น: PHP ยังตรวจข้อมูล สิทธิ์ และ CSRF ตามเดิมทุกครั้ง
(() => {
    const dialog = document.getElementById("confirmDialog");
    const cancelButton = document.getElementById("confirmCancel");
    const acceptButton = document.getElementById("confirmAccept");
    let pending = null;
    let fieldNumber = 0;

    function actionOf(form) {
        return form.querySelector('[name="action"]')?.value || "";
    }

    // แปลงวันที่ พ.ศ. สำหรับตรวจหน้าจอ โดยใช้ขอบเขตเดียวกับ date_input() ใน PHP
    function parseDate(value) {
        const parts = /^(\d{2})\/(\d{2})\/(\d{4})$/.exec(value.trim());
        if (!parts) return null;
        const [, day, month, year] = parts.map(Number);
        if (year < 2400 || year > 2800) return null;
        const date = new Date(Date.UTC(year - 543, month - 1, day));
        return date.getUTCFullYear() === year - 543 &&
            date.getUTCMonth() === month - 1 &&
            date.getUTCDate() === day
            ? date.getTime()
            : null;
    }

    function fieldLabel(field) {
        const label = field.labels?.[0];
        if (!label) return field.getAttribute("aria-label") || "ช่องนี้";
        const copy = label.cloneNode(true);
        copy.querySelectorAll(
            "input, select, textarea, .field-error, .field-hint, .required, .search-select, .be-date-trigger",
        ).forEach((node) => node.remove());
        return copy.textContent.trim() || "ช่องนี้";
    }

    function fieldMessage(field, form) {
        if (field.disabled || !field.willValidate) return "";
        const value = field.value;
        const action = actionOf(form);
        if (
            field.required &&
            (field.type === "checkbox" ? !field.checked : !value.trim())
        ) {
            return field.type === "checkbox"
                ? "กรุณายืนยันรายการนี้"
                : "กรุณาระบุ" + fieldLabel(field);
        }
        if (!value) return "";
        if (field.hasAttribute("data-be-date")) {
            const date = parseDate(value);
            if (date === null)
                return "ระบุวันที่ที่มีจริง เช่น 01/07/2569 (พ.ศ. 2400–2800)";
            if (field.name === "end_date") {
                const start = parseDate(
                    form.elements.namedItem("start_date")?.value || "",
                );
                if (start !== null && date < start)
                    return "วันที่สิ้นสุดต้องไม่ก่อนวันที่เริ่ม";
            }
            if (field.name === "action_date") {
                // ใช้วันที่จากเซิร์ฟเวอร์เพื่อไม่ให้เขตเวลาบนอุปกรณ์ผู้ใช้คลาดเคลื่อน
                const today = parseDate(form.dataset.today || "");
                if (today !== null && date > today)
                    return "วันที่รายงานตัวหรือยกเลิกต้องไม่เกินวันนี้";
            }
        }
        if (
            field.name === "fiscal_year" &&
            !/^(24\d{2}|2[5-7]\d{2}|2800)$/.test(value.trim())
        ) {
            return "ระบุปีงบประมาณ พ.ศ. 4 หลัก ระหว่าง 2400–2800";
        }
        if (
            field.name === "username" &&
            action === "register" &&
            !/^[a-zA-Z0-9_.-]{3,80}$/.test(value.trim())
        ) {
            return "ใช้ภาษาอังกฤษ ตัวเลข _ . หรือ - จำนวน 3–80 ตัวอักษร";
        }
        if (
            field.name === "password" &&
            ["register", "reset", "password"].includes(action)
        ) {
            if ([...value].length < 12) return "รหัสผ่านต้องมีอย่างน้อย 12 ตัวอักษร";
            if (new TextEncoder().encode(value).length > 72)
                return "รหัสผ่านยาวเกิน 72 ไบต์ (อักษรไทยใช้หลายไบต์ต่อตัว)";
            if (value.includes("\0")) return "รหัสผ่านมีอักขระที่ใช้ไม่ได้";
        }
        if (
            field.name === "password_confirm" &&
            value !== form.elements.namedItem("password")?.value
        ) {
            return "รหัสผ่านทั้งสองช่องไม่ตรงกัน";
        }
        if (field.validity.typeMismatch) return "กรุณาตรวจสอบรูปแบบข้อมูลในช่องนี้";
        if (field.validity.patternMismatch) return "กรุณากรอกข้อมูลตามรูปแบบที่ระบุ";
        if (field.validity.tooLong) return "ข้อความยาวเกินจำนวนที่กำหนด";
        if (!field.validity.valid) return "กรุณาตรวจสอบข้อมูลในช่องนี้";
        return "";
    }

    function showFieldError(field, message) {
        const error = document.getElementById(field.dataset.errorId);
        if (!error) return;
        error.textContent = message;
        error.hidden = !message;
        if (message) field.setAttribute("aria-invalid", "true");
        else field.removeAttribute("aria-invalid");
    }

    function confirmation(form, submitter) {
        const action = actionOf(form);
        const name =
            form
                .closest("tr")
                ?.querySelector("td")
                ?.innerText.trim()
                .replace(/\n+/g, " / ") || "";
        const subject = name ? `รายการ: ${name}\n` : "";
        if (action === "user_state") {
            const state = form.elements.namedItem("state");
            return {
                title: "ยืนยันเปลี่ยนสถานะบัญชี?",
                text:
                    subject +
                    "สถานะใหม่: " +
                    state.selectedOptions[0].text +
                    "\nSession และลิงก์รีเซ็ตเดิมจะใช้ไม่ได้ ผู้ใช้เข้าสู่ระบบได้เฉพาะสถานะอนุมัติแล้ว",
                danger: state.value !== "approved",
            };
        }
        if (action === "cancel_save")
            return {
                title: "ยืนยันยกเลิกเรื่องนี้?",
                text: "เมื่อบันทึกแล้วจะเพิ่มช่วงการลาใหม่ไม่ได้ ประวัติเดิมยังคงอยู่ กรุณาตรวจสอบวันที่และเหตุผลก่อนยืนยัน",
                danger: true,
            };
        if (action === "return_save" && form.elements.namedItem("entry_id"))
            return {
                title: "ยืนยันแก้ไขรายงานตัวกลับ?",
                text: "ระบบจะบันทึกทับรายการรายงานตัวเดิมและเก็บประวัติการแก้ไข แฟ้มยังคงสถานะปิดเรื่อง กรุณาตรวจสอบข้อมูลก่อนยืนยัน",
            };
        if (action === "return_save")
            return {
                title: "ยืนยันรายงานตัวกลับ?",
                text: "เมื่อบันทึกแล้วเรื่องนี้จะปิดและเพิ่มช่วงการลาใหม่ไม่ได้ กรุณาตรวจสอบวันที่รายงานตัวก่อนยืนยัน",
            };
        if (
            ["reference_active", "type_active"].includes(action) &&
            form.elements.namedItem("active")?.value === "0"
        )
            return {
                title: "ปิดใช้งานรายการนี้?",
                text:
                    subject +
                    "รายการนี้จะไม่เป็นตัวเลือกสำหรับเรื่องใหม่ ข้อมูลในเรื่องเดิมยังอยู่ และสามารถเปิดใช้งานอีกครั้งได้",
                danger: true,
            };
        if (action === "issue_reset")
            return {
                title: "ออกลิงก์ตั้งรหัสผ่านใหม่?",
                text:
                    subject +
                    "โปรดยืนยันว่าตรวจสอบตัวตนของเจ้าของบัญชีแล้ว ลิงก์ใหม่ใช้ได้ครั้งเดียวและมีอายุ 30 นาที",
            };
        if (action === "user_decision")
            return {
                title:
                    submitter?.value === "rejected"
                        ? "ไม่อนุมัติบัญชีนี้?"
                        : "อนุมัติบัญชีนี้?",
                text:
                    subject +
                    (submitter?.value === "rejected"
                        ? "บัญชีนี้จะยังเข้าใช้งานไม่ได้ กรุณาตรวจสอบก่อนยืนยัน"
                        : "บัญชีนี้จะเข้าใช้งานได้ตามบทบาทที่เลือก กรุณาตรวจสอบบทบาทก่อนยืนยัน"),
                danger: submitter?.value === "rejected",
            };
        return null;
    }

    function resetBusy(form) {
        form.removeAttribute("aria-busy");
        form.querySelector(".submit-progress")?.remove();
    }

    document.querySelectorAll("form.app-form").forEach((form) => {
        const authForm = Boolean(form.closest(".login-page .auth-panel"));
        const fields = [
            ...form.querySelectorAll("input:not([type=hidden]), select, textarea"),
        ];
        const summary = document.createElement("div");
        summary.className = "validation-summary";
        summary.setAttribute("role", "alert");
        summary.tabIndex = -1;
        summary.hidden = true;
        form.prepend(summary);

        // ปิด bubble ของ browser เมื่อ JS พร้อมแล้ว เพื่อแสดงข้อความไทยใต้ช่องแทน
        // หาก JS ไม่ทำงาน HTML required/pattern ยังคงช่วยตรวจตามปกติ
        form.noValidate = true;
        fields.forEach((field) => {
            field.id ||= `ui-field-${++fieldNumber}`;
            const error = document.createElement("span");
            error.id = `${field.id}-error`;
            error.className = "field-error";
            error.hidden = true;
            field.dataset.errorId = error.id;
            field.setAttribute(
                "aria-describedby",
                [field.getAttribute("aria-describedby"), error.id]
                    .filter(Boolean)
                    .join(" "),
            );
            field.insertAdjacentElement("afterend", error);
            field.addEventListener("blur", () =>
                showFieldError(field, fieldMessage(field, form)),
            );
        });

        function validate() {
            const errors = [];
            fields.forEach((field) => {
                const message = fieldMessage(field, form);
                showFieldError(field, message);
                if (message) errors.push({ field, message });
            });
            summary.replaceChildren();
            summary.hidden = errors.length === 0;
            if (errors.length) {
                const title = document.createElement("strong");
                title.textContent = authForm
                    ? `กรุณาตรวจสอบช่องที่ทำเครื่องหมาย (${errors.length} ช่อง)`
                    : `กรุณาตรวจสอบข้อมูล ${errors.length} จุดก่อนบันทึก`;
                summary.append(title);
                if (!authForm)
                    errors.forEach(({ field, message }) => {
                        const link = document.createElement("a");
                        link.href = `#${field.id}`;
                        link.textContent = message;
                        link.addEventListener("click", (event) => {
                            event.preventDefault();
                            field.focus();
                        });
                        summary.append(link);
                    });
            }
            return errors.length === 0;
        }

        form.addEventListener("input", (event) => {
            const field = event.target;
            if (field.getAttribute("aria-invalid") === "true")
                showFieldError(field, fieldMessage(field, form));
            // ตรวจช่องที่ขึ้นต่อกันอีกครั้งเมื่อแก้รหัสผ่านหรือวันเริ่ม
            for (const name of ["password_confirm", "end_date"]) {
                const related = form.elements.namedItem(name);
                if (related?.getAttribute("aria-invalid") === "true")
                    showFieldError(related, fieldMessage(related, form));
            }
            if (!summary.hidden) validate();
        });
        form.addEventListener("change", () => {
            // app.js เปิด/ปิดช่องประเทศและประเภทก่อนตรวจ ไม่แจ้งผิดในช่องที่ซ่อนแล้ว
            if (!summary.hidden) validate();
            else
                fields
                    .filter((field) => field.disabled)
                    .forEach((field) => showFieldError(field, ""));
        });

        let confirmed = false;
        form.addEventListener("submit", (event) => {
            if (form.getAttribute("aria-busy") === "true") {
                event.preventDefault();
                return;
            }
            if (!validate()) {
                event.preventDefault();
                if (authForm)
                    fields
                        .find((field) => field.getAttribute("aria-invalid") === "true")
                        ?.focus();
                else summary.focus();
                return;
            }
            const confirm = confirmation(form, event.submitter);
            if (confirm && !confirmed && dialog?.showModal) {
                event.preventDefault();
                document.getElementById("confirmTitle").textContent = confirm.title;
                document.getElementById("confirmDescription").textContent = confirm.text;
                acceptButton.classList.toggle("danger", Boolean(confirm.danger));
                pending = () => {
                    confirmed = true;
                    // requestSubmit เก็บ name/value ของปุ่ม เช่น อนุมัติ/ไม่อนุมัติครบถ้วน
                    try {
                        form.requestSubmit(event.submitter || undefined);
                    } finally {
                        confirmed = false;
                    }
                };
                dialog.showModal();
                cancelButton.focus();
                return;
            }
            form.setAttribute("aria-busy", "true");
            const progress = document.createElement("span");
            progress.className = "submit-progress";
            progress.setAttribute("role", "status");
            progress.textContent = "กำลังส่งข้อมูล…";
            (form.querySelector(".form-actions") || form).append(progress);
            // ไม่ disable ปุ่ม: ปุ่มที่มี name/value ต้องถูกส่งไป PHP ด้วย
        });
    });

    cancelButton?.addEventListener("click", () => {
        pending = null;
        dialog.close();
    });
    dialog?.addEventListener("cancel", () => {
        pending = null;
    });
    dialog?.addEventListener("keydown", (event) => {
        // คงลำดับ Tab ภายในหน้าต่างยืนยัน รวมการย้อนกลับด้วย Shift+Tab
        if (event.key !== "Tab") return;
        if (event.shiftKey && document.activeElement === cancelButton) {
            event.preventDefault();
            acceptButton.focus();
        } else if (!event.shiftKey && document.activeElement === acceptButton) {
            event.preventDefault();
            cancelButton.focus();
        }
    });
    acceptButton?.addEventListener("click", () => {
        const submit = pending;
        pending = null;
        dialog.close();
        submit?.();
    });

    window.addEventListener("pageshow", () => {
        document.querySelectorAll("form[aria-busy]").forEach(resetBusy);
    });

    // แจ้งผลจาก PHP ไว้จนผู้ใช้เปลี่ยนหน้า เพื่อให้อ่านทัน ไม่ปิดอัตโนมัติ
    const serverError = document.querySelector(".flash-notice.error-notice");
    if (serverError) {
        serverError.tabIndex = -1;
        serverError.focus();
    }
})();
