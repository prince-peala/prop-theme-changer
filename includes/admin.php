<?php
/**
 * Admin – menus, liste, édition, import/export  v4.0.0
 * Auteur : Prince Peala
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ============================================================
// MENUS
// ============================================================

add_action( 'admin_menu', function () {
    add_menu_page( 'Prop Theme Changer', 'Theme Changer', 'manage_options',
        'ptc-themes', 'ptc_page_list', 'dashicons-art', 58 );

    add_submenu_page( 'ptc-themes', 'Ajouter / Éditer', 'Ajouter un thème',
        'manage_options', 'ptc-theme-edit', 'ptc_page_edit' );
} );

// ============================================================
// ASSETS
// ============================================================

add_action( 'admin_enqueue_scripts', function ( $hook ) {
    if ( ! in_array( $hook, [ 'toplevel_page_ptc-themes', 'theme-changer_page_ptc-theme-edit' ], true ) ) return;
    wp_enqueue_style( 'ptc-admin', PTC_URL . 'assets/admin.css', [], PTC_VERSION );
} );

// ============================================================
// ACTIONS ADMIN (export, duplicate, import) – avant tout output
// ============================================================

add_action( 'admin_init', function () {
    if ( ! current_user_can( 'manage_options' ) ) return;
    if ( ( $_GET['page'] ?? '' ) !== 'ptc-themes' ) return;

    $action = sanitize_text_field( $_GET['action'] ?? $_POST['ptc_action'] ?? '' );

    /* ── Export tous ── */
    if ( 'export_all' === $action && isset( $_GET['_wpnonce'] ) ) {
        check_admin_referer( 'ptc_export_all' );
        ptc_send_json_download( ptc_get_themes(), 'ptc-themes-' . date( 'Y-m-d' ) );
    }

    /* ── Export unique ── */
    if ( 'export_single' === $action && ! empty( $_GET['theme_id'] ) ) {
        $tid = sanitize_text_field( $_GET['theme_id'] );
        check_admin_referer( 'ptc_export_' . $tid );
        $theme = ptc_get_theme_by_id( $tid );
        if ( $theme ) ptc_send_json_download( [ $theme ], 'ptc-theme-' . ( $theme['data_theme'] ?? $tid ) );
        wp_die( 'Thème introuvable.' );
    }

    /* ── Dupliquer ── */
    if ( 'duplicate' === $action && ! empty( $_GET['theme_id'] ) ) {
        $tid = sanitize_text_field( $_GET['theme_id'] );
        check_admin_referer( 'ptc_dup_' . $tid );
        ptc_duplicate_theme( $tid );
        wp_safe_redirect( admin_url( 'admin.php?page=ptc-themes&ptc_notice=duplicated' ) );
        exit;
    }
} );

/** Envoie un fichier JSON au navigateur. */
function ptc_send_json_download( array $data, string $filename ): never {
    header( 'Content-Type: application/json; charset=utf-8' );
    header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $filename ) . '.json"' );
    header( 'Cache-Control: no-store' );
    echo wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
    exit;
}

// ============================================================
// PAGE LISTE
// ============================================================

function ptc_page_list(): void {
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Accès refusé.' );

    $notices = [];

    /* Notice dynamique */
    $ptc_notice = sanitize_text_field( $_GET['ptc_notice'] ?? '' );
    $notice_map = [
        'duplicated'     => [ 'success', '✅ Thème dupliqué avec succès.' ],
        'imported'       => [ 'success', '✅ Thème(s) importé(s) avec succès.' ],
        'import_error'   => [ 'error',   '⚠️ Fichier invalide ou aucun thème valide trouvé.' ],
        'import_empty'   => [ 'error',   '⚠️ Aucun fichier ou JSON fourni.' ],
    ];
    if ( isset( $notice_map[ $ptc_notice ] ) ) {
        $notices[] = $notice_map[ $ptc_notice ];
    }

    /* Suppression */
    if ( ! empty( $_GET['action'] ) && 'delete' === $_GET['action'] && ! empty( $_GET['theme_id'] ) ) {
        $tid = sanitize_text_field( $_GET['theme_id'] );
        check_admin_referer( 'ptc_delete_' . $tid );
        ptc_delete_theme( $tid );
        $notices[] = [ 'success', '✅ Thème supprimé.' ];
    }

    /* Import */
    if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['ptc_import_submit'] ) ) {
        check_admin_referer( 'ptc_import' );
        $json_str = '';

        if ( ! empty( $_FILES['ptc_import_file']['tmp_name'] ) ) {
            $json_str = (string) file_get_contents( $_FILES['ptc_import_file']['tmp_name'] );
        } elseif ( ! empty( $_POST['ptc_import_json'] ) ) {
            $json_str = stripslashes( (string) $_POST['ptc_import_json'] );
        }

        if ( '' === $json_str ) {
            wp_safe_redirect( admin_url( 'admin.php?page=ptc-themes&ptc_notice=import_empty' ) ); exit;
        }

        $data = json_decode( $json_str, true );
        if ( ! $data ) {
            wp_safe_redirect( admin_url( 'admin.php?page=ptc-themes&ptc_notice=import_error' ) ); exit;
        }

        $valid = ptc_validate_import( (array) $data );
        if ( ! $valid ) {
            wp_safe_redirect( admin_url( 'admin.php?page=ptc-themes&ptc_notice=import_error' ) ); exit;
        }

        $themes = ptc_get_themes();
        foreach ( $valid as $t ) $themes[] = $t;
        ptc_save_themes( $themes );

        wp_safe_redirect( admin_url( 'admin.php?page=ptc-themes&ptc_notice=imported' ) ); exit;
    }

    $themes   = ptc_get_themes();
    $add_url  = admin_url( 'admin.php?page=ptc-theme-edit' );
    $exp_url  = wp_nonce_url( admin_url( 'admin.php?page=ptc-themes&action=export_all' ), 'ptc_export_all' );
    ?>
    <div class="wrap ptc-wrap">
        <h1 class="wp-heading-inline">Prop Theme Changer <span class="ptc-version">v<?php echo PTC_VERSION; ?></span></h1>
        <a href="<?php echo esc_url( $add_url ); ?>" class="page-title-action">＋ Nouveau thème</a>
        <a href="<?php echo esc_url( $exp_url ); ?>" class="page-title-action">⬇ Export tous</a>
        <hr class="wp-header-end">

        <?php foreach ( $notices as [ $type, $msg ] ) : ?>
            <div class="notice notice-<?php echo esc_attr( $type ); ?> is-dismissible"><p><?php echo $msg; ?></p></div>
        <?php endforeach; ?>

        <?php if ( empty( $themes ) ) : ?>
            <div class="ptc-empty-state">
                <span class="dashicons dashicons-art"></span>
                <p>Aucun thème créé pour le moment.</p>
                <a href="<?php echo esc_url( $add_url ); ?>" class="button button-primary">Créer le premier thème</a>
            </div>
        <?php else : ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Nom</th>
                        <th>data-theme</th>
                        <th>Déclencheur</th>
                        <th>Parent</th>
                        <th>Conditions</th>
                        <th style="width:100px">Couleurs</th>
                        <th style="width:220px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ( $themes as $theme ) :
                    $tid      = $theme['id'];
                    $dt       = $theme['data_theme'] ?? $theme['css_class'] ?? '';
                    $edit_url = admin_url( 'admin.php?page=ptc-theme-edit&theme_id=' . urlencode( $tid ) );
                    $del_url  = wp_nonce_url( admin_url( 'admin.php?page=ptc-themes&action=delete&theme_id=' . urlencode( $tid ) ), 'ptc_delete_' . $tid );
                    $dup_url  = wp_nonce_url( admin_url( 'admin.php?page=ptc-themes&action=duplicate&theme_id=' . urlencode( $tid ) ), 'ptc_dup_' . $tid );
                    $exp_s    = wp_nonce_url( admin_url( 'admin.php?page=ptc-themes&action=export_single&theme_id=' . urlencode( $tid ) ), 'ptc_export_' . $tid );

                    $parent      = ! empty( $theme['parent_id'] ) ? ptc_get_theme_by_id( $theme['parent_id'] ) : null;
                    $cond_labels = [];
                    if ( ! empty( $theme['conditions']['os_scheme'] ) )  $cond_labels[] = '🖥 OS:' . $theme['conditions']['os_scheme'];
                    if ( ! empty( $theme['conditions']['time_start'] ) ) $cond_labels[] = '⏰ ' . $theme['conditions']['time_start'] . '→' . ( $theme['conditions']['time_end'] ?? '' );
                    if ( ! empty( $theme['conditions']['page_ids'] ) )   $cond_labels[] = '📄 ' . count( $theme['conditions']['page_ids'] ) . ' page(s)';
                    $color_count = count( $theme['colors'] ?? [] );
                ?>
                    <tr>
                        <td><strong><a href="<?php echo esc_url( $edit_url ); ?>"><?php echo esc_html( $theme['name'] ?: '(sans nom)' ); ?></a></strong></td>
                        <td><code class="ptc-code"><?php echo esc_html( $dt ); ?></code></td>
                        <td><?php echo $theme['trigger_id'] ? '<code class="ptc-code">#' . esc_html( $theme['trigger_id'] ) . '</code>' : '<span class="ptc-muted">—</span>'; ?></td>
                        <td><?php echo $parent ? esc_html( $parent['name'] ) : '<span class="ptc-muted">—</span>'; ?></td>
                        <td>
                            <?php if ( $cond_labels ) : ?>
                                <?php foreach ( $cond_labels as $cl ) : ?><span class="ptc-cond-tag"><?php echo esc_html( $cl ); ?></span><?php endforeach; ?>
                            <?php else : ?><span class="ptc-muted">Toujours</span><?php endif; ?>
                        </td>
                        <td>
                            <?php if ( $color_count ) : ?>
                                <span class="ptc-badge"><?php echo $color_count; ?> couleur<?php echo $color_count > 1 ? 's' : ''; ?></span>
                            <?php else : ?><span class="ptc-muted">Aucune</span><?php endif; ?>
                        </td>
                        <td>
                            <a href="<?php echo esc_url( $edit_url ); ?>" class="button button-small">Éditer</a>
                            <a href="<?php echo esc_url( $dup_url );  ?>" class="button button-small">Copier</a>
                            <a href="<?php echo esc_url( $exp_s );    ?>" class="button button-small">⬇</a>
                            <a href="<?php echo esc_url( $del_url );  ?>" class="button button-small ptc-btn-delete"
                               onclick="return confirm('Supprimer « <?php echo esc_js( $theme['name'] ); ?> » ?')">✕</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <p class="description" style="margin-top:12px">
                💡 CSS généré : <code>body[data-theme="valeur"] { --e-global-color-id: couleur; }</code>
            </p>
        <?php endif; ?>

        <!-- ── Section Import ── -->
        <div class="ptc-card ptc-import-section" style="margin-top:28px">
            <h2 class="ptc-card-title">⬆ Importer des thèmes</h2>
            <form method="post" enctype="multipart/form-data">
                <?php wp_nonce_field( 'ptc_import' ); ?>
                <input type="hidden" name="ptc_import_submit" value="1">
                <table class="form-table ptc-form-table">
                    <tr>
                        <th>Fichier JSON</th>
                        <td><input type="file" name="ptc_import_file" accept=".json,application/json"></td>
                    </tr>
                    <tr>
                        <th>ou coller le JSON</th>
                        <td>
                            <textarea name="ptc_import_json" class="large-text" rows="5"
                                placeholder='[{"name":"Mon thème","data_theme":"my-theme","colors":{}}]'></textarea>
                        </td>
                    </tr>
                </table>
                <p><button type="submit" class="button button-secondary">⬆ Importer</button></p>
            </form>
        </div>
    </div>
    <?php
}

// ============================================================
// PAGE ÉDITION
// ============================================================

function ptc_page_edit(): void {
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Accès refusé.' );

    $theme_id = sanitize_text_field( $_POST['ptc_theme_id'] ?? $_GET['theme_id'] ?? '' );
    $theme    = $theme_id ? ptc_get_theme_by_id( $theme_id ) : null;
    $is_new   = ! $theme;

    if ( $is_new ) {
        $theme = [
            'id' => $theme_id ?: ptc_generate_id(),
            'name' => '', 'data_theme' => '', 'trigger_id' => '',
            'parent_id' => '', 'selector' => 'body',
            'storage' => 'session', 'transition_ms' => 0,
            'conditions' => [ 'os_scheme' => '', 'time_start' => '', 'time_end' => '', 'page_ids' => [] ],
            'colors' => [],
        ];
    }

    $notices = [];

    if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['ptc_save_theme'] ) ) {
        check_admin_referer( 'ptc_save_theme_' . $theme['id'] );

        if ( empty( trim( $_POST['ptc_name']       ?? '' ) ) ) {
            $notices[] = [ 'error', '⚠️ Le nom du thème est obligatoire.' ];
        } elseif ( empty( trim( $_POST['ptc_data_theme'] ?? '' ) ) ) {
            $notices[] = [ 'error', '⚠️ La valeur data-theme est obligatoire.' ];
        } else {
            $theme  = ptc_save_theme_from_post( $_POST );
            $is_new = false;
            $notices[] = [ 'success', '✅ Thème sauvegardé.' ];
        }
    }

    $el_colors   = ptc_get_elementor_colors();
    $all_themes  = ptc_get_themes();
    $recent      = ptc_get_recent_colors();
    $list_url    = admin_url( 'admin.php?page=ptc-themes' );
    $preview_url = home_url( '?ptc_preview=' . urlencode( $theme['data_theme'] ?? '' ) );

    $conds       = $theme['conditions'] ?? [];
    $page_ids_str = implode( ', ', $conds['page_ids'] ?? [] );
    ?>
    <div class="wrap ptc-wrap">
        <h1><?php echo $is_new ? 'Nouveau thème' : 'Éditer : ' . esc_html( $theme['name'] ); ?></h1>
        <a href="<?php echo esc_url( $list_url ); ?>" class="ptc-back-link">← Retour à la liste</a>

        <?php foreach ( $notices as [ $type, $msg ] ) : ?>
            <div class="notice notice-<?php echo esc_attr( $type ); ?> is-dismissible" style="margin-top:12px"><p><?php echo $msg; ?></p></div>
        <?php endforeach; ?>

        <form method="post" id="ptc-edit-form">
            <?php wp_nonce_field( 'ptc_save_theme_' . $theme['id'] ); ?>
            <input type="hidden" name="ptc_theme_id" value="<?php echo esc_attr( $theme['id'] ); ?>">

            <!-- ════ CARD 1 : Identité ════ -->
            <div class="ptc-card">
                <h2 class="ptc-card-title">⚙️ Identité du thème</h2>
                <table class="form-table ptc-form-table">
                    <tr>
                        <th><label for="ptc_name">Nom <span class="required">*</span></label></th>
                        <td>
                            <input type="text" name="ptc_name" id="ptc_name" class="regular-text"
                                value="<?php echo esc_attr( $theme['name'] ); ?>" placeholder="Dark Mode" required>
                            <p class="description">Libellé interne (admin uniquement).</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="ptc_data_theme">Valeur data-theme <span class="required">*</span></label></th>
                        <td>
                            <div class="ptc-input-prefix-wrap">
                                <span class="ptc-input-prefix">data-theme="</span>
                                <input type="text" name="ptc_data_theme" id="ptc_data_theme" class="regular-text"
                                    value="<?php echo esc_attr( $theme['data_theme'] ?? '' ); ?>"
                                    placeholder="dark" pattern="[a-zA-Z0-9_-]+" required>
                                <span class="ptc-input-suffix">"</span>
                            </div>
                            <p class="ptc-selector-preview description">
                                CSS : <code id="ptc-selector-preview">body[data-theme="<?php echo esc_attr( $theme['data_theme'] ?? '...' ); ?>"]</code>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="ptc_trigger_id">ID déclencheur</label></th>
                        <td>
                            <div class="ptc-input-prefix-wrap">
                                <span class="ptc-input-prefix">#</span>
                                <input type="text" name="ptc_trigger_id" id="ptc_trigger_id" class="regular-text"
                                    value="<?php echo esc_attr( $theme['trigger_id'] ?? '' ); ?>" placeholder="dark-toggle">
                            </div>
                            <p class="description">ID HTML du bouton déclencheur. Peut aussi utiliser le shortcode <code>[ptc_toggle theme="<?php echo esc_attr( $theme['data_theme'] ?? 'valeur' ); ?>"]</code>.</p>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- ════ CARD 2 : Comportement avancé ════ -->
            <div class="ptc-card">
                <h2 class="ptc-card-title">🔗 Comportement avancé</h2>
                <table class="form-table ptc-form-table">
                    <tr>
                        <th><label for="ptc_parent_id">Thème parent</label></th>
                        <td>
                            <select name="ptc_parent_id" id="ptc_parent_id">
                                <option value="">— Aucun —</option>
                                <?php foreach ( $all_themes as $ot ) :
                                    if ( $ot['id'] === $theme['id'] ) continue; ?>
                                    <option value="<?php echo esc_attr( $ot['id'] ); ?>"
                                        <?php selected( $theme['parent_id'] ?? '', $ot['id'] ); ?>>
                                        <?php echo esc_html( $ot['name'] ); ?> (<?php echo esc_html( $ot['data_theme'] ?? '' ); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">Ce thème hérite des couleurs du parent. Les couleurs définies ici les écrasent.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="ptc_selector">Sélecteur scope</label></th>
                        <td>
                            <input type="text" name="ptc_selector" id="ptc_selector" class="regular-text"
                                value="<?php echo esc_attr( $theme['selector'] ?? 'body' ); ?>" placeholder="body">
                            <p class="description">Élément sur lequel est posé <code>data-theme</code>. Par défaut : <code>body</code>. Exemples : <code>#wrapper</code>, <code>.site-container</code>.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Persistance</th>
                        <td>
                            <label>
                                <input type="radio" name="ptc_storage" value="session"
                                    <?php checked( $theme['storage'] ?? 'session', 'session' ); ?>>
                                Session <span class="description">(perdu à la fermeture de l'onglet)</span>
                            </label>&nbsp;&nbsp;
                            <label>
                                <input type="radio" name="ptc_storage" value="local"
                                    <?php checked( $theme['storage'] ?? 'session', 'local' ); ?>>
                                Locale <span class="description">(mémorisée longtemps)</span>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="ptc_transition_ms">Transition</label></th>
                        <td>
                            <input type="number" name="ptc_transition_ms" id="ptc_transition_ms" class="small-text"
                                value="<?php echo (int) ( $theme['transition_ms'] ?? 0 ); ?>" min="0" max="2000" step="50">
                            <span class="description">ms — animation lors du changement de thème. <code>0</code> = désactivé.</span>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- ════ CARD 3 : Conditions ════ -->
            <div class="ptc-card">
                <h2 class="ptc-card-title">🔀 Activation conditionnelle</h2>
                <p class="description" style="margin-bottom:16px">
                    Le thème s'active automatiquement si <strong>toutes</strong> les conditions non vides sont remplies,
                    à condition qu'aucun thème n'ait été activé manuellement.
                </p>
                <table class="form-table ptc-form-table">
                    <tr>
                        <th><label for="ptc_cond_os">Préférence OS</label></th>
                        <td>
                            <select name="ptc_cond_os" id="ptc_cond_os">
                                <option value=""  <?php selected( $conds['os_scheme'] ?? '', ''      ); ?>>— Aucune —</option>
                                <option value="dark"  <?php selected( $conds['os_scheme'] ?? '', 'dark'  ); ?>>🌙 Schéma sombre (prefers-color-scheme: dark)</option>
                                <option value="light" <?php selected( $conds['os_scheme'] ?? '', 'light' ); ?>>☀️ Schéma clair (prefers-color-scheme: light)</option>
                            </select>
                            <p class="description">Active automatiquement ce thème selon la préférence OS de l'utilisateur. Réactif aux changements en temps réel.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Plage horaire</th>
                        <td>
                            <label>De
                                <input type="time" name="ptc_cond_time_start" class="ptc-time-input"
                                    value="<?php echo esc_attr( $conds['time_start'] ?? '' ); ?>">
                            </label>
                            &nbsp;à&nbsp;
                            <label>
                                <input type="time" name="ptc_cond_time_end" class="ptc-time-input"
                                    value="<?php echo esc_attr( $conds['time_end'] ?? '' ); ?>">
                            </label>
                            <p class="description">Active le thème pendant cette plage (heure locale du navigateur). Exemple : <code>20:00 → 08:00</code> pour le mode nuit. Laissez vide pour désactiver.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="ptc_cond_pages">Pages spécifiques</label></th>
                        <td>
                            <input type="text" name="ptc_cond_pages" id="ptc_cond_pages" class="regular-text"
                                value="<?php echo esc_attr( $page_ids_str ); ?>" placeholder="12, 34, 56">
                            <p class="description">IDs de pages WordPress séparés par des virgules. Laissez vide pour toutes les pages.</p>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- ════ CARD 4 : Couleurs Elementor ════ -->
            <div class="ptc-card">
                <h2 class="ptc-card-title">🎨 Remplacement des couleurs Elementor</h2>

                <?php if ( ! empty( $recent ) ) : ?>
                    <div class="ptc-recent-palette">
                        <span class="ptc-recent-label">Couleurs récentes :</span>
                        <?php foreach ( $recent as $rc ) : ?>
                            <button type="button" class="ptc-recent-swatch ptc-checker"
                                style="background:<?php echo esc_attr( $rc ); ?>"
                                data-color="<?php echo esc_attr( $rc ); ?>"
                                title="<?php echo esc_attr( $rc ); ?>"></button>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if ( empty( $el_colors ) ) : ?>
                    <div class="notice notice-warning inline"><p>
                        ⚠️ Aucune couleur Elementor Global trouvée.<br>
                        Vérifiez qu'un <strong>Kit actif</strong> est configuré avec des couleurs globales
                        (<em>Elementor → Kit de site → Global Colors</em>).
                    </p></div>
                <?php else : ?>
                    <p class="description ptc-color-intro">
                        Saisissez la couleur de remplacement (hex, rgb, rgba). Laissez vide pour ne pas la modifier.
                        <?php if ( ! empty( $theme['parent_id'] ) ) : ?>
                            <br>💡 Les couleurs du thème parent sont héritées automatiquement.
                        <?php endif; ?>
                    </p>

                    <?php
                    $groups = [ 'system' => [], 'custom' => [] ];
                    foreach ( $el_colors as $ec ) $groups[ $ec['type'] ][] = $ec;

                    foreach ( [ 'system' => '🔵 System Colors', 'custom' => '🟡 Custom Colors' ] as $type => $label ) :
                        if ( empty( $groups[ $type ] ) ) continue;
                    ?>
                    <h3 class="ptc-group-title"><?php echo $label; ?></h3>
                    <table class="widefat ptc-color-table">
                        <thead><tr>
                            <th style="width:190px">Nom Elementor</th>
                            <th style="width:140px">Couleur actuelle</th>
                            <th style="width:28px"></th>
                            <th>Nouvelle couleur dans ce thème</th>
                            <th style="width:70px">Aperçu</th>
                        </tr></thead>
                        <tbody>
                        <?php foreach ( $groups[ $type ] as $ec ) :
                            $override   = $theme['colors'][ $ec['id'] ] ?? '';
                            $safe_id    = esc_attr( $ec['id'] );
                            $picker_val = '#FFFFFF';
                            if ( $override && preg_match('/^#[0-9a-fA-F]{6}$/', $override) ) $picker_val = $override;
                            elseif ( preg_match('/^#[0-9a-fA-F]{6}$/', $ec['color'] ) )      $picker_val = $ec['color'];
                        ?>
                        <tr class="ptc-color-row">
                            <td>
                                <strong><?php echo esc_html( $ec['title'] ); ?></strong>
                                <br><code class="ptc-id"><?php echo esc_html( $ec['id'] ); ?></code>
                            </td>
                            <td>
                                <div class="ptc-color-chip-wrap">
                                    <span class="ptc-color-chip ptc-checker" style="background:<?php echo esc_attr( $ec['color'] ); ?>"></span>
                                    <code><?php echo esc_html( $ec['color'] ); ?></code>
                                </div>
                            </td>
                            <td class="ptc-arrow">→</td>
                            <td>
                                <div class="ptc-color-field-wrap">
                                    <input type="color" class="ptc-native-picker"
                                        value="<?php echo esc_attr( $picker_val ); ?>"
                                        data-target="ptc-color-<?php echo $safe_id; ?>"
                                        tabindex="-1" aria-label="Choisir couleur">
                                    <input type="text"
                                        name="ptc_colors[<?php echo $safe_id; ?>]"
                                        id="ptc-color-<?php echo $safe_id; ?>"
                                        class="ptc-color-input"
                                        value="<?php echo esc_attr( $override ); ?>"
                                        placeholder="#RRGGBB · rgb() · rgba()"
                                        data-preview="ptc-prev-<?php echo $safe_id; ?>"
                                        autocomplete="off" spellcheck="false">
                                    <?php if ( $override ) : ?>
                                        <button type="button" class="ptc-clear-btn" title="Effacer">✕</button>
                                    <?php endif; ?>
                                </div>
                                <div class="ptc-format-hint" id="ptc-hint-<?php echo $safe_id; ?>">
                                    <?php if ( $override && ! str_starts_with( $override, '#' ) ) :
                                        $fmt = ptc_color_format( $override ); ?>
                                        <span class="ptc-format-badge ptc-format-<?php echo $fmt; ?>">
                                            <?php echo $fmt === 'rgba' ? 'RGBA – opacité active' : 'RGB'; ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <span id="ptc-prev-<?php echo $safe_id; ?>"
                                    class="ptc-color-chip ptc-preview-chip ptc-checker"
                                    style="background:<?php echo esc_attr( $override ?: 'transparent' ); ?>;<?php echo $override ? '' : 'border-style:dashed;'; ?>"
                                    title="Aperçu"></span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- ── Submit bar ── -->
            <div class="ptc-submit-bar">
                <button type="submit" name="ptc_save_theme" class="button button-primary button-large">
                    💾 Sauvegarder
                </button>
                <?php if ( ! $is_new && ! empty( $theme['data_theme'] ) ) : ?>
                    <a href="<?php echo esc_url( $preview_url ); ?>" target="_blank"
                       class="button button-large ptc-preview-btn">👁 Prévisualiser</a>
                <?php endif; ?>
                <a href="<?php echo esc_url( $list_url ); ?>" class="button button-large">Annuler</a>
            </div>
        </form>
    </div>

    <?php ptc_admin_edit_js(); ?>
    <?php
}

// ============================================================
// JS ADMIN (inline, page édition seulement)
// ============================================================

function ptc_admin_edit_js(): void { ?>
<script>
(function () {
    'use strict';

    /* ── Prévisualisation du sélecteur CSS ── */
    var dtInput  = document.getElementById('ptc_data_theme');
    var dtScpInput = document.getElementById('ptc_selector');
    var preview  = document.getElementById('ptc-selector-preview');

    function updateSelectorPreview() {
        if (!dtInput || !preview) return;
        var dt  = (dtInput.value || '...').replace(/[^a-zA-Z0-9_-]/g, '');
        var scp = dtScpInput ? (dtScpInput.value.trim() || 'body') : 'body';
        preview.textContent = scp + '[data-theme="' + dt + '"]';
    }
    if (dtInput)    dtInput.addEventListener('input', updateSelectorPreview);
    if (dtScpInput) dtScpInput.addEventListener('input', updateSelectorPreview);

    /* ── Palette couleurs récentes ── */
    document.querySelectorAll('.ptc-recent-swatch').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var color  = this.getAttribute('data-color');
            var active = document.activeElement;
            if (active && active.classList.contains('ptc-color-input')) {
                active.value = color;
                active.dispatchEvent(new Event('input', { bubbles: true }));
                return;
            }
            // Sinon: colle dans le premier champ vide trouvé
            var first = document.querySelector('.ptc-color-input:not([value]),.ptc-color-input[value=""]');
            if (first) { first.value = color; first.dispatchEvent(new Event('input', { bubbles: true })); first.focus(); }
        });
    });

    /* ── Helpers couleur ── */
    function detectFormat(val) {
        val = val.trim();
        if (!val) return null;
        if (/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/.test(val)) return 'hex';
        if (/^rgba?\s*\(/i.test(val)) return /^rgba\s*\(/i.test(val) ? 'rgba' : 'rgb';
        return null;
    }

    function isValid(val) { return detectFormat(val) !== null; }

    function toPickerHex(val) {
        val = val.trim();
        if (/^#[0-9a-fA-F]{6}$/.test(val)) return val;
        if (/^#[0-9a-fA-F]{3}$/.test(val)) {
            return '#' + val[1]+val[1]+val[2]+val[2]+val[3]+val[3];
        }
        if (/^#[0-9a-fA-F]{8}$/.test(val)) return val.slice(0, 7);
        var m = val.match(/[\d.]+/g);
        if (m && m.length >= 3) {
            return '#' + ('0'+parseInt(m[0]).toString(16)).slice(-2)
                       + ('0'+parseInt(m[1]).toString(16)).slice(-2)
                       + ('0'+parseInt(m[2]).toString(16)).slice(-2);
        }
        return null;
    }

    function updatePreview(input) {
        var el = document.getElementById(input.getAttribute('data-preview'));
        if (!el) return;
        var val = input.value.trim();
        if (!val) {
            el.style.background  = 'transparent';
            el.style.borderStyle = 'dashed';
        } else if (isValid(val)) {
            el.style.background  = val;
            el.style.borderStyle = 'solid';
        }
    }

    function updateBadge(input) {
        var hintId = 'ptc-hint-' + input.id.replace('ptc-color-', '');
        var hint   = document.getElementById(hintId);
        if (!hint) return;
        var fmt = detectFormat(input.value.trim());
        var badge = hint.querySelector('.ptc-format-badge');
        if (!fmt || fmt === 'hex') {
            if (badge) badge.remove();
            return;
        }
        if (!badge) { badge = document.createElement('span'); hint.appendChild(badge); }
        badge.className = 'ptc-format-badge ptc-format-' + fmt;
        badge.textContent = fmt === 'rgba' ? 'RGBA – opacité active' : 'RGB';
    }

    function syncPicker(input) {
        var picker = document.querySelector('.ptc-native-picker[data-target="' + input.id + '"]');
        if (!picker) return;
        var hex = toPickerHex(input.value.trim());
        if (hex) picker.value = hex;
        var fmt = detectFormat(input.value.trim());
        picker.title = fmt === 'rgba' ? 'Picker hex (opacité saisie manuellement)' : 'Choisir couleur';
    }

    function toggleClearBtn(input) {
        var wrap = input.closest('.ptc-color-field-wrap');
        if (!wrap) return;
        var btn = wrap.querySelector('.ptc-clear-btn');
        if (input.value.trim()) {
            if (!btn) {
                btn = document.createElement('button');
                btn.type = 'button'; btn.className = 'ptc-clear-btn'; btn.title = 'Effacer'; btn.textContent = '✕';
                btn.addEventListener('click', function () {
                    input.value = ''; updatePreview(input); updateBadge(input); syncPicker(input); this.remove(); input.focus();
                });
                wrap.appendChild(btn);
            }
        } else { if (btn) btn.remove(); }
    }

    /* ── Native picker → text ── */
    document.querySelectorAll('.ptc-native-picker').forEach(function (picker) {
        picker.addEventListener('input', function () {
            var textInput = document.getElementById(this.getAttribute('data-target'));
            if (!textInput) return;
            var fmt = detectFormat(textInput.value.trim());
            if (fmt === 'rgba') {
                var m = textInput.value.match(/rgba\s*\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*,\s*([^)]+)\)/i);
                if (m) {
                    var h = this.value;
                    textInput.value = 'rgba(' + parseInt(h.slice(1,3),16) + ', ' + parseInt(h.slice(3,5),16) + ', ' + parseInt(h.slice(5,7),16) + ', ' + m[4].trim() + ')';
                } else { textInput.value = this.value.toUpperCase(); }
            } else if (fmt === 'rgb') {
                var h2 = this.value;
                textInput.value = 'rgb(' + parseInt(h2.slice(1,3),16) + ', ' + parseInt(h2.slice(3,5),16) + ', ' + parseInt(h2.slice(5,7),16) + ')';
            } else { textInput.value = this.value.toUpperCase(); }
            updatePreview(textInput); updateBadge(textInput); toggleClearBtn(textInput);
        });
    });

    /* ── Text input ── */
    document.querySelectorAll('.ptc-color-input').forEach(function (input) {
        input.addEventListener('input', function () {
            updatePreview(this); syncPicker(this); updateBadge(this); toggleClearBtn(this);
        });
    });

    /* ── Clear buttons existants ── */
    document.querySelectorAll('.ptc-clear-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var input = this.closest('.ptc-color-field-wrap').querySelector('.ptc-color-input');
            if (!input) return;
            input.value = ''; updatePreview(input); updateBadge(input); syncPicker(input); this.remove(); input.focus();
        });
    });

})();
</script>
<?php }
