<?php
// Semis de semences traitées : à enregistrer lorsque le traitement des
// semences n'a pas été réalisé (et enregistré) sur l'exploitation.
require_once 'includes/securite.php';
require_once 'includes/registre.php';
exiger_connexion();

$db = getDB();
$message_erreur = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($_POST['action'] ?? '') {
        case 'create':
            $stmt = $db->prepare('SELECT * FROM parcelles WHERE id = :id');
            $stmt->bindValue(':id', intval($_POST['parcelle_id']), SQLITE3_INTEGER);
            $parcelle = $stmt->execute()->fetchArray(SQLITE3_ASSOC);

            $surface = floatval(str_replace(',', '.', $_POST['surface']));
            $amm = trim($_POST['amm']);
            $quantite = trim($_POST['quantite_semences']);

            if (!$parcelle) {
                $message_erreur = 'Choisissez une parcelle.';
            } elseif ($surface <= 0 || $surface > $parcelle['surface']) {
                $message_erreur = 'La surface semée doit être comprise entre 0 et la surface de la parcelle (' . $parcelle['surface'] . ' ha).';
            } elseif (!amm_valide($amm)) {
                $message_erreur = "Le n° d'AMM du produit de traitement doit comporter 7 chiffres (voir l'étiquette du sac).";
            } else {
                $stmt = $db->prepare('INSERT INTO semis_semences_traitees
                    (parcelle_id, annee_culturale, date, culture, ilot, surface, produit_nom, amm, quantite_semences, unite_semences, saisi_le)
                    VALUES (:parcelle_id, :annee_culturale, :date, :culture, :ilot, :surface, :produit_nom, :amm, :quantite_semences, :unite_semences, :saisi_le)');
                $stmt->bindValue(':parcelle_id', $parcelle['id'], SQLITE3_INTEGER);
                $stmt->bindValue(':annee_culturale', intval($_POST['annee_culturale']), SQLITE3_INTEGER);
                $stmt->bindValue(':date', trim($_POST['date']), SQLITE3_TEXT);
                $stmt->bindValue(':culture', trim($_POST['culture']), SQLITE3_TEXT);
                $stmt->bindValue(':ilot', (string) $parcelle['ilot'], SQLITE3_TEXT);
                $stmt->bindValue(':surface', $surface, SQLITE3_FLOAT);
                $stmt->bindValue(':produit_nom', trim($_POST['produit_nom']), SQLITE3_TEXT);
                $stmt->bindValue(':amm', $amm, SQLITE3_TEXT);
                $stmt->bindValue(':quantite_semences', $quantite === '' ? null : floatval(str_replace(',', '.', $quantite)), SQLITE3_FLOAT);
                $stmt->bindValue(':unite_semences', trim($_POST['unite_semences']), SQLITE3_TEXT);
                $stmt->bindValue(':saisi_le', date('Y-m-d H:i:s'), SQLITE3_TEXT);
                $stmt->execute();
            }
            break;

        // Un semis n'est jamais effacé : il est annulé avec un motif
        case 'annuler':
            $motif = trim($_POST['motif']);
            if ($motif === '') {
                $message_erreur = "Indiquez le motif de l'annulation.";
                break;
            }
            $stmt = $db->prepare('UPDATE semis_semences_traitees SET annule_le = :annule_le, motif_annulation = :motif
                                  WHERE id = :id AND annule_le IS NULL');
            $stmt->bindValue(':annule_le', date('Y-m-d H:i:s'), SQLITE3_TEXT);
            $stmt->bindValue(':motif', $motif, SQLITE3_TEXT);
            $stmt->bindValue(':id', intval($_POST['id']), SQLITE3_INTEGER);
            $stmt->execute();
            break;
    }

    // Recharge la page pour éviter un double enregistrement en cas d'actualisation
    if ($message_erreur === '') {
        header('Location: semis_traites.php');
        exit();
    }
}

$parcelles = $db->query('SELECT * FROM parcelles ORDER BY nom');
$semis = $db->query('SELECT s.*, p.nom AS parcelle_nom FROM semis_semences_traitees s
                     JOIN parcelles p ON p.id = s.parcelle_id ORDER BY s.date');
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Semis de semences traitées - AgriRec</title>
    <link rel="stylesheet" href="includes/style.css">
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
                <li><a href="rapport-phyto.php">Registre phyto</a></li>
            </ul>
        </nav>
        <form id="logout" action="logout.php" method="post">
            <?= champ_csrf() ?>
            <button class="danger">Déconnexion</button>
        </form>
    </header>

    <div class="container">
        <div style="margin-bottom: 2rem;">
            <h1>Semis de semences traitées</h1>
            <p style="color: var(--text-muted);">À enregistrer quand les semences ont été traitées ailleurs que sur
                l'exploitation (semences achetées traitées). Les informations figurent sur l'étiquette du sac.</p>
        </div>

        <?php if ($message_erreur): ?>
        <div class="message-erreur"><?= echapper($message_erreur) ?></div>
        <?php endif; ?>

        <section class="card">
            <h3>Saisir un semis</h3>
            <form style="display:block" method="post">
                <?= champ_csrf() ?>
                <input type="hidden" name="action" value="create">
                <div class="grille" style="margin-bottom: 1rem;">
                    <div class="champ">
                        <label>Parcelle</label>
                        <select name="parcelle_id" id="parcelle_id" required autofocus onchange="choisirParcelle()">
                            <option value="" disabled selected>Choisir une parcelle</option>
                            <?php while ($parcelle = $parcelles->fetchArray(SQLITE3_ASSOC)): ?>
                            <option value="<?= $parcelle['id'] ?>" data-surface="<?= echapper($parcelle['surface']) ?>"
                                data-culture="<?= echapper($parcelle['culture']) ?>">
                                <?= echapper($parcelle['nom']) ?> (îlot <?= echapper($parcelle['ilot']) ?>, <?= echapper($parcelle['surface']) ?> ha)
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="champ">
                        <label>Année culturale</label>
                        <input type="number" name="annee_culturale" min="<?= date('Y') - 1 ?>" max="<?= date('Y') + 3 ?>"
                            step="1" value="<?= date('Y') ?>" required>
                    </div>
                    <div class="champ">
                        <label>Date du semis</label>
                        <input type="date" name="date" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="champ">
                        <label>Culture semée</label>
                        <input type="text" name="culture" id="culture" list="cultures" required>
                    </div>
                    <div class="champ">
                        <label>Surface semée (ha)</label>
                        <input type="number" name="surface" id="surface" step="0.01" min="0.01" required>
                    </div>
                </div>
                <div class="grille">
                    <div class="champ">
                        <label>Produit de traitement des semences</label>
                        <input type="text" name="produit_nom" placeholder="Nom commercial" required>
                    </div>
                    <div class="champ">
                        <label>N° AMM du produit</label>
                        <input type="text" name="amm" pattern="[0-9]{7}" placeholder="7 chiffres" required>
                    </div>
                    <div class="champ">
                        <label>Quantité de semences (facultatif)</label>
                        <input type="number" name="quantite_semences" step="0.01" min="0">
                    </div>
                    <div class="champ">
                        <label>Unité</label>
                        <input type="text" name="unite_semences" list="unites_semences" placeholder="ex: kg">
                    </div>
                </div>
                <div style="margin-top: 2rem;">
                    <input type="submit" value="Enregistrer le semis">
                </div>
            </form>
        </section>

        <section>
            <h3>Historique des semis de semences traitées</h3>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Campagne</th>
                            <th>Date</th>
                            <th>Parcelle (îlot)</th>
                            <th>Culture</th>
                            <th>Surface</th>
                            <th>Produit de traitement</th>
                            <th>Semences</th>
                            <th style="text-align: center;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($ligne = $semis->fetchArray(SQLITE3_ASSOC)): ?>
                        <tr class="<?= $ligne['annule_le'] ? 'annulee' : '' ?>">
                            <td><?= echapper($ligne['annee_culturale']) ?></td>
                            <td><?= date('d/m/Y', strtotime($ligne['date'])) ?></td>
                            <td><?= echapper($ligne['parcelle_nom']) ?> (<?= echapper($ligne['ilot']) ?>)</td>
                            <td><?= echapper($ligne['culture']) ?></td>
                            <td><?= echapper($ligne['surface']) ?> ha</td>
                            <td><?= echapper($ligne['produit_nom']) ?> - AMM <?= echapper($ligne['amm']) ?></td>
                            <td><?= echapper(trim($ligne['quantite_semences'] . ' ' . $ligne['unite_semences'])) ?></td>
                            <td style="text-align: center;">
                                <?php if ($ligne['annule_le']): ?>
                                <small>Annulé le <?= date('d/m/Y', strtotime($ligne['annule_le'])) ?> :
                                    <?= echapper($ligne['motif_annulation']) ?></small>
                                <?php else: ?>
                                <form method="post" style="display:inline;" onsubmit="return demanderMotif(this)">
                                    <?= champ_csrf() ?>
                                    <input type="hidden" name="action" value="annuler">
                                    <input type="hidden" name="id" value="<?= $ligne['id'] ?>">
                                    <input type="hidden" name="motif">
                                    <button type="submit" class="danger">Annuler</button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <?= datalist('cultures', liste_cultures()) ?>
    <?= datalist('unites_semences', ['kg', 'doses', 'graines/ha']) ?>

    <script>
        // Préremplit la culture et la surface avec celles de la parcelle
        function choisirParcelle() {
            var option = document.getElementById('parcelle_id').selectedOptions[0];
            document.getElementById('culture').value = option.dataset.culture;
            document.getElementById('surface').value = option.dataset.surface;
            document.getElementById('surface').max = option.dataset.surface;
        }

        function demanderMotif(formulaire) {
            var motif = prompt("Motif de l'annulation (ex : erreur de saisie) :");
            if (!motif) {
                return false;
            }
            formulaire.motif.value = motif;
            return true;
        }
    </script>
</body>

</html>
