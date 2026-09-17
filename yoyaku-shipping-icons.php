<?php
/**
 * Plugin Name: Yoyaku Shipping Icons
 * Plugin URI:  https://github.com/benjaminbelaga/yoyaku-shipping-icons
 * Description: Injecte automatiquement un logo devant chaque méthode de livraison WooCommerce (Chronopost, Colissimo, Spring GDS, UPS, FedEx).
 * Version:     1.9.0
 * Author:      Benjamin Belaga
 * Author URI:  https://github.com/benjaminbelaga
 * License:     GPL2+
 * Text Domain: yoyaku-shipping-icons
 */

if ( ! defined( "ABSPATH" ) ) {
    exit; // Exit if accessed directly
}

/**
 * ysl_match_icon()
 *
 * Résout le logo adapté à un libellé de livraison. L'ordre des motifs
 * compte : les plus spécifiques d'abord.
 *
 * @param string $label Libellé (HTML accepté : il est nettoyé ici).
 * @return string Nom de fichier du logo, '' si aucun motif ne correspond.
 */
function ysl_match_icon( $label ) {
    // — PATTERNS : mot-clé à repérer dans le titre → image PNG (classe CSS ysl-shipping-logo)
    $patterns = array(
        // FedEx patterns (ajouté v1.7.0)
        "fedex priority express"    => "fedex-logo.png",
        "fedex priority"            => "fedex-logo.png",
        "fedex first"               => "fedex-logo.png",
        "fedex international"       => "fedex-logo.png",
        "fedex regional"            => "fedex-logo.png",
        "fedex ground"              => "fedex-logo.png",
        "fedex express"             => "fedex-logo.png",
        "fedex®"                    => "fedex-logo.png",
        "fedex"                     => "fedex-logo.png",
        // Chronopost & Colissimo
        "chronopost"                => "chronopost-logo.png",
        "colissimo"                 => "colissimo-logo.png",
        // Spring GDS
        "spring gds"                => "spring-gds.png",
        // UPS patterns (spécifiques d'abord)
        "ups worldwide economy"     => "ups-wwe.png",
        "ups worldwide saver"       => "ups-logo.png",
        "ups express"               => "ups-logo.png",
        "ups standard"              => "ups-logo.png",
        "ups"                       => "ups-logo.png",
    );

    // — Normalisation du label : strip_tags, decode entités (dont &nbsp;), unifie tous les blancs, passe en minuscules
    $haystack = strtolower(
        preg_replace(
            "/\s+/u",
            " ",
            html_entity_decode(
                strip_tags( $label ),
                ENT_QUOTES,
                "UTF-8"
            )
        )
    );

    // — Parcours des motifs et retour du premier logo correspondant
    foreach ( $patterns as $needle => $file ) {
        if ( strpos( $haystack, $needle ) !== false ) {
            return $file;
        }
    }

    return "";
}

/**
 * ysl_icon_html()
 *
 * Helper public réutilisable : renvoie la balise <img> du logo (classe
 * ysl-shipping-logo) pour un libellé, ou '' si aucun motif ne correspond.
 * Utilisé par les filtres WooCommerce et par le chooser de livraison du
 * plugin yoyaku-preorder (Reserve), afin que ce plugin reste la seule
 * source des logos transporteurs.
 *
 * @param string $label Libellé de la méthode de livraison.
 * @return string
 */
function ysl_icon_html( $label ) {
    $file = ysl_match_icon( $label );
    if ( "" === $file ) {
        return "";
    }

    return sprintf(
        "<img class=\"ysl-shipping-logo\" src=\"%s\" alt=\"\" loading=\"lazy\" decoding=\"async\">",
        esc_url( plugin_dir_url( __FILE__ ) . "assets/" . $file )
    );
}

/**
 * ysl_debug_and_icon()
 *
 * Injecte le logo adapté devant le libellé WooCommerce (filtres panier,
 * checkout et Reserve).
 *
 * @param string           $label  Le libellé original rendu par WooCommerce.
 * @param WC_Shipping_Rate $method L\objet méthode de livraison.
 * @return string
 */
function ysl_debug_and_icon( $label, $method ) {
    return ysl_icon_html( $label ) . $label;
}

/**
 * Feuille de style du logo (classe, plus de height inline).
 */
function ysl_enqueue_styles() {
    wp_enqueue_style(
        "yoyaku-shipping-icons",
        plugin_dir_url( __FILE__ ) . "assets/shipping-icons.css",
        array(),
        "1.9.0"
    );
}
add_action( "wp_enqueue_scripts", "ysl_enqueue_styles" );

/**
 * ysl_sort_shipping_rates()
 *
 * Trie les méthodes de livraison par prix croissant, garde Pick up en dernier.
 * v1.8.0: Suppression du check "user selection" qui bloquait le tri initial.
 *
 * @param array $rates Tableau des méthodes de livraison.
 * @return array
 */
function ysl_sort_shipping_rates( $rates ) {
    if ( empty( $rates ) ) {
        return $rates;
    }

    $pickup = array();
    $others = array();

    foreach ( $rates as $id => $rate ) {
        // Recherche flexible pour "Pick up" / "Retrait"
        $label_lower = strtolower( $rate->label );
        if ( strpos( $label_lower, "pick up" ) !== false ||
             strpos( $label_lower, "pickup" ) !== false ||
             strpos( $label_lower, "retrait" ) !== false ) {
            $pickup[ $id ] = $rate;
        } else {
            $others[ $id ] = $rate;
        }
    }

    // Tri par prix croissant (cast float pour éviter comparaison string)
    uasort( $others, function( $a, $b ) {
        return (float) $a->cost <=> (float) $b->cost;
    } );

    // Retourne les méthodes triées + pickup à la fin
    return array_merge( $others, $pickup );
}

// On applique la fonction au rendu des méthodes en panier et checkout (divers hooks)
add_filter( "woocommerce_cart_shipping_method_full_label",     "ysl_debug_and_icon", 10, 2 );
add_filter( "woocommerce_checkout_shipping_method_full_label", "ysl_debug_and_icon", 10, 2 );
add_filter( "woocommerce_cart_shipping_method_label",          "ysl_debug_and_icon", 10, 2 );
add_filter( "woocommerce_checkout_shipping_method_label",      "ysl_debug_and_icon", 10, 2 );

// Tri automatique des méthodes de livraison par prix croissant
// v1.8.0: Priorité 100 pour s'exécuter après les autres plugins shipping
add_filter( "woocommerce_package_rates", "ysl_sort_shipping_rates", 100 );

// Hook supplémentaire pour forcer le tri au moment du calcul des shipping packages
// v1.8.0: Simplifié - tri toujours appliqué pour cohérence d'affichage
add_filter( "woocommerce_cart_shipping_packages", function($packages) {
    foreach ( $packages as $package_key => $package ) {
        if ( isset( $package["rates"] ) ) {
            $packages[$package_key]["rates"] = ysl_sort_shipping_rates( $package["rates"] );
        }
    }
    return $packages;
}, 100 );
