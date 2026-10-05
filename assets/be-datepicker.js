"use strict";

// เสริมช่อง data-be-date ทุกหน้า โดยคงชื่อ input และค่าที่ส่งให้ PHP เป็น พ.ศ. ตามเดิม
(() => {
    const fields = [...document.querySelectorAll("input[data-be-date]")];
    if (
        !fields.length ||
        typeof HTMLDialogElement === "undefined" ||
        !("showModal" in HTMLDialogElement.prototype)
    )
        return;

    const months = [
        "มกราคม",
        "กุมภาพันธ์",
        "มีนาคม",
        "เมษายน",
        "พฤษภาคม",
        "มิถุนายน",
        "กรกฎาคม",
        "สิงหาคม",
        "กันยายน",
        "ตุลาคม",
        "พฤศจิกายน",
        "ธันวาคม",
    ];
    const minimumYear = 2400 - 543;
    const maximumYear = 2800 - 543;
    const dayMilliseconds = 86400000;
    // ใช้ UTC สำหรับคำนวณวัน เพื่อไม่ให้วันเปลี่ยนเพราะเวลาออมแสงของอุปกรณ์
    const makeDate = (year, month, day) => new Date(Date.UTC(year, month, day));
    const todayParts = new Intl.DateTimeFormat("en-US", {
        timeZone: "Asia/Bangkok",
        year: "numeric",
        month: "numeric",
        day: "numeric",
    }).formatToParts(new Date());
    const todayValue = (type) =>
        Number(todayParts.find((part) => part.type === type).value);
    const today = makeDate(
        todayValue("year"),
        todayValue("month") - 1,
        todayValue("day"),
    );

    function parse(value) {
        const match = /^(\d{2})\/(\d{2})\/(\d{4})$/.exec(value.trim());
        if (!match) return null;
        const year = Number(match[3]) - 543;
        const month = Number(match[2]) - 1;
        const day = Number(match[1]);
        const date = makeDate(year, month, day);
        return year >= minimumYear &&
            year <= maximumYear &&
            date.getUTCFullYear() === year &&
            date.getUTCMonth() === month &&
            date.getUTCDate() === day
            ? date
            : null;
    }

    function format(date) {
        const pad = (value) => String(value).padStart(2, "0");
        return `${pad(date.getUTCDate())}/${pad(date.getUTCMonth() + 1)}/${date.getUTCFullYear() + 543}`;
    }

    function button(text, className, label = text) {
        const node = document.createElement("button");
        node.type = "button";
        node.textContent = text;
        node.className = className;
        node.setAttribute("aria-label", label);
        return node;
    }

    const popup = document.createElement("dialog");
    popup.className = "be-calendar";
    popup.id = "beCalendar";
    popup.setAttribute("aria-labelledby", "beCalendarTitle");
    popup.innerHTML = `
        <div class="be-calendar-heading"><strong id="beCalendarTitle">เลือกวันที่ (พ.ศ.)</strong></div>
        <div class="be-calendar-navigation"></div>
        <p class="be-calendar-announcement" aria-live="polite" aria-atomic="true"></p>
        <div class="be-calendar-grid" role="grid" aria-label="ปฏิทิน พ.ศ."></div>
        <div class="be-calendar-footer"></div>`;
    document.body.append(popup);
    const navigation = popup.querySelector(".be-calendar-navigation");
    const grid = popup.querySelector(".be-calendar-grid");
    const announcement = popup.querySelector(".be-calendar-announcement");
    const previous = button("‹", "be-calendar-arrow", "เดือนก่อนหน้า");
    const next = button("›", "be-calendar-arrow", "เดือนถัดไป");
    const monthSelect = document.createElement("select");
    monthSelect.setAttribute("aria-label", "เดือน");
    const yearSelect = document.createElement("select");
    yearSelect.setAttribute("aria-label", "ปี พ.ศ.");
    months.forEach((month, index) => monthSelect.add(new Option(month, index)));
    for (let year = minimumYear; year <= maximumYear; year++) {
        yearSelect.add(new Option(String(year + 543), year));
    }
    navigation.append(previous, monthSelect, yearSelect, next);
    const todayButton = button("วันนี้", "be-calendar-today");
    const clearButton = button("ล้างวันที่", "be-calendar-clear");
    const closeButton = button("ปิด", "be-calendar-close", "ปิดปฏิทิน");
    popup
        .querySelector(".be-calendar-footer")
        .append(todayButton, clearButton, closeButton);

    let active = null;
    let selected = null;
    let cursor = today;
    let shown = today;
    let picked = false;

    // ข้อจำกัดของช่องสัมพันธ์กันยังตรวจที่ PHP ด้วย ไม่ใช้ปฏิทินเป็นการตรวจหลัก
    function bounds() {
        let min = parse(active?.dataset.beMin || "") || makeDate(minimumYear, 0, 1);
        let max = parse(active?.dataset.beMax || "") || makeDate(maximumYear, 11, 31);
        if (["end_date", "to"].includes(active?.name)) {
            const startName = active.name === "to" ? "from" : "start_date";
            const start = parse(active.form?.elements.namedItem(startName)?.value || "");
            if (start && start > min) min = start;
        }
        if (active?.name === "action_date") {
            max = parse(active.form?.dataset.today || "") || today;
        }
        return { min, max };
    }

    function permitted(date) {
        const { min, max } = bounds();
        return date >= min && date <= max;
    }

    function render(focusDay = false) {
        const year = shown.getUTCFullYear();
        const month = shown.getUTCMonth();
        monthSelect.value = String(month);
        yearSelect.value = String(year);
        announcement.textContent = `${months[month]} ${year + 543}`;
        previous.disabled = year === minimumYear && month === 0;
        next.disabled = year === maximumYear && month === 11;
        todayButton.disabled = !permitted(today);
        clearButton.hidden = active.required;
        grid.replaceChildren();
        const weekdays = document.createElement("div");
        weekdays.className = "be-calendar-week";
        weekdays.setAttribute("role", "row");
        ["อา", "จ", "อ", "พ", "พฤ", "ศ", "ส"].forEach((name) => {
            const cell = document.createElement("span");
            cell.textContent = name;
            cell.setAttribute("role", "columnheader");
            weekdays.append(cell);
        });
        grid.append(weekdays);
        const first = makeDate(year, month, 1);
        const start = first.getTime() - first.getUTCDay() * dayMilliseconds;
        let focusTarget = null;
        for (let week = 0; week < 6; week++) {
            const row = document.createElement("div");
            row.className = "be-calendar-week";
            row.setAttribute("role", "row");
            for (let day = 0; day < 7; day++) {
                const date = new Date(start + (week * 7 + day) * dayMilliseconds);
                const cell = button(
                    String(date.getUTCDate()),
                    "be-calendar-day",
                    format(date),
                );
                cell.setAttribute("role", "gridcell");
                cell.dataset.timestamp = String(date.getTime());
                cell.disabled = !permitted(date);
                cell.tabIndex = -1;
                cell.classList.toggle("is-adjacent", date.getUTCMonth() !== month);
                cell.classList.toggle("is-today", date.getTime() === today.getTime());
                cell.setAttribute(
                    "aria-selected",
                    String(date.getTime() === selected?.getTime()),
                );
                if (date.getTime() === today.getTime())
                    cell.setAttribute("aria-current", "date");
                if (!cell.disabled && date.getTime() === cursor.getTime())
                    focusTarget = cell;
                row.append(cell);
            }
            grid.append(row);
        }
        focusTarget ||= grid.querySelector("button:not(:disabled):not(.is-adjacent)");
        if (focusTarget) {
            focusTarget.tabIndex = 0;
            if (focusDay) focusTarget.focus();
        } else if (focusDay) monthSelect.focus();
    }

    function position() {
        if (!popup.open || !active) return;
        const rect = active.getBoundingClientRect();
        const width = popup.offsetWidth,
            height = popup.offsetHeight;
        const narrow = innerWidth <= 600;
        const left = narrow
            ? (innerWidth - width) / 2
            : Math.max(12, Math.min(rect.left, innerWidth - width - 12));
        const below = rect.bottom + 8;
        const top = narrow
            ? (innerHeight - height) / 2
            : below + height <= innerHeight - 12
              ? below
              : Math.max(12, rect.top - height - 8);
        popup.style.left = `${left}px`;
        popup.style.top = `${Math.max(12, top)}px`;
    }

    function setValue(value) {
        if (!active || active.disabled || active.readOnly) return;
        active.value = value;
        // แจ้งตัวตรวจฟอร์มเดิมให้ตรวจค่าและล้างข้อผิดพลาดตามปกติ
        active.dispatchEvent(new Event("input", { bubbles: true }));
        active.dispatchEvent(new Event("change", { bubbles: true }));
        picked = true;
        popup.close();
    }

    function moveMonth(delta, focusDay = false) {
        const nextMonth = makeDate(
            shown.getUTCFullYear(),
            shown.getUTCMonth() + delta,
            1,
        );
        if (
            nextMonth.getUTCFullYear() < minimumYear ||
            nextMonth.getUTCFullYear() > maximumYear
        )
            return;
        const lastDay = makeDate(
            nextMonth.getUTCFullYear(),
            nextMonth.getUTCMonth() + 1,
            0,
        ).getUTCDate();
        cursor = makeDate(
            nextMonth.getUTCFullYear(),
            nextMonth.getUTCMonth(),
            Math.min(cursor.getUTCDate(), lastDay),
        );
        shown = nextMonth;
        render(focusDay);
    }

    previous.addEventListener("click", () => moveMonth(-1));
    next.addEventListener("click", () => moveMonth(1));
    [monthSelect, yearSelect].forEach((select) =>
        select.addEventListener("change", () => {
            shown = makeDate(Number(yearSelect.value), Number(monthSelect.value), 1);
            cursor = shown;
            render();
        }),
    );
    grid.addEventListener("click", (event) => {
        const cell = event.target.closest("[data-timestamp]");
        if (cell && !cell.disabled)
            setValue(format(new Date(Number(cell.dataset.timestamp))));
    });
    grid.addEventListener("keydown", (event) => {
        const cell = event.target.closest("[data-timestamp]");
        if (!cell) return;
        cursor = new Date(Number(cell.dataset.timestamp));
        if (["PageUp", "PageDown"].includes(event.key)) {
            event.preventDefault();
            moveMonth(
                (event.key === "PageUp" ? -1 : 1) * (event.shiftKey ? 12 : 1),
                true,
            );
            return;
        }
        const shifts = {
            ArrowLeft: -1,
            ArrowRight: 1,
            ArrowUp: -7,
            ArrowDown: 7,
            Home: -cursor.getUTCDay(),
            End: 6 - cursor.getUTCDay(),
        };
        if (!(event.key in shifts)) return;
        event.preventDefault();
        const target = new Date(cursor.getTime() + shifts[event.key] * dayMilliseconds);
        if (!permitted(target)) return;
        cursor = target;
        shown = makeDate(cursor.getUTCFullYear(), cursor.getUTCMonth(), 1);
        render(true);
    });
    todayButton.addEventListener("click", () => setValue(format(today)));
    clearButton.addEventListener("click", () => setValue(""));
    closeButton.addEventListener("click", () => popup.close());
    popup.addEventListener("click", (event) => {
        if (event.target !== popup) return;
        const rect = popup.getBoundingClientRect();
        if (
            event.clientX < rect.left ||
            event.clientX > rect.right ||
            event.clientY < rect.top ||
            event.clientY > rect.bottom
        )
            popup.close();
    });
    popup.addEventListener("close", () => {
        fields.forEach((field) =>
            field.nextElementSibling?.setAttribute("aria-expanded", "false"),
        );
        if (picked) active?.focus();
    });
    window.addEventListener("resize", position);

    fields.forEach((field) => {
        const wrapper = document.createElement("span");
        wrapper.className = "be-date-field";
        field.before(wrapper);
        wrapper.append(field);
        const trigger = button("", "be-date-trigger", "เลือกวันที่จากปฏิทิน พ.ศ.");
        trigger.innerHTML =
            '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 3v4m10-4v4M4 10h16M5 5h14a1 1 0 0 1 1 1v14H4V6a1 1 0 0 1 1-1Z"/></svg>';
        trigger.setAttribute("aria-haspopup", "dialog");
        trigger.setAttribute("aria-controls", popup.id);
        trigger.setAttribute("aria-expanded", "false");
        wrapper.append(trigger);
        function syncDisabled() {
            trigger.disabled = field.disabled || field.readOnly;
            if (active === field && trigger.disabled && popup.open) popup.close();
        }
        new MutationObserver(syncDisabled).observe(field, {
            attributes: true,
            attributeFilter: ["disabled", "readonly"],
        });
        syncDisabled();
        trigger.addEventListener("click", (event) => {
            event.preventDefault();
            if (field.disabled || field.readOnly) return;
            active = field;
            selected = parse(field.value);
            const { min, max } = bounds();
            cursor = selected || today;
            if (cursor < min) cursor = min;
            if (cursor > max) cursor = max;
            shown = makeDate(cursor.getUTCFullYear(), cursor.getUTCMonth(), 1);
            picked = false;
            render();
            popup.showModal();
            trigger.setAttribute("aria-expanded", "true");
            position();
            grid.querySelector('[tabindex="0"]')?.focus();
        });
    });
})();
