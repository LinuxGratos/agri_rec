<?php
require_once 'includes/securite.php';
require_once 'includes/registre.php';
exiger_connexion();

$db = getDB();

// Filtres (facultatifs)
$annee_filter = isset($_GET['annee']) && ctype_digit($_GET['annee']) ? $_GET['annee'] : null;
$parcelle_filter = isset($_GET['parcelle']) && ctype_digit($_GET['parcelle']) ? $_GET['parcelle'] : null;

$exploitation = exploitation($db);
$groupes = registre_par_parcelle(lignes_registre_phyto($db, $annee_filter, $parcelle_filter));
$semis = semis_traites($db, $annee_filter, $parcelle_filter);

// Contrôle de tout le registre de la campagne (sans filtre de parcelle), comme l'export
$problemes = controles_registre($exploitation, lignes_registre_phyto($db, $annee_filter), semis_traites($db, $annee_filter));
$lien_annee = $annee_filter ? '&annee=' . urlencode($annee_filter) : '';
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Rapport Phyto - AgriRec</title>
    <link rel="stylesheet" href="includes/style.css" />
</head>

<body>
    <header>
        <div class="logo-area">
            <a href="index.php" style="display: flex; align-items: center; gap: 0.5rem; color: inherit;">
                <img src="assets/logo.png" alt="AgriRec Logo">
                <h2 style="margin:0; font-size: 1.25rem;">AgriRec</h2>
            </a>
        </div>
        <nav>
            <ul>
                <li><a href="index.php">Tableau de bord</a></li>
                <li><a href="interventions_phyto.php">Saisir une intervention</a></li>
            </ul>
        </nav>
        <form id="logout" action="logout.php" method="post">
            <?= champ_csrf() ?>
            <button class="danger">Déconnexion</button>
        </form>
    </header>

    <div class="container">
        <div style="margin-bottom: 2rem;">
            <h1>Registre phytosanitaire</h1>
            <p style="color: var(--text-muted);">
                <?= echapper($exploitation['entity']) ?> - SIRET <?= echapper($exploitation['siret']) ?> -
                PACAGE <?= echapper($exploitation['telepac']) ?>
            </p>
        </div>

        <?php if (!siret_valide($exploitation['siret'])): ?>
        <div class="message-info">Le SIRET de l'exploitation manque : <a href="exploitation.php">renseignez-le</a>.</div>
        <?php endif; ?>

        <section class="card" style="margin-bottom: 3rem;">
            <h3 style="display: inline">Filtrer le registre</h3><a class="button" autofocus style="margin-left: 20px" href="rapport-phyto-brut.php?annee=<?php echo urlencode((string) $annee_filter); ?>&parcelle=<?php echo urlencode((string) $parcelle_filter); ?>" target="_blank">Imprimer le rapport</a>
            <div style="margin-top: 2rem;">
            <form method="get" style="display: flex; gap: 1rem; align-items: flex-end;">
                <div style="flex:1;">
                    <label style="display:block; font-size: 1rem; font-weight: 600; margin-bottom: 0.25rem;">Année
                        Culturale</label>
                    <select name="annee" style="width:100%;">
                        <option value="">Toutes les années</option>
                        <?php
$annees = $db->query("SELECT DISTINCT annee_culturale FROM interventions_phytosanitaires ORDER BY annee_culturale");
while ($annee = $annees->fetchArray(SQLITE3_ASSOC)) {
    $selected = ($annee['annee_culturale'] == $annee_filter) ? 'selected' : '';
    echo "<option value='" . echapper($annee['annee_culturale']) . "' $selected>" . echapper($annee['annee_culturale']) . "</option>";
}
?>
                    </select>
                </div>
                <div style="flex:1;">
                    <label
                        style="display:block; font-size: 1rem; font-weight: 600; margin-bottom: 0.25rem;">Parcelle</label>
                    <select name="parcelle" style="width:100%;">
                        <option value="">Toutes les parcelles</option>
                        <?php
$parcelles = $db->query("SELECT id, nom FROM parcelles ORDER BY nom");
while ($parcelle = $parcelles->fetchArray(SQLITE3_ASSOC)) {
    $selected = ($parcelle['id'] == $parcelle_filter) ? 'selected' : '';
    echo "<option value='" . echapper($parcelle['id']) . "' $selected>" . echapper($parcelle['nom']) . "</option>";
}
?>
                    </select>
                </div>
                <button type="submit">Appliquer les filtres</button>
            </form>
            </div>
        </section>

        <section class="card" style="margin-bottom: 3rem;">
            <h3>Format électronique</h3>
            <p style="color: var(--text-muted);">À partir du 1er janvier 2027, le registre doit être tenu dans un format
                électronique lisible par machine. Ces fichiers contiennent tout le registre<?= $annee_filter ? ' de la campagne ' . echapper($annee_filter) : '' ?>.</p>
            <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                <a class="button" href="export-registre.php?format=csv&contenu=traitements<?= $lien_annee ?>">Traitements (CSV)</a>
                <a class="button" href="export-registre.php?format=csv&contenu=semis<?= $lien_annee ?>">Semis de semences traitées (CSV)</a>
                <a class="button" href="export-registre.php?format=json<?= $lien_annee ?>">Registre complet (JSON)</a>
            </div>
            <?php if ($problemes): ?>
            <div class="message-info" style="margin-top: 1.5rem;">
                <b>Points à compléter avant export :</b>
                <ul>
                    <?php foreach ($problemes as $probleme): ?>
                    <li><?= echapper($probleme) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php else: ?>
            <p style="margin-top: 1rem;">✔ Toutes les informations obligatoires sont renseignées.</p>
            <?php endif; ?>
        </section>

        <?php if (empty($groupes)): ?>
        <div class="card" style="text-align: center; color: var(--text-muted);">
            <p>Aucune intervention trouvée pour les critères sélectionnés.</p>
        </div>
        <?php endif; ?>

        <?php foreach ($groupes as $groupe): ?>
        <div class="table-container" style="margin-bottom: 3rem;">
            <?php include 'includes/tableau-registre-phyto.php'; ?>
        </div>
        <?php endforeach; ?>

        <?php if ($semis): ?>
        <div class="table-container" style="margin-bottom: 3rem;">
            <?php include 'includes/tableau-semis.php'; ?>
        </div>
        <?php endif; ?>
    </div>
</body>

</html>
