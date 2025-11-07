// =============================================
// VALIDACIONES JAVASCRIPT REUTILIZABLES
// =============================================

class Validaciones {
    
    // Validar email
    static email(email) {
        const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    }
    
    // Validar DNI (Argentina)
    static dni(dni) {
        const re = /^\d{7,8}$/;
        return re.test(dni);
    }
    
    // Validar teléfono
    static telefono(telefono) {
        const re = /^[\d\s\-\+\(\)]{7,20}$/;
        return re.test(telefono);
    }
    
    // Validar monto positivo
    static monto(monto) {
        return !isNaN(monto) && parseFloat(monto) > 0;
    }
    
    // Validar fecha (no futura para algunos casos)
    static fechaPasada(fecha) {
        return new Date(fecha) <= new Date();
    }
    
    // Validar fecha futura
    static fechaFutura(fecha) {
        return new Date(fecha) > new Date();
    }
    
    // Validar que fecha fin sea mayor que fecha inicio
    static rangoFechas(inicio, fin) {
        return new Date(fin) > new Date(inicio);
    }
    
    // Validar número de contrato
    static numeroContrato(numero) {
        const re = /^[A-Z0-9\-]{3,20}$/;
        return re.test(numero);
    }
    
    // Mostrar error en campo
    static mostrarError(campo, mensaje) {
        // Remover error anterior
        this.removerError(campo);
        
        // Agregar clase de error
        campo.classList.add('is-invalid');
        
        // Crear elemento de error
        const errorDiv = document.createElement('div');
        errorDiv.className = 'invalid-feedback';
        errorDiv.textContent = mensaje;
        
        // Insertar después del campo
        campo.parentNode.appendChild(errorDiv);
    }
    
    // Remover error de campo
    static removerError(campo) {
        campo.classList.remove('is-invalid');
        const errorDiv = campo.parentNode.querySelector('.invalid-feedback');
        if (errorDiv) {
            errorDiv.remove();
        }
    }
    
    // Validar formulario completo
    static validarFormulario(formulario) {
        let esValido = true;
        const campos = formulario.querySelectorAll('[data-validar]');
        
        campos.forEach(campo => {
            const valor = campo.value.trim();
            const tipo = campo.getAttribute('data-validar');
            
            switch (tipo) {
                case 'email':
                    if (!this.email(valor)) {
                        this.mostrarError(campo, 'Ingrese un email válido.');
                        esValido = false;
                    }
                    break;
                    
                case 'dni':
                    if (!this.dni(valor)) {
                        this.mostrarError(campo, 'Ingrese un DNI válido (7-8 dígitos).');
                        esValido = false;
                    }
                    break;
                    
                case 'telefono':
                    if (!this.telefono(valor)) {
                        this.mostrarError(campo, 'Ingrese un teléfono válido.');
                        esValido = false;
                    }
                    break;
                    
                case 'monto':
                    if (!this.monto(valor)) {
                        this.mostrarError(campo, 'Ingrese un monto válido mayor a 0.');
                        esValido = false;
                    }
                    break;
                    
                case 'requerido':
                    if (!valor) {
                        this.mostrarError(campo, 'Este campo es obligatorio.');
                        esValido = false;
                    }
                    break;
            }
        });
        
        return esValido;
    }
}

// Inicializar validaciones en formularios
document.addEventListener('DOMContentLoaded', function() {
    const formularios = document.querySelectorAll('form[data-validar]');
    
    formularios.forEach(formulario => {
        formulario.addEventListener('submit', function(e) {
            if (!Validaciones.validarFormulario(this)) {
                e.preventDefault();
                
                // Enfocar primer campo con error
                const primerError = this.querySelector('.is-invalid');
                if (primerError) {
                    primerError.focus();
                }
            }
        });
        
        // Limpiar errores al escribir
        const campos = formulario.querySelectorAll('[data-validar]');
        campos.forEach(campo => {
            campo.addEventListener('input', function() {
                Validaciones.removerError(this);
            });
        });
    });
});

// Funciones globales
function confirmarEliminacion(mensaje = '¿Está seguro de que desea eliminar este registro?') {
    return confirm(mensaje);
}

function formatearMoneda(input) {
    // Formatear input de moneda mientras se escribe
    input.addEventListener('input', function(e) {
        let value = this.value.replace(/[^\d]/g, '');
        if (value) {
            value = parseFloat(value) / 100;
            this.value = value.toLocaleString('es-AR', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }
    });
}