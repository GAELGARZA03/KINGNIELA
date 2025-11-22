// JS para cambiar entre pestañas (Tu código original)
function openTab(evt, tabName) {
  let i, tabcontent, tablinks;

  // Oculta todo el contenido de las pestañas
  tabcontent = document.getElementsByClassName("tabcontent");
  for (i = 0; i < tabcontent.length; i++) {
    tabcontent[i].style.display = "none";
  }

  // Quita la clase "active" de todos los botones
  tablinks = document.getElementsByClassName("tablink");
  for (i = 0; i < tablinks.length; i++) {
    tablinks[i].classList.remove("active");
  }

  // Muestra la pestaña actual y añade la clase "active" al botón
  // Validación simple por si se llama manual
  const targetTab = document.getElementById(tabName);
  if(targetTab) {
      targetTab.style.display = "block";
  }
  
  if(evt) {
      evt.currentTarget.classList.add("active");
  }
}

// --- LÓGICA DE TAREAS ---

// 1. Base de datos simulada (Array de objetos)
// Puedes agregar más tareas aquí para probar
const misTareas = [
    {
        id: 1,
        nombre: "Jornada 1",
        grupo: "Mundial 2026 Fantasy",
        fecha: "2025-11-25T14:00", // Fecha futura (Próxima)
        completada: false
    },
    {
        id: 2,
        nombre: "Predicción Octavos",
        grupo: "Liga de Amigos",
        fecha: "2023-10-20T23:59", // Fecha pasada (Vencida)
        completada: false
    },
    {
        id: 3,
        nombre: "Registro Inicial",
        grupo: "Global Kingniela",
        fecha: "2024-01-15T10:00", // Fecha irrelevante porque está completada
        completada: true
    },
    {
        id: 4,
        nombre: "Jornada 2",
        grupo: "Mundial 2026 Fantasy",
        fecha: "2025-12-01T16:00", // Fecha futura (Próxima)
        completada: false
    }
];

// 2. Función para renderizar las tareas
function cargarTareas() {
    const contenedorProximas = document.getElementById('lista-proximas');
    const contenedorVencidas = document.getElementById('lista-vencidas');
    const contenedorCompletadas = document.getElementById('lista-completadas');

    // Limpiar contenedores antes de cargar
    contenedorProximas.innerHTML = '';
    contenedorVencidas.innerHTML = '';
    contenedorCompletadas.innerHTML = '';

    const ahora = new Date();

    // Mensajes si no hay tareas
    if(misTareas.length === 0) {
        contenedorProximas.innerHTML = '<p>No tienes tareas pendientes.</p>';
        return;
    }

    misTareas.forEach(tarea => {
        const fechaTarea = new Date(tarea.fecha);
        
        // Formatear fecha para mostrarla bonita
        const opcionesFecha = { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' };
        const fechaTexto = fechaTarea.toLocaleDateString('es-ES', opcionesFecha);

        // Crear el HTML de la tarjeta
        const tarjeta = document.createElement('div');
        tarjeta.classList.add('task-card');

        // Determinar dónde va la tarea
        if (tarea.completada) {
            // CASO: COMPLETADA
            tarjeta.classList.add('completada');
            tarjeta.innerHTML = `
                <div class="task-info">
                    <h4>${tarea.nombre}</h4>
                    <p>${tarea.grupo}</p>
                    <p class="date">Completada</p>
                </div>
                <button class="btn-check">✓</button>
            `;
            contenedorCompletadas.appendChild(tarjeta);

        } else if (fechaTarea < ahora) {
            // CASO: VENCIDA (Fecha tarea es menor a hoy y no está completada)
            tarjeta.classList.add('vencida');
            tarjeta.innerHTML = `
                <div class="task-info">
                    <h4>${tarea.nombre}</h4>
                    <p>${tarea.grupo}</p>
                    <p class="date">Venció: ${fechaTexto}</p>
                </div>
                <button class="btn-check" onclick="marcarComoCompletada(${tarea.id})">✓</button>
            `;
            contenedorVencidas.appendChild(tarjeta);

        } else {
            // CASO: PRÓXIMA (Fecha tarea es mayor a hoy y no está completada)
            tarjeta.innerHTML = `
                <div class="task-info">
                    <h4>${tarea.nombre}</h4>
                    <p>${tarea.grupo}</p>
                    <p class="date">Vence: ${fechaTexto}</p>
                </div>
                <button class="btn-check" onclick="marcarComoCompletada(${tarea.id})"></button>
            `;
            contenedorProximas.appendChild(tarjeta);
        }
    });
    
    // Mensajes vacíos por estética
    if(contenedorProximas.children.length === 0) contenedorProximas.innerHTML = '<p>No hay tareas próximas.</p>';
    if(contenedorVencidas.children.length === 0) contenedorVencidas.innerHTML = '<p>¡Bien! No tienes tareas vencidas.</p>';
}

// 3. Función para marcar tarea (Simula moverla de lista)
function marcarComoCompletada(id) {
    // Buscar la tarea en el array
    const tarea = misTareas.find(t => t.id === id);
    
    if(tarea) {
        tarea.completada = true; // Cambiar estado
        cargarTareas(); // Volver a pintar todo (esto mueve la tarea visualmente)
        
        // Opcional: Abrir la pestaña de completadas para ver el cambio
        // document.querySelector('.tablink:nth-child(3)').click(); 
        alert("¡Tarea completada!");
    }
}


// Inicializar al cargar
document.addEventListener("DOMContentLoaded", function() {
    // Simular clic en la primera pestaña
    const primerTab = document.getElementsByClassName("tablink")[0];
    if(primerTab) primerTab.click();

    // Cargar las tareas
    cargarTareas();
});