let authSubmitting = false;

function authFields() {
    return Array.from(document.querySelectorAll('.auth-form-panel .form-control'));
}

function authMessageElement() {
    return document.getElementById('responseMessage');
}

function clearAuthMessage() {
    const message = authMessageElement();
    if (!message) {
        return;
    }

    message.textContent = '';
    message.hidden = true;
    message.setAttribute('aria-hidden', 'true');
}

function clearAuthValidation() {
    authFields().forEach((field) => {
        field.setAttribute('aria-invalid', 'false');
    });
    clearAuthMessage();
}

function showAuthValidation(messageText, invalidFields, focusField) {
    const message = authMessageElement();

    authFields().forEach((field) => {
        field.setAttribute('aria-invalid', invalidFields.indexOf(field) !== -1 ? 'true' : 'false');
    });

    if (message) {
        message.textContent = messageText;
        message.hidden = false;
        message.setAttribute('aria-hidden', 'false');
    }

    if (focusField) {
        focusField.focus();
    }
}

function clearFieldValidation() {
    authFields().forEach((field) => {
        field.setAttribute('aria-invalid', 'false');
    });
    clearAuthMessage();
}

function bindAuthValidationReset() {
    authFields().forEach((field) => {
        field.addEventListener('input', clearFieldValidation);
    });
}

function setAuthSubmitting(isSubmitting) {
    authSubmitting = isSubmitting;

    const button = document.querySelector('.auth-submit');
    if (!button) {
        return;
    }

    button.disabled = isSubmitting;
    button.setAttribute('aria-busy', isSubmitting ? 'true' : 'false');
}

function readAuthResponse(response) {
    return response.text().then((body) => {
        let data = null;

        try {
            data = body ? JSON.parse(body) : null;
        } catch (error) {
            data = null;
        }

        return { response: response, data: data };
    });
}

function authFailureMessage(response, data, fallbackMessage) {
    const status = response ? response.status : 0;
    const serverMessage = data && typeof data.mensaje === 'string' ? data.mensaje : '';
    const knownValidationMessages = [
        'Ingresa tu usuario y contraseña.',
        'Ingresa tu usuario.',
        'Ingresa tu contraseña.',
        'Ingresa tu PIN de acceso.'
    ];

    if (status === 400 && knownValidationMessages.indexOf(serverMessage) !== -1) {
        return serverMessage;
    }

    if (status === 429) {
        return 'Demasiados intentos. Espera unos minutos e inténtalo nuevamente.';
    }

    if (status >= 500 || status === 0) {
        return 'No fue posible conectar con el servicio. Inténtalo nuevamente.';
    }

    return fallbackMessage;
}

function loginClinica() {
    const usuarioInput = document.getElementById('usuario');
    const passwordInput = document.getElementById('password');

    if (!usuarioInput || !passwordInput || authSubmitting) {
        return;
    }

    const usuario = usuarioInput.value.trim();
    const password = passwordInput.value;
    const passwordIsEmpty = password.trim() === '';

    clearAuthValidation();

    if (!usuario && passwordIsEmpty) {
        showAuthValidation('Ingresa tu usuario y contraseña.', [usuarioInput, passwordInput], usuarioInput);
        return;
    }

    if (!usuario) {
        showAuthValidation('Ingresa tu usuario.', [usuarioInput], usuarioInput);
        return;
    }

    if (passwordIsEmpty) {
        showAuthValidation('Ingresa tu contraseña.', [passwordInput], passwordInput);
        return;
    }

    setAuthSubmitting(true);

    fetch('/clinica/login', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ usuario: usuario, password: password })
    })
        .then(readAuthResponse)
        .then(({ response, data }) => {
            if (response.ok && data && data.resultado) {
                localStorage.setItem('CLINICAJWT', data.token || '');
                window.location.href = '/clinica';
                return;
            }

            showAuthValidation(
                authFailureMessage(response, data, 'Usuario o contraseña incorrectos.'),
                [usuarioInput, passwordInput],
                passwordInput
            );
        })
        .catch(() => {
            showAuthValidation(
                'No fue posible conectar con el servicio. Inténtalo nuevamente.',
                [usuarioInput, passwordInput],
                passwordInput
            );
        })
        .finally(() => setAuthSubmitting(false));
}

function loginPaciente() {
    const pinInput = document.getElementById('Pin');

    if (!pinInput || authSubmitting) {
        return;
    }

    const pin = pinInput.value.trim();

    clearAuthValidation();

    if (!pin) {
        showAuthValidation('Ingresa tu PIN de acceso.', [pinInput], pinInput);
        return;
    }

    setAuthSubmitting(true);

    fetch('/historia-clinica/login', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ pin: pin })
    })
        .then(readAuthResponse)
        .then(({ response, data }) => {
            if (response.ok && data && data.resultado) {
                localStorage.setItem('CLINICAJWT', data.token || '');
                window.location.href = '/historia-clinica';
                return;
            }

            showAuthValidation(
                authFailureMessage(response, data, 'PIN incorrecto o no vigente.'),
                [pinInput],
                pinInput
            );
        })
        .catch(() => {
            showAuthValidation(
                'No fue posible conectar con el servicio. Inténtalo nuevamente.',
                [pinInput],
                pinInput
            );
        })
        .finally(() => setAuthSubmitting(false));
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bindAuthValidationReset);
} else {
    bindAuthValidationReset();
}
