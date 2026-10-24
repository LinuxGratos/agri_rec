<?php
// Mises à jour de la structure et du contenu de la base.
//
// La base retient son numéro de version (PRAGMA user_version). À chaque
// ouverture, les étapes manquantes sont appliquées dans l'ordre, après
// une copie de sauvegarde automatique dans le dossier des données.
//
// Pour faire évoluer la base : ajouter une nouvelle étape à la fin de la
// liste, avec le numéro suivant. Ne jamais modifier une étape existante.

function etapes_migration() {
    return [
        1 => 'migration_1_texte_brut',
    ];
}

// Version 1 : les textes étaient enregistrés avec les caractères spéciaux
// encodés pour le HTML (&amp; &lt; ...). Ils sont désormais enregistrés tels
// que saisis, et protégés au moment de l'affichage avec echapper().
function migration_1_texte_brut($db) {
    $colonnes = [
        'users' => ['username', 'entity'],
        'parcelles' => ['nom', 'culture'],
        'engrais' => ['nom', 'unite'],
        'produits_phytosanitaires' => ['nom', 'unite_emballage', 'amm'],
        'interventions_phytosanitaires' => ['stade'],
        'details_interventions_phytosanitaires' => ['cible'],
    ];

    foreach ($colonnes as $table => $champs) {
        foreach ($champs as $champ) {
            $lignes = $db->query("SELECT id, $champ AS valeur FROM $table");
            $maj = $db->prepare("UPDATE $table SET $champ = :valeur WHERE id = :id");
            while ($ligne = $lignes->fetchArray(SQLITE3_ASSOC)) {
                if (!is_string($ligne['valeur'])) {
                    continue;
                }
                $maj->bindValue(':valeur', htmlspecialchars_decode($ligne['valeur'], ENT_QUOTES), SQLITE3_TEXT);
                $maj->bindValue(':id', $ligne['id'], SQLITE3_INTEGER);
                $maj->execute();
                $maj->reset();
            }
        }
    }
}

// Applique les étapes manquantes. Appelée par getDB().
function migrer_base($db) {
    $etapes = etapes_migration();
    $version_actuelle = $db->querySingle('PRAGMA user_version');
    $version_cible = max(array_keys($etapes));

    if ($version_actuelle >= $version_cible) {
        return;
    }

    // Sauvegarde avant toute modification (inutile sur une base vide).
    if ($db->querySingle('SELECT COUNT(*) FROM users') > 0) {
        $copie = new SQLite3(DOSSIER_DONNEES . '/sauvegarde-avant-version-' . $version_cible . '-' . date('Ymd-His') . '.sqlite');
        $db->backup($copie);
        $copie->close();
    }

    foreach ($etapes as $version => $fonction) {
        if ($version <= $version_actuelle) {
            continue;
        }
        $db->exec('BEGIN TRANSACTION');
        try {
            $fonction($db);
            $db->exec('PRAGMA user_version = ' . intval($version));
            $db->exec('COMMIT');
        } catch (Exception $e) {
            $db->exec('ROLLBACK');
            erreur_bloquante("La mise à jour de la base (version $version) a échoué : cette étape a été annulée.", $e->getMessage());
        }
    }
}
?>
