"use strict";

// Progressive enhancement: select เดิมยังเป็นค่าที่ส่งให้ PHP
// ข้อความค้นหาไม่มี name และไม่ถูกบันทึกเป็นข้อมูลอ้างอิงใหม่
(() => {
    let sequence = 0;
    const closeAll = new Set();
    document.querySelectorAll("select[data-searchable]").forEach((select) => {
        const label = select.labels?.[0];
        const labelCopy = label?.cloneNode(true);
        labelCopy
            ?.querySelectorAll("select, .required, .field-error")
            .forEach((n) => n.remove());
        const title = labelCopy?.textContent.trim() || "เลือกรายการ";
        const id = `search-select-${++sequence}`;
        const wrapper = document.createElement("div");
        wrapper.className = "search-select";
        const trigger = document.createElement("button");
        trigger.type = "button";
        trigger.className = "search-select-trigger";
        trigger.setAttribute("aria-label", title);
        trigger.setAttribute("aria-haspopup", "listbox");
        trigger.setAttribute("aria-expanded", "false");
        trigger.setAttribute("aria-controls", id);
        const popup = document.createElement("div");
        popup.className = "search-select-popup";
        popup.hidden = true;
        const search = document.createElement("input");
        search.type = "search";
        search.placeholder = "พิมพ์คำบางส่วนเพื่อค้นหา…";
        search.autocomplete = "off";
        search.setAttribute("role", "combobox");
        search.setAttribute("aria-label", "ค้นหา" + title);
        search.setAttribute("aria-autocomplete", "list");
        search.setAttribute("aria-controls", id);
        search.setAttribute("aria-expanded", "false");
        const list = document.createElement("div");
        list.id = id;
        list.className = "search-select-options";
        list.setAttribute("role", "listbox");
        list.setAttribute("aria-label", title);
        const status = document.createElement("div");
        status.className = "search-select-status";
        status.setAttribute("role", "status");
        popup.append(search, list, status);
        wrapper.append(trigger, popup);
        select.after(wrapper);
        select.classList.add("search-select-native");
        select.tabIndex = -1;
        select.setAttribute("aria-hidden", "true");
        let matches = [];
        let active = -1;

        function close(restore = false) {
            popup.hidden = true;
            trigger.setAttribute("aria-expanded", "false");
            search.setAttribute("aria-expanded", "false");
            search.removeAttribute("aria-activedescendant");
            if (restore && !trigger.disabled) trigger.focus();
        }
        closeAll.add(close);

        function highlight(index) {
            active = index;
            [...list.children].forEach((node, i) =>
                node.classList.toggle("highlighted", i === index),
            );
            const node = list.children[index];
            if (node) {
                search.setAttribute("aria-activedescendant", node.id);
                node.scrollIntoView({ block: "nearest" });
            } else search.removeAttribute("aria-activedescendant");
        }

        function choose(index) {
            const option = matches[index];
            if (!option) return;
            select.value = option.value;
            select.dispatchEvent(new Event("input", { bubbles: true }));
            select.dispatchEvent(new Event("change", { bubbles: true }));
            sync();
            close(true);
        }

        function filter() {
            const terms = search.value
                .normalize("NFC")
                .toLocaleLowerCase("th")
                .trim()
                .split(/\s+/);
            matches = [...select.options].filter(
                (option) =>
                    !option.disabled &&
                    terms.every((term) =>
                        option.text
                            .normalize("NFC")
                            .toLocaleLowerCase("th")
                            .includes(term),
                    ),
            );
            list.replaceChildren();
            matches.forEach((option, index) => {
                const node = document.createElement("div");
                node.id = `${id}-${index}`;
                node.className = "search-select-option";
                node.setAttribute("role", "option");
                node.setAttribute("aria-selected", String(select.value === option.value));
                node.textContent = option.text;
                node.addEventListener("mousedown", (event) => event.preventDefault());
                node.addEventListener("click", (event) => {
                    event.preventDefault();
                    choose(index);
                });
                list.append(node);
            });
            status.textContent = matches.length
                ? `${matches.length} รายการ · ใช้ ↑ ↓ และ Enter เพื่อเลือก`
                : "ไม่พบรายการ ลองใช้คำสั้นลงหรือให้ผู้ดูแลเพิ่มข้อมูลอ้างอิง";
            highlight(-1);
        }

        function open() {
            if (select.disabled) return;
            closeAll.forEach((fn) => fn());
            popup.hidden = false;
            trigger.setAttribute("aria-expanded", "true");
            search.setAttribute("aria-expanded", "true");
            search.value = "";
            filter();
            search.focus();
        }

        function sync() {
            trigger.textContent = select.selectedOptions[0]?.text || "เลือกรายการ";
            trigger.setAttribute("aria-label", title + ": " + trigger.textContent);
            trigger.disabled = select.disabled;
            search.disabled = select.disabled;
            for (const attribute of ["aria-invalid", "aria-describedby"]) {
                const value = select.getAttribute(attribute);
                if (value) trigger.setAttribute(attribute, value);
                else trigger.removeAttribute(attribute);
            }
            if (select.disabled) close();
        }
        trigger.addEventListener("click", (event) => {
            event.preventDefault();
            if (popup.hidden) open();
            else close(true);
        });
        trigger.addEventListener("keydown", (event) => {
            if (["ArrowDown", "ArrowUp"].includes(event.key)) {
                event.preventDefault();
                open();
            }
        });
        search.addEventListener("input", (event) => {
            event.stopPropagation();
            filter();
        });
        search.addEventListener("keydown", (event) => {
            if (event.isComposing) return;
            if (event.key === "ArrowDown" || event.key === "ArrowUp") {
                event.preventDefault();
                const step = event.key === "ArrowDown" ? 1 : -1;
                highlight(Math.max(0, Math.min(matches.length - 1, active + step)));
            } else if (event.key === "Enter") {
                event.preventDefault();
                if (active >= 0) choose(active);
                else if (matches.length === 1) choose(0);
            } else if (event.key === "Escape") {
                event.preventDefault();
                event.stopPropagation();
                close(true);
            } else if (event.key === "Tab") close(true);
        });
        document.addEventListener("pointerdown", (event) => {
            if (!wrapper.contains(event.target)) close();
        });
        wrapper.addEventListener("focusout", () => {
            queueMicrotask(() => {
                if (!wrapper.contains(document.activeElement)) close();
            });
        });
        select.addEventListener("change", sync);
        // ลิงก์ในสรุปข้อผิดพลาดจะโฟกัส select เดิม จึงส่งต่อไปยังปุ่มที่มองเห็น
        select.addEventListener("focus", () => trigger.focus());
        new MutationObserver(sync).observe(select, {
            attributes: true,
            attributeFilter: ["disabled", "aria-invalid", "aria-describedby"],
        });
        select.form?.addEventListener("reset", () => setTimeout(sync, 0));
        sync();
    });
})();
