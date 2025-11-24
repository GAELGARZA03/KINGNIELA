document.addEventListener("DOMContentLoaded", function() {
    
    // Referencias
    const accessCard = document.getElementById('access-key-card');
    const simulatorCard = document.getElementById('simulator-card');
    const ingresarBtn = document.getElementById('ingresar-btn');
    const keyInput = document.getElementById('access-key');
    const btnVerResumen = document.getElementById('btn-ver-resumen');
    const resumenArea = document.getElementById('resumen-area');
    const botonesSimular = document.querySelectorAll('.btn-blue, .btn-yellow'); 

    // --- 0. CARGAR ESTADO INICIAL (CORREGIDO) ---
    function cargarEstadoSimulacion() {
        // CORRECCIÓN AQUÍ: El archivo se llama 'estado_simulado.php' en tu carpeta
        fetch('php/estado_simulado.php')
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                botonesSimular.forEach(btn => {
                    if (!btn.parentElement.querySelector('h2')) return; // Saltar botones que no son de jornada
                    const faseTitulo = btn.parentElement.querySelector('h2').innerText;
                    
                    if (data.estados[faseTitulo] === true) {
                        marcarComoSimulado(btn);
                    }
                });
            }
        })
        .catch(e => console.error("Error cargando estado:", e));
    }
    
    cargarEstadoSimulacion();

    // 1. VALIDACIÓN DE CLAVE
    ingresarBtn.addEventListener('click', function() {
        const clave = keyInput.value.trim();
        if (clave === "KING2026" || clave === "123") {
            accessCard.style.display = 'none';
            simulatorCard.style.display = 'block';
        } else {
            alert("Clave incorrecta. Intenta 'KING2026'");
        }
    });

    // 2. BOTÓN VER RESUMEN
    if (btnVerResumen) {
        btnVerResumen.addEventListener('click', function() {
            if (resumenArea.style.display === 'block') {
                resumenArea.style.display = 'none';
                this.textContent = '📊 Ver Resumen del Torneo';
            } else {
                this.textContent = 'Cargando...';
                fetch('php/componente_resumen.php')
                    .then(response => response.text())
                    .then(html => {
                        resumenArea.innerHTML = html;
                        resumenArea.style.display = 'block';
                        btnVerResumen.textContent = 'Ocultar Resumen';
                    });
            }
        });
    }

    // 3. LÓGICA DE SIMULACIÓN
    botonesSimular.forEach(btn => {
        if (btn.id === 'btn-ver-resumen' || btn.id === 'ingresar-btn') return;

        btn.addEventListener('click', function() {
            if (this.classList.contains('simulated')) return;

            const faseTitulo = this.parentElement.querySelector('h2').innerText;
            const textoOriginal = this.innerText;
            
            this.innerText = "Simulando...";
            this.disabled = true;
            this.style.backgroundColor = "#ccc";

            fetch('php/simulador.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ fase: faseTitulo })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    marcarComoSimulado(this);
                    
                    let msg = "Resultados Generados:\n";
                    data.data.forEach(r => msg += `${r.partido}: ${r.resultado}\n`);
                    alert(msg);

                    // LOGICA DE AVANCE
                    if (faseTitulo === 'Jornada 3') {
                        generarCuadroEliminatorio();
                    } else if (['Dieciseisavos de final', 'Octavos de final', 'Cuartos de final', 'Semifinal'].includes(faseTitulo)) {
                        avanzarSiguienteRonda(faseTitulo);
                    }

                    // Refrescar resumen si está abierto
                    if(resumenArea.style.display === 'block') {
                        resumenArea.style.display = 'none';
                        btnVerResumen.click();
                    }

                } else {
                    alert("Error: " + data.message);
                    this.innerText = textoOriginal;
                    this.disabled = false;
                    this.style.backgroundColor = "";
                }
            })
            .catch(e => {
                console.error(e);
                alert("Error de conexión con el simulador");
                this.innerText = textoOriginal;
                this.disabled = false;
                this.style.backgroundColor = "";
            });
        });
    });

    // --- FUNCIONES AUXILIARES ---
    function marcarComoSimulado(btn) {
        btn.innerText = "Simulado";
        btn.classList.remove('btn-blue');
        btn.classList.add('btn-yellow', 'simulated');
        btn.style.backgroundColor = ""; 
        btn.disabled = true;
    }

    function generarCuadroEliminatorio() {
        fetch('php/generar_cuadro.php')
        .then(r => r.json())
        .then(data => {
            if(data.success) alert("¡Se han definido los Dieciseisavos de Final!");
            else console.error(data.message);
        });
    }

    function avanzarSiguienteRonda(faseActual) {
        console.log("Intentando avanzar desde:", faseActual); // DEBUG
        
        fetch('php/avanzar_torneo.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ fase_anterior: faseActual })
        })
        .then(r => r.json())
        .then(data => {
            console.log("Respuesta avance:", data); // DEBUG
            if(data.success) {
                alert("¡Siguiente ronda lista! " + data.message);
                // Opcional: Recargar para desbloquear el siguiente botón visualmente
                location.reload(); 
            } else {
                console.error("Error avanzando ronda:", data.message);
                // Si dice que ya existen, es que ya se crearon.
            }
        })
        .catch(e => console.error("Error de red al avanzar:", e));
    }
});