document.addEventListener("DOMContentLoaded", function() {
    
    // Referencias
    const accessCard = document.getElementById('access-key-card');
    const simulatorCard = document.getElementById('simulator-card');
    const ingresarBtn = document.getElementById('ingresar-btn');
    const keyInput = document.getElementById('access-key');
    const btnVerResumen = document.getElementById('btn-ver-resumen');
    const resumenArea = document.getElementById('resumen-area');
    const botonesSimular = document.querySelectorAll('.btn-blue, .btn-yellow'); // Seleccionar todos

    // --- 0. CARGAR ESTADO INICIAL (PERSISTENCIA DE BOTONES) ---
    function cargarEstadoSimulacion() {
        fetch('php/estado_simulacion.php')
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                botonesSimular.forEach(btn => {
                    // El título h2 está justo antes del botón
                    const faseTitulo = btn.parentElement.querySelector('h2').innerText;
                    
                    if (data.estados[faseTitulo] === true) {
                        // Si ya está simulada, cambiamos estilo
                        marcarComoSimulado(btn);
                    }
                });
            }
        })
        .catch(e => console.error("Error cargando estado:", e));
    }
    
    // Llamar al iniciar
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
        if (btn.id === 'btn-ver-resumen') return;

        btn.addEventListener('click', function() {
            if (this.classList.contains('simulated')) return; // Evitar re-click si ya está amarillo

            const faseTitulo = this.parentElement.querySelector('h2').innerText;
            const textoOriginal = this.innerText;
            
            this.innerText = "Simulando...";
            this.disabled = true;
            this.style.backgroundColor = "#ccc";

            // 3.1 Simular Resultados
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

                    // 3.2 Lógica de Avance de Fase
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
        btn.style.backgroundColor = ""; // Limpiar inline style
        btn.disabled = true; // Bloquear click
    }

    function generarCuadroEliminatorio() {
        // alert("Generando cuadro de eliminatorias...");
        fetch('php/generar_cuadro.php')
        .then(r => r.json())
        .then(data => {
            if(data.success) alert("¡Se han definido los Dieciseisavos de Final!");
            else console.error(data.message);
        });
    }

    function avanzarSiguienteRonda(faseActual) {
        fetch('php/avanzar_torneo.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ fase_anterior: faseActual })
        })
        .then(r => r.json())
        .then(data => {
            if(data.success) {
                alert("¡Siguiente ronda lista! " + data.message);
            } else {
                console.error("Error avanzando ronda:", data.message);
            }
        });
    }
});