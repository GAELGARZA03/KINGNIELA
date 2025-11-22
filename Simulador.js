document.addEventListener("DOMContentLoaded", function() {
    
    // Seleccionar los elementos del DOM
    const accessCard = document.getElementById('access-key-card');
    const simulatorCard = document.getElementById('simulator-card');
    const ingresarBtn = document.getElementById('ingresar-btn');

    // Añadir un evento al botón de ingresar
    ingresarBtn.addEventListener('click', function() {
        // Ocultar la tarjeta de la clave
        accessCard.style.display = 'none';

        // Mostrar la tarjeta del simulador
        simulatorCard.style.display = 'block';
    });

});