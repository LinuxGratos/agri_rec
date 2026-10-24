<?php
// Listes de choix proposées dans les formulaires.
// Vous pouvez ajouter ou retirer des lignes librement : ce ne sont que des
// suggestions, l'utilisateur peut toujours saisir une autre valeur.

// Cultures proposées (champ "Culture")
function liste_cultures() {
    return [
        "Blé tendre d'hiver", "Blé tendre de printemps", "Blé dur", "Orge d'hiver",
        "Orge de printemps", "Triticale", "Seigle", "Avoine", "Maïs grain",
        "Maïs ensilage", "Sorgho", "Colza d'hiver", "Tournesol", "Soja", "Pois",
        "Féverole", "Lin", "Betterave sucrière", "Pomme de terre",
        "Prairie temporaire", "Prairie permanente", "Luzerne", "Vigne", "Verger",
    ];
}

// Grands stades de l'échelle BBCH (code à 2 chiffres de 00 à 99).
// Le code précis dépend de la culture : voir la fiche BBCH de la culture.
function liste_stades_bbch() {
    return [
        '00' => 'Germination, levée',
        '10' => 'Développement des feuilles',
        '21' => 'Début tallage / ramification',
        '30' => 'Début montaison / élongation de la tige',
        '32' => '2 nœuds',
        '39' => 'Dernière feuille étalée',
        '51' => 'Début épiaison / apparition des boutons',
        '61' => 'Début floraison',
        '65' => 'Pleine floraison',
        '71' => 'Formation des grains / fruits',
        '85' => 'Pâteux / maturation',
        '89' => 'Maturité complète',
        '97' => 'Plante desséchée',
    ];
}

// Unités de quantité des produits
function liste_unites() {
    return ['L', 'kg', 'g', 'mL'];
}

// Qui a réalisé le traitement
function liste_realise_par() {
    return [
        'exploitation' => "L'exploitation",
        'prestataire' => 'Un prestataire de service',
        'entraide' => 'Entraide agricole',
    ];
}

// Écrit une <datalist> HTML à partir d'une liste de valeurs
function datalist($id, $valeurs) {
    $html = '<datalist id="' . echapper($id) . '">';
    foreach ($valeurs as $cle => $valeur) {
        if (!array_is_list($valeurs)) {
            // Liste "code => libellé" : on propose le code, avec le libellé en aide
            $html .= '<option value="' . echapper((string) $cle) . '">' . echapper($cle . ' - ' . $valeur) . '</option>';
        } else {
            $html .= '<option value="' . echapper($valeur) . '"></option>';
        }
    }
    return $html . '</datalist>';
}
?>
