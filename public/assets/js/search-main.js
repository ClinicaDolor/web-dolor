const searchWrapper = document.querySelector(".search-input");
const inputBox = searchWrapper.querySelector("input");
const suggBox = searchWrapper.querySelector(".autocom-box");

// Evento cuando el usuario escribe
inputBox.onkeyup = async (e) => {
    let userData = e.target.value; // Datos ingresados por el usuario
    if (userData) {
        try {
            // Realiza una solicitud fetch al servidor
            const response = await fetch(`/buscar?query=${encodeURIComponent(userData)}`);
            const suggestions = await response.json(); // Obtén las sugerencias en JSON
            const servidor = window.location.origin; // Obtiene la URL base del servidor

            // Muestra las sugerencias
            showSuggestions(suggestions.map(suggestion => 
                `<li><a href="${servidor}/clinica/paciente/${suggestion.id}">${suggestion.nombre}</a></li>`
            ));
            searchWrapper.classList.add("active");
        } catch (error) {
            console.error("Error fetching suggestions:", error);
        }
    } else {
        searchWrapper.classList.remove("active"); // Oculta el cuadro de sugerencias
    }
};

// Función para mostrar sugerencias
function showSuggestions(list) {
    let listData;
    const servidor = window.location.origin; // Obtiene la URL base del servidor

    if (!list.length) {
        listData = `<li><a href="${servidor}/clinica/paciente/nuevo" class="text-primary">Agregar Paciente</a></li>`;
    } else {
        listData = list.join('');
    }
    suggBox.innerHTML = listData;
}

// Evento para cerrar el cuadro de sugerencias al hacer clic fuera
document.addEventListener("click", (e) => {
    if (!searchWrapper.contains(e.target)) {
        inputBox.value = "";
        searchWrapper.classList.remove("active");
    }
});
