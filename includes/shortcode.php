<?php
/**
 * Shortcode [ptc_toggle] + Widget Elementor  v4.0.0
 * Auteur : Prince Peala
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ============================================================
// SHORTCODE  [ptc_toggle]
// ============================================================

/**
 * Shortcode pour créer un bouton déclencheur de thème.
 *
 * Attributs :
 *  theme   (requis) – valeur data-theme
 *  label   – texte du bouton               (défaut : "Changer le thème")
 *  tag     – balise HTML                   (défaut : button | a | div | span)
 *  id      – ID HTML de l'élément
 *  class   – classes CSS supplémentaires
 *  active  – label quand le thème est actif (optionnel)
 *
 * Exemples :
 *  [ptc_toggle theme="dark" label="🌙 Mode sombre"]
 *  [ptc_toggle theme="dark" label="Activer" active="Désactiver" tag="a"]
 */
add_shortcode( 'ptc_toggle', function ( $atts ) {
    $atts = shortcode_atts( [
        'theme'  => '',
        'label'  => 'Changer le thème',
        'active' => '',
        'tag'    => 'button',
        'id'     => '',
        'class'  => '',
    ], $atts );

    $dt = ptc_sanitize_data_theme( $atts['theme'] );
    if ( ! $dt ) return '';

    $tag    = in_array( $atts['tag'], [ 'button', 'a', 'div', 'span' ], true ) ? $atts['tag'] : 'button';
    $cls    = trim( 'ptc-toggle ' . sanitize_html_class( $atts['class'] ) );
    $id_attr = $atts['id'] ? ' id="' . esc_attr( $atts['id'] ) . '"' : '';
    $label  = esc_html( $atts['label'] );

    /* Label alternatif quand actif (injecté en data attr, géré par JS) */
    $active_attr = $atts['active'] ? ' data-ptc-label-active="' . esc_attr( $atts['active'] ) . '"' : '';

    $extra = '';
    if ( 'a'      === $tag ) $extra = ' href="#"';
    if ( 'button' === $tag ) $extra = ' type="button"';

    return sprintf(
        '<%1$s class="%2$s" data-ptc-theme="%3$s" aria-pressed="false" role="button"%4$s%5$s%6$s>%7$s</%1$s>',
        $tag,
        esc_attr( $cls ),
        esc_attr( $dt ),
        $extra,
        $id_attr,
        $active_attr,
        $label
    );
} );

/* ── Gestion du label actif/inactif via JS inline ── */
add_action( 'wp_footer', function () {
    if ( ! has_shortcode( get_the_content() ?? '', 'ptc_toggle' ) ) return;
    ?>
<script>
document.addEventListener('ptcThemeChange', function (e) {
    var active = e.detail.dataTheme;
    document.querySelectorAll('[data-ptc-theme]').forEach(function (el) {
        var labelActive   = el.getAttribute('data-ptc-label-active');
        var labelInactive = el.getAttribute('data-ptc-label-inactive') || el.textContent;
        if (!el.getAttribute('data-ptc-label-inactive')) {
            el.setAttribute('data-ptc-label-inactive', el.textContent);
        }
        if (!labelActive) return;
        var isActive = el.getAttribute('data-ptc-theme') === active;
        el.textContent = isActive ? labelActive : labelInactive;
    });
});
</script>
    <?php
}, 100 );

// ============================================================
// WIDGET ELEMENTOR
// ============================================================

add_action( 'elementor/widgets/register', function ( $widgets_manager ) {
    if ( ! class_exists( '\Elementor\Widget_Base' ) ) return;

    class PTC_Elementor_Toggle_Widget extends \Elementor\Widget_Base {

        public function get_name()  { return 'ptc_theme_toggle'; }
        public function get_title() { return 'PTC Theme Toggle'; }
        public function get_icon()  { return 'eicon-toggle'; }

        public function get_categories() { return [ 'general' ]; }
        public function get_keywords()   { return [ 'theme', 'dark', 'toggle', 'ptc' ]; }

        protected function register_controls(): void {

            /* ── CONTENT ── */
            $this->start_controls_section( 'section_content', [
                'label' => 'Contenu',
                'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
            ] );

            /* Thème cible */
            $theme_options = [ '' => '— Choisir —' ];
            foreach ( ptc_get_themes() as $t ) {
                $theme_options[ $t['data_theme'] ?? '' ] = $t['name'] . ' (' . ( $t['data_theme'] ?? '' ) . ')';
            }

            $this->add_control( 'theme_value', [
                'label'   => 'Thème à activer',
                'type'    => \Elementor\Controls_Manager::SELECT,
                'options' => $theme_options,
                'default' => '',
            ] );

            $this->add_control( 'label_default', [
                'label'       => 'Label par défaut',
                'type'        => \Elementor\Controls_Manager::TEXT,
                'default'     => 'Changer le thème',
                'placeholder' => '🌙 Mode sombre',
            ] );

            $this->add_control( 'label_active', [
                'label'       => 'Label quand actif',
                'type'        => \Elementor\Controls_Manager::TEXT,
                'default'     => '',
                'placeholder' => '☀️ Mode clair',
            ] );

            $this->add_control( 'button_tag', [
                'label'   => 'Balise HTML',
                'type'    => \Elementor\Controls_Manager::SELECT,
                'options' => [ 'button' => 'Button', 'a' => 'Lien (a)', 'div' => 'Div', 'span' => 'Span' ],
                'default' => 'button',
            ] );

            $this->end_controls_section();

            /* ── STYLE ── */
            $this->start_controls_section( 'section_style', [
                'label' => 'Style',
                'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
            ] );

            $this->add_control( 'text_color', [
                'label'     => 'Couleur du texte',
                'type'      => \Elementor\Controls_Manager::COLOR,
                'selectors' => [ '{{WRAPPER}} .ptc-toggle' => 'color: {{VALUE}};' ],
            ] );

            $this->add_control( 'background_color', [
                'label'     => 'Couleur de fond',
                'type'      => \Elementor\Controls_Manager::COLOR,
                'selectors' => [ '{{WRAPPER}} .ptc-toggle' => 'background-color: {{VALUE}};' ],
            ] );

            $this->add_group_control( \Elementor\Group_Control_Typography::get_type(), [
                'name'     => 'typography',
                'selector' => '{{WRAPPER}} .ptc-toggle',
            ] );

            $this->add_group_control( \Elementor\Group_Control_Border::get_type(), [
                'name'     => 'border',
                'selector' => '{{WRAPPER}} .ptc-toggle',
            ] );

            $this->add_control( 'border_radius', [
                'label'      => 'Border Radius',
                'type'       => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => [ 'px', '%', 'em' ],
                'selectors'  => [ '{{WRAPPER}} .ptc-toggle' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ],
            ] );

            $this->add_responsive_control( 'padding', [
                'label'      => 'Padding',
                'type'       => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => [ 'px', 'em', 'rem' ],
                'selectors'  => [ '{{WRAPPER}} .ptc-toggle' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ],
            ] );

            $this->end_controls_section();
        }

        protected function render(): void {
            $settings    = $this->get_settings_for_display();
            $dt          = ptc_sanitize_data_theme( $settings['theme_value'] ?? '' );
            if ( ! $dt ) { echo '<p style="color:#d63638">⚠️ Aucun thème sélectionné.</p>'; return; }

            $tag         = in_array( $settings['button_tag'], [ 'button', 'a', 'div', 'span' ], true ) ? $settings['button_tag'] : 'button';
            $label       = esc_html( $settings['label_default'] ?: 'Changer le thème' );
            $active_attr = $settings['label_active'] ? ' data-ptc-label-active="' . esc_attr( $settings['label_active'] ) . '"' : '';
            $extra       = 'button' === $tag ? ' type="button"' : ( 'a' === $tag ? ' href="#"' : '' );

            printf(
                '<%1$s class="ptc-toggle" data-ptc-theme="%2$s" aria-pressed="false" role="button"%3$s%4$s>%5$s</%1$s>',
                $tag,
                esc_attr( $dt ),
                $extra,
                $active_attr,
                $label
            );
        }
    }

    $widgets_manager->register( new PTC_Elementor_Toggle_Widget() );
} );
