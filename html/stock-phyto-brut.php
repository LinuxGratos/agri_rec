<?php
require_once 'includes/securite.php';
require_once 'includes/registre.php';
exiger_connexion();

$db = getDB();

// Validation stricte des entrées utilisateur
$annee_filter = isset($_GET['annee']) && ctype_digit($_GET['annee']) ? $_GET['annee'] : null;

$exploitation = exploitation($db);
$stock = stock_par_annee($db, $annee_filter);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Stock phytosanitaire</title>
    <link rel="stylesheet" href="includes/style.brut.css" />
</head>
<body>
    <h1>Stock phytosanitaire</h1>
    <h2><?php echo echapper($exploitation['entity']); ?> - SIRET : <?php echo echapper($exploitation['siret']); ?> - PACAGE : <?php echo echapper($exploitation['telepac']); ?></h2>
    <br>
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
        <input type="submit" value="Filtrer">
    </form>

    <!-- Affichage du tableau des interventions -->
    <?php if (empty($stock)) : ?>
        <p>Aucun produit utilisé pour les critères sélectionnés.</p>
    <?php else : ?>
        <?php foreach ($stock as $annee_stock => $produits) : ?>
            <table>
                <thead>
                    <tr>
                        <th colspan="7">
                            Année culturale: <?= echapper($annee_stock) ?>
                        </th>
                    </tr>
                    <tr>
                        <th>Produit</th>
                        <th>AMM</th>
                        <th>Volume total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($produits as $detail) : ?>
                        <tr>
                            <td><?= echapper($detail['produit_nom']) ?></td>
                            <td><?= echapper($detail['amm']) ?></td>
                            <td><?= echapper($detail['quantite']) ?> <?= echapper($detail['unite']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div class="intervention-separator"></div>
        <?php endforeach; ?>
    <?php endif; ?>

</body>
</html>