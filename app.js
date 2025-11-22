// Espera a que todo el HTML esté cargado antes de ejecutar el script
document.addEventListener('DOMContentLoaded', function() {

  // 1. Seleccionamos los elementos del HTML que necesitamos
  const botonAbrir = document.getElementById('abrirRecuadroBtn');
  const botonCerrar = document.getElementById('cerrarRecuadroBtn');
  const recuadro = document.getElementById('miRecuadro');

  // 2. Creamos una función para mostrar el recuadro
  function mostrarRecuadro() {
    // Quita la clase 'oculto' para que el CSS lo muestre
    recuadro.classList.remove('oculto');
  }

  // 3. Creamos una función para ocultar el recuadro
  function ocultarRecuadro() {
    // Añade la clase 'oculto' para que el CSS lo esconda
    recuadro.classList.add('oculto');
  }

  // 4. Asignamos los "oyentes de eventos" (Event Listeners)
  // Cuando se haga clic en el botón de ABRIR, ejecuta la función mostrarRecuadro
  botonAbrir.addEventListener('click', mostrarRecuadro);

  // Cuando se haga clic en el botón de CERRAR (la 'x'), ejecuta la función ocultarRecuadro
  botonCerrar.addEventListener('click', ocultarRecuadro);

  // Opcional: Cerrar el recuadro si el usuario hace clic fuera de él
  window.addEventListener('click', function(evento) {
    if (evento.target == recuadro) {
      ocultarRecuadro();
    }
  });

});