# Prop Theme Changer – WordPress Plugin

Système de thèmes visuels basé sur les **Elementor Global Colors**.
Permet de remplacer couleur → couleur sans variables CSS exposées.

---

## Installation

1. Copiez le dossier `prop-theme-changer/` dans `/wp-content/plugins/`.
2. Activez le plugin depuis **Extensions → Extensions installées**.
3. Allez dans **Theme Changer** dans le menu admin.

---

## Prérequis

- WordPress 5.8+
- Elementor (gratuit ou Pro) avec au moins un **Kit actif** configuré avec des couleurs globales.

---

## Utilisation

### 1. Créer un thème

- Cliquez **Ajouter un thème**.
- Renseignez :
  - **Nom** : libellé interne (ex: `Dark Mode`)
  - **Classe CSS** : classe ajoutée sur `<body>` (ex: `dark-mode`)
  - **ID déclencheur** : ID HTML d'un bouton sur votre page (ex: `theme-toggle`)

### 2. Définir les remplacements de couleurs

Pour chaque couleur Elementor, saisissez la couleur de remplacement en hex.
Laissez vide pour ne pas remplacer une couleur.

### 3. Ajouter le bouton sur votre page

Dans Elementor, ajoutez un bouton ou n'importe quel élément et donnez-lui l'ID correspondant :
```
ID HTML = theme-toggle
```

Le plugin écoutera le clic sur `#theme-toggle` et basculera le thème.

---

## CSS généré (exemple)

```css
body.dark-mode {
    --e-global-color-primary: #111111;
    --e-global-color-secondary: #333333;
    --e-global-color-accent: #FF6B6B;
}
```

La surcharge des variables Elementor est la méthode la plus fiable pour
remplacer toutes les couleurs dans les widgets Elementor.

---

## API JavaScript publique

```js
// Activer un thème par sa classe CSS
window.PTC.activate('dark-mode');

// Toggle un thème
window.PTC.toggle('dark-mode');

// Revenir au mode par défaut
window.PTC.reset();

// Écouter les changements
document.addEventListener('ptcThemeChange', function(e) {
    console.log('Thème actif :', e.detail.activeClass);
});
```

---

## Structure des fichiers

```
prop-theme-changer/
├── prop-theme-changer.php   ← Point d'entrée du plugin
├── includes/
│   ├── helpers.php          ← CRUD thèmes + récupération couleurs Elementor
│   ├── admin.php            ← Pages d'administration WordPress
│   └── frontend.php        ← Génération CSS + script JS
├── assets/
│   └── admin.css           ← Styles de l'interface admin
└── README.md
```

---

## Données stockées

Les thèmes sont stockés dans `wp_options` sous la clé `ptc_themes`.

Format :
```json
[
  {
    "id": "ptc_abc123",
    "name": "Dark Mode",
    "css_class": "dark-mode",
    "trigger_id": "theme-toggle",
    "colors": {
      "primary":   "#111111",
      "secondary": "#333333"
    }
  }
]
```

---

## Notes techniques

- **Persistance** : le thème actif est sauvegardé en `sessionStorage` → restauré à chaque chargement de page dans la même session.
- **Accessibilité** : les déclencheurs reçoivent automatiquement l'attribut `aria-pressed`.
- **Aucun build tool requis** : CSS et JS purs, intégrés directement.
