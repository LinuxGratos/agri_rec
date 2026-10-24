<?php
// Fonctions communes au registre phytosanitaire (saisie, écran, impression).
require_once 'listes.php';

// Informations de l'exploitation (raison sociale, SIRET, n° PACAGE)
function exploitation($db) {
    return $db->querySingle('SELECT entity, siret, telepac FROM users ORDER BY id LIMIT 1', true);
}

// Garde uniquement les chiffres d'un SIRET saisi avec des espaces
function nettoyer_siret($texte) {
    return preg_replace('/[^0-9]/', '', (string) $texte);
}

function siret_valide($siret) {
    return preg_match('/^[0-9]{14}$/', $siret) === 1;
}

// Un numéro d'AMM est composé de 7 chiffres
function amm_valide($amm) {
    return preg_match('/^[0-9]{7}$/', $amm) === 1;
}

// Libellé de "réalisé par", avec le nom et le SIRET de l'intervenant
function libelle_realise_par($intervention) {
    $libelles = liste_realise_par();
    $texte = $libelles[$intervention['realise_par']] ?? $intervention['realise_par'];
    if ($intervention['realise_par'] !== 'exploitation') {
        $texte .= ' : ' . $intervention['intervenant_nom'] . ' (SIRET ' . $intervention['intervenant_siret'] . ')';
    }
    return $texte;
}

// Dose par hectare, arrondie
function dose_par_ha($quantite, $surface) {
    return $surface > 0 ? round($quantite / $surface, 3) : '';
}

// Lignes du registre : une ligne par produit utilisé, interventions annulées exclues.
// $annee et $parcelle sont facultatifs (null = tout).
function lignes_registre_phyto($db, $annee = null, $parcelle = null) {
    $sql = "SELECT ip.id AS intervention_id, ip.annee_culturale, ip.parcelle_id,
                   p.nom AS parcelle_nom, ip.ilot, p.latitude, p.longitude,
                   ip.culture, ip.surface_traitee,
                   SUBSTR(ip.date, 1, 10) AS date, SUBSTR(ip.date, 12, 5) AS heure_debut, ip.heure_fin,
                   ip.stade_bbch, ip.stade, ip.realise_par, ip.intervenant_nom, ip.intervenant_siret,
                   dip.produit_nom, dip.amm, dip.type, dip.bio, dip.delai_rentree,
                   dip.volume_total AS quantite, dip.unite, dip.cible
            FROM interventions_phytosanitaires ip
            JOIN parcelles p ON p.id = ip.parcelle_id
            JOIN details_interventions_phytosanitaires dip ON dip.intervention_id = ip.id
            WHERE ip.annule_le IS NULL";
    if ($annee) {
        $sql .= " AND ip.annee_culturale = :annee";
    }
    if ($parcelle) {
        $sql .= " AND ip.parcelle_id = :parcelle";
    }
    $sql .= " ORDER BY ip.annee_culturale, p.nom, ip.date, dip.id";

    $stmt = $db->prepare($sql);
    if ($annee) {
        $stmt->bindValue(':annee', intval($annee), SQLITE3_INTEGER);
    }
    if ($parcelle) {
        $stmt->bindValue(':parcelle', intval($parcelle), SQLITE3_INTEGER);
    }
    $resultat = $stmt->execute();

    $lignes = [];
    while ($ligne = $resultat->fetchArray(SQLITE3_ASSOC)) {
        $ligne['dose_par_ha'] = dose_par_ha($ligne['quantite'], $ligne['surface_traitee']);
        $lignes[] = $ligne;
    }
    return $lignes;
}

// Regroupe les lignes du registre par campagne, parcelle et culture (pour l'affichage)
function registre_par_parcelle($lignes) {
    $groupes = [];
    foreach ($lignes as $ligne) {
        $cle = $ligne['annee_culturale'] . '_' . $ligne['parcelle_id'] . '_' . $ligne['culture'];
        if (!isset($groupes[$cle])) {
            $groupes[$cle] = [
                'annee_culturale' => $ligne['annee_culturale'],
                'parcelle_nom' => $ligne['parcelle_nom'],
                'ilot' => $ligne['ilot'],
                'latitude' => $ligne['latitude'],
                'longitude' => $ligne['longitude'],
                'culture' => $ligne['culture'],
                'lignes' => [],
            ];
        }
        $groupes[$cle]['lignes'][] = $ligne;
    }
    return $groupes;
}

// Semis de semences traitées, annulations exclues
function semis_traites($db, $annee = null, $parcelle = null) {
    $sql = "SELECT s.*, p.nom AS parcelle_nom
            FROM semis_semences_traitees s
            JOIN parcelles p ON p.id = s.parcelle_id
            WHERE s.annule_le IS NULL";
    if ($annee) {
        $sql .= " AND s.annee_culturale = :annee";
    }
    if ($parcelle) {
        $sql .= " AND s.parcelle_id = :parcelle";
    }
    $sql .= " ORDER BY s.annee_culturale, s.date";

    $stmt = $db->prepare($sql);
    if ($annee) {
        $stmt->bindValue(':annee', intval($annee), SQLITE3_INTEGER);
    }
    if ($parcelle) {
        $stmt->bindValue(':parcelle', intval($parcelle), SQLITE3_INTEGER);
    }
    $resultat = $stmt->execute();

    $semis = [];
    while ($ligne = $resultat->fetchArray(SQLITE3_ASSOC)) {
        $semis[] = $ligne;
    }
    return $semis;
}
?>
