<?php
use FastRoute\RouteCollector;

return function(RouteCollector $r) {


//-----------------------------------------------------------------------------------
//--- Inicio Configuración de Rutas para la pagina web ------------------------------

$r->addRoute('GET', '/', ['WebController', 'index']);
$r->addRoute('GET', '/quienes-somos', ['WebController', 'quienesSomos']);
$r->addRoute('GET', '/cursos', ['WebController', 'cursos']);

$r->addRoute('GET', '/que-es-dolor', ['WebController', 'queEsDolor']);
$r->addRoute('GET', '/tipos-dolor', ['WebController', 'tiposDolor']);
$r->addRoute('GET', '/dolor-agudo', ['WebController', 'dolorAgudo']);
$r->addRoute('GET', '/dolor-cronico', ['WebController', 'dolorCronico']);
$r->addRoute('GET', '/dolor-perioperatorio', ['WebController', 'dolorPerioperatorio']);
$r->addRoute('GET', '/dolor-cancer', ['WebController', 'dolorCancer']);
$r->addRoute('GET', '/dolor-cancer-frecuencia', ['WebController', 'dolorCancerFrecuencia']);
$r->addRoute('GET', '/dolor-cancer-causas', ['WebController', 'dolorCancerCausas']);
$r->addRoute('GET', '/dolor-cancer-preguntas-medico-decidir-tratamiento', ['WebController', 'dolorCancerPreguntasMedicoDecidirTratamiento']);
$r->addRoute('GET', '/seguimiento-tratamiento-dolor', ['WebController', 'seguimientoTratamientoDolor']);
$r->addRoute('GET', '/dolor-cancer-cinco-prioridades', ['WebController', 'dolorCancerCincoPrioridades']);
$r->addRoute('GET', '/dolor-cancer-endoscopia-paliativa', ['WebController', 'dolorCancerEndoscopiaPaliativa']);
$r->addRoute('GET', '/neuralgia-postherpetica', ['WebController', 'neuralgiaPostherpetica']);

$r->addRoute('GET', '/evaluacion-dolor', ['WebController', 'evaluacionDolor']);
$r->addRoute('GET', '/cuidadores', ['WebController', 'cuidadores']);
$r->addRoute('GET', '/inyeccion-epidural', ['WebController', 'inyeccionEpidural']);
$r->addRoute('GET', '/neuroestimulacion', ['WebController', 'neuroestimulacion']);
$r->addRoute('GET', '/como-se-inicia-un-tratamiento-con-opioides', ['WebController', 'comoIniciaTratamientoOpioides']);
$r->addRoute('GET', '/estrenimiento', ['WebController', 'estrenimiento']);
//-----------------------------------------------------------------------------------
//--- Fin Configuración de Rutas para la pagina web ---------------------------------



//---------- FUNCION PARA AGREGAR LAS RUTAS EN LOS DOS GRUPOS ----------
$registrarRutasComunes = function (RouteCollector $r) {
//----- 2. ANTECEDENTES FAMILIARES
$r->addRoute('POST', '/paciente/agregar-enfermedad-antecedentes', ['ModulosController', 'pacienteInsertEnfermedad']);
$r->addRoute('POST', '/paciente/editar-enfermedad-antecedentes', ['ModulosController', 'pacienteEditEnfermedad']);
$r->addRoute('POST', '/paciente/eliminar-enfermedad-antecedentes', ['ModulosController', 'pacienteDeleteEnfermedad']);

//----- 3. ANTECEDENTES PERSONALES NO PATOLOGICOS
$r->addRoute('POST', '/paciente/editar-cuestionario-modulo3', ['ModulosController', 'pacienteEditarCuestionarioM3']);

//----- 4. ANTECEDENTES PERSONALES QUIRÚRGICOS
$r->addRoute('POST', '/paciente/agregar-cirugia-antecedentes', ['ModulosController', 'pacienteInsertCirugia']);
$r->addRoute('POST', '/paciente/editar-cirugia-antecedentes', ['ModulosController', 'pacienteEditCirugia']);
$r->addRoute('POST', '/paciente/eliminar-cirugia-antecedentes', ['ModulosController', 'pacienteDeleteCirugia']);

//----- 5. ANTECEDENTES PERSONALES PATOLOGICOS
$r->addRoute('POST', '/paciente/editar-cuestionarioV1-modulo5', ['ModulosController', 'pacienteEditarCuestionarioV1M5']);
$r->addRoute('POST', '/paciente/editar-cuestionarioV2-modulo5', ['ModulosController', 'pacienteEditarCuestionarioV2M5']);

//----- 6. MEDICACION ACTUAL
$r->addRoute('POST', '/paciente/agregar-medicacion-actual', ['ModulosController', 'pacienteInsertMedicamento']);
$r->addRoute('POST', '/paciente/editar-medicacion-actual', ['ModulosController', 'pacienteEditMedicamento']);
$r->addRoute('POST', '/paciente/eliminar-medicacion-actual', ['ModulosController', 'pacienteDeleteMedicamento']);

//----- 7. MEDICACION PARA EL DOLOR
$r->addRoute('POST', '/paciente/editar-cuestionario-modulo7', ['ModulosController', 'pacienteEditarCuestionarioM7']);

//----- 8. AGREGAR PROCEDIMIENTO DOLOR
$r->addRoute('POST', '/paciente/agregar-procedimiento-dolor-modulo8', ['ModulosController', 'pacienteInsertProcedimientos']);
$r->addRoute('POST', '/paciente/editar-procedimiento-dolor-modulo8', ['ModulosController', 'pacienteEditProcedimiento']);
$r->addRoute('POST', '/paciente/eliminar-procedimiento-dolor-modulo8', ['ModulosController', 'pacienteDeleteProcedimiento']);
$r->addRoute('POST', '/paciente/editar-tratamiento-dolor-modulo8', ['ModulosController', 'pacienteEditTratamiento']);

//----- 9. EVALUACION DEL DOLOR
$r->addRoute('POST', '/paciente/editar-img-frente-modulo9', ['ModulosController', 'pacienteEditImgED1']);
$r->addRoute('POST', '/paciente/eliminar-img-frente-modulo9', ['ModulosController', 'pacienteDeleteImgED1']);

$r->addRoute('POST', '/paciente/editar-img-espalda-modulo9', ['ModulosController', 'pacienteEditImgED2']);
$r->addRoute('POST', '/paciente/eliminar-img-espalda-modulo9', ['ModulosController', 'pacienteDeleteImgED2']);

$r->addRoute('POST', '/paciente/editar-evaluacion-dolor-modulo9', ['ModulosController', 'pacienteEditEvaluacion']);

//----- GUARDAR CONTENIDO DEL HTML
$r->addRoute('POST', '/paciente/guardar-contenido-editor', ['ModulosController', 'pacienteContenidoEditor']);

//----- COMENTARIOS MODULOS
$r->addRoute('POST', '/paciente/agregar-comentario-modulo', ['ModulosController', 'pacienteComentarioModulo']);
$r->addRoute('POST', '/paciente/eliminar-comentario-modulo', ['ModulosController', 'pacienteDeleteComentario']);

//----- FINALIZACIÓN DE MÓDULOS
$r->addRoute('POST', '/paciente/finalizar-modulo-paciente', ['ModulosController', 'pacienteFinalizarModulo']);
};

//-------------------- VISTAS DEL DOCTOR --------------------
$r->addGroup('/clinica', function (RouteCollector $r) use ($registrarRutasComunes) {

$r->addRoute('GET', '', ['HomeController', 'indexClinica']);
$r->addRoute('GET', '/acceso', ['LoginController', 'accesoClinica']);
$r->addRoute('POST', '/login', ['LoginController', 'loginClinica']);

$r->addGroup('/pacientes', function (RouteCollector $r) {
$r->addRoute('GET', '', ['ClinicaController', 'pacientesIndex']);
}
);

//---------- RECETAS PACIENTES (INTERNOS Y EXTERNOS) ----------/
$r->addRoute('GET', '/recetas', ['ClinicaController', 'recetasIndex']);
$r->addRoute('GET', '/recetas/nueva', ['ClinicaController', 'recetaNueva']);



$r->addRoute('GET', '/paciente/nuevo', ['ClinicaController', 'pacienteNuevo']);
$r->addRoute('GET', '/paciente/editar/{idPaciente}', ['ClinicaController', 'pacienteEditar']);
$r->addRoute('POST', '/paciente/insert-edit', ['ClinicaController', 'pacienteInsertEdit']);

$r->addRoute('GET', '/paciente/pin/{idPaciente}', ['ClinicaController', 'pacientePin']);
$r->addRoute('POST', '/paciente/insert-pin', ['ClinicaController', 'pacienteInsertPin']);

$r->addRoute('GET', '/paciente/{idPaciente}', ['ClinicaController', 'pacienteDetalle']);

$r->addRoute('GET', '/nota-subsecuente/{idNota}', ['NotaSubsecuenteController', 'notaSubsecuente']);
$r->addRoute('GET', '/nota-subsecuente/paciente/{idPaciente}/referencia/{referencia}', ['ClinicaController', 'pacienteNotaSubsecuente']);
$r->addRoute('POST', '/paciente/insert-nota-subsecuente', ['ClinicaController', 'pacienteInsertNotaSubsecuente']);

$r->addRoute('GET', '/receta/{idReceta}', ['RecetaController', 'receta']);
$r->addRoute('GET', '/receta/paciente/{idPaciente}', ['ClinicaController', 'pacienteReceta']);
$r->addRoute('POST', '/paciente/insert-receta', ['ClinicaController', 'pacienteInsertReceta']);

$r->addRoute('GET', '/receta-externo/{id}', ['ClinicaController', 'recetaExternoDetalle']);
$r->addRoute('POST', '/paciente/insert-receta-externo', ['ClinicaController', 'pacienteInsertRecetaExterno']);

$r->addRoute('GET', '/laboratorio/{idLaboratorio}', ['LaboratorioController', 'laboratorio']);
$r->addRoute('GET', '/laboratorio/paciente/{idPaciente}', ['ClinicaController', 'pacienteLaboratorio']);
$r->addRoute('POST', '/laboratorio/insert-laboratorio', ['ClinicaController', 'pacienteInsertLaboratorio']);

$r->addRoute('GET', '/cofepris', ['HomeController', 'indexCofepris']);
$r->addRoute('POST', '/cofepris/insert-folios', ['CofeprisController', 'insertFolios']);

$r->addRoute('GET', '/cofepris/{idCofepris}', ['CofeprisController', 'cofepris']);
$r->addRoute('GET', '/cofepris/paciente/{idPaciente}', ['ClinicaController', 'pacienteCofepris']);

$r->addRoute('GET', '/cofepris/paciente/{idPaciente}/editar/{idCofepris}', ['ClinicaController', 'pacienteCofepris']);

$r->addRoute('POST', '/paciente/insert-cofepris', ['ClinicaController', 'pacienteInsertCofepris']);
$r->addRoute('GET', '/cofepris/carpeta/{idCarpeta}', ['CofeprisController', 'carpetaCofepris']);
$r->addRoute('POST', '/cofepris/edit-surtido', ['CofeprisController', 'editSurtido']);

//----- VISTAS DE LOS 9 MODULOS
$r->addRoute('GET', '/modulos/paciente/{idPaciente}', ['ClinicaController', 'pacientesModulos']);
$r->addRoute('GET', '/{modulo}/paciente/{idPaciente}', ['ModulosController', 'moduloVistaDoctor']);

$r->addRoute('GET', '/resumen/{modulo}/paciente/{idPaciente}', ['ModulosController', 'moduloVistaResumen']);

//----- FUNCIONALIDADES MODULOS PACIENTE 

//--------- OTROS
$r->addRoute('GET', '/perfil', ['ClinicaController', 'perfil']);
//---------------
$registrarRutasComunes($r);

});


//-------------------- VISTAS DEL PACIENTE --------------------
$r->addGroup('/historia-clinica', function (RouteCollector $r) use ($registrarRutasComunes) {
$r->addRoute('GET', '', ['HomeController', 'indexPaciente']);
$r->addRoute('GET', '/acceso', ['LoginController', 'accesoPaciente']);
$r->addRoute('POST', '/login', ['LoginController', 'loginPaciente']);

//----- VISTAS DE LOS 9 MODULOS
$r->addRoute('GET', '/{modulo}/paciente/{idPaciente}', ['ModulosController', 'moduloVistaPaciente']);
//----- FUNCIONALIDADES MODULOS PACIENTE 
$registrarRutasComunes($r);
});

//-----------------------------------------//
//Ruta para crear las diferentes busquedas
//----------------------------------------//
$r->addRoute('GET', '/buscar', ['BusquedasController', 'buscarPacientes']);
$r->addRoute('GET', '/buscar/tabla-recetas/{idPaciente}', ['BusquedasController', 'tableRecetas']);
$r->addRoute('GET', '/buscar/receta/{idReceta}', ['BusquedasController', 'buscarReceta']);
$r->addRoute('GET', '/buscar/tabla-recetas-externos', ['BusquedasController', 'tableRecetasExternos']);
$r->addRoute('GET', '/buscar/receta-externo/{id}', ['BusquedasController', 'buscarRecetaExterno']);
$r->addRoute('GET', '/buscar/nota-subsecuente/{idNota}', ['BusquedasController', 'buscarNotaSubsecuente']);
$r->addRoute('GET', '/buscar/laboratorio/{idLaboratorio}', ['BusquedasController', 'buscarLaboratorio']);
$r->addRoute('GET', '/buscar/tabla-laboratorio/{idPaciente}/{referencia}', ['BusquedasController', 'tableLaboratorio']);
$r->addRoute('GET', '/buscar/tabla-notas-subsecuentes/{idPaciente}/{referencia}', ['BusquedasController', 'tableNotasSubsecuentes']);


$r->addRoute('GET', '/buscar/tabla-cofepris/{idPaciente}', ['BusquedasController', 'tableCofepris']);
$r->addRoute('GET', '/buscar/cofepris/{idCofepris}', ['BusquedasController', 'buscarCofepris']);
$r->addRoute('GET', '/buscar/tabla-cofepris-folios', ['BusquedasController', 'buscarCofeprisFolios']);

$r->addRoute('GET', '/buscar/web', ['WebController', 'buscarWeb']);

//-----------------------------------------//
//Ruta para crear las diferentes busquedas de los Modulos
//----------------------------------------//
$r->addRoute('GET', '/buscar/contenido-preguntas-modulo-2/{idPaciente}/{idRol}', ['BusquedasController', 'contenidoPreguntasM2']);
$r->addRoute('GET', '/buscar/contenido-preguntas-modulo-3/{idPaciente}/{idRol}/{idCuestionario}', ['BusquedasController', 'contenidoPreguntasM3']);
$r->addRoute('GET', '/buscar/contenido-preguntas-modulo-4/{idPaciente}/{idRol}', ['BusquedasController', 'contenidoPreguntasM4']);
$r->addRoute('GET', '/buscar/contenido-preguntasV1-modulo-5/{idPaciente}/{idRol}/{idCuestionario}', ['BusquedasController', 'contenidoPreguntasM5V1']);
$r->addRoute('GET', '/buscar/contenido-preguntasV2-modulo-5/{idPaciente}/{idRol}', ['BusquedasController', 'contenidoPreguntasM5V2']);
$r->addRoute('GET', '/buscar/contenido-preguntas-modulo-6/{idPaciente}/{idRol}', ['BusquedasController', 'contenidoPreguntasM6']);
$r->addRoute('GET', '/buscar/contenido-preguntas-modulo-7/{idPaciente}/{idRol}/{idCuestionario}', ['BusquedasController', 'contenidoPreguntasM7']);
$r->addRoute('GET', '/buscar/contenido-preguntas-modulo-8/{idPaciente}/{idRol}', ['BusquedasController', 'contenidoPreguntasM8']);
$r->addRoute('GET', '/buscar/contenido-preguntas-tratamiento-modulo-8/{idPaciente}/{idRol}', ['BusquedasController', 'contenidoPreguntasM8V2']);
$r->addRoute('GET', '/buscar/contenido-evaluacion-frente-modulo-9/{idPaciente}/{idRol}', ['BusquedasController', 'contenidoPreguntasM9']);
$r->addRoute('GET', '/buscar/contenido-evaluacion-espalda-modulo-9/{idPaciente}/{idRol}', ['BusquedasController', 'contenidoPreguntasM9V2']);
$r->addRoute('GET', '/buscar/contenido-preguntas-modulo-9/{idPaciente}/{idRol}', ['BusquedasController', 'contenidoPreguntasM9V3']);

$r->addRoute('GET', '/buscar/contenido-comentarios-modulos/{idPaciente}/{idRol}/{idModulo}', ['BusquedasController', 'contenidoComentariosModulo']);

//-----------------------------------------//
//Cerrar sesion
//----------------------------------------//

$r->addRoute('GET', '/cerrar-sesion', ['LoginController', 'cerrarSesion']);
$r->addRoute('POST', '/pdf/receta/{idReceta}', ['PdfController', 'pdfReceta']);
$r->addRoute('POST', '/pdf/receta-externo/{id}', ['PdfController', 'pdfRecetaExterno']);
$r->addRoute('GET', '/pdf/cofepris/{id}', ['PdfController', 'pdfCofepris']);

//-----------------------------------------------//
//-------- Controlador para descargar archivos ---//
$r->addRoute('GET', '/descargar/{ruta}/{archivo}', ['DownloadController', 'download']);

};

?>