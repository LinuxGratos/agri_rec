<?php
// Dossier où sont rangées les données (base de données et sauvegardes).
// Il doit être EN DEHORS du dossier publié par le serveur Web (html/),
// sinon n'importe qui pourrait télécharger la base.
// Par défaut : le dossier "data" situé à côté du dossier "html".
define('DOSSIER_DONNEES', dirname(__DIR__, 2) . '/data');

define('DB_PATH', DOSSIER_DONNEES . '/database.sqlite');

// Ancien emplacement de la base (dans html/, donc téléchargeable).
// Si une base s'y trouve encore, elle est déplacée automatiquement.
define('ANCIEN_DB_PATH', dirname(__DIR__) . '/database.sqlite');

define('SITE_URL', 'http://localhost/project');

// Mettre à true uniquement pour chercher une panne : les messages d'erreur
// détaillés s'affichent alors à l'écran.
define('DEBUG_MODE', false);
?>