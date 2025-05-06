window.addEventListener('pageshow', function (event) {
  // Siempre que se vuelve a mostrar la página (incluso desde caché)
  localStorage.removeItem('preguntaActual');
  localStorage.removeItem('seccionActual');

  for (let i = 1; i <= 9; i++) {
    localStorage.removeItem('preguntaActual_' + i);
  }

  localStorage.removeItem('preguntaActualV2');
});