<?php
session_start();
require_once 'includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$db = getDB();

function clean_input($data) {
    return htmlspecialchars(stripslashes(trim($data)));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create':
                $parcelle_id = intval($_POST['parcelle_id']);
                $date = clean_input($_POST['date']);
                $heure = clean_input($_POST['heure']);
                $annee_culturale = intval($_POST['annee_culturale']);
                $datetime = $date . ' ' . $heure;

                $db->exec('BEGIN TRANSACTION');

                $stmt = $db->prepare('INSERT INTO interventions_phytosanitaires (parcelle_id, date, annee_culturale) VALUES (:parcelle_id, :date, :annee_culturale)');
                $stmt->bindValue(':parcelle_id', $parcelle_id, SQLITE3_INTEGER);
                $stmt->bindValue(':date', $datetime, SQLITE3_TEXT);
                $stmt->bindValue(':annee_culturale', $annee_culturale, SQLITE3_INTEGER);
                $stmt->execute();

                $intervention_id = $db->lastInsertRowID();

                foreach ($_POST['produit_id'] as $key => $produit_id) {
                    $volume_total = floatval($_POST['volume_total'][$key]);
                    $cible = clean_input($_POST['cible'][$key]);

                    $stmt = $db->prepare('INSERT INTO details_interventions_phytosanitaires (intervention_id, produit_id, volume_total, cible) VALUES (:intervention_id, :produit_id, :volume_total, :cible)');
                    $stmt->bindValue(':intervention_id', $intervention_id, SQLITE3_INTEGER);
                    $stmt->bindValue(':produit_id', $produit_id, SQLITE3_INTEGER);
                    $stmt->bindValue(':volume_total', $volume_total, SQLITE3_FLOAT);
                    $stmt->bindValue(':cible', $cible, SQLITE3_TEXT);
                    $stmt->execute();
                }

                $db->exec('COMMIT');
                break;

            case 'delete':
                $id = intval($_POST['id']);

                $db->exec('BEGIN TRANSACTION');

                $stmt = $db->prepare('DELETE FROM details_interventions_phytosanitaires WHERE intervention_id = :id');
                $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
                $stmt->execute();

                $stmt = $db->prepare('DELETE FROM interventions_phytosanitaires WHERE id = :id');
                $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
                $stmt->execute();

                $db->exec('COMMIT');
                break;
        }
    }
}

$parcelles = $db->query('SELECT * FROM parcelles');
$produits = $db->query('SELECT * FROM produits_phytosanitaires');
$interventions = $db->query('SELECT ip.*, p.nom as parcelle_nom, p.surface, p.ilot AS parcelle_ilot
                             FROM interventions_phytosanitaires ip
                             JOIN parcelles p ON ip.parcelle_id = p.id
                             ORDER BY ip.date');

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <title>Gestion des interventions phytosanitaires</title>
    <script>
        function addProduit() {
            var container = document.getElementById('produits-container');
            var newProduit = document.createElement('div');
            newProduit.innerHTML = `
                Produit phytosanitaire : <select name="produit_id[]" required> <br/>
                    <?php
                    $produits->reset();
                    while ($produit = $produits->fetchArray(SQLITE3_ASSOC)):
                    ?>
                        <option value="<?php echo $produit['id']; ?>"><?php echo $produit['nom']; ?></option>
                    <?php endwhile; ?>
                </select>
                 Volume total appliqué sur la parcelle : <input type="number" name="volume_total[]" step="0.01" placeholder="Volume total" required> <br/>
                 Cible de l'intervention : <input type="text" name="cible[]" placeholder="Cible" required> <br/>
            `;
            container.appendChild(newProduit);
        }
    </script>
    <link rel="stylesheet" href="includes/style.css">
</head>
<body>
    <h3> Navigation dans les pages de gestion </h3>
    <ul>
        <li><a href="parcelles.php">Création des parcelles d'intervention</a></li>
        <li><a href="phytosanitaires.php">Création des produits phytosanitaires</a></li>
        <li><a href="rapport-phyto.php">Visualisation des interventions phytosanitaires</a></li>
        <li><a href="index.php">Retour à l'accueil</a></li>
    </ul>
    <h1>Gestion des interventions phytosanitaires</h1>
    <h3>Ajouter une intervention</h3>
    <form method="post">
        <input type="hidden" name="action" value="create">
        Parcelle : <select name="parcelle_id" required>
            <option value="" disabled selected>Choisir une parcelle</option>
            <?php
            while ($parcelle = $parcelles->fetchArray(SQLITE3_ASSOC)):
            ?>
                <option value="<?php echo $parcelle['id']; ?>"><?php echo $parcelle['nom']; ?></option>
            <?php endwhile; ?>
        </select><br/>
        <br/>
        Date d'intervention : <input type="date" name="date" required><br/>
        Heure d'intervention : <input type="time" name="heure" required><br/>
        <br/>
        Année culturale : <input type="number" min="<?php echo date("Y")-1; ?>" max="<?php echo date("Y")+3; ?>" step="1" value="<?php echo date("Y"); ?>" name="annee_culturale" placeholder="Année culturale" required>
        <br/>
        <br/>
        <div id="produits-container">
            <div>
                Produit phytosanitaire : <select name="produit_id[]" required>
                    <option value="" disabled selected>Choisir un phyto</option>
                    <?php
                    while ($produit = $produits->fetchArray(SQLITE3_ASSOC)):
                    ?>
                        <option value="<?php echo $produit['id']; ?>"><?php echo $produit['nom']; ?></option>
                    <?php endwhile; ?>
                </select>
                Volume total appliqué sur la parcelle : <input type="number" name="volume_total[]" step="0.01" placeholder="Volume total" required> <br/>
                Cible de l'intervention : <input type="text" name="cible[]" placeholder="Cible" required>
            </div>
        </div>
        <br/>
        <button type="button" onclick="addProduit()">Ajouter un produit</button>
        <br/>
        <br/>
        <input type="submit" value="Enregistrer l'intervention">
    </form>

    <h3>Liste des interventions</h3>
    <table>
        <tr>
            <th>Date</th>
            <th>Parcelle</th>
            <th>Ilot</th>
            <th>Année culturale</th>
            <th>Produits utilisés</th>
            <th>Actions</th>
        </tr>
        <?php while ($intervention = $interventions->fetchArray(SQLITE3_ASSOC)): ?>
        <tr>
            <td><?php echo htmlspecialchars($intervention['date']); ?></td>
            <td><?php echo htmlspecialchars_decode($intervention['parcelle_nom']); ?></td>
            <td><?php echo htmlspecialchars($intervention['parcelle_ilot']); ?></td>
            <td><?php echo htmlspecialchars($intervention['annee_culturale']); ?></td>
            <td>
                <?php
                $details = $db->query('SELECT dip.*, pp.nom as produit_nom,  pp.unite_emballage as produit_unite
                                       FROM details_interventions_phytosanitaires dip
                                       JOIN produits_phytosanitaires pp ON dip.produit_id = pp.id
                                       WHERE dip.intervention_id = ' . $intervention['id']);
                while ($detail = $details->fetchArray(SQLITE3_ASSOC)) {
                    echo htmlspecialchars_decode($detail['produit_nom']) . ' : ' .
                         htmlspecialchars($detail['volume_total']) . ' ' .
                         htmlspecialchars($detail['produit_unite']) . ' Volume sur la parcelle | ' .
                         htmlspecialchars($detail['volume_total'] / $intervention['surface']) . ' ' .
                         htmlspecialchars($detail['produit_unite']) . '/ha, cible : ' .
                         htmlspecialchars_decode($detail['cible']) . ' <br>';
                }
                ?>
            </td>
            <td>
                <form method="post" style="display:inline;">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?php echo $intervention['id']; ?>">
                    <input type="submit" value="Supprimer" onclick="return confirm('Etes-vous sûr de vouloir supprimer cette intervention ?');">
                </form>
            </td>
        </tr>
        <?php endwhile; ?>
    </table>
</body>
</html>
