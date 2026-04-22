<?php
/**
 * Helpers – couche de données v4.0.0
 * Inclut : validation couleurs, CRUD thèmes, héritage, import/export, couleurs récentes.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ============================================================
// VALIDATION COULEUR  (HEX · RGB · RGBA)
// ============================================================

/**
 * Valide et normalise une couleur CSS.
 * Accepte : #RGB #RRGGBB #RRGGBBAA  rgb()  rgba()  (syntaxes virgule et espace)
 *
 * @return string|false  Valeur normalisée ou false si invalide.
 */
function ptc_sanitize_color( string $value ): string|false {
    $value = trim( $value );
    if ( '' === $value ) return false;

    /* ── HEX ── */
    $hex = $value[0] !== '#' ? '#' . $value : $value;

    if ( preg_match( '/^#([0-9a-fA-F]{3})$/', $hex, $m ) ) {
        [ $r, $g, $b ] = str_split( $m[1] );
        return '#' . strtoupper( "$r$r$g$g$b$b" );
    }
    if ( preg_match( '/^#[0-9a-fA-F]{6}$/', $hex ) ) return strtoupper( $hex );
    if ( preg_match( '/^#[0-9a-fA-F]{8}$/', $hex ) ) return strtoupper( $hex );

    /* ── RGB virgule : rgb(R, G, B) ── */
    if ( preg_match( '/^rgb\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})\s*\)$/i', $value, $m ) ) {
        [ , $r, $g, $b ] = array_map( 'intval', $m );
        return ( $r <= 255 && $g <= 255 && $b <= 255 ) ? "rgb($r, $g, $b)" : false;
    }

    /* ── RGBA virgule : rgba(R, G, B, A) ── */
    if ( preg_match( '/^rgba\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*([0-9]*\.?[0-9]+%?)\s*\)$/i', $value, $m ) ) {
        [ , $r, $g, $b ] = array_map( 'intval', $m );
        if ( $r > 255 || $g > 255 || $b > 255 ) return false;
        return "rgba($r, $g, $b, " . ptc_normalize_alpha( $m[4] ) . ')';
    }

    /* ── RGB/RGBA espace moderne : rgb(R G B / A%) ── */
    if ( preg_match( '/^rgba?\(\s*(\d{1,3})\s+(\d{1,3})\s+(\d{1,3})\s*(?:\/\s*([0-9]*\.?[0-9]+%?))?\s*\)$/i', $value, $m ) ) {
        [ , $r, $g, $b ] = array_map( 'intval', $m );
        if ( $r > 255 || $g > 255 || $b > 255 ) return false;
        if ( isset( $m[4] ) && $m[4] !== '' ) {
            return "rgba($r, $g, $b, " . ptc_normalize_alpha( $m[4] ) . ')';
        }
        return "rgb($r, $g, $b)";
    }

    return false;
}

/** Normalise un canal alpha (float 0-1 ou pourcentage) → string arrondie. */
function ptc_normalize_alpha( string $a ): string {
    $f = str_ends_with( $a, '%' ) ? (float) rtrim( $a, '%' ) / 100 : (float) $a;
    $f = max( 0.0, min( 1.0, $f ) );
    return rtrim( rtrim( number_format( $f, 4, '.', '' ), '0' ), '.' ) ?: '0';
}

/** Retourne le format d'une couleur validée : 'hex' | 'rgb' | 'rgba'. */
function ptc_color_format( string $color ): string {
    if ( str_starts_with( $color, 'rgba' ) ) return 'rgba';
    if ( str_starts_with( $color, 'rgb'  ) ) return 'rgb';
    return 'hex';
}

/** Sanitise une valeur data-theme : [a-zA-Z0-9_-] uniquement. */
function ptc_sanitize_data_theme( string $value ): string {
    return preg_replace( '/[^a-zA-Z0-9_-]/', '', trim( $value ) );
}

// ============================================================
// ELEMENTOR COLORS
// ============================================================

/**
 * Récupère toutes les couleurs Elementor Global du Kit actif.
 *
 * @return array  [ ['id','title','color','format','type'], … ]
 */
function ptc_get_elementor_colors(): array {
    $colors = [];
    $kit_id = (int) get_option( 'elementor_active_kit' );
    if ( ! $kit_id ) return $colors;

    $settings = get_post_meta( $kit_id, '_elementor_page_settings', true );
    if ( ! is_array( $settings ) ) return $colors;

    foreach ( [ 'system_colors' => 'system', 'custom_colors' => 'custom' ] as $key => $type ) {
        foreach ( $settings[ $key ] ?? [] as $item ) {
            $id  = $item['_id']   ?? '';
            $raw = $item['color'] ?? '';
            if ( ! $id || ! $raw ) continue;

            $color = ptc_sanitize_color( $raw ) ?: sanitize_text_field( $raw );

            $colors[] = [
                'id'     => sanitize_text_field( $id ),
                'title'  => sanitize_text_field( $item['title'] ?? $id ),
                'color'  => $color,
                'format' => ptc_color_format( $color ),
                'type'   => $type,
            ];
        }
    }
    return $colors;
}

// ============================================================
// THEME CRUD
// ============================================================

function ptc_get_themes(): array {
    $t = get_option( PTC_OPTION_KEY, [] );
    return is_array( $t ) ? $t : [];
}

function ptc_save_themes( array $themes ): void {
    update_option( PTC_OPTION_KEY, array_values( $themes ) );
}

function ptc_get_theme_by_id( string $id ): ?array {
    foreach ( ptc_get_themes() as $t ) {
        if ( ( $t['id'] ?? '' ) === $id ) return $t;
    }
    return null;
}

function ptc_delete_theme( string $id ): void {
    ptc_save_themes( array_values( array_filter( ptc_get_themes(), fn( $t ) => ( $t['id'] ?? '' ) !== $id ) ) );
}

function ptc_generate_id(): string {
    return 'ptc_' . substr( md5( uniqid( '', true ) ), 0, 10 );
}

/**
 * Résout les couleurs d'un thème en intégrant l'héritage (récursif, profondeur max 5).
 * Les couleurs de l'enfant écrasent celles du parent.
 *
 * @return array  color_id => color_value
 */
function ptc_resolve_theme_colors( array $theme, int $depth = 0 ): array {
    if ( $depth > 5 ) return $theme['colors'] ?? [];

    $base = [];
    if ( ! empty( $theme['parent_id'] ) ) {
        $parent = ptc_get_theme_by_id( $theme['parent_id'] );
        if ( $parent ) $base = ptc_resolve_theme_colors( $parent, $depth + 1 );
    }

    foreach ( $theme['colors'] ?? [] as $id => $color ) {
        $base[ $id ] = $color;
    }
    return $base;
}

/**
 * Duplique un thème (nouveau ID, nom « (copie) », data-theme « -copy »).
 */
function ptc_duplicate_theme( string $id ): ?array {
    $src = ptc_get_theme_by_id( $id );
    if ( ! $src ) return null;

    $copy               = $src;
    $copy['id']         = ptc_generate_id();
    $copy['name']       = $src['name'] . ' (copie)';
    $copy['data_theme'] = rtrim( $src['data_theme'] ?? '', '-' ) . '-copy';

    $themes   = ptc_get_themes();
    $themes[] = $copy;
    ptc_save_themes( $themes );
    return $copy;
}

// ============================================================
// COULEURS RÉCENTES
// ============================================================

function ptc_get_recent_colors( int $uid = 0 ): array {
    $uid = $uid ?: get_current_user_id();
    $c   = get_user_meta( $uid, 'ptc_recent_colors', true );
    return is_array( $c ) ? $c : [];
}

function ptc_push_recent_colors( array $new, int $uid = 0 ): void {
    $uid     = $uid ?: get_current_user_id();
    $merged  = array_values( array_unique( array_merge( $new, ptc_get_recent_colors( $uid ) ) ) );
    update_user_meta( $uid, 'ptc_recent_colors', array_slice( $merged, 0, 12 ) );
}

// ============================================================
// SAUVEGARDE DEPUIS POST
// ============================================================

function ptc_save_theme_from_post( array $p ): array {
    $id    = sanitize_text_field( $p['ptc_theme_id'] ?? '' );
    $theme = ptc_get_theme_by_id( $id ) ?? [ 'id' => $id ?: ptc_generate_id(), 'colors' => [] ];

    $theme['name']          = sanitize_text_field( $p['ptc_name']          ?? '' );
    $theme['data_theme']    = ptc_sanitize_data_theme( $p['ptc_data_theme'] ?? '' );
    $theme['trigger_id']    = sanitize_text_field( $p['ptc_trigger_id']    ?? '' );
    $theme['parent_id']     = sanitize_text_field( $p['ptc_parent_id']     ?? '' );
    $theme['selector']      = sanitize_text_field( $p['ptc_selector']      ?? 'body' ) ?: 'body';
    $theme['storage']       = in_array( $p['ptc_storage'] ?? '', [ 'session', 'local' ], true ) ? $p['ptc_storage'] : 'session';
    $theme['transition_ms'] = max( 0, min( 2000, (int) ( $p['ptc_transition_ms'] ?? 0 ) ) );

    $os = sanitize_text_field( $p['ptc_cond_os'] ?? '' );
    $theme['conditions'] = [
        'os_scheme'  => in_array( $os, [ 'light', 'dark' ], true ) ? $os : '',
        'time_start' => sanitize_text_field( $p['ptc_cond_time_start'] ?? '' ),
        'time_end'   => sanitize_text_field( $p['ptc_cond_time_end']   ?? '' ),
        'page_ids'   => array_values( array_filter( array_map( 'intval', explode( ',', $p['ptc_cond_pages'] ?? '' ) ) ) ),
    ];

    /* Couleurs */
    $theme['colors'] = [];
    $hex_new = [];
    foreach ( $p['ptc_colors'] ?? [] as $cid => $raw ) {
        $raw = sanitize_text_field( trim( $raw ) );
        if ( '' === $raw ) continue;
        $s = ptc_sanitize_color( $raw );
        if ( false !== $s ) {
            $theme['colors'][ sanitize_text_field( $cid ) ] = $s;
            if ( str_starts_with( $s, '#' ) ) $hex_new[] = $s;
        }
    }
    if ( $hex_new ) ptc_push_recent_colors( $hex_new );

    /* Persistance */
    $themes  = ptc_get_themes();
    $updated = false;
    foreach ( $themes as &$t ) {
        if ( ( $t['id'] ?? '' ) === $theme['id'] ) { $t = $theme; $updated = true; break; }
    }
    unset( $t );
    if ( ! $updated ) $themes[] = $theme;
    ptc_save_themes( $themes );

    return $theme;
}

// ============================================================
// IMPORT / EXPORT
// ============================================================

/**
 * Valide un tableau décodé depuis JSON d'import.
 * Accepte un thème unique ou un tableau de thèmes.
 *
 * @return array|false  Thèmes sanitisés avec nouveaux IDs, ou false.
 */
function ptc_validate_import( array $data ): array|false {
    if ( isset( $data['id'] ) ) $data = [ $data ]; // objet unique → tableau

    $valid = [];
    foreach ( $data as $t ) {
        if ( ! is_array( $t ) ) continue;

        $name = sanitize_text_field( $t['name']       ?? '' );
        $dt   = ptc_sanitize_data_theme( $t['data_theme'] ?? $t['css_class'] ?? '' );
        if ( ! $name || ! $dt ) continue;

        $os = sanitize_text_field( $t['conditions']['os_scheme'] ?? '' );

        $clean = [
            'id'            => ptc_generate_id(),
            'name'          => $name,
            'data_theme'    => $dt,
            'trigger_id'    => sanitize_text_field( $t['trigger_id']    ?? '' ),
            'parent_id'     => '',
            'selector'      => sanitize_text_field( $t['selector']      ?? 'body' ) ?: 'body',
            'storage'       => in_array( $t['storage'] ?? '', [ 'session', 'local' ], true ) ? $t['storage'] : 'session',
            'transition_ms' => max( 0, min( 2000, (int) ( $t['transition_ms'] ?? 0 ) ) ),
            'conditions'    => [
                'os_scheme'  => in_array( $os, [ 'light', 'dark' ], true ) ? $os : '',
                'time_start' => sanitize_text_field( $t['conditions']['time_start'] ?? '' ),
                'time_end'   => sanitize_text_field( $t['conditions']['time_end']   ?? '' ),
                'page_ids'   => array_values( array_filter( array_map( 'intval', $t['conditions']['page_ids'] ?? [] ) ) ),
            ],
            'colors' => [],
        ];

        foreach ( $t['colors'] ?? [] as $cid => $cval ) {
            $s = ptc_sanitize_color( sanitize_text_field( $cval ) );
            if ( false !== $s ) $clean['colors'][ sanitize_text_field( $cid ) ] = $s;
        }

        $valid[] = $clean;
    }

    return $valid ?: false;
}
