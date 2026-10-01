"use strict";

// เก็บเฉพาะความชอบของตาราง Excel บนเบราว์เซอร์เครื่องนี้
(() => {
    const tools = document.getElementById("ledgerDisplayTools");
    const table = document.querySelector(".ledger-table");
    if (!tools || !table) return;
    const decrease = document.getElementById("ledgerFontDecrease");
    const increase = document.getElementById("ledgerFontIncrease");
    const output = document.getElementById("ledgerFontSize");
    const compact = document.getElementById("ledgerCompact");
    const key = "study-leave-ledger-display";
    let size = 15;
    try {
        const saved = JSON.parse(localStorage.getItem(key));
        if (Number.isInteger(saved?.size) && saved.size >= 10 && saved.size <= 18) {
            size = saved.size;
        }
        compact.checked = saved?.compact === true;
    } catch {}

    function apply(save = true) {
        // ใช้คลาสแทน inline style เพื่อทำงานกับ Content Security Policy เดิม
        table.classList.remove(
            ...Array.from({ length: 9 }, (_, i) => `ledger-font-${i + 10}`),
        );
        table.classList.add(`ledger-font-${size}`);
        table.classList.toggle("ledger-compact", compact.checked);
        output.textContent = `${size} px`;
        decrease.disabled = size <= 10;
        increase.disabled = size >= 18;
        if (save) {
            try {
                localStorage.setItem(
                    key,
                    JSON.stringify({ size, compact: compact.checked }),
                );
            } catch {}
        }
    }
    decrease.addEventListener("click", () => {
        size = Math.max(10, size - 1);
        apply();
    });
    increase.addEventListener("click", () => {
        size = Math.min(18, size + 1);
        apply();
    });
    compact.addEventListener("change", () => apply());
    document.getElementById("ledgerDisplayReset").addEventListener("click", () => {
        size = 15;
        compact.checked = false;
        apply();
    });
    apply(false);
    tools.hidden = false;
})();
