<?php
// Outils de sécurité communs à toutes les pages.
require_once 'db.php';

// Protège un texte avant de l'afficher dans la page.
// À utiliser pour TOUT ce qui vient de la base ou de l'utilisateur :
//   echo echapper($parcelle['nom']);
function echapper($valeur) {
    return htmlspecialchars((string) $valeur, ENT_QUOTES, 'UTF-8');
}

// Démarre la session avec des cookies protégés et envoie les en-têtes de sécurité.
function demarrer_session() {
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    session_start();

    // Interdit d'afficher l'application dans le cadre d'un autre site.
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
}

// À appeler en haut de chaque page réservée aux utilisateurs connectés.
// Vérifie aussi le jeton de chaque formulaire envoyé (voir champ_csrf).
function exiger_connexion() {
    demarrer_session();

    if (!base_installee()) {
        header('Location: install.php');
        exit();
    }
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit();
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verifier_csrf();
    }
}

// Champ caché à placer dans CHAQUE formulaire en method="post" :
//   echo champ_csrf();
// Il prouve que le formulaire vient bien d'AgriRec et pas d'un autre site.
function champ_csrf() {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return '<input type="hidden" name="csrf" value="' . $_SESSION['csrf'] . '">';
}

function verifier_csrf() {
    if (empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], (string) ($_POST['csrf'] ?? ''))) {
        http_response_code(400);
        echo "<p>Le formulaire a expiré ou n'est pas valide. <a href=\"javascript:history.back()\">Revenir en arrière</a>, recharger la page puis recommencer.</p>";
        exit();
    }
}
?>
