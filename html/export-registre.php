<?php
// Export du registre phytosanitaire dans un format électronique lisible par machine.
//   ?format=csv&contenu=traitements   tableau des traitements (une ligne par produit)
//   ?format=csv&contenu=semis         tableau des semis de semences traitées
//   ?format=json                      tout le registre dans un seul fichier
//   &annee=2027                       facultatif : une seule campagne
require_once 'includes/securite.php';
require_once 'includes/registre.php';
exiger_connexion();

$db = getDB();

$annee = isset($_GET['annee']) && ctype_digit($_GET['annee']) ? $_GET['annee'] : null;
$format = ($_GET['format'] ?? '') === 'json' ? 'json' : 'csv';
$contenu = ($_GET['contenu'] ?? '') === 'semis' ? 'semis' : 'traitements';

$exploitation = exploitation($db);
$traitements = preparer_export($exploitation, lignes_registre_phyto($db, $annee), colonnes_export_traitements());
$semis = preparer_export($exploitation, semis_traites($db, $annee), colonnes_export_semis());

$nom_fichier = 'registre-phyto-' . ($exploitation['siret'] ?: 'exploitation') . '-' . ($annee ?: 'complet') . '-' . date('Ymd');

if ($format === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $nom_fichier . '.json"');
    echo json_encode([
        'registre' => "Registre d'utilisation des produits phytopharmaceutiques",
        'reference' => 'Arrêté du 24 décembre 2025 ; règlement d\'exécution (UE) 2023/564',
        'genere_le' => date('c'),
        'campagne' => $annee ? intval($annee) : null,
        'exploitation' => [
            'raison_sociale' => $exploitation['entity'],
            'siret' => $exploitation['siret'],
            'pacage' => $exploitation['telepac'],
        ],
        'traitements' => $traitements,
        'semis_semences_traitees' => $semis,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit();
}

// CSV : séparateur point-virgule, décimales avec un point, encodage UTF-8
$lignes = $contenu === 'semis' ? $semis : $traitements;
$colonnes = array_keys($contenu === 'semis' ? colonnes_export_semis() : colonnes_export_traitements());

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $nom_fichier . '-' . $contenu . '.csv"');
$sortie = fopen('php://output', 'w');
fwrite($sortie, "\xEF\xBB\xBF"); // indique l'UTF-8 aux tableurs (accents)
fputcsv($sortie, $colonnes, ';', '"', '');
foreach ($lignes as $ligne) {
    fputcsv($sortie, $ligne, ';', '"', '');
}
fclose($sortie);
exit();
?>
