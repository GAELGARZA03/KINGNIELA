// Lógica de Pestañas
function openTab(evt, tabName) {
    let i, tabcontent, tablinks;
    tabcontent = document.getElementsByClassName("tabcontent");
    for (i = 0; i < tabcontent.length; i++) {
        tabcontent[i].style.display = "none";
    }
    tablinks = document.getElementsByClassName("tablink");
    for (i = 0; i < tablinks.length; i++) {
        tablinks[i].classList.remove("active");
    }
    document.getElementById(tabName).style.display = "block";
    if(evt) evt.currentTarget.classList.add("active");
}

document.addEventListener("DOMContentLoaded", function() {
    const currentUser = JSON.parse(localStorage.getItem('kingniela_user'));
    if (!currentUser) {
        window.location.href = 'IniciarSesion.html';
        return;
    }

    // Referencias Modal
    const modal = document.getElementById('miRecuadro');
    const btnAbrir = document.getElementById('abrirRecuadroBtn');
    const btnCerrar = document.getElementById('cerrarRecuadroBtn');
    const btnCrear = document.getElementById('btn-crear-final');
    
    // Inputs
    const selectGrupo = document.getElementById('Select-Grupo');
    const inpNombre = document.getElementById('input-nombre-tarea');
    const inpFecha = document.getElementById('input-fecha-tarea');

    // 1. Cargar Tareas al iniciar
    cargarTareas();

    // 2. Lógica Modal
    btnAbrir.addEventListener('click', () => {
        modal.classList.remove('oculto');
        modal.style.display = 'flex'; // Asegurar flex para centrar
        cargarGrupos(); // Llenar select al abrir
    });
    
    btnCerrar.addEventListener('click', () => {
        modal.classList.add('oculto');
        modal.style.display = 'none';
    });

    window.addEventListener('click', (e) => {
        if (e.target == modal) {
            modal.classList.add('oculto');
            modal.style.display = 'none';
        }
    });

    // 3. Crear Tarea
    btnCrear.addEventListener('click', () => {
        const idGrupo = selectGrupo.value;
        const nombre = inpNombre.value.trim();
        const fecha = inpFecha.value;

        if(!nombre || !fecha || idGrupo == "0") return alert("Llena todos los campos");

        fetch('php/crear_tarea.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ id_quiniela: idGrupo, nombre: nombre, fecha: fecha })
        })
        .then(r => r.json())
        .then(res => {
            if(res.success) {
                alert("Tarea creada y asignada.");
                modal.classList.add('oculto');
                modal.style.display = 'none';
                inpNombre.value = ""; // Limpiar
                cargarTareas(); // Recargar lista
            } else {
                alert("Error: " + res.message);
            }
        });
    });

    // --- FUNCIONES ---

    function cargarGrupos() {
        selectGrupo.innerHTML = '<option value="0">Cargando...</option>';
        fetch('php/mis_quinielas.php') // Reutilizamos el script de quinielas
        .then(r => r.json())
        .then(data => {
            selectGrupo.innerHTML = '<option value="0">Selecciona Grupo</option>';
            if(Array.isArray(data)) {
                data.forEach(q => {
                    selectGrupo.innerHTML += `<option value="${q.Id_Quiniela}">${q.Nombre_Quiniela}</option>`;
                });
            }
        });
    }

    function cargarTareas() {
        const contProximas = document.getElementById('lista-proximas');
        const contVencidas = document.getElementById('lista-vencidas');
        const contCompletadas = document.getElementById('lista-completadas');

        contProximas.innerHTML = '<p>Cargando...</p>';
        contVencidas.innerHTML = '';
        contCompletadas.innerHTML = '';

        fetch('php/mis_tareas.php')
        .then(r => r.json())
        .then(tareas => {
            contProximas.innerHTML = '';
            
            if(tareas.length === 0) {
                contProximas.innerHTML = '<p>No tienes tareas pendientes.</p>';
                return;
            }

            const ahora = new Date();

            tareas.forEach(t => {
                const fechaTarea = new Date(t.Fecha_Vencimiento);
                const fechaTexto = fechaTarea.toLocaleDateString('es-ES', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' });
                const div = document.createElement('div');
                div.className = 'task-card';

                if (t.Realizado == 1) {
                    // COMPLETADA
                    div.classList.add('completada');
                    div.innerHTML = `
                        <div class="task-info">
                            <h4>${t.Nombre_Tarea}</h4>
                            <p>${t.Grupo}</p>
                            <p class="date">Completada</p>
                        </div>
                        <button class="btn-check" style="cursor:default">✓</button>
                    `;
                    contCompletadas.appendChild(div);
                } 
                else if (fechaTarea < ahora) {
                    // VENCIDA
                    div.classList.add('vencida');
                    div.innerHTML = `
                        <div class="task-info">
                            <h4>${t.Nombre_Tarea}</h4>
                            <p>${t.Grupo}</p>
                            <p class="date">Venció: ${fechaTexto}</p>
                        </div>
                        <button class="btn-check" onclick="completarTarea(${t.Id_Tarea})">✓</button>
                    `;
                    contVencidas.appendChild(div);
                } 
                else {
                    // PRÓXIMA
                    div.innerHTML = `
                        <div class="task-info">
                            <h4>${t.Nombre_Tarea}</h4>
                            <p>${t.Grupo}</p>
                            <p class="date">Vence: ${fechaTexto}</p>
                        </div>
                        <button class="btn-check" onclick="completarTarea(${t.Id_Tarea})"></button>
                    `;
                    contProximas.appendChild(div);
                }
            });

            // Mensajes vacíos
            if(contProximas.innerHTML === '') contProximas.innerHTML = '<p>No hay tareas próximas.</p>';
            if(contVencidas.innerHTML === '') contVencidas.innerHTML = '<p>No tienes tareas vencidas.</p>';
        });
    }

    // Hacer global la función para que funcione en el onclick del HTML generado
    window.completarTarea = function(id) {
        fetch('php/completar_tarea.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ id_tarea: id })
        })
        .then(r => r.json())
        .then(res => {
            if(res.success) cargarTareas();
        });
    };

    // Abrir primer tab por defecto
    document.querySelector('.tablink').click();
});