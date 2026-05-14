<?php
session_start();
require_once 'includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$db = getDB();

// Paramètres de filtre
$annee_filter = isset($_GET['annee']) ? $_GET['annee'] : null;
$parcelle_filter = isset($_GET['parcelle']) ? $_GET['parcelle'] : null;

// Requête SQL avec filtres
$query = "
    SELECT 
        ie.id AS intervention_id,
        STRFTIME('%d/%m/%Y', ie.date) AS date,
        ie.annee_culturale,
        p.id AS parcelle_id,
        p.nom AS parcelle_nom,
        p.ilot AS parcelle_ilot,
        p.culture AS type_culture,
        p.surface AS parcelle_surface,
        e.nom AS engrais_nom,
        e.unite AS engrais_unite,
        ie.quantite,
        round((ie.quantite / p.surface), 2) AS quantite_par_ha,
        round((ie.quantite * (e.NO3 / 100) / p.surface), 2)  AS total_NO3,
        round((ie.quantite * (e.P2O5 / 100) / p.surface), 2) AS total_P2O5,
        round((ie.quantite * (e.K2O / 100) / p.surface), 2) AS total_K2O,
        round((ie.quantite * (e.SO3 / 100) / p.surface), 2) AS total_SO3,
        round((ie.quantite * (e.MgO / 100) / p.surface), 2) AS total_MgO,
        round((ie.quantite * (e.CaO / 100) / p.surface), 2) AS total_CaO,
        u.entity,
        u.telepac
    FROM 
        interventions_engrais ie, users u
    JOIN 
        parcelles p ON ie.parcelle_id = p.id
    JOIN 
        engrais e ON ie.engrais_id = e.id
    " . ($annee_filter ? "AND ie.annee_culturale = :annee_culturale " : "") . "
    " . ($parcelle_filter ? "AND p.id = :parcelle_id " : "") . "
    ORDER BY 
        p.nom, ie.annee_culturale, ie.date
";

$stmt = $db->prepare($query);
if ($annee_filter) {
    $stmt->bindValue(':annee_culturale', $annee_filter, SQLITE3_TEXT);
}
if ($parcelle_filter) {
    $stmt->bindValue(':parcelle_id', $parcelle_filter, SQLITE3_INTEGER);
}
$result = $stmt->execute();

// Stocker les interventions
$totals = [];

while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $interventions[] = $row;

    $key = $row['parcelle_nom'];
    if (!isset($totals[$key])) {
        $totals[$key] = [
            'parcelle_nom' => $row['parcelle_nom'],
            'surface' => $row['surface'],
            'NO3' => 0, 'P2O5' => 0, 'K2O' => 0,
            'SO3' => 0, 'MgO' => 0, 'CaO' => 0
        ];
    }
    foreach (['NO3', 'P2O5', 'K2O', 'SO3', 'MgO', 'CaO'] as $element) {
        $totals[$key][$element] += $row['total_' . $element];
    }
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Registre Engrais - AgriRec</title>
    <link rel="stylesheet" href="includes/style.css" />
    <style>
        .report-header {
            background: var(--primary-dark);
            color: white;
            padding: 1.5rem;
            border-radius: var(--radius) var(--radius) 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .report-meta {
            font-size: 0.9rem;
            opacity: 0.9;
            display: flex;
            gap: 1.5rem;
            margin-top: 0.5rem;
        }

        .total-row {
            background: #f8f9fa;
            font-weight: 700;
            color: var(--primary-dark);
        }

        .badge {
            padding: 0.2rem 0.6rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            background: rgba(255, 255, 255, 0.2);
        }
    </style>
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
                <li><a href="interventions_engrais.php">Saisir une intervention</a></li>
            </ul>
        </nav>
        <form id="logout" action="logout.php" method="get">
            <button class="danger">Déconnexion</button>
        </form>
    </header>

    <div class="container">
        <div style="margin-bottom: 2rem;">
            <h1>Registre engrais</h1>
            <p style="color: var(--text-muted);">Consultez les bilans de fertilisation par parcelle et campagne.</p>
        </div>

        <section class="card" style="margin-bottom: 3rem;">
            <h3 style="display: inline">Filtrer le rapport</h3><a class="button" style="margin-left: 20px" href="rapport-engrais-brut.php?annee=<?php echo $annee_filter; ?>&parcelle=<?php echo $parcelle_filter; ?>" target="_blank">Imprimer le rapport</a>
            <div style="margin-top: 2rem;">
            <form method="get" style="display: flex; gap: 1rem; align-items: flex-end;">
                <div style="flex:1;">
                    <label style="display:block; font-size: 1rem; font-weight: 600; margin-bottom: 0.25rem;">Année
                        Culturale</label>
                    <select name="annee" style="width:100%;">
                        <option value="">Toutes les années</option>
                        <?php
$annees = $db->query("SELECT DISTINCT annee_culturale FROM interventions_engrais ORDER BY annee_culturale");
while ($annee = $annees->fetchArray(SQLITE3_ASSOC)) {
    $selected = ($annee['annee_culturale'] == $annee_filter) ? 'selected' : '';
    echo "<option value='" . htmlspecialchars($annee['annee_culturale']) . "' $selected>" . htmlspecialchars($annee['annee_culturale']) . "</option>";
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
    echo "<option value='" . htmlspecialchars($parcelle['id']) . "' $selected>" . htmlspecialchars_decode($parcelle['nom']) . "</option>";
}
?>
                    </select>
                </div>
                <button type="submit">Appliquer les filtres</button>
            </form>
            </div>
        </section>

        <?php if (empty($interventions)) { ?>
        <div class="card" style="text-align: center; color: var(--text-muted);">
            <p>Aucune intervention trouvée pour les critères sélectionnés.</p>
        </div>
        <?php
}
else {
    $current_parcelle = '';
    $current_annee = '';
    $totals = ['NO3' => 0, 'P2O5' => 0, 'K2O' => 0, 'SO3' => 0, 'MgO' => 0, 'CaO' => 0];

    function afficherTotal($annee, $totals)
    {
?>
        <tr class="total-row">
            <td colspan="5" style="text-align: right; padding-right: 2rem;">TOTAL CUMULÉ (U/ha)</td>
            <td>
                <?php echo round($totals['NO3'], 2); ?>
            </td>
            <td>
                <?php echo round($totals['P2O5'], 2); ?>
            </td>
            <td>
                <?php echo round($totals['K2O'], 2); ?>
            </td>
            <td>
                <?php echo round($totals['SO3'], 2); ?>
            </td>
            <td>
                <?php echo round($totals['MgO'], 2); ?>
            </td>
            <td>
                <?php echo round($totals['CaO'], 2); ?>
            </td>
        </tr>
        </tbody>
        </table>
    </div>
    </div>
    <?php
    }

    foreach ($interventions as $index => $intervention) {
        if ($intervention['parcelle_nom'] !== $current_parcelle) {
            if ($current_parcelle !== '') {
                afficherTotal($current_annee, $totals);
            }
            ;
            $current_parcelle = $intervention['parcelle_nom'];
            $current_annee = '';
        }
        ;

        if ($intervention['annee_culturale'] !== $current_annee) {
            if ($current_annee !== '') {
                afficherTotal($current_annee, $totals);
            }
            ;
            $totals = ['NO3' => 0, 'P2O5' => 0, 'K2O' => 0, 'SO3' => 0, 'MgO' => 0, 'CaO' => 0];
?>
    <div style="margin-bottom: 3rem; box-shadow: var(--shadow-md); border-radius: var(--radius); overflow: hidden;">
        <div class="report-header">
            <div>
                <h3 style="margin:0; color: white;">Parcelle :
                    <?php echo htmlspecialchars_decode($intervention['parcelle_nom']); ?>
                </h3>
                <div class="report-meta">
                    <span>Ilot: <b>
                            <?php echo htmlspecialchars($intervention['parcelle_ilot']); ?>
                        </b></span>
                    <span>Surface: <b>
                            <?php echo htmlspecialchars($intervention['parcelle_surface']); ?> ha
                        </b></span>
                    <span>Culture: <b>
                            <?php echo htmlspecialchars_decode($intervention['type_culture']); ?>
                        </b></span>
                </div>
            </div>
            <div style="text-align: right;">
                <span class="badge">CAMPAGNE
                    <?php echo htmlspecialchars($intervention['annee_culturale']); ?>
                </span>
            </div>
        </div>
        <div class="table-container" style="border-radius: 0; box-shadow: none; margin-bottom: 0;">
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Engrais</th>
                        <th>Qté Totale</th>
                        <th>Qté/ha</th>
                        <th>Unité</th>
                        <th>NO3</th>
                        <th>P2O5</th>
                        <th>K2O</th>
                        <th>SO3</th>
                        <th>MgO</th>
                        <th>CaO</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
            $current_annee = $intervention['annee_culturale'];
        }
        ;
?>
                    <tr>
                        <td>
                            <?php echo htmlspecialchars($intervention['date']); ?>
                        </td>
                        <td style="font-weight: 500;">
                            <?php echo htmlspecialchars_decode($intervention['engrais_nom']); ?>
                        </td>
                        <td>
                            <?php echo htmlspecialchars($intervention['quantite']); ?>
                        </td>
                        <td>
                            <?php echo htmlspecialchars($intervention['quantite_par_ha']); ?>
                        </td>
                        <td style="color: var(--text-muted); font-size: 0.8rem;">
                            <?php echo htmlspecialchars($intervention['engrais_unite']); ?>
                        </td>
                        <td>
                            <?php echo round($intervention['total_NO3'], 2); ?>
                        </td>
                        <td>
                            <?php echo round($intervention['total_P2O5'], 2); ?>
                        </td>
                        <td>
                            <?php echo round($intervention['total_K2O'], 2); ?>
                        </td>
                        <td>
                            <?php echo round($intervention['total_SO3'], 2); ?>
                        </td>
                        <td>
                            <?php echo round($intervention['total_MgO'], 2); ?>
                        </td>
                        <td>
                            <?php echo round($intervention['total_CaO'], 2); ?>
                        </td>
                    </tr>
                    <?php
        $totals['NO3'] += $intervention['total_NO3'];
        $totals['P2O5'] += $intervention['total_P2O5'];
        $totals['K2O'] += $intervention['total_K2O'];
        $totals['SO3'] += $intervention['total_SO3'];
        $totals['MgO'] += $intervention['total_MgO'];
        $totals['CaO'] += $intervention['total_CaO'];

        if ($index === count($interventions) - 1) {
            afficherTotal($current_annee, $totals);
        }
        ;
    }
    ;
}
; ?>
        </div>
</body>

</html>