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
        2 => 'migration_2_registre_2026',
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

// Version 2 : informations du registre exigées depuis le 1er janvier 2026
// (arrêté du 24 décembre 2025 relatif à la tenue des registres phytosanitaires).
// Les interventions gardent une copie de la culture, de l'îlot, de la surface
// et du produit au moment du traitement : modifier une parcelle ou le
// catalogue ne change plus le passé.
function migration_2_registre_2026($db) {
    // Exploitation : ajout du SIRET, et n° PACAGE en texte pour garder le 0 initial
    $db->exec("CREATE TABLE users_v2 (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT UNIQUE NOT NULL,
        entity TEXT NOT NULL,
        siret TEXT NOT NULL DEFAULT '',
        telepac TEXT NOT NULL,
        password TEXT NOT NULL
    )");
    $db->exec("INSERT INTO users_v2 (id, username, entity, telepac, password)
               SELECT id, username, entity, CAST(telepac AS TEXT), password FROM users");
    $db->exec("DROP TABLE users");
    $db->exec("ALTER TABLE users_v2 RENAME TO users");

    // Parcelles : coordonnées GPS facultatives (si pas d'îlot PAC)
    $db->exec("ALTER TABLE parcelles ADD COLUMN latitude REAL");
    $db->exec("ALTER TABLE parcelles ADD COLUMN longitude REAL");

    // Catalogue phyto : produit ou adjuvant, délai de rentrée (heures), bio
    $db->exec("ALTER TABLE produits_phytosanitaires ADD COLUMN type TEXT NOT NULL DEFAULT 'produit'");
    $db->exec("ALTER TABLE produits_phytosanitaires ADD COLUMN delai_rentree INTEGER");
    $db->exec("ALTER TABLE produits_phytosanitaires ADD COLUMN bio INTEGER NOT NULL DEFAULT 0");

    // Interventions phyto
    $db->exec("ALTER TABLE interventions_phytosanitaires ADD COLUMN heure_fin TEXT");
    $db->exec("ALTER TABLE interventions_phytosanitaires ADD COLUMN stade_bbch TEXT");
    $db->exec("ALTER TABLE interventions_phytosanitaires ADD COLUMN culture TEXT");
    $db->exec("ALTER TABLE interventions_phytosanitaires ADD COLUMN ilot TEXT");
    $db->exec("ALTER TABLE interventions_phytosanitaires ADD COLUMN surface_traitee REAL");
    $db->exec("ALTER TABLE interventions_phytosanitaires ADD COLUMN realise_par TEXT NOT NULL DEFAULT 'exploitation'");
    $db->exec("ALTER TABLE interventions_phytosanitaires ADD COLUMN intervenant_nom TEXT");
    $db->exec("ALTER TABLE interventions_phytosanitaires ADD COLUMN intervenant_siret TEXT");
    $db->exec("ALTER TABLE interventions_phytosanitaires ADD COLUMN saisi_le TEXT");
    $db->exec("ALTER TABLE interventions_phytosanitaires ADD COLUMN annule_le TEXT");
    $db->exec("ALTER TABLE interventions_phytosanitaires ADD COLUMN motif_annulation TEXT");
    $db->exec("UPDATE interventions_phytosanitaires SET
        culture = (SELECT culture FROM parcelles WHERE parcelles.id = parcelle_id),
        ilot = (SELECT ilot FROM parcelles WHERE parcelles.id = parcelle_id),
        surface_traitee = (SELECT surface FROM parcelles WHERE parcelles.id = parcelle_id)");

    // Produits de chaque intervention : copie des informations du catalogue
    $db->exec("ALTER TABLE details_interventions_phytosanitaires ADD COLUMN produit_nom TEXT");
    $db->exec("ALTER TABLE details_interventions_phytosanitaires ADD COLUMN amm TEXT");
    $db->exec("ALTER TABLE details_interventions_phytosanitaires ADD COLUMN unite TEXT");
    $db->exec("ALTER TABLE details_interventions_phytosanitaires ADD COLUMN type TEXT NOT NULL DEFAULT 'produit'");
    $db->exec("ALTER TABLE details_interventions_phytosanitaires ADD COLUMN delai_rentree INTEGER");
    $db->exec("ALTER TABLE details_interventions_phytosanitaires ADD COLUMN bio INTEGER NOT NULL DEFAULT 0");
    $db->exec("UPDATE details_interventions_phytosanitaires SET
        produit_nom = (SELECT nom FROM produits_phytosanitaires p WHERE p.id = produit_id),
        amm = (SELECT amm FROM produits_phytosanitaires p WHERE p.id = produit_id),
        unite = (SELECT unite_emballage FROM produits_phytosanitaires p WHERE p.id = produit_id)");

    // Semis de semences traitées (traitement fait hors de l'exploitation)
    $db->exec("CREATE TABLE semis_semences_traitees (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        parcelle_id INTEGER NOT NULL,
        annee_culturale INTEGER NOT NULL,
        date DATE NOT NULL,
        culture TEXT NOT NULL,
        ilot TEXT,
        surface REAL NOT NULL,
        produit_nom TEXT NOT NULL,
        amm TEXT NOT NULL,
        quantite_semences REAL,
        unite_semences TEXT,
        saisi_le TEXT,
        annule_le TEXT,
        motif_annulation TEXT,
        FOREIGN KEY (parcelle_id) REFERENCES parcelles(id)
    )");
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
