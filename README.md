## agri_rec s'installe simplement sur votre serveur Web.

### Ce projet fonctionne correctement sur Debian 13 et PHP 8.4.

### Installation

1. Copiez le projet complet, par exemple dans `/var/www/agri_rec`.
2. Faites pointer le serveur Web (DocumentRoot) sur le dossier **`html`** : `/var/www/agri_rec/html`.
3. Donnez au serveur Web le droit d'écrire dans le dossier des données :
   `sudo chown www-data:www-data /var/www/agri_rec/data`
4. Ouvrez le site et créez tout de suite le compte (la première personne qui ouvre le site crée le compte).

La base de données est enregistrée dans `data/database.sqlite`, **en dehors** du dossier `html`,
pour qu'elle ne puisse pas être téléchargée depuis Internet.
Si votre base se trouvait auparavant dans `html/database.sqlite`, elle est déplacée automatiquement
dans `data/` au premier accès (une copie de sauvegarde est faite avant chaque mise à jour de la base).

Si vous aviez copié uniquement le contenu de `html` dans `/var/www/html`, le dossier des données
attendu est `/var/www/data` : créez-le avec `sudo mkdir /var/www/data && sudo chown www-data:www-data /var/www/data`.

### Sécurité

En cas d'exposition extérieure à votre réseau local, veillez à configurer votre serveur Web pour que la communication soit sécurisée (HTTPS).

Ne laissez jamais `DEBUG_MODE` à `true` dans `html/includes/config.php` en dehors d'un dépannage.

### Sauvegarde

Depuis le tableau de bord, la carte **Sauvegarde** télécharge une copie complète de vos données.
Pour restaurer, remplacez le fichier `data/database.sqlite` par la copie téléchargée (renommée `database.sqlite`).

### Utilisation

Le couple utilisateur /mot de passe est à créer à la première connexion.
Vous serez normalement guidé pendant le processus.

Le projet a pour but d'enregistrer simplement ses interventions culturales dans des formulaires simples.

En utilisant l'impression PDF, vous exporterez sous un format commun les visualisations des interventions d'engrais et de phytosanitaires.

Le projet ne réalise absolument aucun contrôle sur les mélanges ou si la matière active utilisée est autorisée ou non, vous devrez vous en assurer via les sites comme https://melanges.arvalisinstitutduvegetal.fr/ et https://ephy.anses.fr/ à minima.

Le projet n'a aucune connaissance de votre plan prévisionnel de fumure, veuillez à respecter celui ci lors de vos enregistrements.

### Pour les personnes qui modifient le code

- Tout texte affiché dans une page passe par `echapper(...)`.
- Tout formulaire `method="post"` contient `<?= champ_csrf() ?>`.
- Chaque page réservée commence par `require_once 'includes/securite.php';` puis `exiger_connexion();`.
- Pour changer la structure de la base, ajoutez une étape dans `html/includes/migrations.php`.
