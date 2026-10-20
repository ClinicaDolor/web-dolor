<?php

declare(strict_types=1);

/** Pure snapshot-shape checks shared by the integration guard and static tests. */
function m07SnapshotInventoryHasExactTables(mixed $inventory, array $expectedTables): bool
{
    if (!is_array($inventory) || $expectedTables === []) {
        return false;
    }

    $actualTables = array_keys($inventory);
    if (array_filter($actualTables, static fn(mixed $table): bool => !is_string($table)) !== []) {
        return false;
    }

    sort($actualTables, SORT_STRING);
    $expected = $expectedTables;
    sort($expected, SORT_STRING);

    return $actualTables === $expected;
}

/** Confirm the explicitly retained synthetic IDs belong to the expected patient. */
function m07RowsContainExpectedIdsForPatient(mixed $tableRows, array $expectedIdsByTable, int $patientId): bool
{
    if (!is_array($tableRows) || $patientId < 1) {
        return false;
    }

    foreach ($expectedIdsByTable as $table => $expectedIds) {
        if (!is_string($table) || !is_array($expectedIds) || !isset($tableRows[$table]) || !is_array($tableRows[$table])) {
            return false;
        }
        $found = [];
        foreach ($tableRows[$table] as $row) {
            if (!is_array($row) || !isset($row['id'])) {
                return false;
            }
            $id = (int)$row['id'];
            if (in_array($id, $expectedIds, true)) {
                if ((int)($row['id_paciente'] ?? 0) !== $patientId || isset($found[$id])) {
                    return false;
                }
                $found[$id] = true;
            }
        }
        foreach ($expectedIds as $id) {
            if (!isset($found[(int)$id])) {
                return false;
            }
        }
    }

    return true;
}
