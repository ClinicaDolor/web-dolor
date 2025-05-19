function guardarContenidoEditor(){
const contenidoHTML = editorInstance.getData(); // desde CKEditor
const idPaciente = document.getElementById('main').getAttribute('data-paciente');
const idRol = document.getElementById('main').getAttribute('data-rol');
const idTema = document.getElementById('main').getAttribute('data-tema');
    
const parametros = {
idPaciente: idPaciente,
idRol: idRol,
idTema: idTema,
contenido: contenidoHTML
};
    
gestionarContenidoTemas(
`/${idRol === "Paciente" ? "historia-clinica" : "clinica"}/paciente/guardar-contenido-editor`,
parametros,
() => {},
1
);
}
        
//---------- CONTROL SERVER ----------
function gestionarContenidoTemas(url, parametros, callback, idUpdate = 0) {
if(idUpdate == 1){
$(".LoaderPage").show();
}
fetch(url, {
method: 'POST',
headers: { 'Content-Type': 'application/json' },
body: JSON.stringify(parametros)
}).then(res => res.json()).then(data => {
if(idUpdate == 1){
$(".LoaderPage").fadeOut(1000);
}
if (data.resultado) callback();
else document.getElementById('mensaje').textContent = 'Error: ' + data.mensaje;
});
}
    