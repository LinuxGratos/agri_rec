<?php
require_once 'includes/securite.php';
require_once 'includes/registre.php';
exiger_connexion();

$db = getDB();
$message_erreur = '';

// Enregistre une intervention et ses produits.
// Renvoie un message d'erreur, ou '' si tout s'est bien passé.
function enregistrer_intervention($db, $saisie)
{
    $stmt = $db->prepare('SELECT * FROM parcelles WHERE id = :id');
    $stmt->bindValue(':id', intval($saisie['parcelle_id']), SQLITE3_INTEGER);
    $parcelle = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    if (!$parcelle) {
        return 'Choisissez une parcelle.';
    }

    $date = trim($saisie['date']);
    $heure_debut = trim($saisie['heure']);
    $heure_fin = trim($saisie['heure_fin'] ?? '');
    $culture = trim($saisie['culture']);
    $surface_traitee = floatval(str_replace(',', '.', $saisie['surface_traitee']));
    $stade_bbch = trim($saisie['stade_bbch'] ?? '');
    $realise_par = $saisie['realise_par'] ?? '';
    $intervenant_nom = trim($saisie['intervenant_nom'] ?? '');
    $intervenant_siret = nettoyer_siret($saisie['intervenant_siret'] ?? '');

    if ($heure_fin !== '' && $heure_fin < $heure_debut) {
        return "L'heure de fin doit être après l'heure de début.";
    }
    if ($culture === '') {
        return 'Indiquez la culture traitée.';
    }
    if ($surface_traitee <= 0 || $surface_traitee > $parcelle['surface']) {
        return 'La surface traitée doit être comprise entre 0 et la surface de la parcelle (' . $parcelle['surface'] . ' ha).';
    }
    if ($stade_bbch !== '' && !preg_match('/^[0-9]{2}$/', $stade_bbch)) {
        return 'Le stade BBCH est un code à 2 chiffres (00 à 99).';
    }
    if (!array_key_exists($realise_par, liste_realise_par())) {
        return 'Indiquez qui a réalisé le traitement.';
    }
    if ($realise_par === 'exploitation') {
        $intervenant_nom = '';
        $intervenant_siret = '';
    } elseif ($intervenant_nom === '' || !siret_valide($intervenant_siret)) {
        return "Pour un prestataire ou une entraide, indiquez le nom et le SIRET (14 chiffres) de l'intervenant.";
    }

    // Produits du mélange, avec une copie des informations du catalogue
    $produits = [];
    foreach ($saisie['produit_id'] ?? [] as $i => $produit_id) {
        $stmt = $db->prepare('SELECT * FROM produits_phytosanitaires WHERE id = :id');
        $stmt->bindValue(':id', intval($produit_id), SQLITE3_INTEGER);
        $produit = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
        $quantite = floatval(str_replace(',', '.', $saisie['volume_total'][$i]));
        $cible = trim($saisie['cible'][$i]);
        if (!$produit || $quantite <= 0 || $cible === '') {
            return 'Pour chaque produit, indiquez le produit, la quantité et la cible.';
        }
        $produits[] = ['catalogue' => $produit, 'quantite' => $quantite, 'cible' => $cible];
    }
    if (!$produits) {
        return 'Ajoutez au moins un produit.';
    }

    $db->exec('BEGIN TRANSACTION');

    $stmt = $db->prepare('INSERT INTO interventions_phytosanitaires
        (parcelle_id, date, heure_fin, annee_culturale, stade, stade_bbch, culture, ilot, surface_traitee,
         realise_par, intervenant_nom, intervenant_siret, saisi_le)
        VALUES (:parcelle_id, :date, :heure_fin, :annee_culturale, :stade, :stade_bbch, :culture, :ilot, :surface_traitee,
         :realise_par, :intervenant_nom, :intervenant_siret, :saisi_le)');
    $stmt->bindValue(':parcelle_id', $parcelle['id'], SQLITE3_INTEGER);
    $stmt->bindValue(':date', $date . ' ' . $heure_debut, SQLITE3_TEXT);
    $stmt->bindValue(':heure_fin', $heure_fin === '' ? null : $heure_fin, SQLITE3_TEXT);
    $stmt->bindValue(':annee_culturale', intval($saisie['annee_culturale']), SQLITE3_INTEGER);
    $stmt->bindValue(':stade', trim($saisie['stade'] ?? ''), SQLITE3_TEXT);
    $stmt->bindValue(':stade_bbch', $stade_bbch === '' ? null : $stade_bbch, SQLITE3_TEXT);
    $stmt->bindValue(':culture', $culture, SQLITE3_TEXT);
    $stmt->bindValue(':ilot', (string) $parcelle['ilot'], SQLITE3_TEXT);
    $stmt->bindValue(':surface_traitee', $surface_traitee, SQLITE3_FLOAT);
    $stmt->bindValue(':realise_par', $realise_par, SQLITE3_TEXT);
    $stmt->bindValue(':intervenant_nom', $intervenant_nom, SQLITE3_TEXT);
    $stmt->bindValue(':intervenant_siret', $intervenant_siret, SQLITE3_TEXT);
    $stmt->bindValue(':saisi_le', date('Y-m-d H:i:s'), SQLITE3_TEXT);
    $stmt->execute();

    $intervention_id = $db->lastInsertRowID();

    foreach ($produits as $p) {
        $stmt = $db->prepare('INSERT INTO details_interventions_phytosanitaires
            (intervention_id, produit_id, volume_total, cible, produit_nom, amm, unite, type, delai_rentree, bio)
            VALUES (:intervention_id, :produit_id, :volume_total, :cible, :produit_nom, :amm, :unite, :type, :delai_rentree, :bio)');
        $stmt->bindValue(':intervention_id', $intervention_id, SQLITE3_INTEGER);
        $stmt->bindValue(':produit_id', $p['catalogue']['id'], SQLITE3_INTEGER);
        $stmt->bindValue(':volume_total', $p['quantite'], SQLITE3_FLOAT);
        $stmt->bindValue(':cible', $p['cible'], SQLITE3_TEXT);
        $stmt->bindValue(':produit_nom', $p['catalogue']['nom'], SQLITE3_TEXT);
        $stmt->bindValue(':amm', $p['catalogue']['amm'], SQLITE3_TEXT);
        $stmt->bindValue(':unite', $p['catalogue']['unite_emballage'], SQLITE3_TEXT);
        $stmt->bindValue(':type', $p['catalogue']['type'], SQLITE3_TEXT);
        $stmt->bindValue(':delai_rentree', $p['catalogue']['delai_rentree'], SQLITE3_INTEGER);
        $stmt->bindValue(':bio', $p['catalogue']['bio'], SQLITE3_INTEGER);
        $stmt->execute();
    }

    $db->exec('COMMIT');
    return '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($_POST['action'] ?? '') {
        case 'create':
            $message_erreur = enregistrer_intervention($db, $_POST);
            break;

        // Une intervention n'est jamais effacée : elle est annulée avec un motif
        // et reste visible (barrée) dans l'historique.
        case 'annuler':
            $motif = trim($_POST['motif']);
            if ($motif === '') {
                $message_erreur = "Indiquez le motif de l'annulation.";
                break;
            }
            $stmt = $db->prepare('UPDATE interventions_phytosanitaires SET annule_le = :annule_le, motif_annulation = :motif
                                  WHERE id = :id AND annule_le IS NULL');
            $stmt->bindValue(':annule_le', date('Y-m-d H:i:s'), SQLITE3_TEXT);
            $stmt->bindValue(':motif', $motif, SQLITE3_TEXT);
            $stmt->bindValue(':id', intval($_POST['id']), SQLITE3_INTEGER);
            $stmt->execute();
            break;
    }

    // Recharge la page pour éviter un double enregistrement en cas d'actualisation
    if ($message_erreur === '') {
        header('Location: interventions_phyto.php');
        exit();
    }
}

$parcelles = $db->query('SELECT * FROM parcelles ORDER BY nom');
$produits = $db->query('SELECT * FROM produits_phytosanitaires ORDER BY nom');
$interventions = $db->query('SELECT ip.*, p.nom AS parcelle_nom
                             FROM interventions_phytosanitaires ip
                             JOIN parcelles p ON ip.parcelle_id = p.id
                             ORDER BY ip.date');
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Interventions Phyto - AgriRec</title>
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
                <li><a href="phytosanitaires.php">Catalogue Phyto</a></li>
                <li><a href="rapport-phyto.php">Rapports</a></li>
            </ul>
        </nav>
        <form id="logout" action="logout.php" method="post">
            <?= champ_csrf() ?>
            <button class="danger">Déconnexion</button>
        </form>
    </header>

    <div class="container">
        <div style="margin-bottom: 2rem;">
            <h1>Interventions Phytosanitaires</h1>
            <p style="color: var(--text-muted);">Enregistrez les traitements de protection des cultures par parcelle,
                sans délai après chaque traitement.</p>
        </div>

        <?php if ($message_erreur): ?>
        <div class="message-erreur"><?= echapper($message_erreur) ?></div>
        <?php endif; ?>

        <section class="card">
            <h3>Saisir une nouvelle intervention</h3>
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
                        <label>Culture traitée</label>
                        <input type="text" name="culture" id="culture" list="cultures" required>
                    </div>
                    <div class="champ">
                        <label>Surface traitée (ha)</label>
                        <input type="number" name="surface_traitee" id="surface_traitee" step="0.01" min="0.01" required>
                    </div>
                </div>

                <div class="grille" style="margin-bottom: 1rem;">
                    <div class="champ">
                        <label>Date</label>
                        <input type="date" name="date" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="champ">
                        <label>Heure de début</label>
                        <input type="time" name="heure" value="<?= date('H:i') ?>" required>
                    </div>
                    <div class="champ">
                        <label>Heure de fin</label>
                        <input type="time" name="heure_fin">
                        <small>Obligatoire si l'étiquette impose un horaire</small>
                    </div>
                    <div class="champ">
                        <label>Stade BBCH</label>
                        <input type="text" name="stade_bbch" list="stades_bbch" pattern="[0-9]{2}" placeholder="ex: 32">
                        <small>Obligatoire si l'autorisation du produit limite le stade</small>
                    </div>
                    <div class="champ">
                        <label>Stade (description)</label>
                        <input type="text" name="stade" placeholder="ex: 2 nœuds">
                    </div>
                </div>

                <div class="grille" style="margin-bottom: 2rem; padding-bottom: 2rem; border-bottom: 1px solid var(--border-color);">
                    <div class="champ">
                        <label>Traitement réalisé par</label>
                        <select name="realise_par" id="realise_par" onchange="afficherIntervenant()">
                            <?php foreach (liste_realise_par() as $valeur => $libelle): ?>
                            <option value="<?= $valeur ?>"><?= echapper($libelle) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="champ intervenant" style="display:none;">
                        <label>Nom de l'intervenant</label>
                        <input type="text" name="intervenant_nom">
                    </div>
                    <div class="champ intervenant" style="display:none;">
                        <label>SIRET de l'intervenant</label>
                        <input type="text" name="intervenant_siret" placeholder="14 chiffres">
                    </div>
                </div>

                <h4 style="margin-bottom: 1rem;">Produits utilisés dans ce mélange</h4>
                <div id="produits-container">
                    <div class="card ligne-produit" style="background: #f8f9fa; padding: 1rem; margin-bottom: 1rem;">
                        <div class="grille">
                            <div class="champ">
                                <label>Produit ou adjuvant</label>
                                <select name="produit_id[]" required>
                                    <option value="" disabled selected>Choisir un produit</option>
                                    <?php while ($produit = $produits->fetchArray(SQLITE3_ASSOC)): ?>
                                    <option value="<?= $produit['id'] ?>">
                                        <?= echapper($produit['nom']) ?> (<?= echapper($produit['unite_emballage']) ?>)<?= $produit['type'] === 'adjuvant' ? ' - adjuvant' : '' ?>
                                    </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="champ">
                                <label>Quantité totale utilisée</label>
                                <input type="number" name="volume_total[]" step="0.001" min="0.001" placeholder="ex: 2.5" required>
                                <small>Dans l'unité du produit, pour toute la surface traitée</small>
                            </div>
                            <div class="champ">
                                <label>Cible</label>
                                <input type="text" name="cible[]" placeholder="ex: Adventices" required>
                            </div>
                            <div style="display: flex; align-items: flex-end;">
                                <button type="button" class="danger" onclick="retirerProduit(this)" style="width:100%;">Retirer</button>
                            </div>
                        </div>
                    </div>
                </div>

                <button type="button" class="secondary" onclick="ajouterProduit()">+ Ajouter un autre produit à ce mélange</button>
                <div style="margin-top: 2rem;">
                    <input type="submit" value="Enregistrer l'intervention">
                </div>
            </form>
        </section>

        <section>
            <h3>Historique des interventions phyto</h3>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Campagne</th>
                            <th>Parcelle (îlot)</th>
                            <th>Date / Heures</th>
                            <th>Culture / Stade</th>
                            <th>Produits & doses</th>
                            <th>Réalisé par</th>
                            <th style="text-align: center;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($intervention = $interventions->fetchArray(SQLITE3_ASSOC)): ?>
                        <tr class="<?= $intervention['annule_le'] ? 'annulee' : '' ?>">
                            <td><span class="badge" style="background: var(--primary-color); color: white;">
                                    <?= echapper($intervention['annee_culturale']) ?>
                                </span>
                            </td>
                            <td>
                                <div><?= echapper($intervention['parcelle_nom']) ?></div>
                                <div style="font-size: 0.8rem; color: var(--text-muted);">Îlot : <?= echapper($intervention['ilot']) ?></div>
                                <div style="font-size: 0.8rem; color: var(--text-muted);"><?= echapper($intervention['surface_traitee']) ?> ha traités</div>
                            </td>
                            <td>
                                <div style="font-weight: 600;"><?= date('d/m/Y', strtotime($intervention['date'])) ?></div>
                                <div style="font-size: 0.8rem; color: var(--text-muted);">
                                    <?= date('H:i', strtotime($intervention['date'])) ?><?= $intervention['heure_fin'] ? ' - ' . echapper($intervention['heure_fin']) : '' ?>
                                </div>
                            </td>
                            <td>
                                <div><?= echapper($intervention['culture']) ?></div>
                                <div style="font-size: 0.8rem; color: var(--text-muted);">
                                    <?= $intervention['stade_bbch'] !== null ? 'BBCH ' . echapper($intervention['stade_bbch']) : '' ?>
                                    <?= echapper($intervention['stade']) ?>
                                </div>
                            </td>
                            <td>
                                <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                                    <?php
                                    $details = $db->prepare('SELECT * FROM details_interventions_phytosanitaires WHERE intervention_id = :id');
                                    $details->bindValue(':id', $intervention['id'], SQLITE3_INTEGER);
                                    $resultat = $details->execute();
                                    while ($detail = $resultat->fetchArray(SQLITE3_ASSOC)):
                                    ?>
                                    <div style="background: #f1f3f5; padding: 0.5rem; border-radius: 6px; font-size: 0.85rem;">
                                        <b><?= echapper($detail['produit_nom']) ?></b>
                                        <?php if ($detail['type'] === 'adjuvant'): ?><span class="etiquette">adjuvant</span><?php endif; ?>
                                        <br>AMM <?= echapper($detail['amm']) ?> :
                                        <?= echapper($detail['volume_total']) ?> <?= echapper($detail['unite']) ?>
                                        (<span style="color: var(--primary-color); font-weight: 600;">
                                            <?= dose_par_ha($detail['volume_total'], $intervention['surface_traitee']) ?> <?= echapper($detail['unite']) ?>/ha
                                        </span>)
                                        <br><small style="color: var(--text-muted);">Cible : <?= echapper($detail['cible']) ?></small>
                                    </div>
                                    <?php endwhile; ?>
                                </div>
                            </td>
                            <td style="font-size: 0.85rem;"><?= echapper(libelle_realise_par($intervention)) ?></td>
                            <td style="text-align: center;">
                                <?php if ($intervention['annule_le']): ?>
                                <small>Annulée le <?= date('d/m/Y', strtotime($intervention['annule_le'])) ?> :
                                    <?= echapper($intervention['motif_annulation']) ?></small>
                                <?php else: ?>
                                <form method="post" style="display:inline;" onsubmit="return demanderMotif(this)">
                                    <?= champ_csrf() ?>
                                    <input type="hidden" name="action" value="annuler">
                                    <input type="hidden" name="id" value="<?= $intervention['id'] ?>">
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
    <?= datalist('stades_bbch', liste_stades_bbch()) ?>

    <script>
        // Préremplit la culture et la surface traitée avec celles de la parcelle
        function choisirParcelle() {
            var option = document.getElementById('parcelle_id').selectedOptions[0];
            document.getElementById('culture').value = option.dataset.culture;
            document.getElementById('surface_traitee').value = option.dataset.surface;
            document.getElementById('surface_traitee').max = option.dataset.surface;
        }

        // Affiche le nom et le SIRET de l'intervenant pour un prestataire ou une entraide
        function afficherIntervenant() {
            var visible = document.getElementById('realise_par').value !== 'exploitation';
            document.querySelectorAll('.intervenant').forEach(function (champ) {
                champ.style.display = visible ? 'block' : 'none';
                champ.querySelector('input').required = visible;
            });
        }

        // Ajoute une ligne produit en copiant la première
        function ajouterProduit() {
            var lignes = document.querySelectorAll('.ligne-produit');
            var nouvelle = lignes[0].cloneNode(true);
            nouvelle.querySelectorAll('input').forEach(function (champ) { champ.value = ''; });
            nouvelle.querySelector('select').selectedIndex = 0;
            document.getElementById('produits-container').appendChild(nouvelle);
        }

        function retirerProduit(bouton) {
            if (document.querySelectorAll('.ligne-produit').length > 1) {
                bouton.closest('.ligne-produit').remove();
            }
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
