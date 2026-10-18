(function () {
    var mobileBreakpoint = 768;
    var wasMobileViewport = window.innerWidth < mobileBreakpoint;

    function isMobileViewport() {
        return window.innerWidth < mobileBreakpoint;
    }

    function syncSidebarState(sidebar) {
        if (!sidebar) return;

        var mobile = isMobileViewport();
        var isOpen = sidebar.classList.contains('active');
        var isCompact = !mobile && sidebar.classList.contains('sidebar-compact');

        if (mobile) sidebar.classList.remove('sidebar-compact');

        document.querySelectorAll('.sidebar-toggler').forEach(function (control) {
            control.setAttribute('aria-controls', 'sidebar');
            control.setAttribute('aria-expanded', mobile ? (isOpen ? 'true' : 'false') : 'true');
            control.setAttribute('aria-pressed', mobile ? 'false' : (isCompact ? 'true' : 'false'));
            control.setAttribute('aria-label', mobile
                ? (isOpen ? 'Cerrar menú principal' : 'Abrir menú principal')
                : (isCompact ? 'Expandir menú principal' : 'Compactar menú principal'));
            control.setAttribute('title', mobile
                ? (isOpen ? 'Cerrar menú principal' : 'Abrir menú principal')
                : (isCompact ? 'Expandir menú principal' : 'Compactar menú principal'));
        });
    }

    document.querySelectorAll('.sidebar-item.has-sub').forEach(function (item) {
        var link = item.querySelector('.sidebar-link');
        var submenu = item.querySelector('.submenu');

        if (!link || !submenu) return;

        link.addEventListener('click', function (event) {
            event.preventDefault();
            submenu.classList.toggle('active');
        });
    });

    document.querySelectorAll('.sidebar-toggler').forEach(function (toggler) {
        toggler.addEventListener('click', function (event) {
            var sidebar = document.getElementById('sidebar');
            if (!sidebar) return;

            if (isMobileViewport()) {
                sidebar.classList.toggle('active');
            } else {
                sidebar.classList.toggle('sidebar-compact');
            }

            syncSidebarState(sidebar);
        });
    });

    if (typeof window.PerfectScrollbar === 'function') {
        var sidebarWrapper = document.querySelector('.sidebar-wrapper');
        if (sidebarWrapper) new window.PerfectScrollbar(sidebarWrapper);
    }

    window.addEventListener('load', function () {
        var sidebar = document.getElementById('sidebar');
        if (!sidebar) return;

        if (isMobileViewport()) sidebar.classList.remove('active');
        syncSidebarState(sidebar);
    }, { once: true });

    window.addEventListener('resize', function () {
        var sidebar = document.getElementById('sidebar');
        if (!sidebar) return;

        var mobile = isMobileViewport();
        if (mobile) {
            sidebar.classList.remove('sidebar-compact');
            if (!wasMobileViewport) sidebar.classList.remove('active');
        } else if (wasMobileViewport) {
            sidebar.classList.add('active');
        }
        wasMobileViewport = mobile;
        syncSidebarState(sidebar);
    });

    syncSidebarState(document.getElementById('sidebar'));

    if (window.feather && typeof window.feather.replace === 'function') {
        window.feather.replace();
    }
}());
