<?php
session_start();
require_once 'includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$db = getDB();

// Validation stricte des entrées utilisateur
$annee_filter = isset($_GET['annee']) && ctype_digit($_GET['annee']) ? $_GET['annee'] : null;

// Requête SQL avec filtres et pagination
$query = "
    SELECT
        ip.annee_culturale,
        pp.nom AS produit_nom,
        pp.unite_emballage AS produit_unite,
        pp.amm AS produit_amm,
        SUM(dip.volume_total) AS volume_total,
        u.entity,
        u.telepac
    FROM
        interventions_phytosanitaires ip, users u
    JOIN
        details_interventions_phytosanitaires dip ON ip.id = dip.intervention_id
    JOIN
        produits_phytosanitaires pp ON dip.produit_id = pp.id
	
";

$params = [];
if ($annee_filter) {
    $query .= " WHERE ip.annee_culturale = :annee_culturale";
    $params[':annee_culturale'] = $annee_filter;
}

$query .= " GROUP BY ip.annee_culturale, pp.nom
            ORDER BY ip.annee_culturale, pp.nom";

try {
    $stmt = $db->prepare($query);
    if (!$stmt) {
        throw new Exception($db->lastErrorMsg());
    }

    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, is_int($value) ? SQLITE3_INTEGER : SQLITE3_TEXT);
    }

    $result = $stmt->execute();
    if (!$result) {
        throw new Exception($db->lastErrorMsg());
    }

    // Stocker les interventions groupées
    $interventions = [];

    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $key = $row['annee_culturale'];

        $entity = $row['entity'];
        $telepac = $row['telepac'];

        if (!isset($interventions[$key])) {
            $interventions[$key] = [
                'annee_culturale' => $row['annee_culturale'],
                'parcelle_id' => $row['parcelle_id'],
                'parcelle_nom' => $row['parcelle_nom'],
                'parcelle_ilot' => $row['parcelle_ilot'],
                'surface' => $row['surface'],
                'type_culture' => $row['type_culture'],
                'interventions' => []
            ];
        }

        $interventions[$key]['interventions'][] = [
            'date' => $row['date'],
            'stade' => $row['stade'],
            'produit_nom' => $row['produit_nom'],
            'produit_unite' => $row['produit_unite'],
            'produit_amm' => $row['produit_amm'],
            'volume_total' => $row['volume_total'],
            'volume_par_ha' => $row['volume_par_ha'],
            'cible' => $row['cible']
        ];
    }

} catch (Exception $e) {
    die('Erreur : ' . $e->getMessage());
}

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
    <h2><?php echo $entity; ?> - Telepac: <?php echo $telepac; ?></h2>
    <br>
    <!-- Formulaire de tri -->
    <form method="get">
        <select name="annee">
            <option value="">Toutes les années</option>
            <?php
            $annees = $db->query("SELECT DISTINCT annee_culturale FROM interventions_phytosanitaires ORDER BY annee_culturale");
            while ($annee = $annees->fetchArray(SQLITE3_ASSOC)) {
                $selected = ($annee['annee_culturale'] == $annee_filter) ? 'selected' : '';
                echo "<option value='" . htmlspecialchars($annee['annee_culturale']) . "' $selected>" . htmlspecialchars($annee['annee_culturale']) . "</option>";
            }
            ?>
        </select>
        <input type="submit" value="Filtrer">
    </form>

    <!-- Affichage du tableau des interventions -->
    <?php if (empty($interventions)) : ?>
        <p>Aucune intervention trouvée pour les critères sélectionnés.</p>
    <?php else : ?>
        <?php foreach ($interventions as $intervention) : ?>
            <table>
                <thead>
                    <tr>
                        <th colspan="7">
                            Année culturale: <?= htmlspecialchars($intervention['annee_culturale']) ?>
                        </th>
                    </tr>
                    <tr>
                        <th>Produit</th>
                        <th>AMM</th>
                        <th>Volume total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($intervention['interventions'] as $detail) : ?>
                        <tr>
                            <td><?= htmlspecialchars_decode($detail['produit_nom']) ?></td>
                            <td><?= htmlspecialchars($detail['produit_amm']) ?></td>
                            <td><?= htmlspecialchars($detail['volume_total']) ?> <?= htmlspecialchars($detail['produit_unite']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div class="intervention-separator"></div>
        <?php endforeach; ?>
    <?php endif; ?>

</body>
</html>