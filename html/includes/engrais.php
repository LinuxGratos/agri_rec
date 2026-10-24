<?php
// Lecture des interventions d'engrais, commune à rapport-engrais.php (écran)
// et rapport-engrais-brut.php (impression).
// Renvoie [liste des interventions, totaux par parcelle en unités/ha].
function lire_interventions_engrais($db, $annee = null, $parcelle = null) {
    $query = "
        SELECT
            ie.id AS intervention_id,
            STRFTIME('%d/%m/%Y', ie.date) AS date,
            ie.annee_culturale,
            p.id AS parcelle_id,
            p.nom AS parcelle_nom,
            p.ilot AS parcelle_ilot,
            p.culture AS type_culture,
            p.surface AS parcelle_surface,
            e.nom AS engrais_nom,
            e.unite AS engrais_unite,
            ie.quantite,
            round((ie.quantite / p.surface), 2) AS quantite_par_ha,
            round((ie.quantite * (e.NO3 / 100) / p.surface), 2)  AS total_NO3,
            round((ie.quantite * (e.P2O5 / 100) / p.surface), 2) AS total_P2O5,
            round((ie.quantite * (e.K2O / 100) / p.surface), 2) AS total_K2O,
            round((ie.quantite * (e.SO3 / 100) / p.surface), 2) AS total_SO3,
            round((ie.quantite * (e.MgO / 100) / p.surface), 2) AS total_MgO,
            round((ie.quantite * (e.CaO / 100) / p.surface), 2) AS total_CaO
        FROM interventions_engrais ie
        JOIN parcelles p ON ie.parcelle_id = p.id
        JOIN engrais e ON ie.engrais_id = e.id
        WHERE 1 = 1
        " . ($annee ? "AND ie.annee_culturale = :annee_culturale " : "") . "
        " . ($parcelle ? "AND p.id = :parcelle_id " : "") . "
        ORDER BY p.nom, ie.annee_culturale, ie.date
    ";

    $stmt = $db->prepare($query);
    if ($annee) {
        $stmt->bindValue(':annee_culturale', intval($annee), SQLITE3_INTEGER);
    }
    if ($parcelle) {
        $stmt->bindValue(':parcelle_id', intval($parcelle), SQLITE3_INTEGER);
    }
    $result = $stmt->execute();

    $interventions = [];
    $totals = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $interventions[] = $row;

        $key = $row['parcelle_nom'];
        if (!isset($totals[$key])) {
            $totals[$key] = [
                'parcelle_nom' => $row['parcelle_nom'],
                'surface' => $row['parcelle_surface'],
                'NO3' => 0, 'P2O5' => 0, 'K2O' => 0,
                'SO3' => 0, 'MgO' => 0, 'CaO' => 0
            ];
        }
        foreach (['NO3', 'P2O5', 'K2O', 'SO3', 'MgO', 'CaO'] as $element) {
            $totals[$key][$element] += $row['total_' . $element];
        }
    }
    return [$interventions, $totals];
}
?>
