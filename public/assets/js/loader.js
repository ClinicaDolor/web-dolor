(function () {
    function hideLoaders() {
        document.querySelectorAll('.LoaderPage').forEach(function (loader) {
            if (window.jQuery && window.jQuery.fn && typeof window.jQuery.fn.fadeOut === 'function') {
                window.jQuery(loader).fadeOut('slow');
                return;
            }

            loader.style.display = 'none';
        });
    }

    window.addEventListener('pageshow', function (event) {
        if (event.persisted) hideLoaders();
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', hideLoaders, { once: true });
    } else {
        hideLoaders();
    }
}());

window.toggleSize = function toggleSize() {
    document.querySelectorAll('.resizable').forEach(function (col) {
        col.classList.toggle('col-sm-12');

        var index = col.getAttribute('data-index');
        if (index !== null) col.style.order = index;
    });
};
