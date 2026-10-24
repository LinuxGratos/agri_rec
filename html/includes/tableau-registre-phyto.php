<?php
// Tableau du registre pour un groupe (campagne + parcelle + culture).
// Utilisé par rapport-phyto.php (écran) et rapport-phyto-brut.php (impression).
// Attend la variable $groupe (voir registre_par_parcelle dans registre.php).
?>
<table class="registre">
    <thead>
        <tr>
            <th colspan="11">
                Campagne <?= echapper($groupe['annee_culturale']) ?> |
                Parcelle : <?= echapper($groupe['parcelle_nom']) ?> |
                Îlot : <?= echapper($groupe['ilot']) ?>
                <?php if ($groupe['latitude'] !== null): ?>
                | GPS : <?= echapper($groupe['latitude']) ?>, <?= echapper($groupe['longitude']) ?>
                <?php endif; ?>
                | Culture : <?= echapper($groupe['culture']) ?>
            </th>
        </tr>
        <tr>
            <th>Date</th>
            <th>Heures</th>
            <th>Stade</th>
            <th>Surface traitée</th>
            <th>Produit</th>
            <th>AMM</th>
            <th>Quantité</th>
            <th>Dose/ha</th>
            <th>Cible</th>
            <th>Délai de rentrée</th>
            <th>Réalisé par</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($groupe['lignes'] as $ligne): ?>
        <tr>
            <td><?= date('d/m/Y', strtotime($ligne['date'])) ?></td>
            <td><?= echapper($ligne['heure_debut']) ?><?= $ligne['heure_fin'] ? ' - ' . echapper($ligne['heure_fin']) : '' ?></td>
            <td>
                <?= $ligne['stade_bbch'] !== null ? 'BBCH ' . echapper($ligne['stade_bbch']) : '' ?>
                <?= echapper($ligne['stade']) ?>
            </td>
            <td><?= echapper($ligne['surface_traitee']) ?> ha</td>
            <td>
                <?= echapper($ligne['produit_nom']) ?>
                <?= $ligne['type'] === 'adjuvant' ? '(adjuvant)' : '' ?>
                <?= $ligne['bio'] ? '(AB)' : '' ?>
            </td>
            <td><?= echapper($ligne['amm']) ?></td>
            <td><?= echapper($ligne['quantite']) ?> <?= echapper($ligne['unite']) ?></td>
            <td><?= echapper($ligne['dose_par_ha']) ?> <?= echapper($ligne['unite']) ?>/ha</td>
            <td><?= echapper($ligne['cible']) ?></td>
            <td><?= $ligne['delai_rentree'] !== null ? echapper($ligne['delai_rentree']) . ' h' : '' ?></td>
            <td><?= echapper(libelle_realise_par($ligne)) ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
