(function () {
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
        toggler.addEventListener('click', function () {
            var sidebar = document.getElementById('sidebar');
            if (sidebar) sidebar.classList.toggle('active');
        });
    });

    if (typeof window.PerfectScrollbar === 'function') {
        var sidebarWrapper = document.querySelector('.sidebar-wrapper');
        if (sidebarWrapper) new window.PerfectScrollbar(sidebarWrapper);
    }

    window.addEventListener('load', function () {
        if (window.innerWidth >= 768) return;

        var sidebar = document.getElementById('sidebar');
        if (sidebar) sidebar.classList.remove('active');
    }, { once: true });

    if (window.feather && typeof window.feather.replace === 'function') {
        window.feather.replace();
    }
}());
