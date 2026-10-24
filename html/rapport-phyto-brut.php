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
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Registre des interventions phytosanitaires</title>
    <link rel="stylesheet" href="includes/style.brut.css" />
</head>
<body>
    <h1>Registre des interventions phytosanitaires</h1>
    <h2>
        <?= echapper($exploitation['entity']) ?> - SIRET : <?= echapper($exploitation['siret']) ?> -
        PACAGE : <?= echapper($exploitation['telepac']) ?>
    </h2>
    <p>Édité le <?= date('d/m/Y à H:i') ?></p>
    <!-- Formulaire de tri -->
    <form method="get">
        <select name="annee">
            <option value="">Toutes les années</option>
            <?php
            $annees = $db->query("SELECT DISTINCT annee_culturale FROM interventions_phytosanitaires ORDER BY annee_culturale");
            while ($annee = $annees->fetchArray(SQLITE3_ASSOC)) {
                $selected = ($annee['annee_culturale'] == $annee_filter) ? 'selected' : '';
                echo "<option value='" . echapper($annee['annee_culturale']) . "' $selected>" . echapper($annee['annee_culturale']) . "</option>";
            }
            ?>
        </select>
        <select name="parcelle">
            <option value="">Toutes les parcelles</option>
            <?php
            $parcelles = $db->query("SELECT id, nom FROM parcelles ORDER BY nom");
            while ($parcelle = $parcelles->fetchArray(SQLITE3_ASSOC)) {
                $selected = ($parcelle['id'] == $parcelle_filter) ? 'selected' : '';
                echo "<option value='" . echapper($parcelle['id']) . "' $selected>" . echapper($parcelle['nom']) . "</option>";
            }
            ?>
        </select>
        <input type="submit" value="Filtrer">
    </form>

    <?php if (empty($groupes)) : ?>
        <p>Aucune intervention trouvée pour les critères sélectionnés.</p>
    <?php endif; ?>

    <?php foreach ($groupes as $groupe) : ?>
        <?php include 'includes/tableau-registre-phyto.php'; ?>
        <div class="intervention-separator"></div>
    <?php endforeach; ?>

    <?php if ($semis) : ?>
        <?php include 'includes/tableau-semis.php'; ?>
    <?php endif; ?>
</body>
</html>
