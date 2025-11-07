                </div> <!-- Cierre del container-fluid de contenido -->
            </main>
        </div> <!-- Cierre del row principal -->
    </div> <!-- Cierre del container-fluid principal -->

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    
    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- DataTables -->
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    
    <!-- Validaciones personalizadas -->
    <script src="../assets/js/validaciones.js"></script>
    
    <!-- Scripts adicionales de la página -->
    <?= $scripts_extra ?? '' ?>

    <script>
        // Inicializar DataTables
        $(document).ready(function() {
            $('.table').DataTable({
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/es-AR.json'
                },
                responsive: true,
                dom: '<"row"<"col-md-6"l><"col-md-6"f>>rt<"row"<"col-md-6"i><"col-md-6"p>>',
                pageLength: 25
            });
        });

        // Validación de formularios con Bootstrap
        (function () {
            'use strict'
            var forms = document.querySelectorAll('[data-validar]')
            Array.prototype.slice.call(forms)
                .forEach(function (form) {
                    form.addEventListener('submit', function (event) {
                        if (!form.checkValidity()) {
                            event.preventDefault()
                            event.stopPropagation()
                        }
                        form.classList.add('was-validated')
                    }, false)
                })
        })()

        // Auto-ocultar alertas después de 5 segundos
        setTimeout(function() {
            var alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                var bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);

        // Confirmación para acciones destructivas
        function confirmarAccion(mensaje) {
            return confirm(mensaje || '¿Está seguro de que desea realizar esta acción?');
        }

        // Formatear números como moneda
        function formatoMoneda(valor, moneda = 'ARS') {
            return new Intl.NumberFormat('es-AR', {
                style: 'currency',
                currency: moneda
            }).format(valor);
        }

        // Toggle de contraseña
        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const icon = document.querySelector(`[onclick="togglePassword('${inputId}')] i`);
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

        // Cargar datos de unidad al seleccionar contrato
        function cargarDatosUnidad(contratoId, unidadFieldId, inquilinoFieldId) {
            if (!contratoId) return;
            
            // Aquí iría una llamada AJAX para cargar los datos
            // Por ahora es un placeholder
            console.log('Cargando datos del contrato:', contratoId);
        }

        // Calcular fecha de vencimiento
        function calcularVencimiento(fechaInicio, meses) {
            if (!fechaInicio) return '';
            
            const fecha = new Date(fechaInicio);
            fecha.setMonth(fecha.getMonth() + parseInt(meses));
            return fecha.toISOString().split('T')[0];
        }

        // Validar fechas
        function validarFechas(fechaInicio, fechaFin) {
            const inicio = new Date(fechaInicio);
            const fin = new Date(fechaFin);
            return fin >= inicio;
        }

        // Mostrar/uocultar elementos
        function toggleElement(elementId, show) {
            const element = document.getElementById(elementId);
            if (element) {
                element.style.display = show ? 'block' : 'none';
            }
        }

        // Cargar opciones dinámicas via AJAX
        function cargarOpciones(url, selectId, valorSeleccionado = '') {
            fetch(url)
                .then(response => response.json())
                .then(data => {
                    const select = document.getElementById(selectId);
                    select.innerHTML = '<option value="">Seleccionar...</option>';
                    
                    data.forEach(item => {
                        const option = document.createElement('option');
                        option.value = item.id;
                        option.textContent = item.nombre || item.texto;
                        if (item.id == valorSeleccionado) {
                            option.selected = true;
                        }
                        select.appendChild(option);
                    });
                })
                .catch(error => {
                    console.error('Error cargando opciones:', error);
                });
        }

        // Inicializar tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });

        // Inicializar popovers
        var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
        var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
            return new bootstrap.Popover(popoverTriggerEl);
        });

        // Manejar modales dinámicamente
        function abrirModal(modalId) {
            const modal = new bootstrap.Modal(document.getElementById(modalId));
            modal.show();
        }

        function cerrarModal(modalId) {
            const modal = bootstrap.Modal.getInstance(document.getElementById(modalId));
            if (modal) {
                modal.hide();
            }
        }

        // Utilidades para formularios
        function limpiarFormulario(formId) {
            document.getElementById(formId).reset();
            document.getElementById(formId).classList.remove('was-validated');
        }

        function habilitarFormulario(formId, habilitar) {
            const form = document.getElementById(formId);
            const elementos = form.querySelectorAll('input, select, textarea, button');
            elementos.forEach(elemento => {
                elemento.disabled = !habilitar;
            });
        }

        // Navegación por pestañas
        function cambiarPestana(pestanaId) {
            const trigger = document.querySelector(`[data-bs-target="#${pestanaId}"]`);
            if (trigger) {
                bootstrap.Tab.getInstance(trigger).show();
            }
        }

        // Gestión de archivos
        function previewImagen(input, imgElementId) {
            const file = input.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById(imgElementId).src = e.target.result;
                }
                reader.readAsDataURL(file);
            }
        }

        // Utilidades de fecha
        function fechaHoy() {
            return new Date().toISOString().split('T')[0];
        }

        function formatearFecha(fecha) {
            return new Date(fecha).toLocaleDateString('es-AR');
        }

        // Gestión de estado de carga
        function mostrarCarga(elementoId = null) {
            if (elementoId) {
                document.getElementById(elementoId).innerHTML = '<div class="spinner-border spinner-border-sm" role="status"></div> Cargando...';
            }
            document.body.style.cursor = 'wait';
        }

        function ocultarCarga(elementoId = null, contenidoOriginal = '') {
            if (elementoId && contenidoOriginal) {
                document.getElementById(elementoId).innerHTML = contenidoOriginal;
            }
            document.body.style.cursor = 'default';
        }
    </script>
</body>
</html>