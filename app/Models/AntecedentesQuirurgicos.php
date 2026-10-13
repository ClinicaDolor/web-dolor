<?php
namespace App\Models;

use App\Config\Database;
use App\Models\PacienteModulosModelo;

class AntecedentesQuirurgicos
{
    private $bd;

    public function __construct()
    {
        $this->bd = Database::getInstance();
    }

    private function escape($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }

    private function getCirugias(int $idPaciente, string $order = 'id ASC'): array
    {
        $allowedOrder = [
            'id ASC' => 'id ASC',
            'fecha DESC' => 'fecha DESC, id DESC',
        ];
        $orderBy = $allowedOrder[$order] ?? $allowedOrder['id ASC'];
        $stmt = $this->bd->prepare(
            "SELECT id, id_paciente, fecha, cirugia, observaciones
             FROM pc_antecedentes_quirurgicos
             WHERE id_paciente = :id_paciente
             ORDER BY {$orderBy}"
        );
        $stmt->execute([':id_paciente' => $idPaciente]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    //-------------------- EDITOR DE TEXTO WORD --------------------//
    public function editorTextoAQ($idPaciente)
    {
        $idPaciente = filter_var($idPaciente, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($idPaciente === false) {
            return '<p>No se encontró información.</p>';
        }

        $preguntas = $this->getCirugias((int) $idPaciente);
        $result = '<table class="table-bordered" border="1" cellpadding="6" cellspacing="0" style="border-collapse: collapse; width: 100%; font-family: Arial, sans-serif;">
        <tr>
        <th class="text-center align-middle">Fecha</th>
        <th class="text-center align-middle">Nombre de la cirugía</th>
        <th class="text-center align-middle">Observaciones</th>
        </tr>';

        if (!empty($preguntas)) {
            foreach ($preguntas as $pregunta) {
                $result .= '<tr>
                <td class="text-center align-middle">' . $this->escape($pregunta['fecha'] ?? '') . '</td>
                <td class="text-center align-middle">' . $this->escape($pregunta['cirugia'] ?? '') . '</td>
                <td class="text-center align-middle">' . $this->escape($pregunta['observaciones'] ?? '') . '</td>
                </tr>';
            }
        } else {
            $result .= '<tr><td class="text-center align-middle" colspan="3">No se encontró información.</td></tr>';
        }

        return $result . '</table>';
    }

    private function patientInstructions(): string
    {
        return 'Registra el nombre de la cirugía, la fecha en que se realizó y cualquier observación que quieras conservar. Puedes agregar otra cirugía, regresar a un registro anterior o eliminarlo si fue agregado por error.';
    }

    private function renderPatientQuestions(int $idPaciente, array $preguntas, string $botonFinalizar): string
    {
        $result = '<div class="p06-instruction sectionQuestion" id="p06InstructionSection" data-autoplay="false">
            <p class="p06-instruction-text texto">' . $this->escape($this->patientInstructions()) . '</p>
        </div>';

        if (empty($preguntas)) {
            return $result . '<div class="p06-empty-state" role="status">
                <div class="p06-empty-icon" aria-hidden="true"><i data-feather="clipboard"></i></div>
                <h3>No hay cirugías registradas</h3>
                <p>Si necesitas registrar una cirugía, agrega un registro. Si no corresponde, puedes finalizar esta sección sin crear datos clínicos.</p>
                <button type="button" class="btn btn-primary p06-add-button" onclick="agregarCirugiaPaciente(' . $idPaciente . ', \'Paciente\')">
                    <i data-feather="plus" aria-hidden="true"></i><span>Agregar cirugía</span>
                </button>
            </div>
            <div class="p06-empty-actions">' . $botonFinalizar . '</div>';
        }

        $result .= '<div class="p06-record-list" data-p06-record-count="' . count($preguntas) . '">';
        foreach ($preguntas as $index => $pregunta) {
            $idCirugia = (int) ($pregunta['id'] ?? 0);
            $fecha = $this->escape($pregunta['fecha'] ?? '');
            $cirugia = $this->escape($pregunta['cirugia'] ?? '');
            $observaciones = $this->escape($pregunta['observaciones'] ?? '');
            $active = $index === 0 ? ' active' : '';
            $result .= '<article class="p06-record pregunta-container' . $active . '" data-index="' . $index . '" data-id="' . $idCirugia . '">
                <div class="p06-record-heading">
                    <div>
                        <p class="dashboard-eyebrow">REGISTRO ' . ($index + 1) . '</p>
                        <h3>Antecedente quirúrgico</h3>
                    </div>
                    <span class="p06-record-status">Registro editable</span>
                </div>
                <p class="p06-record-instruction texto">Indica el nombre de la cirugía, su fecha de realización y las observaciones que quieras conservar.</p>
                <div class="p06-fields">
                    <div class="p06-field">
                        <label for="p06-cirugia-' . $idCirugia . '">Nombre de la cirugía</label>
                        <input id="p06-cirugia-' . $idCirugia . '" data-p06-field="cirugia" type="text" class="form-control" value="' . $cirugia . '" placeholder="Escribe aquí el nombre de la cirugía..." onchange="editarCirugia(' . $idCirugia . ', this, 2, \'Paciente\')">
                    </div>
                    <div class="p06-field">
                        <label for="p06-fecha-' . $idCirugia . '">Fecha de realización</label>
                        <input id="p06-fecha-' . $idCirugia . '" data-p06-field="fecha" type="date" class="form-control" value="' . $fecha . '" onchange="editarCirugia(' . $idCirugia . ', this, 1, \'Paciente\')">
                    </div>
                    <div class="p06-field p06-field-wide">
                        <label for="p06-observaciones-' . $idCirugia . '">Observaciones</label>
                        <textarea id="p06-observaciones-' . $idCirugia . '" data-p06-field="observaciones" class="form-control" rows="3" placeholder="Escribe aquí tus observaciones..." onchange="editarCirugia(' . $idCirugia . ', this, 3, \'Paciente\')">' . $observaciones . '</textarea>
                    </div>
                </div>
                <div class="p06-record-actions">
                    <button type="button" class="btn p06-danger-button" onclick="eliminarCirugiaPaciente(' . $idCirugia . ', \'Paciente\')">
                        <i data-feather="trash-2" aria-hidden="true"></i><span>Eliminar cirugía</span>
                    </button>
                    <button type="button" class="btn p06-secondary-button" onclick="p06FocusCurrentRecord()">
                        <i data-feather="save" aria-hidden="true"></i><span>Guardar cambios</span>
                    </button>
                </div>
                <div class="p06-navigation" aria-label="Navegación de cirugías">
                    ' . ($index > 0 ? '<button type="button" class="btn btn-secondary" onclick="anteriorPregunta()"><i data-feather="chevron-left" aria-hidden="true"></i><span>Anterior</span></button>' : '<span></span>') . '
                    ' . ($index < count($preguntas) - 1 ? '<button type="button" class="btn btn-primary" onclick="siguientePregunta()"><span>Siguiente</span><i data-feather="chevron-right" aria-hidden="true"></i></button>' : $botonFinalizar) . '
                </div>
            </article>';
        }
        return $result . '</div>
            <div class="p06-list-actions">
                <button type="button" class="btn p06-secondary-button" onclick="agregarCirugiaPaciente(' . $idPaciente . ', \'Paciente\')"><i data-feather="plus" aria-hidden="true"></i><span>Agregar otra cirugía</span></button>
            </div>';
    }

    //---------- MOSTRAR PREGUNTAS DEL MODULO 4 ----------//
    public function mostrarPreguntasM4($idPaciente, $idRol)
    {
        $idPaciente = filter_var($idPaciente, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($idPaciente === false) {
            return '<p class="text-danger">No se pudo identificar al paciente.</p>';
        }

        $idPaciente = (int) $idPaciente;
        if ($idRol === 'Paciente') {
            $preguntas = $this->getCirugias($idPaciente);
            $botonFinalizar = (new PacienteModulosModelo())->botonFinalizarModulo(4, $idPaciente, 'Paciente');
            return $this->renderPatientQuestions($idPaciente, $preguntas, $botonFinalizar);
        }

        $preguntas = $this->getCirugias($idPaciente, 'fecha DESC');
        $result = '<div class="p06-doctor-instruction sectionQuestion" id="p06DoctorInstruction" data-autoplay="false">
            <p class="p06-instruction-text texto">A continuación, indica si a tu paciente se le han realizado una o más cirugías.</p>
        </div>
        <div class="p06-doctor-toolbar">
            <button id="btnAgregarCirugia" type="button" onclick="agregarCirugiaPaciente(' . $idPaciente . ', \'Doctor\')" class="btn btn-success"><i data-feather="plus" aria-hidden="true"></i><span>Agregar cirugía</span></button>
        </div>
        <div class="table-responsive p06-doctor-table-wrap"><table class="table table-striped" id="table_cirugia">
            <thead><tr><th>Fecha</th><th>Nombre de la cirugía</th><th>Observaciones</th><th aria-label="Acciones"><i data-feather="trash-2" aria-hidden="true"></i></th></tr></thead><tbody>';

        if (empty($preguntas)) {
            $result .= '<tr><td colspan="4" class="p06-doctor-empty">No hay cirugías registradas.</td></tr>';
        } else {
            foreach ($preguntas as $pregunta) {
                $idCirugia = (int) ($pregunta['id'] ?? 0);
                $fecha = $this->escape($pregunta['fecha'] ?? '');
                $cirugia = $this->escape($pregunta['cirugia'] ?? '');
                $observaciones = $this->escape($pregunta['observaciones'] ?? '');
                $result .= '<tr>
                    <td><input class="form-control" type="date" onchange="editarCirugia(' . $idCirugia . ', this, 1, \'Doctor\')" value="' . $fecha . '"></td>
                    <td><input class="form-control" type="text" onchange="editarCirugia(' . $idCirugia . ', this, 2, \'Doctor\')" value="' . $cirugia . '" placeholder="Nombre de la cirugía"></td>
                    <td><input class="form-control" type="text" onchange="editarCirugia(' . $idCirugia . ', this, 3, \'Doctor\')" value="' . $observaciones . '" placeholder="Observaciones"></td>
                    <td class="text-center"><button type="button" class="btn p06-icon-button" aria-label="Eliminar cirugía" onclick="eliminarCirugiaPaciente(' . $idCirugia . ', \'Doctor\')"><i data-feather="trash-2" aria-hidden="true"></i></button></td>
                </tr>';
            }
        }
        return $result . '</tbody></table></div>';
    }

    public function agregarCirugiaPaciente($data)
    {
        $idPaciente = filter_var($data['idPaciente'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($idPaciente === false) {
            return ['resultado' => 422, 'mensaje' => 'Paciente no válido.'];
        }

        $stmt = $this->bd->prepare('INSERT INTO pc_antecedentes_quirurgicos (id_paciente) VALUES (:id_paciente)');
        if ($stmt->execute([':id_paciente' => $idPaciente])) {
            return ['resultado' => 200, 'mensaje' => 'Se habilitó una nueva cirugía correctamente.'];
        }
        return ['resultado' => 422, 'mensaje' => 'No se pudo agregar la cirugía.'];
    }

    public function editarCirugiaPaciente($data)
    {
        $idPaciente = filter_var($data['idPaciente'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $idCirugia = filter_var($data['idCirugia'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $opcionEdicion = filter_var($data['edicion'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 3]]);
        if ($idPaciente === false || $idCirugia === false || $opcionEdicion === false || !array_key_exists('detalle', $data)) {
            return ['resultado' => 422, 'mensaje' => 'Datos de actualización no válidos.'];
        }

        $columns = [1 => 'fecha', 2 => 'cirugia', 3 => 'observaciones'];
        $column = $columns[$opcionEdicion];
        $exists = $this->bd->prepare('SELECT id FROM pc_antecedentes_quirurgicos WHERE id = :id AND id_paciente = :id_paciente LIMIT 1');
        $exists->execute([':id' => $idCirugia, ':id_paciente' => $idPaciente]);
        if (!$exists->fetchColumn()) {
            return ['resultado' => 403, 'mensaje' => 'Registro no autorizado.'];
        }

        $stmt = $this->bd->prepare("UPDATE pc_antecedentes_quirurgicos SET {$column} = :detalle WHERE id = :id AND id_paciente = :id_paciente");
        if ($stmt->execute([':detalle' => (string) $data['detalle'], ':id' => $idCirugia, ':id_paciente' => $idPaciente])) {
            return ['resultado' => 200, 'mensaje' => 'La información se actualizó correctamente.'];
        }
        return ['resultado' => 422, 'mensaje' => 'No se pudo actualizar la cirugía.'];
    }

    public function eliminarCirugiaPaciente($data)
    {
        $idPaciente = filter_var($data['idPaciente'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $idQuirurgico = filter_var($data['idQuirurgico'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($idPaciente === false || $idQuirurgico === false) {
            return ['resultado' => 422, 'mensaje' => 'Datos de eliminación no válidos.'];
        }

        $exists = $this->bd->prepare('SELECT id FROM pc_antecedentes_quirurgicos WHERE id = :id AND id_paciente = :id_paciente LIMIT 1');
        $exists->execute([':id' => $idQuirurgico, ':id_paciente' => $idPaciente]);
        if (!$exists->fetchColumn()) {
            return ['resultado' => 403, 'mensaje' => 'Registro no autorizado.'];
        }

        $stmt = $this->bd->prepare('DELETE FROM pc_antecedentes_quirurgicos WHERE id = :id AND id_paciente = :id_paciente');
        if ($stmt->execute([':id' => $idQuirurgico, ':id_paciente' => $idPaciente])) {
            return ['resultado' => 200, 'mensaje' => 'La cirugía se eliminó correctamente.'];
        }
        return ['resultado' => 422, 'mensaje' => 'No se pudo eliminar la cirugía.'];
    }
}
