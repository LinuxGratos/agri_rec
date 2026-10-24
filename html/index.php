<?php
require_once 'includes/securite.php';
require_once 'includes/registre.php';
exiger_connexion();

$exploitation = exploitation(getDB());
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de bord - AgriRec</title>
    <link rel="stylesheet" href="includes/style.css">
</head>

<body class="cute-background">
    <header>
        <div class="logo-area">
            <a href="index.php" style="display: flex; align-items: center; gap: 0.5rem; color: inherit;">
                <img src="assets/logo.png" alt="AgriRec Logo">
                <h2 style="margin:0; font-size: 1.25rem;">AgriRec</h2>
            </a>
        </div>
        <form id="logout" action="logout.php" method="post">
            <?= champ_csrf() ?>
            <button class="danger">Déconnexion</button>
        </form>
    </header>

    <div class="container">

        <?php if (!siret_valide($exploitation['siret'])): ?>
        <div class="message-info">
            Le SIRET de l'exploitation est obligatoire dans le registre phytosanitaire.
            <a href="exploitation.php">Renseignez-le ici</a>.
        </div>
        <?php endif; ?>

        <div class="dashboard-grid">
            <a autofocus href="parcelles.php" class="nav-card">
                <h3>Gestion des parcelles</h3>
                <p>Configurez et listez vos terrains</p>
                <div style="margin-top: 1rem; font-weight: 600;">Accéder &rarr;</div>
            </a>

            <a href="engrais.php" class="nav-card">
                <h3>Catalogue d'engrais</h3>
                <p>Catalogue des fertilisants et intrants</p>
                <div style="margin-top: 1rem; font-weight: 600;">Accéder &rarr;</div>
            </a>

            <a href="phytosanitaires.php" class="nav-card">
                <h3>Catalogue phytosanitaire</h3>
                <p>Inventaire des traitements et protections</p>
                <div style="margin-top: 1rem; font-weight: 600;">Accéder &rarr;</div>
            </a>
        </div>
        <div class="dashboard-grid">
            <a href="interventions_engrais.php" class="nav-card">
                <h3>Intervention d'engrais</h3>
                <p>Saisir les apports sur vos parcelles</p>
                <div style="margin-top: 1rem; font-weight: 600;">Accéder &rarr;</div>
            </a>

            <a href="interventions_phyto.php" class="nav-card">
                <h3>Intervention phyto</h3>
                <p>Saisir les traitements phytosanitaires</p>
                <div style="margin-top: 1rem; font-weight: 600;">Accéder &rarr;</div>
            </a>

            <a href="semis_traites.php" class="nav-card">
                <h3>Semis de semences traitées</h3>
                <p>Semences traitées hors de l'exploitation</p>
                <div style="margin-top: 1rem; font-weight: 600;">Accéder &rarr;</div>
            </a>
        </div>
        <div class="dashboard-grid">
            <a href="rapport-engrais.php" class="nav-card">
                <h3>Registre engrais</h3>
                <p>Historique et bilans des fertilisations</p>
                <div style="margin-top: 1rem; font-weight: 600;">Accéder &rarr;</div>
            </a>

            <a href="rapport-phyto.php" class="nav-card">
                <h3>Registre phyto</h3>
                <p>Historique et bilans phytosanitaires</p>
                <div style="margin-top: 1rem; font-weight: 600;">Accéder &rarr;</div>
            </a>
            <a href="stock-phyto.php" class="nav-card">
                <h3>Stock phyto</h3>
                <p>Utilisation des stocks phytosanitaires</p>
                <div style="margin-top: 1rem; font-weight: 600;">Accéder &rarr;</div>
            </a>
        </div>
        <div class="dashboard-grid">
            <a href="exploitation.php" class="nav-card">
                <h3>Exploitation</h3>
                <p>Raison sociale, SIRET, n° PACAGE</p>
                <div style="margin-top: 1rem; font-weight: 600;">Accéder &rarr;</div>
            </a>

            <a href="sauvegarde.php" class="nav-card">
                <h3>Sauvegarde</h3>
                <p>Télécharger une copie complète de vos données</p>
                <div style="margin-top: 1rem; font-weight: 600;">Télécharger &rarr;</div>
            </a>
        </div>
    </div>
</body>

</html>
