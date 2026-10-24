<?php
// Tableau des semis de semences traitées.
// Utilisé par rapport-phyto.php (écran) et rapport-phyto-brut.php (impression).
// Attend la variable $semis (voir semis_traites dans registre.php).
?>
<table class="registre">
    <thead>
        <tr>
            <th colspan="8">Semis de semences traitées</th>
        </tr>
        <tr>
            <th>Campagne</th>
            <th>Date</th>
            <th>Parcelle</th>
            <th>Îlot</th>
            <th>Culture</th>
            <th>Surface</th>
            <th>Produit de traitement (AMM)</th>
            <th>Semences</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($semis as $ligne): ?>
        <tr>
            <td><?= echapper($ligne['annee_culturale']) ?></td>
            <td><?= date('d/m/Y', strtotime($ligne['date'])) ?></td>
            <td><?= echapper($ligne['parcelle_nom']) ?></td>
            <td><?= echapper($ligne['ilot']) ?></td>
            <td><?= echapper($ligne['culture']) ?></td>
            <td><?= echapper($ligne['surface']) ?> ha</td>
            <td><?= echapper($ligne['produit_nom']) ?> (<?= echapper($ligne['amm']) ?>)</td>
            <td><?= echapper(trim($ligne['quantite_semences'] . ' ' . $ligne['unite_semences'])) ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
