<?php
require_once 'includes/securite.php';
demarrer_session();

// La déconnexion se fait par le bouton (formulaire POST protégé)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['user_id'])) {
    verifier_csrf();
    session_destroy();
}

header('Location: login.php');
exit();
?>
