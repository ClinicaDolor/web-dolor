import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const read = relativePath => fs.readFileSync(path.join(root, relativePath), 'utf8');
const assert = (condition, message) => {
    if (!condition) throw new Error(message);
};

const sidebarHelper = read('app/Helpers/Sidebar.php');
const sidebarController = read('app/Controllers/SidebarController.php');
const mainJs = read('public/assets/js/main.js');

assert(sidebarHelper.includes("$activeClass = ($item['titulo'] == $this->activeItem) ? 'active' : '';"),
    'El estado activo no se calcula mediante la comparación de la etiqueta canónica.');
assert(sidebarHelper.includes("aria-current=\"page\""),
    'El enlace activo no expone aria-current="page".');
assert(sidebarController.includes("'Antecedentes Personales No Patológicos'"),
    'La etiqueta canónica de P05 no está acentuada en SidebarController.');
assert(sidebarController.includes("'Antecedentes Personales Patológicos'"),
    'La etiqueta canónica de P07 no está acentuada en SidebarController.');

const moduleSlugs = [
    'ficha-identificiacion',
    'antecedentes-familiares',
    'antecedentes-personales-no-patologicos',
    'antecedentes-personales-quirurgicos',
    'antecedentes-personales-patologicos',
    'procedimientos-control-dolor',
    'medicacion-actual',
    'evaluacion-dolor',
    'medicacion-control-dolor'
];
for (const slug of moduleSlugs) {
    assert(sidebarController.includes(`'${slug}' => [`),
        `Falta la entrada de navegación del módulo ${slug}.`);
}

assert(mainJs.includes("document.body.classList.contains('app-shell-patient')"),
    'La sincronización del toggler no está limitada al shell paciente.');
assert(mainJs.includes("setAttribute('aria-controls', 'sidebar')"),
    'El toggler paciente no sincroniza aria-controls.');
assert(mainJs.includes("setAttribute('aria-expanded', isOpen ? 'true' : 'false')"),
    'El toggler paciente no sincroniza aria-expanded.');
assert(mainJs.includes("event.preventDefault()"),
    'No se evita la navegación accidental de un toggler ancla heredado.');
assert(mainJs.includes("sidebar.classList.toggle('active')"),
    'El toggler no conserva la apertura/cierre del sidebar.');

const patientViews = [
    'app/views/paciente/index.php',
    'app/views/historia-clinica-paciente/ficha-identificiacion.php',
    'app/views/historia-clinica-paciente/antecedentes-familiares.php',
    'app/views/historia-clinica-paciente/antecedentes-personales-no-patologicos.php',
    'app/views/historia-clinica-paciente/antecedentes-personales-quirurgicos.php',
    'app/views/historia-clinica-paciente/antecedentes-personales-patologicos.php',
    'app/views/historia-clinica-paciente/medicacion-actual.php',
    'app/views/historia-clinica-paciente/medicacion-control-dolor.php',
    'app/views/historia-clinica-paciente/procedimientos-control-dolor.php',
    'app/views/historia-clinica-paciente/evaluacion-dolor.php'
];

for (const relativePath of patientViews) {
    const source = read(relativePath);
    assert(source.includes('class="sidebar-toggler'), `${relativePath}: falta el control sidebar-toggler.`);
    assert(source.includes('aria-controls="sidebar"'), `${relativePath}: falta aria-controls del sidebar.`);
    assert(source.includes('aria-expanded="true"'), `${relativePath}: falta estado ARIA inicial explícito.`);
    assert(source.includes('aria-label="Abrir menú principal"'), `${relativePath}: falta nombre accesible del toggler.`);
}

const login = read('app/views/login/login-paciente.php');
assert(!login.includes('class="sidebar-toggler'), 'El login paciente no debe renderizar navegación interna.');

console.log(`PHASE_04B_02_SIDEBAR_CONTRACT: PASS (${patientViews.length} vistas paciente, helper y JS compartido)`);
