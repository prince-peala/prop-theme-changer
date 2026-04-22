<?php
/**
 * REST API  v4.0.0
 *
 * GET  /wp-json/ptc/v1/themes          → liste tous les thèmes (public, lecture seule)
 * GET  /wp-json/ptc/v1/themes/{id}     → détail d'un thème
 * GET  /wp-json/ptc/v1/themes/{id}/css → CSS généré pour un thème
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'rest_api_init', function () {

    $namespace = 'ptc/v1';

    /* ── Liste tous les thèmes ── */
    register_rest_route( $namespace, '/themes', [
        'methods'             => 'GET',
        'callback'            => 'ptc_rest_get_themes',
        'permission_callback' => '__return_true',
    ] );

    /* ── Détail d'un thème ── */
    register_rest_route( $namespace, '/themes/(?P<id>[a-z0-9_]+)', [
        'methods'             => 'GET',
        'callback'            => 'ptc_rest_get_theme',
        'permission_callback' => '__return_true',
        'args'                => [
            'id' => [ 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ],
        ],
    ] );

    /* ── CSS d'un thème ── */
    register_rest_route( $namespace, '/themes/(?P<id>[a-z0-9_]+)/css', [
        'methods'             => 'GET',
        'callback'            => 'ptc_rest_get_theme_css',
        'permission_callback' => '__return_true',
        'args'                => [
            'id' => [ 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ],
        ],
    ] );
} );

/** Formate un thème pour la réponse REST (sans données sensibles). */
function ptc_rest_format_theme( array $theme ): array {
    $dt       = $theme['data_theme'] ?? $theme['css_class'] ?? '';
    $scope    = $theme['selector'] ?? 'body';
    $resolved = ptc_resolve_theme_colors( $theme );

    return [
        'id'            => $theme['id'],
        'name'          => $theme['name'],
        'data_theme'    => $dt,
        'selector'      => $scope,
        'css_selector'  => $scope . '[data-theme="' . $dt . '"]',
        'trigger_id'    => $theme['trigger_id'] ?? '',
        'parent_id'     => $theme['parent_id']  ?? '',
        'storage'       => $theme['storage']    ?? 'session',
        'transition_ms' => (int) ( $theme['transition_ms'] ?? 0 ),
        'conditions'    => $theme['conditions'] ?? [],
        'colors'        => $theme['colors'],
        'colors_resolved' => $resolved,
        'color_count'   => count( $resolved ),
    ];
}

function ptc_rest_get_themes(): WP_REST_Response {
    $themes = array_values( array_map( 'ptc_rest_format_theme', ptc_get_themes() ) );
    return new WP_REST_Response( [
        'version' => PTC_VERSION,
        'count'   => count( $themes ),
        'themes'  => $themes,
    ], 200 );
}

function ptc_rest_get_theme( WP_REST_Request $req ): WP_REST_Response|WP_Error {
    $theme = ptc_get_theme_by_id( $req->get_param( 'id' ) );
    if ( ! $theme ) {
        return new WP_Error( 'ptc_not_found', 'Thème introuvable.', [ 'status' => 404 ] );
    }
    return new WP_REST_Response( ptc_rest_format_theme( $theme ), 200 );
}

function ptc_rest_get_theme_css( WP_REST_Request $req ): WP_REST_Response|WP_Error {
    $theme = ptc_get_theme_by_id( $req->get_param( 'id' ) );
    if ( ! $theme ) {
        return new WP_Error( 'ptc_not_found', 'Thème introuvable.', [ 'status' => 404 ] );
    }

    $dt      = $theme['data_theme'] ?? $theme['css_class'] ?? '';
    $scope   = $theme['selector'] ?? 'body';
    $colors  = ptc_resolve_theme_colors( $theme );
    $css     = $scope . '[data-theme="' . $dt . '"] {' . "\n";

    foreach ( $colors as $cid => $color ) {
        $css .= '    --e-global-color-' . $cid . ': ' . $color . ';' . "\n";
    }
    $css .= '}';

    return new WP_REST_Response( [ 'css' => $css, 'selector' => $scope . '[data-theme="' . $dt . '"]' ], 200 );
}
