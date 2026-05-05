<?php
session_start();
require_once 'includes/db.php';

// Vérifier si le fichier de base de données existe
if (!file_exists(DB_PATH)) {
    header('Location: install.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $db = getDB();
    $stmt = $db->prepare('SELECT * FROM users WHERE username = :username');
    $stmt->bindValue(':username', $username, SQLITE3_TEXT);
    $result = $stmt->execute();
    $user = $result->fetchArray(SQLITE3_ASSOC);

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        header('Location: index.php');
        exit;
    }
    else {
        $error = "Nom d'utilisateur ou mot de passe incorrect";
    }
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - AgriRec</title>
    <link rel="stylesheet" href="includes/style.css">
</head>

<body class="login-body">
    <div class="login-card">
        <img src="assets/logo.png" alt="AgriRec Logo" style="width: 120px; height: auto;">
        <h2>Bienvenue</h2>
        <p style="margin-bottom: 2rem; color: var(--text-muted);">Enregistrement d'interventions parcellaires</p>

        <?php if (isset($error)): ?>
        <div
            style="background: rgba(239, 35, 60, 0.1); color: #ef233c; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; text-align: left;">
            <?php echo $error; ?>
        </div>
        <?php
endif; ?>

        <form method="post">
            <div style="width: 100%; text-align: left; margin-bottom: 1rem;">
                <label
                    style="display: block; font-size: 0.8rem; font-weight: 600; text-transform: uppercase;">Utilisateur</label>
                <input type="text" name="username" placeholder="Nom d'utilisateur" required style="width: 100%;">
            </div>

            <div style="width: 100%; text-align: left; margin-bottom: 1.5rem;">
                <label style="display: block; font-size: 0.8rem; font-weight: 600; text-transform: uppercase;">Mot de
                    passe</label>
                <input type="password" name="password" placeholder="••••••••" required style="width: 100%;">
            </div>

            <input type="submit" value="Accéder au Logiciel" style="width: 100%; padding: 1rem;">
        </form>

        <p style="margin-top: 2rem; font-size: 0.8rem; color: var(--text-muted);">
            Propulsé par AgriRec &copy; 2026
        </p>
    </div>
</body>

</html>