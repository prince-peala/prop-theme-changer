<?php
/**
 * Frontend – CSS généré + script switcher complet  v4.0.0
 *
 * Fonctionnalités :
 *  – Sélecteur scope configurable (body par défaut)
 *  – Héritage de couleurs (résolu côté PHP)
 *  – Transitions CSS au changement de thème
 *  – Prévisualisation via ?ptc_preview=valeur
 *  – Conditions : OS (prefers-color-scheme), plage horaire, page IDs
 *  – Persistance : sessionStorage ou localStorage selon config
 *  – Override manuel prioritaire sur conditions automatiques
 *  – API publique window.PTC
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ============================================================
// MODE PREVIEW  (?ptc_preview=valeur)
// ============================================================

add_action( 'wp_head', 'ptc_output_preview_script', 1 );

function ptc_output_preview_script(): void {
    if ( ! isset( $_GET['ptc_preview'] ) ) return;
    $val = ptc_sanitize_data_theme( (string) $_GET['ptc_preview'] );
    if ( ! $val ) return;
    // Appliqué AVANT paint pour éviter le flash
    ?>
<script>
(function(){
    var v='<?php echo esc_js( $val ); ?>';
    document.documentElement.setAttribute('data-theme',v);
    document.addEventListener('DOMContentLoaded',function(){document.body.setAttribute('data-theme',v);});
})();
</script>
    <?php
}

// ============================================================
// CSS
// ============================================================

add_action( 'wp_head', 'ptc_output_css', 99 );

function ptc_output_css(): void {
    $themes = ptc_get_themes();
    if ( empty( $themes ) ) return;

    $lines = [];

    foreach ( $themes as $theme ) {
        $dt = $theme['data_theme'] ?? $theme['css_class'] ?? '';
        if ( ! $dt ) continue;

        /* Couleurs résolues avec héritage */
        $colors = ptc_resolve_theme_colors( $theme );
        if ( empty( $colors ) ) continue;

        $scope    = $theme['selector'] ?? 'body';
        $selector = $scope . '[data-theme="' . esc_attr( $dt ) . '"]';
        $ms       = (int) ( $theme['transition_ms'] ?? 0 );

        $block = [];
        foreach ( $colors as $cid => $color ) {
            if ( ! $color ) continue;
            $block[] = '    --e-global-color-' . esc_attr( $cid ) . ': ' . esc_attr( $color ) . ';';
        }

        /* Transition sur les variables CSS */
        if ( $ms > 0 ) {
            $block[] = '    transition: color ' . $ms . 'ms ease, background-color ' . $ms . 'ms ease, border-color ' . $ms . 'ms ease;';
        }

        if ( empty( $block ) ) continue;

        $lines[] = '';
        $lines[] = '/* Thème : ' . esc_html( $theme['name'] ) . ' */';
        $lines[] = $selector . ' {';
        foreach ( $block as $ln ) $lines[] = $ln;
        $lines[] = '}';
    }

    if ( empty( $lines ) ) return;

    echo "\n<style id=\"ptc-theme-css\">\n/* Prop Theme Changer v" . PTC_VERSION . " – Prince Peala */";
    echo implode( "\n", $lines );
    echo "\n</style>\n";
}

// ============================================================
// JS SWITCHER
// ============================================================

add_action( 'wp_footer', 'ptc_output_js', 99 );

function ptc_output_js(): void {
    $themes = ptc_get_themes();
    if ( empty( $themes ) ) return;

    $js_themes = [];
    foreach ( $themes as $t ) {
        $dt = $t['data_theme'] ?? $t['css_class'] ?? '';
        if ( ! $dt ) continue;

        $conds = $t['conditions'] ?? [];
        $js_themes[] = [
            'id'            => $t['id'],
            'name'          => $t['name'],
            'data_theme'    => $dt,
            'trigger_id'    => $t['trigger_id'] ?? '',
            'selector'      => $t['selector'] ?? 'body',
            'storage'       => $t['storage'] ?? 'session',
            'transition_ms' => (int) ( $t['transition_ms'] ?? 0 ),
            'conditions'    => [
                'os_scheme'  => $conds['os_scheme']  ?? '',
                'time_start' => $conds['time_start'] ?? '',
                'time_end'   => $conds['time_end']   ?? '',
                'page_ids'   => array_values( array_map( 'intval', $conds['page_ids'] ?? [] ) ),
            ],
        ];
    }

    if ( empty( $js_themes ) ) return;

    $themes_json  = wp_json_encode( $js_themes );
    $current_page = (int) get_queried_object_id();
    ?>
<script id="ptc-switcher">
/* Prop Theme Changer v<?php echo PTC_VERSION; ?> – Prince Peala */
(function () {
    'use strict';

    var THEMES       = <?php echo $themes_json; ?>;
    var STORAGE_KEY  = 'ptc_active';   // valeur data-theme sauvegardée
    var MANUAL_KEY   = 'ptc_manual';   // flag : override manuel actif
    var DEFAULT_VAL  = 'default';
    var ATTR         = 'data-theme';
    var PAGE_ID      = <?php echo $current_page; ?>;

    /* ── Stockage dynamique (session ou local selon le thème) ── */
    function getStore(type) {
        return type === 'local' ? localStorage : sessionStorage;
    }

    /* ── Élément scope du thème ── */
    function getScopeEl(theme) {
        if (!theme || !theme.selector || theme.selector === 'body') return document.body;
        return document.querySelector(theme.selector) || document.body;
    }

    /* ── Valeur active sur un scope ── */
    function getActive(theme) {
        return getScopeEl(theme).getAttribute(ATTR);
    }

    /* ── Applique data-theme sur le scope avec transition optionnelle ── */
    function applyTheme(el, value, ms) {
        if (!ms || ms <= 0) {
            el.setAttribute(ATTR, value);
            return;
        }
        el.setAttribute('data-ptc-transitioning', '');
        el.setAttribute(ATTR, value);
        setTimeout(function () { el.removeAttribute('data-ptc-transitioning'); }, ms + 50);
    }

    /* ── Active un thème (isManual = true si déclenché par l'utilisateur) ── */
    function activateTheme(dataTheme, isManual) {
        var theme = THEMES.find(function (t) { return t.data_theme === dataTheme; });
        var el    = getScopeEl(theme);
        var store = getStore(theme ? theme.storage : 'session');
        var ms    = theme ? theme.transition_ms : 0;

        applyTheme(el, dataTheme || DEFAULT_VAL, ms);

        if (dataTheme && dataTheme !== DEFAULT_VAL) {
            try {
                store.setItem(STORAGE_KEY, dataTheme);
                if (isManual) store.setItem(MANUAL_KEY, '1');
            } catch (e) {}
        } else {
            THEMES.forEach(function (t) {
                try { getStore(t.storage).removeItem(STORAGE_KEY); getStore(t.storage).removeItem(MANUAL_KEY); } catch (e) {}
            });
            el.setAttribute(ATTR, DEFAULT_VAL);
        }

        dispatchChange(dataTheme || DEFAULT_VAL);
    }

    /* ── Toggle : si actif → reset, sinon → activer manuellement ── */
    function toggleTheme(theme) {
        var isActive = getActive(theme) === theme.data_theme;
        activateTheme(isActive ? null : theme.data_theme, true);
    }

    /* ── Événement personnalisé ── */
    function dispatchChange(value) {
        if (typeof CustomEvent !== 'function') return;
        document.dispatchEvent(new CustomEvent('ptcThemeChange', {
            detail: { dataTheme: value },
            bubbles: true
        }));
    }

    /* ────────────────────────────────────────────────────────
     * CONDITIONS
     * ──────────────────────────────────────────────────────── */

    /* Vérifie la préférence OS */
    function checkOsScheme(scheme) {
        if (!scheme) return true;
        return window.matchMedia('(prefers-color-scheme: ' + scheme + ')').matches;
    }

    /* Vérifie si l'heure actuelle est dans la plage (gère minuit) */
    function checkTimeRange(start, end) {
        if (!start || !end) return true;
        var now  = new Date();
        var cur  = now.getHours() * 60 + now.getMinutes();
        var toMin = function (t) { var p = t.split(':'); return parseInt(p[0]) * 60 + parseInt(p[1]); };
        var s = toMin(start), e = toMin(end);
        return s <= e ? (cur >= s && cur < e) : (cur >= s || cur < e); // gère 20:00→08:00
    }

    /* Vérifie si la page courante est dans la liste */
    function checkPageIds(ids) {
        if (!ids || !ids.length) return true;
        return ids.indexOf(PAGE_ID) !== -1;
    }

    /* Trouve le premier thème dont toutes les conditions sont remplies */
    function findConditionMatch() {
        return THEMES.find(function (t) {
            var c = t.conditions || {};
            return checkOsScheme(c.os_scheme)
                && checkTimeRange(c.time_start, c.time_end)
                && checkPageIds(c.page_ids);
        }) || null;
    }

    /* Détermine si UNE condition non vide est définie dans le thème */
    function hasConditions(t) {
        var c = t.conditions || {};
        return !!(c.os_scheme || c.time_start || c.page_ids && c.page_ids.length);
    }

    /* ────────────────────────────────────────────────────────
     * RESTAURATION AU CHARGEMENT
     * ──────────────────────────────────────────────────────── */
    function restoreTheme() {
        /* 1. Recherche d'un override manuel dans le storage */
        var savedVal = null;
        var isManualOverride = false;

        THEMES.forEach(function (t) {
            try {
                var store = getStore(t.storage);
                var v = store.getItem(STORAGE_KEY);
                var m = store.getItem(MANUAL_KEY);
                if (v) { savedVal = v; isManualOverride = !!m; }
            } catch (e) {}
        });

        if (savedVal && isManualOverride) {
            /* Override manuel → respecte le choix de l'utilisateur */
            var t = THEMES.find(function (x) { return x.data_theme === savedVal; });
            applyTheme(getScopeEl(t), savedVal, 0);
            return;
        }

        /* 2. Aucun override → recherche par conditions */
        var match = findConditionMatch();
        if (match) {
            applyTheme(getScopeEl(match), match.data_theme, 0);
            return;
        }

        /* 3. Thème sauvegardé sans override manuel */
        if (savedVal) {
            var t2 = THEMES.find(function (x) { return x.data_theme === savedVal; });
            applyTheme(getScopeEl(t2), savedVal, 0);
            return;
        }

        /* 4. Défaut */
        document.body.setAttribute(ATTR, DEFAULT_VAL);
    }

    /* ────────────────────────────────────────────────────────
     * MEDIA QUERY LISTENER (prefers-color-scheme dynamique)
     * ──────────────────────────────────────────────────────── */
    function bindMediaQuery() {
        var mql = window.matchMedia('(prefers-color-scheme: dark)');
        function onSchemeChange() {
            /* N'agit que si pas d'override manuel actif */
            var hasManual = THEMES.some(function (t) {
                try { return !!getStore(t.storage).getItem(MANUAL_KEY); } catch (e) { return false; }
            });
            if (hasManual) return;
            var match = findConditionMatch();
            if (match) activateTheme(match.data_theme, false);
            else       activateTheme(null, false);
        }
        if (mql.addEventListener) mql.addEventListener('change', onSchemeChange);
        else if (mql.addListener)  mql.addListener(onSchemeChange); // Safari < 14
    }

    /* ────────────────────────────────────────────────────────
     * DÉCLENCHEURS
     * ──────────────────────────────────────────────────────── */
    function updateAria() {
        THEMES.forEach(function (theme) {
            var active = getActive(theme);
            /* trigger_id */
            if (theme.trigger_id) {
                var el = document.getElementById(theme.trigger_id);
                if (el) el.setAttribute('aria-pressed', active === theme.data_theme ? 'true' : 'false');
            }
            /* shortcode data-ptc-theme */
            document.querySelectorAll('[data-ptc-theme="' + theme.data_theme + '"]').forEach(function (el) {
                el.setAttribute('aria-pressed', active === theme.data_theme ? 'true' : 'false');
            });
        });
    }

    function bindTriggers() {
        THEMES.forEach(function (theme) {
            /* trigger_id */
            if (theme.trigger_id) {
                var el = document.getElementById(theme.trigger_id);
                if (el) {
                    el.style.cursor = 'pointer';
                    el.setAttribute('aria-pressed', getActive(theme) === theme.data_theme ? 'true' : 'false');
                    el.addEventListener('click', function (e) { e.preventDefault(); toggleTheme(theme); });
                }
            }
        });

        /* Shortcode / data-ptc-theme */
        document.querySelectorAll('[data-ptc-theme]').forEach(function (el) {
            var val   = el.getAttribute('data-ptc-theme');
            var theme = THEMES.find(function (t) { return t.data_theme === val; });
            if (!theme) return;
            el.style.cursor = 'pointer';
            el.setAttribute('aria-pressed', getActive(theme) === val ? 'true' : 'false');
            el.addEventListener('click', function (e) { e.preventDefault(); toggleTheme(theme); });
        });

        document.addEventListener('ptcThemeChange', updateAria);
    }

    /* ────────────────────────────────────────────────────────
     * INIT
     * ──────────────────────────────────────────────────────── */
    restoreTheme();

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { bindTriggers(); bindMediaQuery(); });
    } else {
        bindTriggers();
        bindMediaQuery();
    }

    /* ────────────────────────────────────────────────────────
     * API PUBLIQUE  window.PTC
     * ──────────────────────────────────────────────────────── */
    window.PTC = {
        /** Active un thème par sa valeur data-theme */
        activate : function (val) { activateTheme(val, true); },
        /** Toggle un thème */
        toggle   : function (val) {
            var t = THEMES.find(function (x) { return x.data_theme === val; });
            if (t) toggleTheme(t);
        },
        /** Retour au mode par défaut + efface le storage */
        reset    : function () { activateTheme(null, true); },
        /** Valeur data-theme active sur body */
        getActive: function () { return document.body.getAttribute(ATTR); },
        /** Tous les thèmes */
        themes   : THEMES
    };

})();
</script>
    <?php
}
