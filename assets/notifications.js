"use strict";

// รับข้อความจาก PHP เดิมเท่านั้น ไม่เปลี่ยนการบันทึกหรือการตรวจฟอร์ม
(() => {
    const notices = [...document.querySelectorAll(".flash-notice")];
    if (!notices.length) return;
    const stack = document.createElement("div");
    stack.className = "notification-stack";
    stack.setAttribute("aria-label", "ผลการทำรายการ");
    document.body.append(stack);
    const reducedMotion = matchMedia("(prefers-reduced-motion: reduce)");
    notices.forEach((notice) => {
        stack.append(notice);
        notice.classList.add("toast-notice");
        const closeButton = notice.querySelector(".notification-close");
        closeButton.hidden = false;
        let animation = null;
        let closing = false;
        let timer = null;
        // สำเร็จปิดหลัง 6 วินาที; ข้อผิดพลาดค้างไว้จนกดปิด
        let remaining = notice.classList.contains("error-notice") ? 0 : 6000;
        let startedAt = 0;
        let hovered = false;
        function pause() {
            if (timer === null) return;
            clearTimeout(timer);
            timer = null;
            remaining = Math.max(0, remaining - (performance.now() - startedAt));
        }
        function resume() {
            if (
                closing ||
                !remaining ||
                hovered ||
                document.hidden ||
                notice.contains(document.activeElement)
            )
                return;
            if (timer !== null) return;
            startedAt = performance.now();
            timer = setTimeout(close, remaining);
        }
        function remove() {
            notice.remove();
            if (!stack.children.length) stack.remove();
        }
        function close() {
            if (closing) return;
            closing = true;
            pause();
            animation?.cancel();
            if (notice.contains(document.activeElement)) {
                const main = document.getElementById("main");
                main?.setAttribute("tabindex", "-1");
                main?.focus({ preventScroll: true });
            }
            notice.inert = true;
            if (reducedMotion.matches || !notice.animate) {
                remove();
                return;
            }
            animation = notice.animate(
                [
                    { opacity: 1, transform: "translateX(0)" },
                    { opacity: 0, transform: "translateX(20px)" },
                ],
                { duration: 180, easing: "ease-in", fill: "both" },
            );
            animation.onfinish = remove;
        }
        if (!reducedMotion.matches && notice.animate) {
            animation = notice.animate(
                [
                    { opacity: 0, transform: "translateX(28px)" },
                    { opacity: 1, transform: "translateX(0)" },
                ],
                { duration: 280, easing: "cubic-bezier(0.2, 0, 0, 1)" },
            );
        }
        closeButton.addEventListener("click", close);
        notice.addEventListener("pointerenter", () => {
            hovered = true;
            pause();
        });
        notice.addEventListener("pointerleave", () => {
            hovered = false;
            resume();
        });
        notice.addEventListener("focusin", pause);
        notice.addEventListener("focusout", () => setTimeout(resume, 0));
        document.addEventListener("visibilitychange", () =>
            document.hidden ? pause() : resume(),
        );
        reducedMotion.addEventListener("change", () => {
            if (!reducedMotion.matches) return;
            animation?.cancel();
            if (closing) remove();
        });
        resume();
    });
})();
