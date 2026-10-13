<?php
namespace App\Models;
use App\Config\Database;
use App\Models\PacienteModulosModelo;

class MedicacionDolorModel{

    private $bd;

    public function __construct(){
    $this->bd = Database::getInstance();
    
    }


    //---------- OBTENER DATOS DEL MODULO ----------//
    public function obtenerModulosM7(){
    $stmt = $this->bd->query("SELECT id, tema FROM pac_temas_modulo_7 ORDER BY id ASC");
    $modulos = array();
    while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
    // Se indexa el arreglo por id para facilitar el acceso
    $modulos[$row['id']] = $row['tema'];
    }
    return $modulos;
    }


    public function obtenerNameModulos($idTema){
    $stmt = $this->bd->query("SELECT tema FROM pac_temas_modulo_7 WHERE id = '".$idTema."' ORDER BY id ASC");
    $modulos = array();
    while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
    $tema = $row['tema'];
    }
    return $tema;
    }

    //---------- OBTENER DATOS DEL MODULO ----------//
    public function obtenerPreguntasModulos(){
    $stmt = $this->bd->query("SELECT id FROM pac_preguntas_modulo_7 ORDER BY id ASC");
    $idPreguntas = [];
                
    while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
    $idPreguntas[] = $row['id'];
    }
        
    return $idPreguntas;
    }


    //---------- AGREGA PREGUNTAS AL INICIAR ----------//
    public function medicacionDolorQuest($idPaciente, $idPregunta) {
    $sql = "SELECT COUNT(*) FROM pac_respuestas_paciente_modulo_7 WHERE id_paciente = ? AND id_pregunta = ?";
    $stmt = $this->bd->prepare($sql);
    $stmt->execute([$idPaciente, $idPregunta]);
    $numero = $stmt->fetchColumn();
                    
    if ($numero == 0) {
    $sql_insert = "INSERT INTO pac_respuestas_paciente_modulo_7 (id_pregunta, id_paciente) 
    VALUES (?, ?)";
    $stmt_insert = $this->bd->prepare($sql_insert);
    $stmt_insert->execute([$idPregunta, $idPaciente]);
    }
    }

    //-------------------- EDITOR DE TEXTO WORD --------------------//
    public function editorTextoMD($idPaciente, $idTema){
    $result = ''; 

    $stmt = $this->bd->query("SELECT 
    pac_respuestas_paciente_modulo_7.id AS idRespuesta, 
    pac_temas_modulo_7.tema,
    pac_preguntas_modulo_7.pregunta, 
    pac_respuestas_paciente_modulo_7.respuesta, 
    pac_respuestas_paciente_modulo_7.dosis, 
    pac_respuestas_paciente_modulo_7.resultados, 
    pac_respuestas_paciente_modulo_7.descripcion, 
    pac_respuestas_paciente_modulo_7.consumo
    FROM pac_temas_modulo_7 
    INNER JOIN pac_preguntas_modulo_7 ON pac_preguntas_modulo_7.id_tema = pac_temas_modulo_7.id 
    INNER JOIN pac_respuestas_paciente_modulo_7 ON pac_preguntas_modulo_7.id = pac_respuestas_paciente_modulo_7.id_pregunta 
    WHERE pac_respuestas_paciente_modulo_7.id_paciente = '".$idPaciente."' AND pac_temas_modulo_7.id = '".$idTema."' ORDER BY pac_temas_modulo_7.id ASC");
    $preguntas = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    
    $modulos = $this->obtenerNameModulos($idTema);

    if (!empty($preguntas)) {
    $result .= '<table class="table-bordered" border="1" cellpadding="6" cellspacing="0" style="border-collapse: collapse; width: 100%; font-family: Arial, sans-serif;">
    <tr>
    <th class="text-center align-middle" colspan="7">'.$modulos.'</th>
    </tr>
    
    <tr>
    <th class="text-center align-middle" width="40px">#</th>
    <th class="text-start align-middle">Nombre del medicamento</th>
    <th class="text-start align-middle text-center" width="200px">Si/No</th>
    <th class="text-start align-middle text-center">Dosis que te utiliza</th>
    <th class="text-start align-middle text-center" width="280px">Resultados obtenidos</th>
    <th class="text-start align-middle text-center">Descripción de resultados</th>
    <th class="text-start align-middle text-center">Aún lo utiliza</th>
    </tr>';
    
    $num = 1;
    foreach ($preguntas as $pregunta): 
    $preguntaPC = $pregunta['pregunta'];
    $respuesta = $pregunta['respuesta'];
    $dosis = $pregunta['dosis'];
    $resultados = $pregunta['resultados'];
    $descripcion = $pregunta['descripcion'];
    $consumo = $pregunta['consumo'];

    $result .= '    
    <tr>
    <td class="text-center align-middle">'.$num.'</td>
    <td class="text-start align-middle">'.$preguntaPC.'</td>
    <td class="text-center align-middle">'.$respuesta.'</td>
    <td class="text-center align-middle">'.$dosis.'</td>
    <td class="text-center align-middle">'.$resultados.'</td>
    <td class="text-center align-middle">'.$descripcion.'</td>
    <td class="text-center align-middle">'.$consumo.'</td>
    </tr>';
    
    $num++;
    endforeach;

    $result .= '</table>';
    }


    return $result;
    }
    
    //---------- OBTENER PREGUNTAS DEL MODULO (V1) ----------//
    /**
     * Renderiza el cuestionario del portal paciente sin mezclarlo con la
     * tabla del área Doctor. El contrato data-p09-* permite que el cliente
     * guarde y avance sin handlers inline ni reconstrucciones durante el clic.
     */
    public function mostrarPreguntasM7Paciente(int $idPaciente, int $idRol, int $idTema): string
    {
    $escape = static fn($value): string => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    $columnCheck = $this->bd->prepare("SELECT COUNT(*) FROM information_schema.columns
        WHERE table_schema = DATABASE() AND table_name = 'pac_preguntas_modulo_7' AND column_name = 'pregunta'");
    $columnCheck->execute();
    $hasQuestionText = (int)$columnCheck->fetchColumn() === 1;
    $questionSelect = $hasQuestionText ? 'q.pregunta' : "'' AS pregunta";
    $stmt = $this->bd->prepare("SELECT
        r.id AS idRespuesta,
        t.tema,
        q.id AS idPregunta,
        {$questionSelect},
        COALESCE(r.respuesta, '') AS respuesta,
        COALESCE(r.dosis, '') AS dosis,
        COALESCE(r.resultados, '') AS resultados,
        COALESCE(r.descripcion, '') AS descripcion,
        COALESCE(r.consumo, '') AS consumo
    FROM pac_temas_modulo_7 t
    INNER JOIN pac_preguntas_modulo_7 q ON q.id_tema = t.id
    LEFT JOIN pac_respuestas_paciente_modulo_7 r
        ON r.id_pregunta = q.id AND r.id_paciente = :idPaciente
    WHERE t.id = :idTema
    ORDER BY q.id ASC");
    $stmt->execute([':idPaciente' => $idPaciente, ':idTema' => $idTema]);
    $preguntas = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    if (!$hasQuestionText || !$preguntas) {
        return '<div class="alert alert-warning p09-catalog-warning" role="status">El catálogo de este módulo no está disponible en el entorno de prueba. No se cargaron preguntas ni se realizaron cambios.</div>';
    }
    $modulos = $this->obtenerModulosM7();
    $temaIds = array_map('intval', array_keys($modulos));
    $temaIndex = array_search($idTema, $temaIds, true);
    $prevId = $temaIndex !== false && $temaIndex > 0 ? $temaIds[$temaIndex - 1] : null;
    $nextId = $temaIndex !== false && $temaIndex < count($temaIds) - 1 ? $temaIds[$temaIndex + 1] : null;
    $result = '';

    foreach ($preguntas as $index => $pregunta) {
        $idRespuesta = (int)($pregunta['idRespuesta'] ?? 0);
        $idPregunta = (int)$pregunta['idPregunta'];
        $domKey = $idRespuesta > 0 ? (string)$idRespuesta : 'q' . $idPregunta;
        $preguntaTexto = (string)$pregunta['pregunta'];
        $respuesta = trim((string)($pregunta['respuesta'] ?? ''));
        $dosis = (string)($pregunta['dosis'] ?? '');
        $resultados = (string)($pregunta['resultados'] ?? '');
        $descripcion = (string)($pregunta['descripcion'] ?? '');
        $consumo = (string)($pregunta['consumo'] ?? '');
        $active = $index === 0 ? ' active' : '';
        $responseName = 'p09_respuesta_' . $domKey;
        $resultName = 'p09_resultado_' . $domKey;
        $consumptionName = 'p09_consumo_' . $domKey;
        $result .= '<article class="pregunta-container p09-question' . $active . '" data-index="' . $index . '" data-p09-question="' . $domKey . '" data-p09-question-id="' . $idPregunta . '" data-p09-theme="' . $idTema . '" data-p09-prompt="' . $escape($preguntaTexto) . '">';
        $result .= '<div class="text-secondary fw-bold mb-1 p05-question-habit-label">Clase de medicamento:</div>';
        $result .= '<h3 class="mb-3 p05-question-habit-name">' . $escape($pregunta['tema']) . '</h3>';
        $result .= '<div class="p05-question-prompt sectionQuestion mt-3 mb-3"><h4 class="text-secondary fw-bold mb-1 texto"><b>' . $escape($preguntaTexto) . '</b></h4></div>';
        $result .= '<fieldset class="p09-fieldset"><legend class="visually-hidden">¿Has utilizado este medicamento?</legend><div class="d-flex flex-wrap gap-2 p09-choice-grid">';
        foreach ([['Si', 'Sí', 'si'], ['No', 'No', 'no']] as $choice) {
            [$value, $label, $slug] = $choice;
            $inputId = 'p09-respuesta-' . $domKey . '-' . $slug;
            $result .= '<label class="p05-choice-card text-center" for="' . $inputId . '"><input id="' . $inputId . '" type="radio" class="custom-checkbox" name="' . $responseName . '" value="' . $value . '" data-p09-control="response" data-p09-response="' . $idRespuesta . '" data-p09-question-id="' . $idPregunta . '" data-p09-theme="' . $idTema . '" data-p09-type="1" data-p09-saved-value="' . $escape($respuesta) . '"' . ($respuesta === $value ? ' checked' : '') . '><span>' . $label . '</span></label>';
        }
        $result .= '</div></fieldset>';

        if ($respuesta === 'Si') {
            $result .= '<div class="p09-dependencies" data-p09-dependencies>';
            $result .= '<label class="form-label" for="p09-dosis-' . $domKey . '">¿Cuál es la dosis que utilizas?</label>';
            $result .= '<input id="p09-dosis-' . $domKey . '" type="text" class="form-control mb-3" value="' . $escape($dosis) . '" placeholder="Ingresa aquí la dosis..." data-p09-control="text" data-p09-response="' . $idRespuesta . '" data-p09-question-id="' . $idPregunta . '" data-p09-theme="' . $idTema . '" data-p09-type="2" data-p09-saved-value="' . $escape($dosis) . '">';
            $result .= '<fieldset class="p09-fieldset"><legend class="form-label">¿Cuáles fueron tus resultados al utilizar el medicamento?</legend><div class="d-flex flex-wrap gap-2 p09-choice-grid">';
            foreach ([['Funciono', 'Funcionó', 'funciono'], ['No funciono', 'No funcionó', 'no-funciono'], ['Tuve efectos adversos', 'Tuve efectos adversos', 'adversos']] as $choice) {
                [$value, $label, $slug] = $choice;
                $inputId = 'p09-resultado-' . $domKey . '-' . $slug;
                $result .= '<label class="p05-choice-card text-center" for="' . $inputId . '"><input id="' . $inputId . '" type="radio" class="custom-checkbox" name="' . $resultName . '" value="' . $escape($value) . '" data-p09-control="result" data-p09-response="' . $idRespuesta . '" data-p09-question-id="' . $idPregunta . '" data-p09-theme="' . $idTema . '" data-p09-type="3" data-p09-saved-value="' . $escape($resultados) . '"' . ($resultados === $value ? ' checked' : '') . '><span>' . $label . '</span></label>';
            }
            $result .= '</div></fieldset>';
            if ($resultados === 'Tuve efectos adversos') {
                $result .= '<label class="form-label" for="p09-descripcion-' . $domKey . '">Describe los efectos adversos</label>';
                $result .= '<textarea id="p09-descripcion-' . $domKey . '" class="form-control mb-3" rows="3" placeholder="Ingresa aquí la descripción..." data-p09-control="text" data-p09-response="' . $idRespuesta . '" data-p09-question-id="' . $idPregunta . '" data-p09-theme="' . $idTema . '" data-p09-type="5" data-p09-saved-value="' . $escape($descripcion) . '">' . $escape($descripcion) . '</textarea>';
            }
            $result .= '<fieldset class="p09-fieldset"><legend class="form-label">¿Todavía utilizas este medicamento?</legend><div class="d-flex flex-wrap gap-2 p09-choice-grid">';
            foreach ([['Si', 'Sí', 'si'], ['No', 'No', 'no']] as $choice) {
                [$value, $label, $slug] = $choice;
                $inputId = 'p09-consumo-' . $domKey . '-' . $slug;
                $result .= '<label class="p05-choice-card text-center" for="' . $inputId . '"><input id="' . $inputId . '" type="radio" class="custom-checkbox" name="' . $consumptionName . '" value="' . $value . '" data-p09-control="consumption" data-p09-response="' . $idRespuesta . '" data-p09-question-id="' . $idPregunta . '" data-p09-theme="' . $idTema . '" data-p09-type="4" data-p09-saved-value="' . $escape($consumo) . '"' . ($consumo === $value ? ' checked' : '') . '><span>' . $label . '</span></label>';
            }
            $result .= '</div></fieldset></div>';
        }

        $result .= '<div class="mt-3 d-flex justify-content-between p09-navigation">';
        if ($index === 0 && $prevId !== null) {
            $result .= '<button type="button" class="btn btn-secondary" data-p09-previous-theme="' . $prevId . '"><i data-feather="chevron-left" aria-hidden="true"></i> ' . $escape($modulos[$prevId]) . '</button>';
        } elseif ($index > 0) {
            $result .= '<button type="button" class="btn btn-secondary" data-p09-previous-question><i data-feather="chevron-left" aria-hidden="true"></i> Anterior</button>';
        } else {
            $result .= '<span></span>';
        }
        if ($index < count($preguntas) - 1) {
            $result .= '<button type="button" class="btn btn-primary patient-nonpathological-next" data-p09-next disabled aria-disabled="true">Siguiente <i data-feather="chevron-right" aria-hidden="true"></i></button>';
        } elseif ($nextId !== null) {
            $result .= '<button type="button" class="btn btn-success" data-p09-next-theme="' . $nextId . '">' . $escape($modulos[$nextId]) . ' <i data-feather="chevron-right" aria-hidden="true"></i></button>';
        } else {
            $result .= '<button type="button" class="btn btn-success" data-p09-finalize>Finalizar módulo <i data-feather="check" aria-hidden="true"></i></button>';
        }
        $result .= '</div></article>';
    }
    return $result;
    }

    //---------- OBTENER PREGUNTAS DEL MODULO (V1) ----------//
    public function mostrarPreguntasM7($idPaciente, $idRol, $idTema){
    $result = '';
        
    $stmt = $this->bd->query("SELECT 
    pac_respuestas_paciente_modulo_7.id AS idRespuesta, 
    pac_temas_modulo_7.tema,
    pac_preguntas_modulo_7.pregunta, 
    pac_respuestas_paciente_modulo_7.respuesta, 
    pac_respuestas_paciente_modulo_7.dosis, 
    pac_respuestas_paciente_modulo_7.resultados, 
    pac_respuestas_paciente_modulo_7.descripcion, 
    pac_respuestas_paciente_modulo_7.consumo
    FROM pac_temas_modulo_7 
    INNER JOIN pac_preguntas_modulo_7 ON pac_preguntas_modulo_7.id_tema = pac_temas_modulo_7.id 
    INNER JOIN pac_respuestas_paciente_modulo_7 ON pac_preguntas_modulo_7.id = pac_respuestas_paciente_modulo_7.id_pregunta 
    WHERE pac_respuestas_paciente_modulo_7.id_paciente = '".$idPaciente."' AND pac_temas_modulo_7.id = '".$idTema."' ORDER BY pac_temas_modulo_7.id ASC");
    $preguntas = $stmt->fetchAll(\PDO::FETCH_ASSOC);


    if($idRol == "Paciente"){
    $modulos = $this->obtenerModulosM7();
    $model = new PacienteModulosModelo();
    $botonFinalizar = $model->botonFinalizarModulo(7,$idPaciente,$idRol);

    if (!empty($preguntas)) {
    $num = 1;
    foreach ($preguntas as $index => $pregunta) :
    $temaModulo = $pregunta['tema'];
    $idRespuesta = $pregunta['idRespuesta'];
    $preguntaPC = $pregunta['pregunta'];
    $respuesta = $pregunta['respuesta'];
    $dosis = $pregunta['dosis'];
    $resultados = $pregunta['resultados'];
    $descripcion = $pregunta['descripcion'];
    $consumo = $pregunta['consumo'];

    // La primera pregunta se muestra activa
    $claseActiva = ($index == 0) ? 'active' : '';  
    $result .= '<div class="pregunta-container '.$claseActiva.'" data-index="'.$index.'" data-id="'.$num.'" data-pregunta="'.$preguntaPC.'" data-rol="'.$idRol.'" data-tema="'.$idTema.'">';

    $result .= '<h4 class="text-success fw-bold"><b>Clase de medicamento: '.$temaModulo.'</b></h4>';
    $result .= '<div id="seccion'.$idRespuesta.'" data-autoplay="false" class="col-12 col-md-11 d-flex align-items-center sectionQuestion mt-3 pb-0">';
    $result .= '<h8 class="text-secondary fw-bold mb-1 texto"><b>'.$preguntaPC.'</b></h8>
    </div>';

    $result .= '
    <div class="d-flex flex-wrap gap-2 mb-3">
        <div class="text-center d-flex flex-column align-items-center">
            <input type="checkbox" class="form-check-input custom-checkbox mb-1" 
            onchange="respuestaPreguntaSelect('.$idPaciente.','.$idRespuesta.', this, '.$idTema.', 1, \''.$idRol.'\', 1)" 
            data-value="Si" value="Si" '.($respuesta == 'Si' ? 'checked' : '').'>
            <h5 class="text-secondary">Sí</h5>
        </div>
        <div class="text-center d-flex flex-column align-items-center">
            <input type="checkbox" class="form-check-input custom-checkbox mb-1" 
            onchange="respuestaPreguntaSelect('.$idPaciente.','.$idRespuesta.', this, '.$idTema.', 1, \''.$idRol.'\', 1)" 
            data-value="No" value="No" '.($respuesta == 'No' ? 'checked' : '').'>
            <h5 class="text-secondary">No</h5>
        </div>
    </div>';

    if($respuesta == 'Si'){
    $result .= '<h8 class="text-secondary fw-bold mb-1 texto"><b>¿Cuál es la dosis que te utiliza?</b></h8>';
    $result .= '<input type="text" class="form-control mb-3" value="' . ($dosis == '' ? '' : $dosis) . '" placeholder="Ingresa aquí la dosis..." onchange="respuestaPreguntaSelect('.$idPaciente.','.$idRespuesta.', this, '.$idTema.', 2,\''.$idRol.'\')">';

    $result .= '<h8 class="text-secondary fw-bold mb-1 texto"><b>¿Cuales fueron tus resultado obtenidos al utilizar el medicamento?</b></h8>';
    $result .= '
    <div class="d-flex flex-wrap gap-2 mb-3">
        <div class="text-center d-flex flex-column align-items-center">
            <input type="checkbox" class="form-check-input custom-checkbox mb-1" 
            onchange="respuestaPreguntaSelect('.$idPaciente.','.$idRespuesta.', this, '.$idTema.', 3, \''.$idRol.'\')" 
            data-value="Si" value="Funciono" '.($resultados == 'Funciono' ? 'checked' : '').'>
            <h5 class="text-secondary">Funcionó</h5>
        </div>
        <div class="text-center d-flex flex-column align-items-center">
            <input type="checkbox" class="form-check-input custom-checkbox mb-1" 
            onchange="respuestaPreguntaSelect('.$idPaciente.','.$idRespuesta.', this, '.$idTema.', 3, \''.$idRol.'\')" 
            data-value="Si" value="No funciono" '.($resultados == 'No funciono' ? 'checked' : '').'>
            <h5 class="text-secondary">No funcionó</h5>
        </div>
        <div class="text-center d-flex flex-column align-items-center">
            <input type="checkbox" class="form-check-input custom-checkbox mb-1" 
            onchange="respuestaPreguntaSelect('.$idPaciente.','.$idRespuesta.', this, '.$idTema.', 3, \''.$idRol.'\')" 
            data-value="Si" value="Tuve efectos adversos" '.($resultados == 'Tuve efectos adversos' ? 'checked' : '').'>
            <h5 class="text-secondary">Efectos adversos</h5>
        </div>
    </div>';
    
    if($resultados == 'Tuve efectos adversos'){
    $result .= '<h8 class="text-secondary fw-bold mb-1 texto"><b>Descripción de los efectos adversos:</b></h8>';
    $result .= '<input type="text" class="form-control mb-3" value="' . ($descripcion == '' ? '' : $descripcion) . '" placeholder="Ingresa aquí la descripcion..." onchange="respuestaPreguntaSelect('.$idPaciente.','.$idRespuesta.', this, '.$idTema.', 5,\''.$idRol.'\')">';
    }

    $result .= '<h8 class="text-secondary fw-bold mb-1 texto"><b>¿Sigues utilizando este medicamento actualmente?</b></h8>';
    $result .= '
    <h8 class="text-secondary fw-bold mb-1 texto"><b>¿Sigues utilizando este medicamento actualmente?</b></h8>
    <div class="d-flex flex-wrap gap-2 mb-3 mt-2">
        <div class="text-center d-flex flex-column align-items-center">
            <input type="checkbox" class="form-check-input custom-checkbox mb-1"
            onchange="respuestaPreguntaSelect('.$idPaciente.','.$idRespuesta.', this, '.$idTema.', 4, \''.$idRol.'\')" 
            data-value="Si" value="Si" '.($consumo == 'Si' ? 'checked' : '').'>
            <h5 class="text-secondary">Sí</h5>
        </div>
        <div class="text-center d-flex flex-column align-items-center">
            <input type="checkbox" class="form-check-input custom-checkbox mb-1"
            onchange="respuestaPreguntaSelect('.$idPaciente.','.$idRespuesta.', this, '.$idTema.', 4, \''.$idRol.'\')" 
            data-value="No" value="No" '.($consumo == 'No' ? 'checked' : '').'>
            <h5 class="text-secondary">No</h5>
        </div>
    </div>';
    
    }


    //----- Botones de navegación -----
    $result .= '<div class="mt-3 d-flex justify-content-between">';
    // Botón "Anterior"
    if ($index == 0) {
    if($idTema > 1){ 
    // Se obtiene el nombre del módulo anterior desde el array
    $prevNombre = $modulos[$idTema - 1];
    $prevId = $idTema - 1;
    $result .= '<button class="btn btn-success" onclick="anteriorPregunta(' . $idTema . ')"><i data-feather="chevron-left"></i>'.$prevNombre.'</button>';
    } else {
    $result .= '<div></div>';
    }
    } else {
    $result .= '<button class="btn btn-secondary" onclick="anteriorPregunta(' . $idTema . ')"><i data-feather="chevron-left"></i> Anterior</button>';
    }
       
    // Verificar si la respuesta está vacía para preguntas de tipo "select"
    if (empty($respuesta)) {
    // Si la respuesta está vacía, no mostrar los botones "Siguiente" ni "Ir a siguiente sección"
    $result .= '</div>'; // Cierre contenedor de botones
    $result .= '</div>'; // Cierre contenedor de la pregunta
    $num++;
    continue; // Saltar a la siguiente iteración
    }
       
    // Botón "Siguiente" o "Ir a siguiente sección"
    if ($index < count($preguntas) - 1) {
    if($index == 0 && $respuesta == "No"){
    // Botón para ir a la última sección
    if(isset($modulos[$idTema+1])){
    $nextNombre = $modulos[$idTema+1];
    $nextId = $idTema + 1;
    $result .= '<button class="btn btn-primary" onclick="siguientePregunta(' . $idTema . ')">Siguiente <i data-feather="chevron-right"></i></button>';
    } else {
    $result .= '<button class="btn btn-success" onclick="irASiguienteSeccion(\''.$nextNombre.'\','.$nextId.')">'.$nextNombre.' <i data-feather="chevron-right"></i></button>';
    }
    } else {
    // Botón normal para la siguiente pregunta
    $result .= '<button class="btn btn-primary" onclick="siguientePregunta(' . $idTema . ')">Siguiente <i data-feather="chevron-right"></i></button>';
    }
    } else {
    // Al final del módulo, se comprueba si existe un siguiente módulo
    if(isset($modulos[$idTema+1])){
    $nextNombre = $modulos[$idTema+1];
    $nextId = $idTema + 1;
    $result .= '<button class="btn btn-success" onclick="irASiguienteSeccion(\''.$nextNombre.'\','.$nextId.')">'.$nextNombre.' <i data-feather="chevron-right"></i></button>';
    } else {
    $result .= $botonFinalizar;
    }
    }
   
    $result .= '</div>';  // Cierre contenedor de botones 

    $result .= '</div>';
    $num++;
    endforeach;
    }

    }else{

    $modulos = $this->obtenerNameModulos($idTema);
    
    $result .= '<div class="alert alert-success text-center" role="alert">
    '.$modulos.'
    </div>';

    $result .= '    
    <div class="table-responsive">
    <table class="table table-striped" id="table_medicamentos_'.$idTema.'">
    <thead>
    <tr>
    <th class="text-center align-middle" width="40px">#</th>
    <th class="text-start align-middle">Nombre del medicamento</th>
    <th class="text-start align-middle text-center" width="200px">Si/No</th>
    <th class="text-start align-middle text-center">Dosis que te utiliza</th>
    <th class="text-start align-middle text-center" width="280px">Resultados obtenidos</th>
    <th class="text-start align-middle text-center">Descripción de resultados</th>
    <th class="text-start align-middle text-center">Aún lo utiliza</th>
    </tr>
    </thead>
    <tbody>';  
    if (!empty($preguntas)) {
    $num = 1;
    foreach ($preguntas as $index => $pregunta) :
    $idRespuesta = $pregunta['idRespuesta'];
    $preguntaPC = $pregunta['pregunta'];
    $respuesta = $pregunta['respuesta'];
    $dosis = $pregunta['dosis'];
    $resultados = $pregunta['resultados'];
    $descripcion = $pregunta['descripcion'];
    $consumo = $pregunta['consumo'];

    $result .= '<tr>';
    $result .= '<td class="text-center align-middle">'.$num.'</td>';
    $result .= '<td class="text-start align-middle">'.$preguntaPC.'</td>';
    
    $result .= '<td class="text-start align-middle">
    <select class="form-select text-center" onchange="respuestaPreguntaSelect('.$idPaciente.','.$idRespuesta.', this, '.$idTema.', 1,\''.$idRol.'\',1)">
    <option value="" disabled selected>Selecciona una opción...</option>
    <option value="Si" ' . ($respuesta == 'Si' ? 'selected' : '') . '>Sí</option>
    <option value="No" ' . ($respuesta == 'No' ? 'selected' : '') . '>No</option>
    </select>
    </td>';

    $result .= '<td class="text-start align-middle">
    <input type="text" class="form-control text-center" value="' . ($dosis == '' ? '' : $dosis) . '" placeholder="Ingresa aquí la dosis..." onchange="respuestaPreguntaSelect('.$idPaciente.','.$idRespuesta.', this, '.$idTema.', 2,\''.$idRol.'\')"
    ' . ($respuesta == 'Si' ? '' : 'disabled') . '>
    </td>';

    $result .= '<td class="text-start align-middle">
    <select class="form-select text-center" onchange="respuestaPreguntaSelect('.$idPaciente.','.$idRespuesta.', this, '.$idTema.', 3,\''.$idRol.'\')" ' . ($respuesta == 'Si' ? '' : 'disabled') . '>
    <option value="" disabled selected>Selecciona una opción...</option>
    <option value="Funciono" ' . ($resultados == 'Funciono' ? 'selected' : '') . '>Funciono</option>
    <option value="No funciono" ' . ($resultados == 'No funciono' ? 'selected' : '') . '>No funciono</option>
    <option value="Tuve efectos adversos" ' . ($resultados == 'Tuve efectos adversos' ? 'selected' : '') . '>Tuve efectos adversos</option>
    </select>
    </td>';

    $result .= '<td class="text-start align-middle">
    <input type="text" class="form-control text-center" value="' . ($descripcion == '' ? '' : $descripcion) . '" placeholder="Ingresa aquí la descripción..." onchange="respuestaPreguntaSelect('.$idPaciente.','.$idRespuesta.', this, '.$idTema.', 5,\''.$idRol.'\')"
    ' . ($resultados == 'Tuve efectos adversos' ? '' : 'disabled') . '>
    </td>';

    $result .= '<td class="text-start align-middle">
    <select class="form-select text-center" onchange="respuestaPreguntaSelect('.$idPaciente.','.$idRespuesta.', this, '.$idTema.', 4,\''.$idRol.'\')" ' . ($respuesta == 'Si' ? '' : 'disabled') . '>
    <option value="" disabled selected>Selecciona una opción...</option>
    <option value="Si" ' . ($consumo == 'Si' ? 'selected' : '') . '>Sí</option>
    <option value="No" ' . ($consumo == 'No' ? 'selected' : '') . '>No</option>
    </select>
    </td>';

    $result .= '</tr>';
    $num++;
    endforeach;
    }
    $result .= '</tbody>
    </table>
    </div>';

    }

    return $result;
    }



    public function editarCuestionarioM7Paciente($data){
    $idPaciente = filter_var($data['idPaciente'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $idRespuesta = filter_var($data['idRespuesta'] ?? 0, FILTER_VALIDATE_INT);
    $idPregunta = filter_var($data['idPregunta'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $idTema = filter_var($data['idTema'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $idTipo = filter_var($data['idTipo'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 5]]);
    if ($idPaciente === false || $idRespuesta === false || $idRespuesta < 0 || $idPregunta === false || $idTema === false || $idTipo === false || !array_key_exists('detalle', $data) || !is_scalar($data['detalle'])) {
        return ['resultado' => 422, 'mensaje' => 'Datos de respuesta no válidos.'];
    }

    $detalle = trim((string)$data['detalle']);
    $limits = [1 => 20, 2 => 5000, 3 => 40, 4 => 20, 5 => 5000];
    if (mb_strlen($detalle) > $limits[$idTipo]) {
        return ['resultado' => 422, 'mensaje' => 'La respuesta excede el límite permitido.'];
    }
    $allowed = [
        1 => ['Si', 'No'],
        3 => ['Funciono', 'No funciono', 'Tuve efectos adversos'],
        4 => ['Si', 'No'],
    ];
    if (isset($allowed[$idTipo]) && !in_array($detalle, $allowed[$idTipo], true)) {
        return ['resultado' => 422, 'mensaje' => 'La opción seleccionada no es válida.'];
    }

    $question = $this->bd->prepare('SELECT id, id_tema FROM pac_preguntas_modulo_7 WHERE id = :idPregunta AND id_tema = :idTema LIMIT 1');
    $question->execute([':idPregunta' => $idPregunta, ':idTema' => $idTema]);
    if (!$question->fetch(\PDO::FETCH_ASSOC)) return ['resultado' => 422, 'mensaje' => 'La pregunta no pertenece a este módulo.'];

    $context = $this->bd->prepare("SELECT r.id, r.id_paciente, r.id_pregunta, r.respuesta, r.dosis, r.resultados, r.descripcion, r.consumo,
        q.id_tema
        FROM pac_respuestas_paciente_modulo_7 r
        INNER JOIN pac_preguntas_modulo_7 q ON q.id = r.id_pregunta
        WHERE r.id = :idRespuesta AND r.id_paciente = :idPaciente AND r.id_pregunta = :idPregunta AND q.id_tema = :idTema");
    $context->execute([':idRespuesta' => $idRespuesta, ':idPaciente' => $idPaciente, ':idPregunta' => $idPregunta, ':idTema' => $idTema]);
    $row = $context->fetch(\PDO::FETCH_ASSOC);
    if (!$row && $idRespuesta > 0) return ['resultado' => 403, 'mensaje' => 'No tienes autorización para actualizar esta respuesta.'];
    if (!$row) {
        $existing = $this->bd->prepare('SELECT id, id_paciente, id_pregunta, respuesta, dosis, resultados, descripcion, consumo FROM pac_respuestas_paciente_modulo_7 WHERE id_paciente = :idPaciente AND id_pregunta = :idPregunta LIMIT 1');
        $existing->execute([':idPaciente' => $idPaciente, ':idPregunta' => $idPregunta]);
        $row = $existing->fetch(\PDO::FETCH_ASSOC);
        if (!$row) {
            $insert = $this->bd->prepare("INSERT INTO pac_respuestas_paciente_modulo_7 (id_pregunta, id_paciente, respuesta, dosis, resultados, descripcion, consumo) VALUES (:idPregunta, :idPaciente, '', '', '', '', '')");
            $insert->execute([':idPregunta' => $idPregunta, ':idPaciente' => $idPaciente]);
            $idRespuesta = (int)$this->bd->lastInsertId();
            $row = ['id' => $idRespuesta, 'id_paciente' => $idPaciente, 'id_pregunta' => $idPregunta, 'respuesta' => '', 'dosis' => '', 'resultados' => '', 'descripcion' => '', 'consumo' => ''];
        } else {
            $idRespuesta = (int)$row['id'];
        }
    }
    if ((int)$row['id_paciente'] !== (int)$idPaciente) return ['resultado' => 403, 'mensaje' => 'No tienes autorización para actualizar esta respuesta.'];
    if ((int)$row['id_pregunta'] !== (int)$idPregunta) return ['resultado' => 422, 'mensaje' => 'El contexto de la respuesta no es válido.'];
    if (in_array($idTipo, [2, 3, 4], true) && (string)$row['respuesta'] !== 'Si') return ['resultado' => 422, 'mensaje' => 'Completa primero la respuesta afirmativa.'];
    if ($idTipo === 5 && (string)$row['resultados'] !== 'Tuve efectos adversos') return ['resultado' => 422, 'mensaje' => 'La descripción sólo aplica a efectos adversos.'];

    $column = [1 => 'respuesta', 2 => 'dosis', 3 => 'resultados', 4 => 'consumo', 5 => 'descripcion'][$idTipo];
    $updates = [$column . ' = :detalle'];
    $params = [':detalle' => $detalle, ':idRespuesta' => $idRespuesta, ':idPaciente' => $idPaciente, ':idPregunta' => (int)$row['id_pregunta']];
    if ($idTipo === 1 && $detalle === 'No') {
        $updates[] = "dosis = ''";
        $updates[] = "resultados = ''";
        $updates[] = "descripcion = ''";
        $updates[] = "consumo = ''";
    } elseif ($idTipo === 3 && in_array($detalle, ['Funciono', 'No funciono'], true)) {
        $updates[] = "descripcion = ''";
    }
    $set = implode(', ', $updates);
    $current = (string)($row[$column] ?? '');
    $needsClear = ($idTipo === 1 && $detalle === 'No' && ((string)($row['respuesta'] ?? '') !== 'No' || (string)($row['resultados'] ?? '') !== ''));
    if ($current === $detalle && !$needsClear) return ['resultado' => 200, 'mensaje' => 'La respuesta ya estaba guardada.', 'sin_cambios' => true, 'idRespuesta' => $idRespuesta];

    $sql = "UPDATE pac_respuestas_paciente_modulo_7 SET {$set}
        WHERE id = :idRespuesta AND id_paciente = :idPaciente AND id_pregunta = :idPregunta";
    $stmt = $this->bd->prepare($sql);
    $stmt->execute($params);
    if ($stmt->rowCount() === 0 && $current !== $detalle) return ['resultado' => 422, 'mensaje' => 'No se pudo actualizar la respuesta.'];
    return ['resultado' => 200, 'mensaje' => 'La información se actualizó correctamente.', 'sin_cambios' => false, 'idRespuesta' => $idRespuesta];

    }

    /**
     * Finaliza P09 una sola vez para el paciente autorizado.
     * La comprobación se mantiene dentro del modelo para que reintentos de
     * red no creen filas duplicadas en la base sintética ni en producción.
     */
    public function finalizarModuloPaciente(int $idPaciente): array
    {
    $this->bd->beginTransaction();
    try {
        $existing = $this->bd->prepare('SELECT id FROM pac_historia_clinica_finalizar WHERE id_modulo = 7 AND id_paciente = :idPaciente LIMIT 1 FOR UPDATE');
        $existing->execute([':idPaciente' => $idPaciente]);
        if ($existing->fetchColumn() !== false) {
            $this->bd->commit();
            return ['resultado' => 200, 'mensaje' => '¡El módulo ya estaba finalizado!'];
        }

        $insert = $this->bd->prepare('INSERT INTO pac_historia_clinica_finalizar (id_modulo, id_paciente) VALUES (7, :idPaciente)');
        $insert->execute([':idPaciente' => $idPaciente]);
        $this->bd->commit();
        return ['resultado' => 200, 'mensaje' => '¡Se finalizó el módulo correctamente!'];
    } catch (\Throwable $exception) {
        if ($this->bd->inTransaction()) $this->bd->rollBack();
        return ['resultado' => 500, 'mensaje' => 'No se pudo finalizar el módulo.'];
    }
    }

}
