<?php
namespace App\Controllers;

class SidebarController {

public function __construct() {

}

public function configureSidebar($role, $view, $sidebar, $id = null, $referencia = null) {

//----- Definir menu de inicio segun el rol -----
$homeElements = [
'DOCTOR' => ['titulo' => 'Inicio', 'url' => SERVIDOR . 'clinica', 'icono' => 'home'],
'PACIENTE' => ['titulo' => 'Inicio', 'url' => SERVIDOR . 'historia-clinica', 'icono' => 'home']
];

//----- Elementos que siempre estaran presentes -----
$comunElements = [
'perfil' => ['titulo' => 'Perfil', 'url' => SERVIDOR . 'clinica/perfil', 'icono' => 'user'],
'logout' => ['titulo' => 'Cerrar Sesión', 'url' => SERVIDOR . 'cerrar-sesion', 'icono' => 'log-out']
];


//----- Listado de elementos segun el rol y su vista ----- 
$viewRolElements = [

'DOCTOR' => [
'clinica' => [
['titulo' => 'Pacientes', 'url' => SERVIDOR . 'clinica/pacientes', 'icono' => 'users'],
['titulo' => 'Cofepris', 'url' => SERVIDOR . 'clinica/cofepris', 'icono' => 'file-text'],
['titulo' => 'Recetas', 'url' => SERVIDOR . 'clinica/recetas', 'icono' => 'file-text']
],

'clinica-recetas' => [
['titulo' => 'Pacientes', 'url' => SERVIDOR . 'clinica/pacientes', 'icono' => 'users'],
['titulo' => 'Recetas', 'url' => SERVIDOR . 'clinica/recetas', 'icono' => 'file-text'],
['titulo' => 'Nueva Receta', 'url' => SERVIDOR . 'clinica/recetas/nueva', 'icono' => 'file-text']
],

'clinica-modulos-paciente' => [
['titulo' => 'Pacientes', 'url' => SERVIDOR . 'clinica/pacientes', 'icono' => 'users'],
['titulo' => 'Expediente', 'url' => SERVIDOR . 'clinica/paciente/'.$id, 'icono' => 'folder'],
['titulo' => 'Historia Clinica', 'url' => SERVIDOR . 'clinica/modulos/paciente/' . $id, 'icono' => 'archive']
],

'clinica-paciente-nuevo' => [
['titulo' => 'Pacientes', 'url' => SERVIDOR . 'clinica/pacientes', 'icono' => 'users'],
['titulo' => 'Paciente Nuevo', 'url' => SERVIDOR . 'clinica/paciente/nuevo', 'icono' => 'edit']
],

'clinica-paciente-editar' => [
['titulo' => 'Pacientes', 'url' => SERVIDOR . 'clinica/pacientes', 'icono' => 'users'],
['titulo' => 'Expediente', 'url' => SERVIDOR . 'clinica/paciente/'.$id, 'icono' => 'folder'],
['titulo' => 'Paciente Editar', 'url' => SERVIDOR . 'clinica/paciente/editar/'.$id, 'icono' => 'edit']
],

'clinica-paciente-detalle' => [
['titulo' => 'Pacientes', 'url' => SERVIDOR . 'clinica/pacientes', 'icono' => 'users'],
['titulo' => 'Expediente', 'url' => SERVIDOR . 'clinica/paciente/'.$id, 'icono' => 'folder']
],

'clinica-paciente-recetas' => [
['titulo' => 'Expediente', 'url' => SERVIDOR . 'clinica/paciente/'.$id, 'icono' => 'folder'],
['titulo' => 'Paciente Recetas', 'url' => SERVIDOR . 'clinica/receta/paciente/'.$id, 'icono' => 'file-text']
],

'clinica-paciente-notas' => [
['titulo' => 'Expediente', 'url' => SERVIDOR . 'clinica/paciente/'.$id, 'icono' => 'folder'],
['titulo' => 'Paciente Notas', 'url' => SERVIDOR . 'clinica/nota-subsecuente/paciente/'.$id.'/referencia/'.$referencia, 'icono' => 'file-text']
],

'clinica-paciente-laboratorio' => [
['titulo' => 'Expediente', 'url' => SERVIDOR . 'clinica/paciente/'.$id, 'icono' => 'folder'],
['titulo' => 'Paciente Laboratorio', 'url' => SERVIDOR . 'clinica/laboratorio/paciente/'.$id, 'icono' => 'file-text']
],


'clinica-paciente-pin' => [
['titulo' => 'Pacientes', 'url' => SERVIDOR . 'clinica/pacientes', 'icono' => 'users'],
['titulo' => 'Paciente Pin', 'url' => SERVIDOR . 'clinica/paciente/pin/'.$id, 'icono' => 'key']
],

'clinica-receta' => [
['titulo' => 'Expediente', 'url' => SERVIDOR . 'clinica/paciente/'.$referencia, 'icono' => 'folder'],
['titulo' => 'Receta (Detalle)', 'url' => SERVIDOR . 'clinica/receta/'.$id, 'icono' => 'file-text']
],

'clinica-receta-externo' => [
['titulo' => 'Recetas', 'url' => SERVIDOR . 'clinica/recetas', 'icono' => 'file-text'],
['titulo' => 'Receta (Detalle)', 'url' => SERVIDOR . 'clinica/receta-externo/'.$id, 'icono' => 'file-text']
],
'clinica-receta-nueva' => [
['titulo' => 'Pacientes', 'url' => SERVIDOR . 'clinica/pacientes', 'icono' => 'users'],

['titulo' => 'Recetas', 'url' => SERVIDOR . 'clinica/recetas', 'icono' => 'file-text'],
['titulo' => 'Nueva Receta', 'url' => SERVIDOR . 'clinica/recetas/nueva', 'icono' => 'file-text']
],

'cofepris' => [
['titulo' => 'Cofepris', 'url' => SERVIDOR . 'clinica/cofepris', 'icono' => 'file-text']
],
'clinica-cofepris' => [
['titulo' => 'Expediente', 'url' => SERVIDOR . 'clinica/paciente/'.$referencia, 'icono' => 'folder'],
['titulo' => 'Cofepris', 'url' => SERVIDOR . 'clinica/cofepris/'.$id, 'icono' => 'file-text']
],
'clinica-paciente-cofepris' => [
['titulo' => 'Expediente', 'url' => SERVIDOR . 'clinica/paciente/'.$id, 'icono' => 'folder'],
['titulo' => 'Paciente Cofepris', 'url' => SERVIDOR . 'clinica/cofepris/paciente/'.$id, 'icono' => 'file-text']
],
'clinica-cofepris-folios' => [
['titulo' => 'Cofepris Folios', 'url' => SERVIDOR . 'clinica/cofepris/carpeta/'.$id, 'icono' => 'file-text']
],    

'clinica-laboratorio' => [
['titulo' => 'Expediente', 'url' => SERVIDOR . 'clinica/paciente/'.$referencia, 'icono' => 'folder'],
['titulo' => 'Laboratorio', 'url' => SERVIDOR . 'clinica/laboratorio/'.$id, 'icono' => 'file-text']
],

'nota-subsecuente' => [
['titulo' => 'Expediente', 'url' => SERVIDOR . 'clinica/paciente/'.$referencia, 'icono' => 'folder'],
['titulo' => 'Nota Subsecuente', 'url' => SERVIDOR . 'clinica/nota-subsecuente/'.$id, 'icono' => 'file-text']
],

//---------- MODULOS DE HISTORIA CLINICA ----------
'ficha-identificiacion' => [
['titulo' => 'Expediente', 'url' => SERVIDOR . 'clinica/paciente/'.$id, 'icono' => 'folder'],
['titulo' => 'Historia Clinica', 'url' => SERVIDOR . 'clinica/modulos/paciente/' . $id, 'icono' => 'archive'],
['titulo' => 'Ficha de identificación del paciente', 'url' => SERVIDOR . 'clinica/'.$view.'/paciente/'.$id, 'icono' => 'file-text']
],

'antecedentes-familiares' => [
['titulo' => 'Expediente', 'url' => SERVIDOR . 'clinica/paciente/'.$id, 'icono' => 'folder'],
['titulo' => 'Historia Clinica', 'url' => SERVIDOR . 'clinica/modulos/paciente/' . $id, 'icono' => 'archive'],
['titulo' => 'Antecedentes familiares', 'url' => SERVIDOR . 'clinica/'.$view.'/paciente/'.$id, 'icono' => 'file-text']
],

'antecedentes-personales-no-patologicos' => [
['titulo' => 'Expediente', 'url' => SERVIDOR . 'clinica/paciente/'.$id, 'icono' => 'folder'],
['titulo' => 'Historia Clinica', 'url' => SERVIDOR . 'clinica/modulos/paciente/' . $id, 'icono' => 'archive'],
['titulo' => 'Antecedentes Personales No Patológicos', 'url' => SERVIDOR . 'clinica/'.$view.'/paciente/'.$id, 'icono' => 'file-text']
],

'perfil' => [
['titulo' => 'Paciente', 'url' => SERVIDOR . 'clinica/pacientes', 'icono' => 'users']
],

'antecedentes-personales-quirurgicos' => [
['titulo' => 'Expediente', 'url' => SERVIDOR . 'clinica/paciente/'.$id, 'icono' => 'folder'],
['titulo' => 'Historia Clinica', 'url' => SERVIDOR . 'clinica/modulos/paciente/' . $id, 'icono' => 'archive'],
['titulo' => 'Antecedentes Personales Quirúrgicos', 'url' => SERVIDOR . 'clinica/'.$view.'/paciente/'.$id, 'icono' => 'file-text']
],

'antecedentes-personales-patologicos' => [
['titulo' => 'Expediente', 'url' => SERVIDOR . 'clinica/paciente/'.$id, 'icono' => 'folder'],
['titulo' => 'Historia Clinica', 'url' => SERVIDOR . 'clinica/modulos/paciente/' . $id, 'icono' => 'archive'],
['titulo' => 'Antecedentes Personales Patológicos', 'url' => SERVIDOR . 'clinica/'.$view.'/paciente/'.$id, 'icono' => 'file-text']
],

'procedimientos-control-dolor' => [
['titulo' => 'Expediente', 'url' => SERVIDOR . 'clinica/paciente/'.$id, 'icono' => 'folder'],
['titulo' => 'Historia Clinica', 'url' => SERVIDOR . 'clinica/modulos/paciente/' . $id, 'icono' => 'archive'],
['titulo' => 'Procedimientos que ha utilizado para controlar el dolor', 'url' => SERVIDOR . 'clinica/'.$view.'/paciente/'.$id, 'icono' => 'file-text']
],

'medicacion-actual' => [
['titulo' => 'Expediente', 'url' => SERVIDOR . 'clinica/paciente/'.$id, 'icono' => 'folder'],
['titulo' => 'Historia Clinica', 'url' => SERVIDOR . 'clinica/modulos/paciente/' . $id, 'icono' => 'archive'],
['titulo' => 'Medicación Actual', 'url' => SERVIDOR . 'clinica/'.$view.'/paciente/'.$id, 'icono' => 'file-text']
],

'evaluacion-dolor' => [
['titulo' => 'Expediente', 'url' => SERVIDOR . 'clinica/paciente/'.$id, 'icono' => 'folder'],
['titulo' => 'Historia Clinica', 'url' => SERVIDOR . 'clinica/modulos/paciente/' . $id, 'icono' => 'archive'],
['titulo' => 'Evaluación del dolor', 'url' => SERVIDOR . 'clinica/'.$view.'/paciente/'.$id, 'icono' => 'file-text']
],

'medicacion-control-dolor' => [
['titulo' => 'Expediente', 'url' => SERVIDOR . 'clinica/paciente/'.$id, 'icono' => 'folder'],
['titulo' => 'Historia Clinica', 'url' => SERVIDOR . 'clinica/modulos/paciente/' . $id, 'icono' => 'archive'],
['titulo' => 'Medicamentos que ha utilizado para controlar el dolor', 'url' => SERVIDOR . 'clinica/'.$view.'/paciente/'.$id, 'icono' => 'file-text']
]

],

'PACIENTE' => [
'historia-clinica' => [],

//---------- MODULOS DE HISTORIA CLINICA ----------
'ficha-identificiacion' => [
['titulo' => 'Ficha de identificación del paciente', 'url' => SERVIDOR . 'historia-clinica/'.$view.'/paciente/'.$id, 'icono' => 'file-text']
],


'antecedentes-familiares' => [
['titulo' => 'Antecedentes familiares', 'url' => SERVIDOR . 'historia-clinica/'.$view.'/paciente/'.$id, 'icono' => 'file-text']
],

'antecedentes-personales-no-patologicos' => [
['titulo' => 'Antecedentes Personales No Patologicos', 'url' => SERVIDOR . 'historia-clinica/'.$view.'/paciente/'.$id, 'icono' => 'file-text']
],

'antecedentes-personales-quirurgicos' => [
['titulo' => 'Antecedentes Personales Quirúrgicos', 'url' => SERVIDOR . 'historia-clinica/'.$view.'/paciente/'.$id, 'icono' => 'file-text']
],

'antecedentes-personales-patologicos' => [
['titulo' => 'Antecedentes Personales Patologicos', 'url' => SERVIDOR . 'historia-clinica/'.$view.'/paciente/'.$id, 'icono' => 'file-text']
],

'procedimientos-control-dolor' => [
['titulo' => 'Procedimientos que ha utilizado para controlar el dolor', 'url' => SERVIDOR . 'historia-clinica/'.$view.'/paciente/'.$id, 'icono' => 'file-text']
],

'medicacion-actual' => [
['titulo' => 'Medicación Actual', 'url' => SERVIDOR . 'historia-clinica/'.$view.'/paciente/'.$id, 'icono' => 'file-text']
],

'evaluacion-dolor' => [
['titulo' => 'Evaluación del dolor', 'url' => SERVIDOR . 'historia-clinica/'.$view.'/paciente/'.$id, 'icono' => 'file-text']
],

'medicacion-control-dolor' => [
['titulo' => 'Medicamentos que ha utilizado para controlar el dolor', 'url' => SERVIDOR . 'historia-clinica/'.$view.'/paciente/'.$id, 'icono' => 'file-text']
]

]
]; 

//----- Configuracion de listado de elementos ----------
$finalItems = array_merge(
[$homeElements[$role]], // Menú de inicio según el rol
$viewRolElements[$role][$view] ?? []
);

// Solo mostrar "Perfil" si NO es paciente
if ($role !== 'PACIENTE') {
$finalItems[] = $comunElements['perfil'];
}

// Cerrar sesión siempre
$finalItems[] = $comunElements['logout'];


// ----- Agregar los elementos al sidebar -----
foreach ($finalItems as $item) {
$sidebar->addItemList($item['titulo'], $item['url'], $item['icono']);
}

}
}
