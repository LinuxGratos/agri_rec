<?php
require_once 'includes/securite.php';
require_once 'includes/registre.php';
exiger_connexion();

$db = getDB();

function clean_input($data)
{
    return trim($data);
}

// Délai de rentrée en heures : vide = non renseigné
function delai_rentree($valeur)
{
    return trim($valeur) === '' ? null : intval($valeur);
}

$message_erreur = '';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (isset($_POST["action"])) {
        switch ($_POST["action"]) {
            case "create":
                $nom = clean_input($_POST["nom"]);
                $unite_emballage = clean_input($_POST["unite_emballage"]);
                $amm = clean_input($_POST["amm"]);
                if (!amm_valide($amm)) {
                    $message_erreur = "Le n° d'AMM doit comporter 7 chiffres (voir ephy.anses.fr).";
                    break;
                }

                $stmt = $db->prepare(
                    "INSERT INTO produits_phytosanitaires (nom, unite_emballage, amm, type, delai_rentree, bio) VALUES (:nom, :unite_emballage, :amm, :type, :delai_rentree, :bio)",
                );
                $stmt->bindValue(":type", $_POST["type"] === "adjuvant" ? "adjuvant" : "produit", SQLITE3_TEXT);
                $stmt->bindValue(":delai_rentree", delai_rentree($_POST["delai_rentree"]), SQLITE3_INTEGER);
                $stmt->bindValue(":bio", isset($_POST["bio"]) ? 1 : 0, SQLITE3_INTEGER);
                $stmt->bindValue(":nom", $nom, SQLITE3_TEXT);
                $stmt->bindValue(
                    ":unite_emballage",
                    $unite_emballage,
                    SQLITE3_TEXT,
                );
                $stmt->bindValue(":amm", $amm, SQLITE3_TEXT);
                $stmt->execute();
                break;

            case "update":
                $id = intval($_POST["id"]);
                $nom = clean_input($_POST["nom"]);
                $unite_emballage = clean_input($_POST["unite_emballage"]);
                $amm = clean_input($_POST["amm"]);
                if (!amm_valide($amm)) {
                    $message_erreur = "Le n° d'AMM doit comporter 7 chiffres (voir ephy.anses.fr).";
                    break;
                }

                // Les interventions déjà enregistrées gardent leur copie : le registre ne change pas.
                $stmt = $db->prepare(
                    "UPDATE produits_phytosanitaires SET nom = :nom, unite_emballage = :unite_emballage, amm = :amm, type = :type, delai_rentree = :delai_rentree, bio = :bio WHERE id = :id",
                );
                $stmt->bindValue(":type", $_POST["type"] === "adjuvant" ? "adjuvant" : "produit", SQLITE3_TEXT);
                $stmt->bindValue(":delai_rentree", delai_rentree($_POST["delai_rentree"]), SQLITE3_INTEGER);
                $stmt->bindValue(":bio", isset($_POST["bio"]) ? 1 : 0, SQLITE3_INTEGER);
                $stmt->bindValue(":id", $id, SQLITE3_INTEGER);
                $stmt->bindValue(":nom", $nom, SQLITE3_TEXT);
                $stmt->bindValue(
                    ":unite_emballage",
                    $unite_emballage,
                    SQLITE3_TEXT,
                );
                $stmt->bindValue(":amm", $amm, SQLITE3_TEXT);
                $stmt->execute();
                break;

            case "delete":
                $id = intval($_POST["id"]);

                $stmt = $db->prepare(
                    "DELETE FROM produits_phytosanitaires WHERE id = :id",
                );
                $stmt->bindValue(":id", $id, SQLITE3_INTEGER);
                try {
                    $stmt->execute();
                } catch (Exception $e) {
                    $message_erreur = "Ce produit figure dans le registre : il ne peut pas être supprimé.";
                }
                break;
        }
    }
}

$produits = $db->query("SELECT * FROM produits_phytosanitaires ORDER BY nom");
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Catalogue Phytosanitaires - AgriRec</title>
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
                <li><a href="parcelles.php">Parcelles</a></li>
                <li><a href="interventions_phyto.php">Nouvelle Intervention</a></li>
            </ul>
        </nav>
        <form id="logout" action="logout.php" method="post">
            <?= champ_csrf() ?>
            <button class="danger">Déconnexion</button>
        </form>
    </header>

    <div class="container">
        <div style="margin-bottom: 2rem;">
            <h1>Catalogue Phytosanitaires</h1>
            <p style="color: var(--text-muted);">Gérez votre catalogue de produits de protection des cultures.</p>
        </div>

        <?php if ($message_erreur): ?>
        <div class="message-erreur"><?= echapper($message_erreur) ?></div>
        <?php endif; ?>

        <section class="card">
            <h3>Ajouter un produit</h3>
            <form method="post">
                <?= champ_csrf() ?>
                <input type="hidden" name="action" value="create">
                <div
                    style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; width: 100%;">
                    <div>
                        <label style="display:block; font-size: 0.8rem; font-weight: 600; margin-bottom: 0.25rem;">Nom
                            du produit</label>
                        <input type="text" name="nom" placeholder="ex: RoundUp" required style="width:100%;" autofocus>
                    </div>
                    <div>
                        <label style="display:block; font-size: 0.8rem; font-weight: 600; margin-bottom: 0.25rem;">Unité</label>
                        <input type="text" name="unite_emballage" list="unites" placeholder="ex: L" required style="width:100%;">
                    </div>
                    <div>
                        <label style="display:block; font-size: 0.8rem; font-weight: 600; margin-bottom: 0.25rem;">N°
                            AMM</label>
                        <input type="text" name="amm" placeholder="ex: 2040523" pattern="[0-9]{7}" required style="width:100%;">
                    </div>
                </div>
                <div class="grille" style="margin-top: 1rem;">
                    <div class="champ">
                        <label>Type</label>
                        <select name="type">
                            <option value="produit">Produit phytopharmaceutique</option>
                            <option value="adjuvant">Adjuvant</option>
                        </select>
                    </div>
                    <div class="champ">
                        <label>Délai de rentrée (heures)</label>
                        <input type="number" name="delai_rentree" min="0" step="1" placeholder="ex: 6, 24 ou 48">
                    </div>
                    <div class="champ">
                        <label><input type="checkbox" name="bio" style="width:auto;"> Utilisable en agriculture biologique</label>
                    </div>
                </div>
                <p style="color: var(--text-muted); font-size: 0.8rem;">Le nom commercial complet, l'AMM et le délai de rentrée
                    figurent sur l'étiquette et sur <a href="https://ephy.anses.fr/" target="_blank">ephy.anses.fr</a>.</p>
                <div style="margin-top: 1.5rem; text-align: right; width: 100%;">
                    <input type="submit" value="Enregistrer le produit">
                </div>
            </form>
        </section>

        <section>
            <h3>Catalogue des produits</h3>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Nom</th>
                            <th>Unité</th>
                            <th>AMM</th>
                            <th>Type</th>
                            <th>Délai de rentrée</th>
                            <th style="text-align: center;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($produit = $produits->fetchArray(SQLITE3_ASSOC)): ?>
                        <tr>
                            <td style="font-weight: 600;">
                                <?php echo echapper($produit["nom"]); ?>
                            </td>
                            <td style="color: var(--text-muted);">
                                <?php echo echapper($produit["unite_emballage"]); ?>
                            </td>
                            <td><?php echo echapper($produit["amm"]); ?></td>
                            <td>
                                <?= $produit["type"] === "adjuvant" ? "Adjuvant" : "Produit" ?>
                                <?php if ($produit["bio"]): ?><span class="etiquette">AB</span><?php endif; ?>
                            </td>
                            <td><?= $produit["delai_rentree"] !== null ? echapper($produit["delai_rentree"]) . " h" : "" ?></td>
                            <td style="text-align: center;">
                                <div>
                                    <button class="secondary"
                                        onclick="showUpdateForm(<?php echo echapper(json_encode($produit)); ?>)">Modifier</button>
                                    <form method="post" style="display:inline;">
                                        <?= champ_csrf() ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo $produit["id"]; ?>">
                                        <button type="submit" class="danger"
                                            onclick="return confirm('Supprimer ce produit ?');">Supprimer</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php
endwhile; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Modal de modification -->
        <div id="updateFormBackdrop"
            style="display:none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
            <div class="card" style="width: 100%; max-width: 600px; margin: 2rem;">
                <h3>Modifier le produit</h3>
                <form method="post">
                    <?= champ_csrf() ?>
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="id" id="update_id">

                    <div style="width: 100%; margin-bottom: 1rem;">
                        <label
                            style="display:block; font-size: 0.8rem; font-weight: 600; margin-bottom: 0.25rem;">Nom</label>
                        <input type="text" name="nom" id="update_nom" required style="width:100%;">
                    </div>

                    <div
                        style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; width: 100%; margin-bottom: 1.5rem;">
                        <div>
                            <label
                                style="display:block; font-size: 0.8rem; font-weight: 600; margin-bottom: 0.25rem;">Unité</label>
                            <input type="text" name="unite_emballage" id="update_unite_emballage" list="unites" required
                                style="width:100%;">
                        </div>
                        <div>
                            <label
                                style="display:block; font-size: 0.8rem; font-weight: 600; margin-bottom: 0.25rem;">AMM</label>
                            <input type="text" name="amm" id="update_amm" pattern="[0-9]{7}" required style="width:100%;">
                        </div>
                    </div>

                    <div class="grille" style="margin-bottom: 1.5rem;">
                        <div class="champ">
                            <label>Type</label>
                            <select name="type" id="update_type">
                                <option value="produit">Produit phytopharmaceutique</option>
                                <option value="adjuvant">Adjuvant</option>
                            </select>
                        </div>
                        <div class="champ">
                            <label>Délai de rentrée (heures)</label>
                            <input type="number" name="delai_rentree" id="update_delai_rentree" min="0" step="1">
                        </div>
                        <div class="champ">
                            <label><input type="checkbox" name="bio" id="update_bio" style="width:auto;"> Utilisable en AB</label>
                        </div>
                    </div>
                    <p style="color: var(--text-muted); font-size: 0.8rem;">Les interventions déjà enregistrées ne sont pas modifiées.</p>

                    <div style="display: flex; justify-content: flex-end; gap: 1rem; width: 100%;">
                        <button type="button" class="secondary" onclick="hideUpdateForm()">Annuler</button>
                        <input type="submit" value="Mettre à jour">
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?= datalist('unites', liste_unites()) ?>

    <script>
        function showUpdateForm(produit) {
            document.getElementById('updateFormBackdrop').style.display = 'flex';
            document.getElementById('update_id').value = produit.id;
            document.getElementById('update_nom').value = produit.nom;
            document.getElementById('update_unite_emballage').value = produit.unite_emballage;
            document.getElementById('update_amm').value = produit.amm;
            document.getElementById('update_type').value = produit.type;
            document.getElementById('update_delai_rentree').value = produit.delai_rentree ?? '';
            document.getElementById('update_bio').checked = produit.bio == 1;
        }

        function hideUpdateForm() {
            document.getElementById('updateFormBackdrop').style.display = 'none';
        }
    </script>
</body>

</html>
