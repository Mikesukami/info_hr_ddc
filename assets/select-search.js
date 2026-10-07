"use strict";

// Progressive enhancement: select เดิมยังเป็นค่าที่ส่งให้ PHP
// ข้อความค้นหาไม่มี name และไม่ถูกบันทึกเป็นข้อมูลอ้างอิงใหม่
(() => {
    let sequence = 0;
    const closeAll = new Set();
    document.querySelectorAll("select:not([multiple])").forEach((select) => {
        if (select.size > 1) return;
        const searchable = select.hasAttribute("data-searchable");
        const reducedMotion = matchMedia("(prefers-reduced-motion: reduce)");
        const label = select.labels?.[0];
        const labelCopy = label?.cloneNode(true);
        labelCopy
            ?.querySelectorAll("select, .required, .field-error")
            .forEach((n) => n.remove());
        const title =
            labelCopy?.textContent.trim() ||
            select.getAttribute("aria-label") ||
            "เลือกรายการ";
        const id = `search-select-${++sequence}`;
        const wrapper = document.createElement("div");
        wrapper.className = "search-select" + (searchable ? "" : " plain-select");
        const trigger = document.createElement("button");
        trigger.type = "button";
        trigger.className = "search-select-trigger";
        trigger.setAttribute("aria-label", title);
        trigger.setAttribute("aria-haspopup", "listbox");
        trigger.setAttribute("aria-expanded", "false");
        trigger.setAttribute("aria-controls", id);
        if (!searchable) trigger.setAttribute("role", "combobox");
        const popup = document.createElement("div");
        popup.className = "search-select-popup";
        popup.hidden = true;
        if (!searchable) popup.classList.add("plain-select-popup");
        const search = document.createElement("input");
        search.type = "search";
        search.hidden = !searchable;
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
        status.hidden = !searchable;
        popup.append(search, list, status);
        wrapper.append(trigger);
        const host = select.closest("dialog") || document.body;
        // เมนูใน dialog ต้องอยู่ใน top layer เพื่อไม่ถูกตัดด้วยขอบปฏิทิน
        const topLayer =
            host !== document.body && typeof popup.showPopover === "function";
        if (topLayer) popup.setAttribute("popover", "manual");
        host.append(popup);
        select.after(wrapper);
        select.classList.add("search-select-native");
        select.tabIndex = -1;
        select.setAttribute("aria-hidden", "true");
        let matches = [];
        let active = -1;

        let isOpen = false;
        let motion = null;
        let typed = "";
        let typedAt = 0;
        function belongs(node) {
            return node && (wrapper.contains(node) || popup.contains(node));
        }
        function position() {
            if (!isOpen) return;
            const rect = trigger.getBoundingClientRect();
            const width = Math.min(Math.max(rect.width, 160), innerWidth - 24);
            popup.style.width = width + "px";
            popup.style.left =
                Math.max(12, Math.min(rect.left, innerWidth - width - 12)) + "px";
            const below = innerHeight - rect.bottom - 18;
            const above = rect.top - 18;
            const upward = below < Math.min(popup.scrollHeight, 240) && above > below;
            const space = Math.max(80, upward ? above : below);
            list.style.maxHeight =
                Math.max(50, Math.min(230, space - (searchable ? 108 : 16))) + "px";
            popup.dataset.placement = upward ? "top" : "bottom";
            popup.style.top =
                (upward
                    ? Math.max(12, rect.top - popup.getBoundingClientRect().height - 6)
                    : rect.bottom + 6) + "px";
        }
        function close(restore = false, immediate = false) {
            isOpen = false;
            trigger.setAttribute("aria-expanded", "false");
            search.setAttribute("aria-expanded", "false");
            search.removeAttribute("aria-activedescendant");
            trigger.removeAttribute("aria-activedescendant");
            if (restore && !trigger.disabled) trigger.focus();
            popup.inert = true;
            popup.setAttribute("aria-hidden", "true");
            motion?.cancel();
            motion = null;
            if (popup.hidden || immediate || reducedMotion.matches || !popup.animate) {
                if (topLayer && popup.matches(":popover-open")) popup.hidePopover();
                popup.hidden = true;
                return;
            }
            const current = popup.animate(
                [
                    { opacity: 1, transform: "translateY(0) scale(1)" },
                    { opacity: 0, transform: "translateY(-3px) scale(0.98)" },
                ],
                { duration: 110, easing: "ease-in", fill: "both" },
            );
            motion = current;
            current.onfinish = () => {
                if (motion !== current) return;
                if (topLayer && popup.matches(":popover-open")) popup.hidePopover();
                popup.hidden = true;
                current.cancel();
                motion = null;
            };
        }
        closeAll.add(close);

        function highlight(index) {
            active = index;
            [...list.children].forEach((node, i) =>
                node.classList.toggle("highlighted", i === index),
            );
            const node = list.children[index];
            if (node) {
                (searchable ? search : trigger).setAttribute(
                    "aria-activedescendant",
                    node.id,
                );
                node.scrollIntoView({ block: "nearest" });
            } else
                (searchable ? search : trigger).removeAttribute("aria-activedescendant");
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
            closeAll.forEach((fn) => fn(false, true));
            isOpen = true;
            popup.hidden = false;
            if (topLayer) popup.showPopover();
            trigger.setAttribute("aria-expanded", "true");
            search.setAttribute("aria-expanded", "true");
            popup.inert = false;
            popup.setAttribute("aria-hidden", "false");
            search.value = "";
            typed = "";
            filter();
            sync();
            position();
            if (!searchable)
                highlight(matches.findIndex((option) => option.value === select.value));
            (searchable ? search : trigger).focus();
            if (!reducedMotion.matches && popup.animate) {
                const current = popup.animate(
                    [
                        { opacity: 0, transform: "translateY(-4px) scale(0.98)" },
                        { opacity: 1, transform: "translateY(0) scale(1)" },
                    ],
                    { duration: 180, easing: "cubic-bezier(0.2, 0, 0, 1)" },
                );
                motion = current;
                current.onfinish = () => {
                    if (motion === current) motion = null;
                };
            }
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
            if (select.disabled) close(false, true);
        }
        trigger.addEventListener("click", (event) => {
            event.preventDefault();
            if (!isOpen) open();
            else close(true);
        });
        trigger.addEventListener("keydown", (event) => {
            if (event.isComposing) return;
            if (["ArrowDown", "ArrowUp"].includes(event.key)) {
                event.preventDefault();
                if (!isOpen) open();
                else if (!searchable)
                    highlight(
                        Math.max(
                            0,
                            Math.min(
                                matches.length - 1,
                                active + (event.key === "ArrowDown" ? 1 : -1),
                            ),
                        ),
                    );
            } else if (!searchable && isOpen && ["Enter", " "].includes(event.key)) {
                event.preventDefault();
                if (active >= 0) choose(active);
            } else if (isOpen && event.key === "Escape") {
                event.preventDefault();
                event.stopPropagation();
                close(true);
            } else if (!searchable && isOpen && ["Home", "End"].includes(event.key)) {
                event.preventDefault();
                highlight(event.key === "Home" ? 0 : matches.length - 1);
            } else if (isOpen && event.key === "Tab") close();
            else if (
                !searchable &&
                event.key.length === 1 &&
                event.key !== " " &&
                !event.ctrlKey &&
                !event.metaKey
            ) {
                if (!isOpen) open();
                typed = Date.now() - typedAt > 700 ? event.key : typed + event.key;
                typedAt = Date.now();
                const found = matches.findIndex((option) =>
                    option.text
                        .toLocaleLowerCase("th")
                        .startsWith(typed.toLocaleLowerCase("th")),
                );
                if (found >= 0) highlight(found);
            }
        });
        search.addEventListener("input", (event) => {
            event.stopPropagation();
            filter();
            position();
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
            if (!belongs(event.target)) close();
        });
        function onFocusOut(event) {
            if (event.relatedTarget && !belongs(event.relatedTarget)) close();
        }
        wrapper.addEventListener("focusout", onFocusOut);
        popup.addEventListener("focusout", onFocusOut);
        window.addEventListener("resize", position);
        document.addEventListener(
            "scroll",
            (event) => {
                if (isOpen && !popup.contains(event.target)) {
                    const rect = trigger.getBoundingClientRect();
                    if (rect.bottom < 0 || rect.top > innerHeight) close(false, true);
                    else position();
                }
            },
            true,
        );
        select.closest("dialog")?.addEventListener("close", () => close(false, true));
        reducedMotion.addEventListener("change", () => {
            if (reducedMotion.matches) {
                motion?.cancel();
                motion = null;
                if (!isOpen) {
                    if (topLayer && popup.matches(":popover-open")) popup.hidePopover();
                    popup.hidden = true;
                }
            }
        });
        select.addEventListener("dropdown:sync", sync);
        select.addEventListener("change", sync);
        // ลิงก์ในสรุปข้อผิดพลาดจะโฟกัส select เดิม จึงส่งต่อไปยังปุ่มที่มองเห็น
        select.addEventListener("focus", () => trigger.focus());
        new MutationObserver(sync).observe(select, {
            attributes: true,
            attributeFilter: [
                "disabled",
                "aria-invalid",
                "aria-describedby",
                "selected",
                "label",
            ],
            childList: true,
            subtree: true,
            characterData: true,
        });
        select.form?.addEventListener("reset", () => setTimeout(sync, 0));
        sync();
    });
})();
