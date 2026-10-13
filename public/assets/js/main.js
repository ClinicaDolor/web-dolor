(function () {
    var patientShell = document.body.classList.contains('app-shell-patient');

    function syncPatientSidebarState(sidebar) {
        if (!patientShell || !sidebar) return;

        var isOpen = sidebar.classList.contains('active');
        document.querySelectorAll('.sidebar-toggler').forEach(function (control) {
            control.setAttribute('aria-controls', 'sidebar');
            control.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            control.setAttribute('aria-label', isOpen ? 'Cerrar menú principal' : 'Abrir menú principal');
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
            if (patientShell && toggler.tagName.toLowerCase() === 'a') event.preventDefault();

            var sidebar = document.getElementById('sidebar');
            if (!sidebar) return;

            sidebar.classList.toggle('active');
            syncPatientSidebarState(sidebar);
        });
    });

    if (typeof window.PerfectScrollbar === 'function') {
        var sidebarWrapper = document.querySelector('.sidebar-wrapper');
        if (sidebarWrapper) new window.PerfectScrollbar(sidebarWrapper);
    }

    window.addEventListener('load', function () {
        var sidebar = document.getElementById('sidebar');
        if (!sidebar) return;

        if (window.innerWidth < 768) sidebar.classList.remove('active');
        syncPatientSidebarState(sidebar);
    }, { once: true });

    syncPatientSidebarState(document.getElementById('sidebar'));

    if (window.feather && typeof window.feather.replace === 'function') {
        window.feather.replace();
    }
}());
