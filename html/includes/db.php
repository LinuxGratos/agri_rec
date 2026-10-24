<?php
require_once 'config.php';
require_once 'migrations.php';

// Affiche une erreur bloquante, avec le détail technique en mode debug.
function erreur_bloquante($message, $detail = '') {
    http_response_code(500);
    echo '<p>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p>';
    if (DEBUG_MODE && $detail !== '') {
        echo '<pre>' . htmlspecialchars($detail, ENT_QUOTES, 'UTF-8') . '</pre>';
    }
    exit();
}

// Prépare le dossier des données et y déplace l'ancienne base si besoin.
function preparer_dossier_donnees() {
    if (!is_dir(DOSSIER_DONNEES) && !@mkdir(DOSSIER_DONNEES, 0770)) {
        erreur_bloquante(
            "Le dossier des données " . DOSSIER_DONNEES . " n'existe pas et ne peut pas être créé. " .
            "Créez-le et donnez-le au serveur Web, par exemple : " .
            "sudo mkdir " . DOSSIER_DONNEES . " && sudo chown www-data:www-data " . DOSSIER_DONNEES
        );
    }
    if (!is_writable(DOSSIER_DONNEES)) {
        erreur_bloquante(
            "Le serveur Web ne peut pas écrire dans " . DOSSIER_DONNEES . ". " .
            "Corrigez les droits, par exemple : sudo chown www-data:www-data " . DOSSIER_DONNEES
        );
    }

    if (file_exists(ANCIEN_DB_PATH) && !file_exists(DB_PATH)) {
        if (!@rename(ANCIEN_DB_PATH, DB_PATH)) {
            erreur_bloquante(
                "Impossible de déplacer la base " . ANCIEN_DB_PATH . " vers " . DB_PATH . ". " .
                "Déplacez ce fichier à la main puis rechargez la page."
            );
        }
    }
}

// Indique si l'application a déjà été installée.
function base_installee() {
    return file_exists(DB_PATH);
}

function getDB() {
    try {
        $db = new SQLite3(DB_PATH);
        $db->enableExceptions(true);
        // Empêche de supprimer une parcelle ou un produit utilisé dans le registre
        $db->exec('PRAGMA foreign_keys = ON');
    } catch (Exception $e) {
        erreur_bloquante("Une erreur est survenue lors de la connexion à la base de données.", $e->getMessage());
    }

    // Met la base à jour si le programme a évolué (voir migrations.php).
    // Une base pas encore installée (sans table users) est laissée telle quelle.
    if ($db->querySingle("SELECT name FROM sqlite_master WHERE type = 'table' AND name = 'users'")) {
        migrer_base($db);
    }

    return $db;
}

preparer_dossier_donnees();
?>
