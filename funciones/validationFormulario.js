document.addEventListener('DOMContentLoaded', function () {
  const tipoUsuarioSelect = document.getElementById('tipo_usuario');
  const form = document.getElementById('signup');

  const validation = new JustValidate('#signup', {
    validateBeforeSubmitting: true,
    errorFieldCssClass: 'is-invalid',
    successFieldCssClass: 'is-valid',
    focusInvalidField: true,
    lockForm: true,
  });

  validation
    .addField('#name', [
      { rule: 'required', errorMessage: 'Nombre es requerido' },
    ])
    .addField('#email', [
      { rule: 'required', errorMessage: 'Email es requerido' },
      { rule: 'email', errorMessage: 'Email no válido' },
      {
        validator: (value) => () => {
          return fetch('../public/validate-email.php?email=' + encodeURIComponent(value))
            .then((r) => r.json())
            .then((json) => json.available);
        },
        errorMessage: 'Email ya registrado',
      },
    ])
    .addField('#password', [
      { rule: 'required', errorMessage: 'Contraseña es requerida' },
      { rule: 'password', errorMessage: 'Mínimo 8 caracteres con números y letras' },
    ])
    .addField('#password_confirmation', [
      {
        validator: (value, fields) => value === fields['#password'].elem.value,
        errorMessage: 'Las contraseñas no coinciden',
      },
    ])
    .addField('#tipo_usuario', [
      { rule: 'required', errorMessage: 'Selecciona un tipo de usuario' },
      {
        validator: (value) => ['estudiante', 'empresa', 'docente'].includes(value),
        errorMessage: 'Selecciona un tipo de usuario',
      },
    ]);

  function setupDynamicValidation() {
    const tipo = tipoUsuarioSelect.value;

    validation
      .removeField("[name='telefono_estudiante']")
      .removeField("[name='descripcion_estudiante']")
      .removeField("[name='cv']")
      .removeField("[name='telefono_empresa']")
      .removeField("[name='rfc']")
      .removeField("[name='direccion_empresa']")
      .removeField("[name='telefono_docente']")
      .removeField("[name='institucion_docente']") 
      .removeField("[name='especialidad_docente']")
      .removeField("[name='bio_docente']");

    if (tipo === 'estudiante') {
      validation
        .addField("[name='telefono_estudiante']", [
          { rule: 'required', errorMessage: 'Teléfono es requerido' },
          { validator: (v) => /^\d{10}$/.test(v), errorMessage: 'Deben ser 10 dígitos' },
          {
            validator: (value) => () => {
              return fetch(`../public/validate-tel.php?telefono=${encodeURIComponent(value)}`)
                .then((res) => res.json())
                .then((json) => json.available);
            },
            errorMessage: 'Teléfono ya registrado',
          },
        ])
        .addField("[name='descripcion_estudiante']", [
          { validator: (v) => v.trim().length > 0, errorMessage: 'Descripción no puede estar vacía' },
          { validator: (v) => v.length <= 300, errorMessage: 'Máximo 300 caracteres' },
        ])
        .addField("[name='cv']", [
          {
            validator: (value, fields) => {
              const file = fields["[name='cv']"]?.elem?.files?.[0];
              return !!file && file.type === 'application/pdf';
            },
            errorMessage: 'Debes subir un archivo PDF',
          },
        ]);
    } else if (tipo === 'empresa') {
      validation
        .addField("[name='telefono_empresa']", [
          { rule: 'required', errorMessage: 'Teléfono es requerido' },
          { validator: (v) => /^\d{10}$/.test(v), errorMessage: 'Deben ser 10 dígitos' },
          {
            validator: (value) => () => {
              return fetch(`../public/validate-tel.php?telefono=${encodeURIComponent(value)}`)
                .then((res) => res.json())
                .then((json) => json.available);
            },
            errorMessage: 'Teléfono ya registrado',
          },
        ])
        .addField("[name='rfc']", [
          {rule: 'required', errorMessage: 'RFC es requerido' },
          {
            validator: (value) => {
              const rfc = (value || '').toUpperCase().replace(/[^A-Z0-9]/g, '');
              return rfc.length === 13;
            },
            errorMessage: 'RFC debe tener 13 caracteres alfanuméricos',
          },
          {
            validator: (value) => {
              const rfc = (value || '').toUpperCase().replace(/[^A-Z0-9]/g, '');
              return fetch('../public/validate-rfc.php?rfc=' + encodeURIComponent(rfc))
                .then((response) => response.json())
                .then((json) => json.available && json.valid_format);
            },
            errorMessage: 'RFC ya registrado o inválido',
          },
        ])
        .addField("[name='direccion_empresa']", [
          { validator: (v) => v.trim().length > 0, errorMessage: 'Dirección no puede estar vacía' },
          { validator: (v) => v.length <= 300, errorMessage: 'Máximo 300 caracteres' },
        ]);
    } else if (tipo === 'docente') {
        validation
            .addField("[name='telefono_docente']", [
            { rule: 'required', errorMessage: 'Teléfono es requerido' },
            { validator: (v) => /^\d{10}$/.test(v), errorMessage: 'Deben ser 10 dígitos' },
            {
                validator: (value) => () => {
                return fetch(`../public/validate-tel.php?telefono=${encodeURIComponent(value)}`)
                    .then((res) => res.json())
                    .then((json) => json.available);
                },
                errorMessage: 'Teléfono ya registrado',
            },
            ])
            .addField("[name='institucion_docente']", [
            { rule: 'required', errorMessage: 'Institución es requerida' },
            { validator: (v) => v.trim().length >= 2, errorMessage: 'Mínimo 2 caracteres' },
            { validator: (v) => v.length <= 150, errorMessage: 'Máximo 150 caracteres' },
            ])
            .addField("[name='especialidad_docente']", [
            { validator: (v) => v.trim().length > 0, errorMessage: 'Especialidad requerida' },
            { validator: (v) => v.length <= 150, errorMessage: 'Máximo 150 caracteres' },
            ])
            .addField("[name='bio_docente']", [
            { validator: (v) => v.length <= 300, errorMessage: 'Máximo 300 caracteres' },
            ]);
        }
  }

  tipoUsuarioSelect.addEventListener('change', () => {
    setupDynamicValidation();
    if (typeof validation.revalidate === 'function') {
      validation.revalidate();
    }
  });

  setupDynamicValidation();

  validation.onSuccess(() => {
    form.submit();
  });
});