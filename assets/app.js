"use strict";
(() => {
    const sidebar = document.getElementById("sidebar"),
        button = document.getElementById("menuButton"),
        shade = document.getElementById("shade"),
        workspace = document.querySelector(".workspace");
    const mobile = matchMedia("(max-width:850px)");
    let collapsed = false;
    try {
        collapsed = localStorage.getItem("study-leave-sidebar") === "collapsed";
    } catch {}
    function sync() {
        if (!sidebar) return;
        const open = mobile.matches ? sidebar.classList.contains("open") : !collapsed;
        document.documentElement.classList.toggle(
            "sidebar-collapsed",
            !mobile.matches && collapsed,
        );
        sidebar.inert = !open;
        button.setAttribute("aria-expanded", String(open));
        shade.classList.toggle("open", mobile.matches && open);
        workspace.inert = mobile.matches && open;
        if (mobile.matches && open) {
            sidebar.setAttribute("role", "dialog");
            sidebar.setAttribute("aria-modal", "true");
        } else {
            sidebar.removeAttribute("role");
            sidebar.removeAttribute("aria-modal");
        }
    }
    function closeMobile() {
        sidebar?.classList.remove("open");
        sync();
        button?.focus();
    }
    if (button) {
        button.addEventListener("click", () => {
            if (mobile.matches) {
                sidebar.classList.toggle("open");
                sync();
                if (sidebar.classList.contains("open"))
                    sidebar.querySelector("summary, a").focus();
            } else {
                collapsed = !collapsed;
                try {
                    localStorage.setItem(
                        "study-leave-sidebar",
                        collapsed ? "collapsed" : "expanded",
                    );
                } catch {}
                sync();
            }
        });
        shade.addEventListener("click", closeMobile);
        mobile.addEventListener("change", () => {
            sidebar.classList.remove("open");
            sync();
        });
        document.addEventListener("keydown", (event) => {
            if (!mobile.matches || !sidebar.classList.contains("open")) return;
            if (event.key === "Escape") closeMobile();
            if (event.key === "Tab") {
                const links = [
                    ...sidebar.querySelectorAll("a[href], summary, button"),
                ].filter(
                    (element) => element.getClientRects().length > 0 && !element.disabled,
                );
                if (event.shiftKey && document.activeElement === links[0]) {
                    event.preventDefault();
                    links.at(-1).focus();
                } else if (!event.shiftKey && document.activeElement === links.at(-1)) {
                    event.preventDefault();
                    links[0].focus();
                }
            }
        });
        sync();
    }
    const destination = document.querySelector("[name=destination]"),
        country = document.getElementById("countryField");
    function syncCountry() {
        if (!destination || !country) return;
        country.hidden = destination.value !== "foreign";
        country.querySelector("select").disabled = country.hidden;
    }
    destination?.addEventListener("change", syncCountry);
    syncCountry();
    const type = document.querySelector("[name=type_id]"),
        study = document.getElementById("studyFields"),
        course = document.getElementById("courseFields");
    function syncType() {
        if (!type || !study) return;
        const kind = [...document.querySelectorAll("[data-type-id]")].find(
            (x) => x.dataset.typeId === type.value,
        )?.dataset.formKind;
        for (const [container, show] of [
            [study, kind === "study"],
            [course, kind === "course"],
        ]) {
            container.hidden = !show;
            container
                .querySelectorAll("input, select")
                .forEach((input) => (input.disabled = !show));
        }
    }
    type?.addEventListener("change", syncType);
    syncType();
    document.addEventListener("input", (event) => {
        const input = event.target;
        if (
            input.matches("[data-be-date]") &&
            event.inputType?.startsWith("insert") &&
            input.selectionStart === input.value.length &&
            /^[0-9/]*$/.test(input.value)
        ) {
            const d = input.value.replaceAll("/", "");
            if (d.length <= 8)
                input.value =
                    d.slice(0, 2) +
                    (d.length > 2 ? "/" + d.slice(2, 4) : "") +
                    (d.length > 4 ? "/" + d.slice(4) : "");
        }
    });
})();
