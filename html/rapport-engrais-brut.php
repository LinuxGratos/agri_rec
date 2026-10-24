<?php
require_once 'includes/securite.php';
require_once 'includes/registre.php';
require_once 'includes/engrais.php';
exiger_connexion();

$db = getDB();

// Paramètres de filtre
$annee_filter = isset($_GET['annee']) && ctype_digit($_GET['annee']) ? $_GET['annee'] : null;
$parcelle_filter = isset($_GET['parcelle']) && ctype_digit($_GET['parcelle']) ? $_GET['parcelle'] : null;

[$interventions, $totals] = lire_interventions_engrais($db, $annee_filter, $parcelle_filter);
$exploitation = exploitation($db);
?>
<!DOCTYPE html>
<html lang="fr">
    <head>
        <meta charset="UTF-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <title>Registre des interventions engrais</title>
        <link rel="stylesheet" href="includes/style.brut.css" />
    </head>
    <body>
        <h1>Registre des interventions engrais</h1>
        <h2><?php echo echapper($exploitation['entity']); ?> - SIRET : <?php echo echapper($exploitation['siret']); ?> - PACAGE : <?php echo echapper($exploitation['telepac']); ?></h2>
        <!-- Formulaire de tri -->
        <form method="get">
            <select name="annee">
                <option value="">Toutes les années</option>
                <?php
                $annees = $db->query("SELECT DISTINCT annee_culturale FROM interventions_engrais ORDER BY annee_culturale");
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

        <?php if (empty($interventions)) { ?>
        <p>Aucune intervention trouvée pour les critères sélectionnés.</p>
        <?php } else {
            $current_parcelle = '';
            $current_annee = '';
            $totals = ['NO3' => 0, 'P2O5' => 0, 'K2O' => 0, 'SO3' => 0, 'MgO' => 0, 'CaO' => 0];

            function afficherTotal($annee, $totals) {
        ?>
                <tr class="total-row">
                    <td colspan="5">Total pour l'année <?php echo echapper($annee); ?></td>
                    <td><?php echo round($totals['NO3'], 2); ?></td>
                    <td><?php echo round($totals['P2O5'], 2); ?></td>
                    <td><?php echo round($totals['K2O'], 2); ?></td>
                    <td><?php echo round($totals['SO3'], 2); ?></td>
                    <td><?php echo round($totals['MgO'], 2); ?></td>
                    <td><?php echo round($totals['CaO'], 2); ?></td>
                </tr>
                </table>
            <?php
            }

            foreach ($interventions as $index => $intervention) {
                if ($intervention['parcelle_nom'] !== $current_parcelle) {
                    if ($current_parcelle !== '') {
                        afficherTotal($current_annee, $totals);
                    };
                    $current_parcelle = $intervention['parcelle_nom'];
                    $current_annee = '';
                };

                if ($intervention['annee_culturale'] !== $current_annee) {
                    if ($current_annee !== '') {
                        afficherTotal($current_annee, $totals);
                    };
                    $totals = ['NO3' => 0, 'P2O5' => 0, 'K2O' => 0, 'SO3' => 0, 'MgO' => 0, 'CaO' => 0];
                    ?>
                    <table>
                        <tr>
                            <th>
                            Année culturale : <?php echo echapper($intervention['annee_culturale']); ?> |
                            Parcelle : <?php echo echapper($intervention['parcelle_nom']); ?> <br/>
                            Ilot : <?php echo echapper($intervention['parcelle_ilot']); ?> <br/>
                            Surface : <?php echo echapper($intervention['parcelle_surface']); ?> |
                            Culture : <?php echo echapper($intervention['type_culture']); ?>
                            </th>
                            <th colspan="10" class="emptyth"></th>
                        </tr>
                        <tr>
                            <th>Date</th>
                            <th>Engrais</th>
                            <th>Unité</th>
                            <th>Quantité totale</th>
                            <th>Quantité par ha</th>
                            <th>NO3 (U/ha)</th>
                            <th>P2O5 (U/ha)</th>
                            <th>K2O (U/ha)</th>
                            <th>SO3 (U/ha)</th>
                            <th>MgO (U/ha)</th>
                            <th>CaO (U/ha)</th>
                        </tr>
                    <?php
                    $current_annee = $intervention['annee_culturale'];
                } ;
                ?>
                <tr>
                    <td><?php echo echapper($intervention['date']); ?></td>
                    <td><?php echo echapper($intervention['engrais_nom']); ?></td>
                    <td><?php echo echapper($intervention['engrais_unite']); ?></td>
                    <td><?php echo echapper($intervention['quantite']); ?></td>
                    <td><?php echo echapper($intervention['quantite_par_ha']); ?></td>
                    <td><?php echo round($intervention['total_NO3'], 2); ?></td>
                    <td><?php echo round($intervention['total_P2O5'], 2); ?></td>
                    <td><?php echo round($intervention['total_K2O'], 2); ?></td>
                    <td><?php echo round($intervention['total_SO3'], 2); ?></td>
                    <td><?php echo round($intervention['total_MgO'], 2); ?></td>
                    <td><?php echo round($intervention['total_CaO'], 2); ?></td>
                </tr>
                <?php
                $totals['NO3'] += $intervention['total_NO3'];
                $totals['P2O5'] += $intervention['total_P2O5'];
                $totals['K2O'] += $intervention['total_K2O'];
                $totals['SO3'] += $intervention['total_SO3'];
                $totals['MgO'] += $intervention['total_MgO'];
                $totals['CaO'] += $intervention['total_CaO'];

                // Si c'est la dernière intervention, afficher le total
                if ($index === count($interventions) - 1) {
                    afficherTotal($current_annee, $totals);
                };
            };
        }; ?>
    </body>
</html>
