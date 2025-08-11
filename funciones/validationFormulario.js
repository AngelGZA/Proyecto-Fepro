document.addEventListener('DOMContentLoaded', function() {
    const tipoUsuarioSelect = document.getElementById("tipo_usuario");
    const form = document.getElementById("signup");

    // Configuración de validación
    const validation = new JustValidate("#signup", {
        validateBeforeSubmitting: true,
        errorFieldCssClass: 'is-invalid',
        successFieldCssClass: 'is-valid',
        focusInvalidField: true,
        lockForm: true,
    });

    // ===== 1. VALIDACIONES BASE (SIEMPRE ACTIVAS) =====
    validation
        .addField("#name", [
            { rule: "required", errorMessage: "Nombre es requerido" }
        ])
        .addField("#email", [
            { rule: "required", errorMessage: "Email es requerido" },
            { rule: "email", errorMessage: "Email no válido" },
            {
                validator: (value) => () => {
                    return fetch("../public/validate-email.php?email=" + encodeURIComponent(value))
                        .then(response => response.json())
                        .then(json => json.available);
                },
                errorMessage: "Email ya registrado"
            }
        ])
        .addField("#password", [
            { rule: "required", errorMessage: "Contraseña es requerida" },
            { rule: "password", errorMessage: "Mínimo 8 caracteres con números y letras" }
        ])
        .addField("#password_confirmation", [
            {
                validator: (value, fields) => value === fields["#password"].elem.value,
                errorMessage: "Las contraseñas no coinciden"
            }
        ])
        .addField("#tipo_usuario", [
            {
                validator: (value) => value === "estudiante" || value === "empresa",
                errorMessage: "Selecciona un tipo de usuario"
            }
        ]);

    // ===== 2. FUNCIÓN PARA VALIDACIONES DINÁMICAS =====
    function setupDynamicValidation() {
        const tipoUsuario = tipoUsuarioSelect.value;

        // Limpiar validaciones previas
        validation
            .removeField('[name="telefono_estudiante"]')
            .removeField('[name="telefono_empresa"]')
            .removeField('[name="rfc"]')
            .removeField('[name="descripcion_estudiante"]')
            .removeField('[name="direccion_empresa"]')
            .removeField('[name="cv"]');

        // Valida según el tipo de usuario
        if (tipoUsuario === "estudiante") {
            validation
                .addField('[name="telefono_estudiante"]', [
                    { rule: "required", errorMessage: "Teléfono es requerido" },
                    { 
                        validator: (value) => /^\d{10}$/.test(value),
                        errorMessage: "Deben ser 10 dígitos" 
                    },
                    {
                        validator: (value) => () => {
                            return fetch(`../public/validate-tel.php?telefono=${encodeURIComponent(value)}`)
                                .then(res => res.json())
                                .then(json => json.available);
                        },
                        errorMessage: "Teléfono ya registrado"
                    }
                ])
                .addField('[name="descripcion_estudiante"]', [
                    { 
                        validator: (value) => value.trim().length > 0,
                        errorMessage: "Descripción no puede estar vacía" 
                    },
                    { 
                        validator: (value) => value.length <= 300,
                        errorMessage: "Máximo 300 caracteres" 
                    }
                ])
    .addField("[name='cv']", [
        {
            validator: (value, fields) => {
                if (document.getElementById("tipo_usuario").value !== "estudiante") return true;
                const file = fields["[name='cv']"].elem.files[0];
                return file && file.type === "application/pdf";
            },
            errorMessage: "Debes subir un archivo PDF"
        }
    ])
    .onSuccess((event) => {
        document.getElementById("signup").submit();
    });
        } 
        else if (tipoUsuario === "empresa") {
            validation
                    .addField("[name='telefono_empresa']", [
                    {
                        rule: "required",
                        errorMessage: "Teléfono es requerido"
                    },
                    {
                        validator: (value) => /^\d{10}$/.test(value),
                        errorMessage: "Deben ser 10 dígitos"
                    },
                    {
                        validator: (value) => () => {
                            return fetch(`../public/validate-tel.php?telefono=${encodeURIComponent(value)}`)
                                .then(res => res.json())
                                .then(json => json.available);
                        },
                        errorMessage: "Teléfono ya registrado"
                    }
                ], {
                    conditions: [
                        () => document.getElementById("tipo_usuario").value === "empresa"
                    ]
                })
                .addField("[name='rfc']", [
                    {
                        validator: (value) => {
                            const rfc = value.replace(/[^A-Z0-9]/g, '');
                            return rfc.length === 13;
                        },
                        errorMessage: "RFC debe tener 13 caracteres alfanuméricos"
                    },+
                    {
                        validator: (value) => {
                            const rfc = value.replace(/[^A-Z0-9]/g, '');
                            return fetch("../public/validate-rfc.php?rfc=" + encodeURIComponent(rfc))
                            .then(response => response.json())
                            .then(json => json.available && json.valid_format);
                        },
                        errorMessage: "RFC ya registrado o inválido"
                    }
                ], {
                    conditions: [
                        function() {
                            return document.getElementById("tipo_usuario").value === "empresa";
                        }
                    ]
                })
                .addField("[name='direccion_empresa']", [
                    {
                        validator: (value) => value.trim().length > 0,
                        errorMessage: "Dirección no puede estar vacía"
                    },
                    {
                        validator: (value) => value.length <= 300,
                        errorMessage: "Máximo 300 caracteres"
                    }
                ]);
        }
    }

    // ===== 3. EVENTOS Y EJECUCIÓN INICIAL =====
    tipoUsuarioSelect.addEventListener('change', () => {
        setupDynamicValidation();
        validation.revalidate(); // Fuerza revalidación inmediata
    });

    // Inicializar validaciones
    setupDynamicValidation();

    // ===== 4. MANEJO DEL ENVÍO =====
    validation.onSuccess((event) => {
        form.submit();
    });
});