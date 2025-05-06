function loginClinica(){

    const usuario = document.getElementById('usuario').value;
    const password = document.getElementById('password').value;

    fetch('/clinica/login', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ usuario: usuario, password: password })
    })
    .then(response => response.json())
    .then(data => {

        if (data.resultado) {
            localStorage.setItem('CLINICAJWT', data.token);
            window.location.href = '/clinica';
        } else {
            document.getElementById('responseMessage').textContent = 'Error: ' + data.mensaje;
        }

    });
}

function loginPaciente(){

    const pin = document.getElementById('Pin').value;

    fetch('/historia-clinica/login', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ pin: pin })
    })
    .then(response => response.json())
    .then(data => {

        if (data.resultado) {
            localStorage.setItem('CLINICAJWT', data.token);
            window.location.href = '/historia-clinica';
        } else {
            document.getElementById('responseMessage').textContent = 'Error: ' + data.mensaje;
        }

    });
}