$(window).on("pageshow", function (event) {
    if (event.originalEvent.persisted) {  
        $(".LoaderPage").fadeIn(0).fadeOut("slow");
    }
});

$(document).ready(function() {
    $(".LoaderPage").fadeOut("slow");
});


function toggleSize() {

    document.querySelectorAll('.resizable').forEach(col => {
    col.classList.toggle('col-sm-12');
    col.style.order = col.getAttribute("data-index");
    });

    }