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
                   dip.volume_total AS quantite, dip.unite, dip.cible, ip.saisi_le
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

// Colonnes du fichier exporté (CSV et JSON) => champ correspondant du registre.
// Pour ajouter une colonne à l'export, il suffit d'ajouter une ligne ici.
function colonnes_export_traitements() {
    return [
        'siret_exploitation' => 'siret_exploitation',
        'pacage' => 'pacage',
        'id_intervention' => 'intervention_id',
        'campagne' => 'annee_culturale',
        'date' => 'date',
        'heure_debut' => 'heure_debut',
        'heure_fin' => 'heure_fin',
        'parcelle' => 'parcelle_nom',
        'ilot_pac' => 'ilot',
        'latitude' => 'latitude',
        'longitude' => 'longitude',
        'culture' => 'culture',
        'surface_traitee_ha' => 'surface_traitee',
        'stade_bbch' => 'stade_bbch',
        'stade' => 'stade',
        'nom_commercial' => 'produit_nom',
        'numero_amm' => 'amm',
        'type' => 'type',
        'utilisable_ab' => 'utilisable_ab',
        'quantite' => 'quantite',
        'unite' => 'unite',
        'dose_par_ha' => 'dose_par_ha',
        'unite_dose' => 'unite_dose',
        'cible' => 'cible',
        'delai_rentree_heures' => 'delai_rentree',
        'realise_par' => 'realise_par',
        'intervenant_nom' => 'intervenant_nom',
        'intervenant_siret' => 'intervenant_siret',
        'saisi_le' => 'saisi_le',
    ];
}

function colonnes_export_semis() {
    return [
        'siret_exploitation' => 'siret_exploitation',
        'pacage' => 'pacage',
        'id_semis' => 'id',
        'campagne' => 'annee_culturale',
        'date' => 'date',
        'parcelle' => 'parcelle_nom',
        'ilot_pac' => 'ilot',
        'culture' => 'culture',
        'surface_ha' => 'surface',
        'produit_traitement_semences' => 'produit_nom',
        'numero_amm' => 'amm',
        'quantite_semences' => 'quantite_semences',
        'unite_semences' => 'unite_semences',
        'saisi_le' => 'saisi_le',
    ];
}

// Met des lignes du registre au format d'export, selon la liste de colonnes choisie
function preparer_export($exploitation, $lignes, $colonnes) {
    $export = [];
    foreach ($lignes as $ligne) {
        $ligne['siret_exploitation'] = $exploitation['siret'];
        $ligne['pacage'] = $exploitation['telepac'];
        $ligne['utilisable_ab'] = !empty($ligne['bio']) ? 'oui' : 'non';
        $ligne['unite_dose'] = isset($ligne['unite']) ? $ligne['unite'] . '/ha' : '';

        $sortie = [];
        foreach ($colonnes as $colonne => $champ) {
            $sortie[$colonne] = $ligne[$champ] ?? null;
        }
        $export[] = $sortie;
    }
    return $export;
}

// Contrôle des informations obligatoires avant export.
// Renvoie la liste des points à corriger (vide si tout est complet).
function controles_registre($exploitation, $lignes, $semis) {
    $problemes = [];
    if (!siret_valide($exploitation['siret'])) {
        $problemes[] = "Le SIRET de l'exploitation est manquant ou invalide (page Exploitation).";
    }
    if (trim($exploitation['telepac']) === '') {
        $problemes[] = "Le n° PACAGE de l'exploitation est manquant (page Exploitation).";
    }
    foreach ($lignes as $l) {
        $quoi = 'Traitement du ' . date('d/m/Y', strtotime($l['date'])) . ' sur ' . $l['parcelle_nom'] . ' (' . $l['produit_nom'] . ')';
        if (!amm_valide((string) $l['amm'])) {
            $problemes[] = "$quoi : n° d'AMM « " . $l['amm'] . " » invalide (7 chiffres attendus).";
        }
        if (trim((string) $l['culture']) === '') {
            $problemes[] = "$quoi : culture manquante.";
        }
        if (!($l['surface_traitee'] > 0)) {
            $problemes[] = "$quoi : surface traitée manquante.";
        }
        if (trim((string) $l['ilot']) === '' && $l['latitude'] === null) {
            $problemes[] = "$quoi : ni îlot PAC ni coordonnées GPS pour la parcelle.";
        }
    }
    foreach ($semis as $s) {
        if (!amm_valide((string) $s['amm'])) {
            $problemes[] = 'Semis du ' . date('d/m/Y', strtotime($s['date'])) . ' sur ' . $s['parcelle_nom'] . " : n° d'AMM invalide.";
        }
    }
    return $problemes;
}

// Quantités totales utilisées par produit et par campagne (interventions annulées exclues)
function stock_par_annee($db, $annee = null) {
    $sql = "SELECT ip.annee_culturale, dip.produit_nom, dip.amm, dip.unite, SUM(dip.volume_total) AS quantite
            FROM details_interventions_phytosanitaires dip
            JOIN interventions_phytosanitaires ip ON ip.id = dip.intervention_id
            WHERE ip.annule_le IS NULL";
    if ($annee) {
        $sql .= " AND ip.annee_culturale = :annee";
    }
    $sql .= " GROUP BY ip.annee_culturale, dip.produit_nom, dip.amm, dip.unite
              ORDER BY ip.annee_culturale, dip.produit_nom";

    $stmt = $db->prepare($sql);
    if ($annee) {
        $stmt->bindValue(':annee', intval($annee), SQLITE3_INTEGER);
    }
    $resultat = $stmt->execute();

    $annees = [];
    while ($ligne = $resultat->fetchArray(SQLITE3_ASSOC)) {
        $annees[$ligne['annee_culturale']][] = $ligne;
    }
    return $annees;
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
