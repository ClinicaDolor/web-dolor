-- M04: durable explicit negative declaration.
-- PRECONDITION: verify the effective database and the existing columns before
-- applying this file. This file is preparation only and has not been run.
--
-- The table stores the patient's declaration state; it is not a procedure
-- table and must never receive a fabricated surgery row.

CREATE TABLE IF NOT EXISTS pc_antecedentes_quirurgicos_estado (
  id INT NOT NULL AUTO_INCREMENT,
  id_paciente INT NOT NULL,
  id_clinica INT NOT NULL,
  estado VARCHAR(32) NOT NULL,
  origen VARCHAR(32) NOT NULL,
  version BIGINT UNSIGNED NOT NULL DEFAULT 1,
  declarado_en DATETIME NOT NULL,
  actualizado_en DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_m04_negative_patient_clinic (id_paciente, id_clinica),
  KEY idx_m04_negative_clinic (id_clinica),
  CONSTRAINT m04_negative_patient_fk FOREIGN KEY (id_paciente) REFERENCES pc_paciente (id),
  CONSTRAINT m04_negative_clinic_fk FOREIGN KEY (id_clinica) REFERENCES clinicas (id),
  CONSTRAINT m04_negative_state_ck CHECK (estado IN ('NEGATIVE_DECLARED', 'SUPERSEDED_BY_SURGERY'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ROLLBACK (manual and only after an owner-approved read-only check confirms
-- that the table is empty and no application release depends on it):
-- DROP TABLE pc_antecedentes_quirurgicos_estado;
