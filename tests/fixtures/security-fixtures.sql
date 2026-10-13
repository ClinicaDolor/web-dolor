-- Fixtures sintéticos para auditoría local. No contiene datos de pacientes reales.
-- IDs reservados para pruebas: clínicas 9101-9102, médicos 9201-9202,
-- pacientes 9301-9302, recetas 9401-9402 y registros de expediente 9501-9513.

SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE clinicas (
  id INT NOT NULL,
  nombre VARCHAR(100) NOT NULL,
  direccion TEXT NULL,
  telefono VARCHAR(15) NULL,
  email VARCHAR(100) NULL,
  horario TEXT NOT NULL,
  horario_apertura TIME NULL,
  horario_cierre TIME NULL,
  sitio_web VARCHAR(255) NULL,
  fecha_creacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  fecha_actualizacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  estatus INT NOT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE doctores (
  id INT NOT NULL,
  clinica_id INT NOT NULL,
  nombre TEXT NOT NULL,
  especialidad TEXT NULL,
  telefono TEXT NULL,
  email TEXT NOT NULL,
  fecha_creacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  fecha_actualizacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY clinica_id (clinica_id),
  CONSTRAINT doctores_fixture_clinica_fk FOREIGN KEY (clinica_id) REFERENCES clinicas (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE accesos_doctores (
  id INT NOT NULL AUTO_INCREMENT,
  doctor_id INT NOT NULL,
  usuario VARCHAR(50) NOT NULL,
  contraseña VARCHAR(255) NOT NULL,
  ultimo_acceso TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  fecha_creacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  fecha_actualizacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY doctor_id (doctor_id),
  CONSTRAINT accesos_doctores_fixture_doctor_fk FOREIGN KEY (doctor_id) REFERENCES doctores (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE pc_paciente (
  id INT NOT NULL AUTO_INCREMENT,
  id_clinica INT NOT NULL,
  fecha_alta TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  nombres TEXT NOT NULL,
  apellido_paterno TEXT NOT NULL,
  apellido_materno TEXT NOT NULL,
  edad INT NOT NULL,
  sexo VARCHAR(1) NOT NULL,
  fecha_nacimiento DATE NOT NULL,
  estado_civil TEXT NOT NULL,
  curp TEXT NOT NULL,
  lugar_origen TEXT NOT NULL,
  lugar_residencia TEXT NOT NULL,
  ocupacion TEXT NOT NULL,
  num_hijos INT NOT NULL,
  edad_hijos TEXT NOT NULL,
  medico_tratante TEXT NOT NULL,
  medico_refiere TEXT NOT NULL,
  diagnostico_inicial TEXT NOT NULL,
  calle TEXT NOT NULL,
  num_interior TEXT NOT NULL,
  num_exterior TEXT NOT NULL,
  colonia TEXT NOT NULL,
  delegacion TEXT NOT NULL,
  cp TEXT NOT NULL,
  municipio TEXT NOT NULL,
  estado TEXT NOT NULL,
  distancia TEXT NOT NULL,
  email TEXT NOT NULL,
  telefono TEXT NOT NULL,
  celular TEXT NOT NULL,
  res_nombre TEXT NOT NULL,
  res_telefono TEXT NOT NULL,
  cuidador TEXT NOT NULL,
  cuidador_telefono TEXT NOT NULL,
  aseguradora TEXT NOT NULL,
  informe_aseguradora VARCHAR(2) NOT NULL,
  hoja_membretada VARCHAR(2) NOT NULL,
  formato_aseguradora VARCHAR(2) NOT NULL,
  honorarios VARCHAR(2) NOT NULL,
  observaciones TEXT NOT NULL,
  motivo_atencion TEXT NOT NULL,
  quien_recomienda TEXT NOT NULL,
  redes_sociales TEXT NOT NULL,
  ultima_actualizacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  status INT NOT NULL,
  PRIMARY KEY (id),
  KEY id_clinica (id_clinica),
  CONSTRAINT pc_paciente_fixture_clinica_fk FOREIGN KEY (id_clinica) REFERENCES clinicas (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE pc_paciente_acceso (
  id INT NOT NULL AUTO_INCREMENT,
  paciente_id INT NOT NULL,
  pin TEXT NOT NULL,
  fecha_creacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  fecha_expiracion DATETIME NOT NULL,
  estatus INT NOT NULL,
  PRIMARY KEY (id),
  KEY paciente_id (paciente_id),
  CONSTRAINT pc_acceso_fixture_paciente_fk FOREIGN KEY (paciente_id) REFERENCES pc_paciente (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Esquemas auxiliares verificados contra la base local de desarrollo.
-- Los registros que se insertan más adelante son sintéticos y no representan
-- expedientes reales ni archivos clínicos reales.
CREATE TABLE nota_subsecuente (
  id INT NOT NULL AUTO_INCREMENT,
  fecha_hora DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  id_paciente INT NOT NULL,
  contenido TEXT NOT NULL,
  ta TEXT NOT NULL,
  frec_cardiaca TEXT NOT NULL,
  pulso TEXT NOT NULL,
  spo2 TEXT NOT NULL,
  fio2 TEXT NOT NULL,
  ecog TEXT NOT NULL,
  karnovsky TEXT NOT NULL,
  peso TEXT NOT NULL,
  talla TEXT NOT NULL,
  codigo_referencia VARCHAR(200) NOT NULL,
  proxima_cita DATE NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE laboratorio (
  id INT NOT NULL AUTO_INCREMENT,
  fecha_hora TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  id_paciente INT NOT NULL,
  nombre TEXT NOT NULL,
  titulo TEXT NOT NULL,
  ruta VARCHAR(255) NOT NULL,
  tipo VARCHAR(100) NOT NULL,
  tamano INT NOT NULL,
  descripcion TEXT NOT NULL,
  codigo_referencia VARCHAR(200) NOT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE cofepris (
  id INT NOT NULL AUTO_INCREMENT,
  fecha_hora TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  id_paciente INT NOT NULL,
  carpeta TEXT NOT NULL,
  folio VARCHAR(20) NOT NULL,
  diagnostico TEXT NOT NULL,
  medicamento TEXT NOT NULL,
  num_cajas TEXT NOT NULL,
  presentacion VARCHAR(100) NOT NULL,
  dosificacion TEXT NOT NULL,
  num_dias VARCHAR(100) NOT NULL,
  via_administracion TEXT NOT NULL,
  surtido INT NOT NULL,
  farmacia DOUBLE NOT NULL,
  paciente DOUBLE NOT NULL,
  medico DOUBLE NOT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE receta_medica (
  id INT NOT NULL,
  fecha_hora TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  id_paciente INT NOT NULL,
  diagnostico TEXT NOT NULL,
  medicamento TEXT NOT NULL,
  ta TEXT NOT NULL,
  fc DOUBLE NOT NULL,
  spo2 DOUBLE NOT NULL,
  temperatura DOUBLE NOT NULL,
  codigo_referencia VARCHAR(200) NOT NULL,
  PRIMARY KEY (id),
  KEY id_paciente (id_paciente)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Contrato mínimo de las vistas de recetas y COFEPRIS del portal médico.
-- Estas tablas no agregan datos clínicos; permiten reproducir sus estados
-- vacíos y las consultas existentes sin modificar el esquema productivo.
CREATE TABLE receta_medica_externos (
  id INT NOT NULL AUTO_INCREMENT,
  fecha_hora TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  nombre TEXT NOT NULL,
  apellido_paterno TEXT NOT NULL,
  apellido_materno TEXT NULL,
  edad INT NULL,
  sexo VARCHAR(1) NULL,
  fecha_nacimiento DATE NULL,
  diagnostico TEXT NULL,
  medicamento TEXT NULL,
  ta TEXT NULL,
  fc DOUBLE NULL,
  spo2 DOUBLE NULL,
  temperatura DOUBLE NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE cofepris_folio (
  id INT NOT NULL AUTO_INCREMENT,
  fecha_hora TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  folio_inicial INT NOT NULL,
  folio_final INT NOT NULL,
  carpeta VARCHAR(50) NOT NULL,
  total_folio INT NOT NULL,
  estado INT NOT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE historia_clinica_modulos (
  id INT NOT NULL,
  nombre TEXT NOT NULL,
  imagen TEXT NOT NULL,
  url VARCHAR(100) NOT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE pac_historia_clinica_finalizar (
  id INT NOT NULL AUTO_INCREMENT,
  id_modulo INT NOT NULL,
  id_paciente INT NOT NULL,
  fecha_hora TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY id_modulo (id_modulo),
  KEY id_paciente (id_paciente),
  CONSTRAINT pac_finalizar_fixture_modulo_fk FOREIGN KEY (id_modulo) REFERENCES historia_clinica_modulos (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT pac_finalizar_fixture_paciente_fk FOREIGN KEY (id_paciente) REFERENCES pc_paciente (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE pac_historia_clinica_comentario (
  id INT NOT NULL AUTO_INCREMENT,
  id_modulo INT NOT NULL,
  id_paciente INT NOT NULL,
  comentario TEXT NOT NULL,
  fecha_hora TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY id_modulo (id_modulo),
  KEY id_paciente (id_paciente),
  CONSTRAINT pac_comentario_fixture_modulo_fk FOREIGN KEY (id_modulo) REFERENCES historia_clinica_modulos (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT pac_comentario_fixture_paciente_fk FOREIGN KEY (id_paciente) REFERENCES pc_paciente (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tablas mínimas requeridas por PacienteModulosModelo para calcular
-- porcentajeModulo1()..porcentajeModulo9(). Se mantienen vacías salvo por
-- los registros sintéticos explícitos al final de este fixture.
CREATE TABLE pc_antecedentes_familiares (
  id INT NOT NULL AUTO_INCREMENT,
  id_paciente INT NOT NULL,
  enfermedad VARCHAR(150) NOT NULL,
  tipo VARCHAR(100) NULL,
  detalle VARCHAR(20) NOT NULL,
  especificar TEXT NULL,
  PRIMARY KEY (id),
  KEY id_paciente (id_paciente)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE pac_temas_modulo_3 (
  id INT NOT NULL,
  tema VARCHAR(255) NOT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE pac_preguntas_modulo_3 (
  id INT NOT NULL,
  id_tema INT NOT NULL,
  pregunta TEXT NOT NULL,
  tipo VARCHAR(50) NOT NULL,
  PRIMARY KEY (id),
  KEY id_tema (id_tema)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE pac_respuestas_paciente_modulo_3 (
  id INT NOT NULL AUTO_INCREMENT,
  id_pregunta INT NOT NULL,
  id_paciente INT NOT NULL,
  respuesta TEXT NULL,
  PRIMARY KEY (id),
  KEY id_pregunta (id_pregunta),
  KEY id_paciente (id_paciente)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE pc_antecedentes_quirurgicos (
  id INT NOT NULL AUTO_INCREMENT,
  id_paciente INT NOT NULL,
  fecha DATE NULL,
  cirugia TEXT NULL,
  observaciones TEXT NULL,
  PRIMARY KEY (id),
  KEY id_paciente (id_paciente)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE pac_temas_modulo_5 (
  id INT NOT NULL,
  tema VARCHAR(255) NOT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE pac_preguntas_modulo_5 (
  id INT NOT NULL,
  id_tema INT NOT NULL,
  tipo VARCHAR(50) NOT NULL,
  PRIMARY KEY (id),
  KEY id_tema (id_tema)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE pac_respuestas_paciente_modulo_5 (
  id INT NOT NULL AUTO_INCREMENT,
  id_pregunta INT NOT NULL,
  id_paciente INT NOT NULL,
  respuesta TEXT NULL,
  tipo VARCHAR(100) NULL,
  year_diagnostico VARCHAR(10) NULL,
  PRIMARY KEY (id),
  KEY id_pregunta (id_pregunta),
  KEY id_paciente (id_paciente)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE pac_enfermedades_modulo_5 (
  id INT NOT NULL,
  descripcion VARCHAR(255) NOT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE pac_enfermedades_respuestas_modulo_5 (
  id INT NOT NULL AUTO_INCREMENT,
  id_enfermedad INT NOT NULL,
  id_paciente INT NOT NULL,
  respuesta VARCHAR(20) NULL,
  tipo VARCHAR(100) NULL,
  year_diagnostico VARCHAR(10) NULL,
  PRIMARY KEY (id),
  KEY id_enfermedad (id_enfermedad),
  KEY id_paciente (id_paciente)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE pac_medicacion_actual_modulo_6 (
  id INT NOT NULL AUTO_INCREMENT,
  id_paciente INT NOT NULL,
  medicamento TEXT NULL,
  descripcion TEXT NULL,
  tiempo_uso TEXT NULL,
  dosis TEXT NULL,
  medico TEXT NULL,
  PRIMARY KEY (id),
  KEY id_paciente (id_paciente)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE pac_temas_modulo_7 (
  id INT NOT NULL,
  tema VARCHAR(255) NOT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE pac_preguntas_modulo_7 (
  id INT NOT NULL,
  id_tema INT NOT NULL,
  PRIMARY KEY (id),
  KEY id_tema (id_tema)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE pac_respuestas_paciente_modulo_7 (
  id INT NOT NULL AUTO_INCREMENT,
  id_pregunta INT NOT NULL,
  id_paciente INT NOT NULL,
  respuesta VARCHAR(20) NULL,
  dosis TEXT NULL,
  resultados TEXT NULL,
  consumo TEXT NULL,
  descripcion TEXT NULL,
  PRIMARY KEY (id),
  KEY id_pregunta (id_pregunta),
  KEY id_paciente (id_paciente)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE pac_procedimientos_dolor_modulo_8 (
  id INT NOT NULL AUTO_INCREMENT,
  id_paciente INT NOT NULL,
  procedimiento TEXT NULL,
  fecha DATE NULL,
  resultados TEXT NULL,
  descripcion TEXT NULL,
  PRIMARY KEY (id),
  KEY id_paciente (id_paciente)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE pac_tratamientos_dolor_modulo_8 (
  id INT NOT NULL,
  tratamiento TEXT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE pac_respuestas_paciente_modulo_8 (
  id INT NOT NULL AUTO_INCREMENT,
  id_tratamiento INT NOT NULL,
  id_paciente INT NOT NULL,
  utilizo VARCHAR(20) NULL,
  resultado TEXT NULL,
  descripcion TEXT NULL,
  PRIMARY KEY (id),
  KEY id_tratamiento (id_tratamiento),
  KEY id_paciente (id_paciente)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE pc_paciente_evaluacion_dolor (
  id INT NOT NULL AUTO_INCREMENT,
  id_paciente INT NOT NULL,
  dolor VARCHAR(20) NULL,
  tiempo_dolor TEXT NULL,
  descripcion TEXT NULL,
  incremento TEXT NULL,
  PRIMARY KEY (id),
  KEY id_paciente (id_paciente)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO clinicas (id, nombre, horario, estatus) VALUES
  (9101, 'Clínica sintética A', '00:00-23:59', 1),
  (9102, 'Clínica sintética B', '00:00-23:59', 1);

INSERT INTO doctores (id, clinica_id, nombre, especialidad, email) VALUES
  (9201, 9101, 'Doctor sintético A', 'Dolor', 'doctor-a@example.invalid'),
  (9202, 9102, 'Doctor sintético B', 'Dolor', 'doctor-b@example.invalid');

-- Credenciales sintéticas únicamente para pruebas locales de autenticación.
-- Contraseña de ambos usuarios: DoctorSynth-2026!
INSERT INTO accesos_doctores (doctor_id, usuario, contraseña, activo) VALUES
  (9201, 'doctor-a-sintetico', '$2y$12$Z6EVgn0/kQi93WsqcRv9Iep4g98xgFEVrlUUwLZcDa1f1bTBlZDOi', 1),
  (9202, 'doctor-b-sintetico', '$2y$12$Z6EVgn0/kQi93WsqcRv9Iep4g98xgFEVrlUUwLZcDa1f1bTBlZDOi', 1);

INSERT INTO pc_paciente (
  id, id_clinica, nombres, apellido_paterno, apellido_materno, edad, sexo,
  fecha_nacimiento, estado_civil, curp, lugar_origen, lugar_residencia,
  ocupacion, num_hijos, edad_hijos, medico_tratante, medico_refiere,
  diagnostico_inicial, calle, num_interior, num_exterior, colonia, delegacion,
  cp, municipio, estado, distancia, email, telefono, celular, res_nombre,
  res_telefono, cuidador, cuidador_telefono, aseguradora, informe_aseguradora,
  hoja_membretada, formato_aseguradora, honorarios, observaciones,
  motivo_atencion, quien_recomienda, redes_sociales, status
) VALUES
  (9301, 9101, 'Paciente sintético A', 'Auditoría', 'Local', 40, 'F', '1986-01-01',
   'Soltero', 'CURP-SINTETICA-A', 'Local', 'Local', 'Prueba', 0, '0', '', '',
   'Prueba controlada', 'Calle local', '0', '1', 'Colonia local', 'Delegación local',
   '00000', 'Municipio local', 'Estado local', '0', 'patient-a@example.invalid',
   '0000000000', '0000000000', 'Responsable sintético A', '0000000000',
   'Cuidador sintético A', '0000000000', '', 'N', 'N', 'N', 'N', '',
   'Prueba de autorización', 'Fixture', 'N/A', 1),
  (9302, 9102, 'Paciente sintético B', 'Auditoría', 'Local', 41, 'M', '1985-01-01',
   'Soltero', 'CURP-SINTETICA-B', 'Local', 'Local', 'Prueba', 0, '0', '', '',
   'Prueba controlada', 'Calle local', '0', '2', 'Colonia local', 'Delegación local',
   '00000', 'Municipio local', 'Estado local', '0', 'patient-b@example.invalid',
   '0000000000', '0000000000', 'Responsable sintético B', '0000000000',
   'Cuidador sintético B', '0000000000', '', 'N', 'N', 'N', 'N', '',
   'Prueba de autorización', 'Fixture', 'N/A', 1);

-- PIN sintético únicamente para pruebas locales. PIN de ambos pacientes: PIN-Synth-2026
INSERT INTO pc_paciente_acceso (paciente_id, pin, fecha_expiracion, estatus) VALUES
  (9301, '$2y$12$r4O/rwfN05yHCDsVqHn6RuvtN.QHhd5bneLHZcpud7ED0wWd97ymK', '2030-12-31 23:59:59', 0),
  (9302, '$2y$12$r4O/rwfN05yHCDsVqHn6RuvtN.QHhd5bneLHZcpud7ED0wWd97ymK', '2030-12-31 23:59:59', 0);

INSERT INTO receta_medica (
  id, id_paciente, diagnostico, medicamento, ta, fc, spo2, temperatura, codigo_referencia
) VALUES
  (9401, 9301, 'Diagnóstico sintético A', 'Medicamento sintético A', '120/80', 70, 98, 36.5, 'REF-FIXTURE-A'),
  (9402, 9302, 'Diagnóstico sintético B', 'Medicamento sintético B', '110/70', 72, 97, 36.7, 'REF-FIXTURE-B');

INSERT INTO nota_subsecuente (
  id, id_paciente, contenido, ta, frec_cardiaca, pulso, spo2, fio2,
  ecog, karnovsky, peso, talla, codigo_referencia, proxima_cita
) VALUES (
  9501, 9301, 'Nota subsecuente sintética para auditoría local.', '120/80',
  '70', '70', '98', '21', '0', '100', '70', '1.70', 'REF-FIXTURE-A', '2030-01-15'
);

INSERT INTO laboratorio (
  id, id_paciente, nombre, titulo, ruta, tipo, tamano, descripcion, codigo_referencia
) VALUES (
  9502, 9301, 'resultado-sintetico.txt', 'Resultado sintético de auditoría',
  'laboratorio', 'text/plain', 0, 'Archivo no clínico; no se incluye descarga en el fixture.', 'REF-FIXTURE-A'
), (
  9511, 9301, 'resultado-sintetico-a.pdf', 'Resultado PDF sintético A',
  'laboratorio', 'pdf', 6863, 'Documento sintético sin datos personales reales.', 'REF-FIXTURE-A'
), (
  9512, 9302, 'resultado-sintetico-b.pdf', 'Resultado PDF sintético B',
  'laboratorio', 'pdf', 6861, 'Documento sintético sin datos personales reales.', 'REF-FIXTURE-B'
);

INSERT INTO cofepris (
  id, id_paciente, carpeta, folio, diagnostico, medicamento, num_cajas,
  presentacion, dosificacion, num_dias, via_administracion, surtido,
  farmacia, paciente, medico
) VALUES (
  9503, 9301, 'CARPETA-SINTETICA', 'FOLIO-9503', 'Diagnóstico sintético',
  'Medicamento sintético', '1', 'Presentación sintética', 'Según indicación sintética',
  '7', 'Oral', 0, 0, 0, 0
);

INSERT INTO cofepris (
  id, id_paciente, carpeta, folio, diagnostico, medicamento, num_cajas,
  presentacion, dosificacion, num_dias, via_administracion, surtido,
  farmacia, paciente, medico
) VALUES (
  9506, 9302, 'CARPETA-SINTETICA-B', 'FOLIO-9506', 'Diagnóstico sintético B',
  'Medicamento sintético B', '2', 'Presentación sintética B', 'Según indicación sintética B',
  '5', 'Oral', 0, 0, 0, 0
);

INSERT INTO historia_clinica_modulos (id, nombre, imagen, url) VALUES
  (1, 'Ficha de identificación del paciente', 'iconos/paciente.png', 'ficha-identificiacion'),
  (2, 'Antecedentes familiares', 'iconos/punto2.png', 'antecedentes-familiares'),
  (3, 'Antecedentes Personales No Patológicos', 'iconos/punto3.png', 'antecedentes-personales-no-patologicos'),
  (4, 'Antecedentes Personales Quirúrgicos', 'iconos/punto4.png', 'antecedentes-personales-quirurgicos'),
  (5, 'Antecedentes Personales Patológicos', 'iconos/punto5.png', 'antecedentes-personales-patologicos'),
  (6, 'Medicación Actual', 'iconos/punto6.png', 'medicacion-actual'),
  (7, 'Medicamentos que ha utilizado para controlar el dolor', 'iconos/punto7.png', 'medicacion-control-dolor'),
  (8, 'Procedimientos que ha utilizado para controlar el dolor', 'iconos/punto8.png', 'procedimientos-control-dolor'),
  (9, 'Evaluación del dolor', 'iconos/punto9.png', 'evaluacion-dolor');

-- Estados sintéticos para hacer visible la regla existente: módulos 1 y 2
-- finalizados; los demás conservan el estado derivado de sus porcentajes.
INSERT INTO pac_historia_clinica_finalizar (id_modulo, id_paciente) VALUES
  (1, 9301),
  (2, 9301);

-- Un registro válido y sintético para comprobar el cálculo del módulo 2.
INSERT INTO pc_antecedentes_familiares
  (id_paciente, enfermedad, tipo, detalle, especificar)
VALUES
  (9301, 'Antecedente sintético', 'Familiar', 'No', NULL);

SET FOREIGN_KEY_CHECKS = 1;
