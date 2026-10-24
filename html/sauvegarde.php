<?php
// Télécharge une copie complète de la base de données.
// Pour restaurer : remplacer le fichier data/database.sqlite par cette copie.
require_once 'includes/securite.php';
exiger_connexion();

$db = getDB();

// Copie cohérente de la base dans un fichier temporaire, puis envoi au navigateur
$fichier_temporaire = tempnam(sys_get_temp_dir(), 'agrirec');
$copie = new SQLite3($fichier_temporaire);
$db->backup($copie);
$copie->close();

header('Content-Type: application/vnd.sqlite3');
header('Content-Disposition: attachment; filename="agrirec-sauvegarde-' . date('Y-m-d') . '.sqlite"');
header('Content-Length: ' . filesize($fichier_temporaire));
readfile($fichier_temporaire);
unlink($fichier_temporaire);
exit();
?>
