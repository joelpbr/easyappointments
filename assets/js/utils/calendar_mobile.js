/** Shared viewport sizing and tap/drag handling for both calendar views. */
App.Utils.CalendarMobile = (function () {
    function height(element) {
        if (!element) return 0;
        const viewport = window.visualViewport;
        const bottom = viewport ? viewport.height + viewport.offsetTop : window.innerHeight;
        const page = document.getElementById('calendar-page');
        const padding = page ? parseFloat(getComputedStyle(page).paddingBottom) || 0 : 0;
        return Math.max(0, Math.floor(bottom - element.getBoundingClientRect().top - padding));
    }

    function observe(resize) {
        let frame;
        const schedule = () => {
            cancelAnimationFrame(frame);
            frame = requestAnimationFrame(resize);
        };
        window.visualViewport?.addEventListener('resize', schedule);
        window.addEventListener('resize', schedule);
        if (window.ResizeObserver) {
            const observer = new ResizeObserver(schedule);
            ['header', 'calendar-toolbar', 'sync-alert'].forEach((id) => {
                const element = document.getElementById(id);
                if (element) observer.observe(element);
            });
        }
        schedule();
    }

    function selectionHandlers(select, allDayClick = () => {}, allows = () => true) {
        const selectedEvents = new WeakSet();
        return {
            dateClick(info) {
                if (info.allDay) {
                    allDayClick(info);
                    return;
                }
                // FullCalendar can report both dateClick and select for one gesture.
                // Let its range selection win; a quick tap falls back to the service duration.
                setTimeout(() => {
                    if (!selectedEvents.has(info.jsEvent)) {
                        const selection = {...info, start: info.date, end: info.date};
                        if (allows(selection)) select(selection);
                    }
                }, 0);
            },
            select(info) {
                if (info.jsEvent) selectedEvents.add(info.jsEvent);
                select(info);
            },
        };
    }

    return {height, observe, selectionHandlers};
})();
