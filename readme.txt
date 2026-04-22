=== Prop Theme Changer ===
Contributors: princepeala
Tags: elementor, dark mode, theme switcher, color scheme, design
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 8.0
Stable tag: 4.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Système de thèmes visuels basé sur les Elementor Global Colors. Activez un dark mode, un thème saisonnier ou tout autre thème en un clic.

== Description ==

**Prop Theme Changer** vous permet de créer des thèmes visuels complets basés sur vos couleurs Elementor Global — sans toucher au code, sans variables CSS à gérer.

Chaque thème définit des remplacements couleur → couleur. Quand le thème est actif, l'attribut `data-theme="valeur"` est posé sur `<body>` et le CSS surcharge automatiquement les variables Elementor.

= Fonctionnalités =

* **Couleurs** : HEX, RGB et RGBA (avec opacité) supportés
* **Héritage** : un thème peut hériter des couleurs d'un thème parent
* **Conditions automatiques** : activation selon la préférence OS (dark/light), la plage horaire ou les pages visitées
* **Transitions** : animation fluide lors du changement de thème
* **Import / Export** : sauvegardez et transférez vos thèmes en JSON
* **Duplication** : clonez un thème existant en un clic
* **Shortcode** : `[ptc_toggle theme="dark" label="Mode sombre"]`
* **Widget Elementor** : glissez le widget "PTC Theme Toggle" directement dans votre page
* **REST API** : accédez à vos thèmes via `/wp-json/ptc/v1/themes`
* **Prévisualisation** : ajoutez `?ptc_preview=dark` à n'importe quelle URL
* **Persistance** : sessionStorage ou localStorage au choix
* **API JavaScript** : `window.PTC.activate()`, `.toggle()`, `.reset()`

== Installation ==

1. Téléchargez le plugin et décompressez l'archive
2. Copiez le dossier `prop-theme-changer` dans `/wp-content/plugins/`
3. Activez le plugin depuis **Extensions → Extensions installées**
4. Allez dans **Theme Changer** dans le menu admin

**Prérequis :** Elementor (gratuit ou Pro) avec un Kit actif configuré avec des couleurs globales.

== Frequently Asked Questions ==

= Le plugin fonctionne-t-il sans Elementor ? =
Non. Prop Theme Changer est conçu pour fonctionner avec les couleurs Elementor Global uniquement.

= Fonctionne-t-il avec Elementor Free ? =
Oui, Elementor Free suffit tant que vous avez configuré des couleurs globales dans votre Kit de site.

= Les thèmes sont-ils persistants entre les visites ? =
Oui. Vous pouvez choisir entre sessionStorage (onglet en cours) et localStorage (persistant) pour chaque thème.

= Puis-je avoir plusieurs thèmes actifs en même temps ? =
Non. Un seul thème peut être actif à la fois.

== Screenshots ==

1. Liste des thèmes dans l'admin
2. Page d'édition d'un thème avec sélecteur de couleurs
3. Conditions d'activation automatique
4. Widget Elementor PTC Theme Toggle

== Changelog ==

= 4.0.0 =
* Héritage de thème (thème parent)
* Conditions d'activation (OS, heure, pages)
* Transitions CSS configurables
* Import / Export JSON
* Duplication de thème
* Shortcode [ptc_toggle]
* Widget Elementor natif
* REST API publique
* Mode prévisualisation
* Palette de couleurs récentes
* Persistance localStorage ou sessionStorage
* Support RGBA (opacité)
* Attribut data-theme à la place des classes CSS
* Correction enregistrement des thèmes

= 1.0.0 =
* Version initiale

== Upgrade Notice ==

= 4.0.0 =
Mise à jour majeure. Les thèmes créés en versions précédentes sont rétrocompatibles automatiquement.
