# Inventario funcional

## Portal público

Rutas públicas observadas en `app/Config/routes.php`: inicio, información institucional, cursos, dolor agudo, dolor crónico, dolor oncológico, cuidadores, evaluación del dolor, inyección epidural, neuroestimulación y contenido educativo relacionado.

## Área clínica

El rol comprobado en el código es `Doctor`. Las funciones observadas son:

- Dashboard clínico.
- Pacientes, alta, edición, expediente y PIN.
- Nueve módulos de historia clínica.
- Resúmenes de módulos.
- Recetas internas y externas.
- Notas subsecuentes.
- Laboratorios.
- COFEPRIS y folios.
- PDFs.
- Perfil y búsqueda.

## Área paciente

El rol comprobado es `Paciente`. El acceso se realiza mediante PIN y permite capturar o revisar los nueve módulos de historia clínica:

1. Ficha de identificación.
2. Antecedentes familiares.
3. Antecedentes personales no patológicos.
4. Antecedentes quirúrgicos.
5. Antecedentes patológicos.
6. Medicación actual.
7. Medicación para el dolor.
8. Procedimientos y tratamientos para el dolor.
9. Evaluación del dolor.

### Demostración sintética del catálogo completo

En la base aislada `clinica_dolor_demo_20261010_final` se verificaron los nueve
registros de `historia_clinica_modulos`, su orden, sus URLs y los enlaces de
continuación del paciente `9301`. Los porcentajes se obtuvieron llamando a los
métodos existentes de `PacienteModulosModelo`; no se añadieron reglas de
cálculo. Los módulos 1 y 2 fueron marcados como finalizados únicamente dentro
del fixture para comprobar la presentación de estados completados.

## Reglas inferidas

- El avance de módulos se calcula con porcentajes específicos por módulo.
- El paciente puede participar en la captura de su historia clínica.
- El médico puede consultar y editar información clínica.

Estas reglas requieren validación clínica formal antes de convertirlas en controles rígidos.
