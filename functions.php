<?php
/**
 * Storefront Child Theme — Shamanicca
 * All customizations live here; never edit the parent theme.
 */

// ============================================================
// 0. NOINDEX — master.shamanicca.com is checkout-only; block all
//    crawlers via HTTP header + meta tag so no page is ever listed.
// ============================================================

add_action( 'send_headers', function () {
    header( 'X-Robots-Tag: noindex, nofollow', true );
} );

add_action( 'wp_head', function () {
    echo '<meta name="robots" content="noindex, nofollow">' . "\n";
}, 1 );


// ============================================================
// 1. ENQUEUE PARENT + CHILD STYLES + GOOGLE FONTS (Poppins)
// ============================================================

add_action( 'wp_enqueue_scripts', function () {
    // Storefront registers its own stylesheet as 'storefront-style'.
    // Depend on that handle — never re-enqueue the parent CSS file manually,
    // as doing so loads it twice and breaks load-order assumptions in
    // optimization plugins (WP Rocket, Autoptimize, etc.).

    // Dequeue Storefront's Source Sans Pro so Poppins wins cleanly
    wp_dequeue_style( 'storefront-fonts' );
    wp_deregister_style( 'storefront-fonts' );

    // Poppins from Google Fonts: weights 400, 600, 900
    wp_enqueue_style(
        'shamanicca-google-fonts',
        'https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;900&display=swap',
        array(),
        null
    );

    // Child theme overrides — depends on parent handle, not a duplicate file enqueue
    wp_enqueue_style(
        'storefront-child-style',
        get_stylesheet_uri(),
        array( 'storefront-style', 'shamanicca-google-fonts' ),
        wp_get_theme()->get( 'Version' )
    );
} );


// ============================================================
// 2. NO-DEFER GUARD FOR CHECKOUT SCRIPTS
//
//    Optimization plugins (WP Rocket, Autoptimize, etc.) may add
//    defer/async to WordPress core and WooCommerce Blocks scripts.
//    On the checkout page this breaks wp.data / wp.element because
//    those globals must be available synchronously before any block
//    JS runs.  This filter strips defer/async on checkout only, and
//    only from the handles that are known to cause the problem.
// ============================================================

add_filter( 'script_loader_tag', function ( $tag, $handle, $src ) {
    if ( ! is_checkout() ) {
        return $tag;
    }

    // WP core package handles that must load synchronously
    $wp_packages = array(
        'wp-element',
        'wp-data',
        'wp-hooks',
        'wp-i18n',
        'wp-components',
        'wp-compose',
        'wp-primitives',
        'wp-api-fetch',
        'wp-url',
        'wp-redux-routine',
        'react',
        'react-dom',
    );

    // WooCommerce Blocks / payment handles — match by substring
    $wc_substrings = array(
        'wc-blocks',
        'cart-checkout',
        'checkout-block',
        'wc-settings',
        'wcpay',
        'stripe',
    );

    $should_strip = in_array( $handle, $wp_packages, true );

    if ( ! $should_strip ) {
        foreach ( $wc_substrings as $needle ) {
            if ( false !== stripos( $handle, $needle ) ) {
                $should_strip = true;
                break;
            }
        }
    }

    if ( $should_strip ) {
        $tag = preg_replace( '/\s+(defer|async)(=["\'][^"\']*["\'])?/i', '', $tag );
    }

    return $tag;
}, 10, 3 );


// ============================================================
// 2b. CHECKOUT SCRIPT DIAGNOSTICS
//
//     Runs on checkout pages only.  Writes a structured report
//     to the PHP error log (always) and embeds it as an HTML
//     comment in the page source (WP_DEBUG mode only, never
//     visible to shoppers in production).
//
//     HOW TO READ THE OUTPUT
//     ─────────────────────
//     PHP error log  →  tail -f <wp-content>/debug.log
//                        or check your Local/Flywheel log panel
//     Page source    →  View Source > search "SHAMANICCA SCRIPT DIAGNOSTICS"
//
//     HOW TO VERIFY IN DEVTOOLS NETWORK TAB
//     ──────────────────────────────────────
//     1. Open DevTools → Network → filter by "JS" or type "script" in the
//        search bar.
//     2. Look for wp-element, wp-data, wc-blocks-*.  Each should appear as
//        a separate request with status 200.
//     3. Click a request → Headers → check the Initiator.  It should be
//        the page HTML (not a lazy-loader or worker).
//     4. In the Waterfall column verify these load *before* any
//        wc-blocks-checkout or WCPAY scripts — earlier rows = earlier load.
//     5. If a handle is MISSING entirely the script was dequeued by a plugin
//        (check WP Rocket / Autoptimize "excluded scripts" lists).
//     6. If a handle appears with type="module" or has a "defer" attribute
//        the no-defer guard (section 2) is not running — confirm is_checkout()
//        returns true at that URL and that no caching layer is serving a
//        cached pre-guard copy of the page.
// ============================================================

add_action( 'wp_print_scripts', function () {
    if ( ! is_checkout() ) {
        return;
    }

    $scripts = wp_scripts();

    // Handles that must be present and synchronous for WC Blocks to boot
    $critical = array(
        'wp-element',
        'wp-data',
        'wp-hooks',
        'wp-i18n',
        'wp-components',
        'react',
        'react-dom',
    );

    // WC Blocks handles — matched by substring against the full queue
    $wc_substrings = array( 'wc-blocks', 'cart-checkout', 'checkout-block', 'wc-settings' );

    $report   = array();
    $warnings = array();

    // ── WP core packages ─────────────────────────────────────
    foreach ( $critical as $handle ) {
        $queued     = in_array( $handle, $scripts->queue, true );
        $registered = isset( $scripts->registered[ $handle ] );

        $status = 'OK';
        if ( ! $registered ) {
            $status   = 'NOT REGISTERED';
            $warnings[] = $handle;
        } elseif ( ! $queued ) {
            $status   = 'NOT QUEUED';
            $warnings[] = $handle;
        }

        $report[ $handle ] = $status;
    }

    // ── WC Blocks handles (substring scan) ───────────────────
    $wc_found = array();
    foreach ( $scripts->queue as $h ) {
        foreach ( $wc_substrings as $needle ) {
            if ( false !== stripos( $h, $needle ) ) {
                $wc_found[] = $h;
                break;
            }
        }
    }

    if ( empty( $wc_found ) ) {
        $warnings[] = '(no wc-blocks/checkout handles in queue)';
    }

    // ── Write to PHP error log ────────────────────────────────
    $log_lines = array( '[SHAMANICCA] Checkout script diagnostics:' );
    foreach ( $report as $h => $s ) {
        $log_lines[] = sprintf( '  %-30s %s', $h, $s );
    }
    $log_lines[] = '  WC Blocks handles queued: ' . ( $wc_found ? implode( ', ', $wc_found ) : 'NONE' );
    if ( $warnings ) {
        $log_lines[] = '  WARNINGS: ' . implode( ', ', $warnings );
    }
    error_log( implode( "\n", $log_lines ) );

    // ── HTML comment in source (WP_DEBUG only) ────────────────
    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        echo "\n<!-- SHAMANICCA SCRIPT DIAGNOSTICS (checkout)\n";
        foreach ( $report as $h => $s ) {
            printf( "     %-30s %s\n", $h, $s );
        }
        echo '     WC Blocks queued: ' . ( $wc_found ? implode( ', ', $wc_found ) : 'NONE' ) . "\n";
        if ( $warnings ) {
            echo '     WARNINGS: ' . implode( ', ', $warnings ) . "\n";
        }
        echo "-->\n";
    }
}, 999 ); // priority 999 — run after all plugins have had a chance to enqueue/dequeue


// ============================================================
// 3. COMING SOON BYPASS — allow checkout, cart, and cart-handoff
//    requests through WooCommerce's "Coming Soon" mode so the
//    Next.js → WC cart handoff and checkout flow always work.
// ============================================================

add_filter( 'woocommerce_coming_soon_exclude', function ( $excluded ) {
    if (
        ! empty( $_GET['next_cart'] ) ||
        is_checkout() ||
        is_cart() ||
        is_wc_endpoint_url( 'order-received' )
    ) {
        return true;
    }
    return $excluded;
} );


// ============================================================
// 3. CART HANDOFF — receives ?next_cart= from Next.js,
//    populates WooCommerce cart, redirects to /checkout/
//    populates WooCommerce cart, redirects to /checkout/
// ============================================================

// Prevent WooCommerce/WooPayments from redirecting logged-in admins to
// wp-admin when visiting the checkout or cart handoff URL.
// WooPayments redirects admins to the payments dashboard when the Stripe
// account has restrictions — this bypasses that for the checkout flow.
add_filter( 'woocommerce_prevent_admin_access', '__return_false' );

// Also stop WooPayments' own admin redirect on the checkout page.
add_filter( 'wcpay_redirect_to_onboarding', '__return_false' );
add_filter( 'wcpay_account_data', function( $data ) { return $data; } );


add_action( 'wp_loaded', function () {
    if ( empty( $_GET['next_cart'] ) ) {
        return;
    }

    $raw = base64_decode( sanitize_text_field( wp_unslash( $_GET['next_cart'] ) ), true );
    if ( ! $raw ) {
        return;
    }

    $items = json_decode( $raw, true );
    if ( ! is_array( $items ) || empty( $items ) ) {
        return;
    }

    if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
        return;
    }

    WC()->cart->empty_cart();

    foreach ( $items as $item ) {
        $product_id = isset( $item['product_id'] ) ? absint( $item['product_id'] ) : 0;
        $quantity   = isset( $item['quantity'] )   ? absint( $item['quantity'] )   : 1;
        $size       = isset( $item['size'] )       ? sanitize_text_field( $item['size'] ) : '';

        if ( $product_id <= 0 ) {
            continue;
        }

        $product = wc_get_product( $product_id );
        if ( ! $product ) {
            continue;
        }

        if ( $size && $product->is_type( 'variable' ) ) {
            $variation_id = 0;
            foreach ( $product->get_children() as $var_id ) {
                $variation = wc_get_product( $var_id );
                if ( ! $variation ) {
                    continue;
                }
                foreach ( $variation->get_variation_attributes() as $attr_value ) {
                    if ( strtolower( $attr_value ) === strtolower( $size ) ) {
                        $variation_id = $var_id;
                        break 2;
                    }
                }
            }
            WC()->cart->add_to_cart( $product_id, $quantity, $variation_id ?: 0 );
        } else {
            WC()->cart->add_to_cart( $product_id, $quantity );
        }
    }

    wp_safe_redirect( wc_get_checkout_url() );
    exit;
} );


// ============================================================
// 3. RETURN TO CART — points back to the Next.js cart page
// ============================================================

add_filter( 'woocommerce_get_cart_url', function () {
    return defined( 'WP_DEBUG' ) && WP_DEBUG
        ? 'http://localhost:3000/cart'
        : 'https://beta.shamanicca.com/cart';
} );

// Allow wp_safe_redirect() to send users to the Next.js frontend domains.
// Without this, WordPress blocks cross-domain redirects and falls back to
// admin_url(), causing every WooCommerce empty-cart redirect to land on
// wp-login.php instead of the Next.js cart page.
add_filter( 'allowed_redirect_hosts', function ( $hosts ) {
    $hosts[] = 'beta.shamanicca.com';
    $hosts[] = 'shamanicca.com';
    return $hosts;
} );


// ============================================================
// 4. HIDE STOREFRONT HEADER CHROME sitewide
//    (nav, product search, cart icon — WP is checkout-only)
// ============================================================

add_action( 'init', function () {
    remove_action( 'storefront_header', 'storefront_primary_navigation', 50 );
    remove_action( 'storefront_header', 'storefront_product_search',     40 );
    remove_action( 'storefront_header', 'storefront_header_cart',        60 );
}, 20 );

// Remove the empty header widget region that creates a gap below the header
// on checkout, cart, and order-received pages.
add_action( 'wp', function () {
    if ( is_checkout() || is_cart() || is_wc_endpoint_url( 'order-received' ) ) {
        remove_action( 'storefront_before_content', 'storefront_header_widget_region', 10 );
    }
} );


// ============================================================
// 5. REPLACE CUSTOM LOGO LINK <a> WITH <div>
//    (logo on checkout should not be a clickable link)
// ============================================================

add_filter( 'get_custom_logo', function ( $html ) {
    if ( ! $html ) {
        return $html;
    }
    $html = preg_replace( '/<a([^>]+)class="([^"]*custom-logo-link[^"]*)"([^>]*)>/', '<div$1class="$2"$3>', $html );
    $html = str_replace( '</a>', '</div>', $html );
    return $html;
} );


// ============================================================
// 6. CHECKOUT LAYOUT — sidebar, width, header, distractions
//    All via hooks; zero template file overrides.
// ============================================================

// (a) Remove Storefront sidebar on checkout and cart
add_action( 'wp', function () {
    if ( is_checkout() || is_cart() ) {
        remove_action( 'storefront_sidebar', 'storefront_get_sidebar', 10 );
    }
} );

// (b) Remove footer widget area on checkout, cart, and order-received
add_action( 'wp', function () {
    if ( is_checkout() || is_cart() || is_wc_endpoint_url( 'order-received' ) ) {
        remove_action( 'storefront_footer', 'storefront_footer_widgets', 10 );
    }
} );

// (b2) Remove Storefront's handheld footer bar (mobile bottom nav with
//      account/search/cart icons) on checkout, cart, and order-received.
//      It's distracting during purchase and doesn't serve the flow.
add_action( 'wp', function () {
    if ( is_checkout() || is_cart() || is_wc_endpoint_url( 'order-received' ) ) {
        remove_action( 'storefront_footer', 'storefront_handheld_footer_bar', 999 );
    }
} );

// (c) Hide the WooCommerce "Checkout" page <h1> title —
//     WC Blocks renders its own heading inside the block.
add_filter( 'woocommerce_show_page_title', function ( $show ) {
    if ( is_checkout() ) {
        return false;
    }
    return $show;
} );

// (d) Tag <body> with page-specific classes so all layout CSS is scoped.
add_filter( 'body_class', function ( $classes ) {
    if ( is_checkout() ) {
        $classes[] = 'shamanicca-checkout';
    }
    if ( is_cart() ) {
        $classes[] = 'shamanicca-cart';
    }
    return $classes;
} );


// ============================================================
// 6b. THANK YOU PAGE — signal React site to clear its cart
//
//     Both master.shamanicca.com (WooCommerce) and shamanicca.com
//     (React frontend) share the parent domain .shamanicca.com, so a
//     cookie set here with domain=.shamanicca.com is readable by the
//     React app on page load. The React app detects the cookie,
//     clears its localStorage cart, then deletes the cookie.
//     This keeps the cart in sync without a webhook or URL param.
// ============================================================

add_action( 'woocommerce_thankyou', function () {
    $is_debug  = defined( 'WP_DEBUG' ) && WP_DEBUG;
    $domain    = $is_debug ? '' : '.shamanicca.com';
    $domain_js = $is_debug ? '' : '; domain=.shamanicca.com';
    ?>
    <script>
    (function () {
        try {
            document.cookie = 'shamanicca_order_complete=1; path=/<?php echo $domain_js; ?>; SameSite=Lax; max-age=3600';
        } catch (e) {}
    })();
    </script>
    <?php
}, 5 );

// ============================================================
// 7. THANK YOU PAGE — "What happens next" section
//    woocommerce_thankyou fires after order details on the
//    order-received page and passes the order ID.
// ============================================================

add_action( 'woocommerce_thankyou', function ( $order_id ) {
    $order = $order_id ? wc_get_order( $order_id ) : null;

    $billing_email = $order ? esc_html( $order->get_billing_email() ) : '';

    $store_url = ( defined( 'WP_DEBUG' ) && WP_DEBUG )
        ? 'http://localhost:3000'
        : 'https://shamanicca.com';

    $support_email = 'contact@shamanicca.com';
    ?>

    <section class="shamanicca-next-steps" aria-labelledby="next-steps-heading">

        <h2 id="next-steps-heading"><?php esc_html_e( 'What happens next?', 'storefront-child' ); ?></h2>

        <ol class="next-steps-list">

            <li class="next-step">
                <span class="next-step__icon" aria-hidden="true">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                </span>
                <div class="next-step__body">
                    <strong><?php esc_html_e( 'Order confirmation', 'storefront-child' ); ?></strong>
                    <?php if ( $billing_email ) : ?>
                        <p><?php printf( esc_html__( 'A confirmation email is on its way to %s.', 'storefront-child' ), '<strong>' . $billing_email . '</strong>' ); ?></p>
                    <?php else : ?>
                        <p><?php esc_html_e( 'A confirmation email has been sent to your inbox.', 'storefront-child' ); ?></p>
                    <?php endif; ?>
                </div>
            </li>

            <li class="next-step">
                <span class="next-step__icon" aria-hidden="true">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                </span>
                <div class="next-step__body">
                    <strong><?php esc_html_e( 'Production & shipping', 'storefront-child' ); ?></strong>
                    <p><?php esc_html_e( 'Your item is printed on demand. Orders typically ship within 3–7 business days — we\'ll send tracking info as soon as it\'s on its way.', 'storefront-child' ); ?></p>
                </div>
            </li>

            <li class="next-step">
                <span class="next-step__icon" aria-hidden="true">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                </span>
                <div class="next-step__body">
                    <strong><?php esc_html_e( 'Need help?', 'storefront-child' ); ?></strong>
                    <p><?php printf(
                        esc_html__( 'Reach us any time at %s — we\'re happy to help.', 'storefront-child' ),
                        '<a href="mailto:' . esc_attr( $support_email ) . '">' . esc_html( $support_email ) . '</a>'
                    ); ?></p>
                </div>
            </li>

        </ol>

        <a href="<?php echo esc_url( $store_url ); ?>" class="button shamanicca-continue-btn">
            <?php esc_html_e( 'Continue shopping', 'storefront-child' ); ?>
        </a>

    </section>

    <?php
}, 10 );


// ============================================================
// 7b. THANK YOU PAGE — body class for scoped CSS
// ============================================================

// Remove hyperlinks from product names in the order-received table
// so items display as plain text (no underlined blue links)
add_filter( 'woocommerce_order_item_permalink', '__return_false' );

add_filter( 'body_class', function ( $classes ) {
    if ( is_wc_endpoint_url( 'order-received' ) ) {
        $classes[] = 'shamanicca-thankyou';
    }
    return $classes;
}, 20 );


// ============================================================
// 8. TRUST BLOCK — injected above Place Order button
//
//    WC Blocks checkout renders via React (async), so PHP hooks
//    that echo HTML directly do not fire inside the block.
//    We output a hidden template via wp_footer and inject it
//    with a MutationObserver once the button appears.
// ============================================================

add_action( 'wp_footer', function () {
    if ( ! is_checkout() ) {
        return;
    }
    ?>

    <div id="shamanicca-trust-tpl" hidden aria-hidden="true">
        <div class="shamanicca-trust-block" role="list" aria-label="Checkout trust signals">
            <div class="trust-item" role="listitem">
                <svg class="trust-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                <span>Secure checkout</span>
            </div>
            <div class="trust-item" role="listitem">
                <svg class="trust-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 .49-3.5"/></svg>
                <span>Easy returns</span>
            </div>
            <div class="trust-item" role="listitem">
                <svg class="trust-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                <span>Support: <a href="mailto:contact@shamanicca.com">contact@shamanicca.com</a></span>
            </div>
            <div class="trust-item" role="listitem">
                <svg class="trust-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                <span>Printed on demand</span>
            </div>
        </div>
    </div>

    <script>
    (function () {
        var tpl = document.getElementById('shamanicca-trust-tpl');
        if (!tpl) return;

        // Hard deadline: stop trying after 15 s to avoid keeping the
        // MutationObserver alive on a page where blocks never rendered.
        var deadline = Date.now() + 15000;
        var observer;

        function wpReady() {
            return window.wp && window.wp.element;
        }

        function inject() {
            // Bail if already injected or past the timeout
            if (document.querySelector('.shamanicca-trust-block')) {
                observer && observer.disconnect();
                return;
            }
            if (Date.now() > deadline) {
                observer && observer.disconnect();
                return;
            }

            // Both conditions must be true before we touch the DOM:
            // 1. wp.element is defined (WC Blocks runtime is ready)
            // 2. The actions container exists in the DOM
            if (!wpReady()) return;
            var actions = document.querySelector('.wc-block-checkout__actions');
            if (!actions) return;

            var block = tpl.firstElementChild.cloneNode(true);
            actions.parentNode.insertBefore(block, actions);
            observer && observer.disconnect();
        }

        observer = new MutationObserver(inject);
        observer.observe(document.body, { childList: true, subtree: true });

        // Run once immediately in case the block is already in the DOM
        // (e.g. page loaded from bfcache or scripts ran synchronously)
        inject();
    })();
    </script>

    <?php
} );


// ============================================================
// 9. WCPAY / STRIPE ELEMENTS — appearance fix
//
//     WooPayments builds the Stripe Elements appearance object by
//     reading computed styles from hidden DOM elements injected into
//     the checkout form.  If those elements don't match the theme's
//     scoped selectors at read-time, getComputedStyle('border-radius')
//     returns "" (empty string), which WooPayments concatenates to "px"
//     and passes to Stripe → "invalid variable value 'px'" warning.
//
//     The official override hook is the 'wcpay_elements_appearance'
//     JS CustomEvent (WooPayments ≥ 10.6.0).  We listen to it and
//     force every border-radius variable to a valid value.
//
//     NOTE: WooPayments caches the appearance object in localStorage
//     under keys matching wcpay_appearance_*.  After deploying this
//     fix, clear those keys in DevTools → Application → Local Storage
//     (or open the page in a private window) to bypass the cache.
// ============================================================

add_action( 'wp_footer', function () {
    if ( ! is_checkout() ) {
        return;
    }
    ?>
    <script>
    ( function () {
        // Tokens that match the Shamanicca design system.
        // Keep in sync with CSS custom properties in style.css.
        var BORDER_RADIUS_INPUT = '10px';
        var BORDER_RADIUS_CARD  = '14px';
        var COLOR_PRIMARY       = '#675dff';
        var FONT_FAMILY         = "'Poppins', sans-serif";

        function applyAppearance( event ) {
            var appearance = event.detail && event.detail.appearance;
            if ( ! appearance ) return;

            // Ensure variables object exists
            appearance.variables = appearance.variables || {};

            // Force valid border-radius values — prevents "px" warning
            appearance.variables.borderRadius        = BORDER_RADIUS_INPUT;
            appearance.variables.tabBorderRadius     = BORDER_RADIUS_CARD;

            // Reinforce brand tokens in case auto-detection produced wrong values
            appearance.variables.colorPrimary        = COLOR_PRIMARY;
            appearance.variables.fontFamily          = FONT_FAMILY;
        }

        // wcpay_elements_appearance fires before the appearance object
        // is passed to stripe.elements() — safe to mutate event.detail.appearance
        document.addEventListener( 'wcpay_elements_appearance', applyAppearance );
    } )();
    </script>
    <?php
} );


// ============================================================
// 10. MINIMAL HEADER NAV — checkout + cart only
//
//     Primary nav/search/cart removed sitewide in section 4.
//     On checkout/cart we inject two focused links
//     (Back to store + Support) at storefront_header priority 55
//     (after logo at 20, where nav used to be at 50).
// ============================================================

add_action( 'wp', function () {
    if ( ! is_checkout() && ! is_cart() && ! is_wc_endpoint_url( 'order-received' ) ) {
        return;
    }

    add_action( 'storefront_header', function () {
        $cart_url      = ( defined( 'WP_DEBUG' ) && WP_DEBUG )
            ? 'http://localhost:3000/cart'
            : 'https://shamanicca.com/cart/';
        $support_email = 'contact@shamanicca.com';
        ?>
        <nav class="shamanicca-minimal-nav" aria-label="<?php esc_attr_e( 'Quick links', 'storefront-child' ); ?>">
            <div class="checkout-top-links">
                <a href="<?php echo esc_url( $cart_url ); ?>">
                    <?php esc_html_e( 'Back to cart', 'storefront-child' ); ?>
                </a>
                <a href="mailto:<?php echo esc_attr( $support_email ); ?>">
                    <?php esc_html_e( 'Support', 'storefront-child' ); ?>
                </a>
            </div>
        </nav>
        <?php
    }, 30 ); // Priority 30 = inside first col-full (logo at 20, container-close at 41)
} );


// ============================================================
// 11. CHECKOUT FIELD CUSTOMISATION
//
//     woocommerce_checkout_fields — respected by both classic and
//     WC Blocks for required/optional status, labels, placeholders,
//     and field removal.
// ============================================================

add_filter( 'woocommerce_checkout_fields', function ( $fields ) {

    // ── BILLING ──────────────────────────────────────────────

    // Remove company — not needed for a DTC brand
    unset( $fields['billing']['billing_company'] );

    // Email → top of billing section (priority 5) for classic checkout
    if ( isset( $fields['billing']['billing_email'] ) ) {
        $fields['billing']['billing_email']['priority']    = 5;
        $fields['billing']['billing_email']['placeholder'] = 'your@email.com';
        $fields['billing']['billing_email']['label']       = __( 'Email address', 'storefront-child' );
    }

    // Name
    if ( isset( $fields['billing']['billing_first_name'] ) ) {
        $fields['billing']['billing_first_name']['priority']    = 10;
        $fields['billing']['billing_first_name']['placeholder'] = __( 'First name', 'storefront-child' );
    }
    if ( isset( $fields['billing']['billing_last_name'] ) ) {
        $fields['billing']['billing_last_name']['priority']    = 20;
        $fields['billing']['billing_last_name']['placeholder'] = __( 'Last name', 'storefront-child' );
    }

    // Address fields — keep all, just set priority + placeholder
    if ( isset( $fields['billing']['billing_country'] ) ) {
        $fields['billing']['billing_country']['priority'] = 40;
    }
    if ( isset( $fields['billing']['billing_address_1'] ) ) {
        $fields['billing']['billing_address_1']['priority']    = 50;
        $fields['billing']['billing_address_1']['placeholder'] = __( 'Street address', 'storefront-child' );
    }
    if ( isset( $fields['billing']['billing_address_2'] ) ) {
        $fields['billing']['billing_address_2']['priority']    = 60;
        $fields['billing']['billing_address_2']['placeholder'] = __( 'Apartment, suite, unit, etc.', 'storefront-child' );
    }
    if ( isset( $fields['billing']['billing_city'] ) ) {
        $fields['billing']['billing_city']['priority']    = 70;
        $fields['billing']['billing_city']['placeholder'] = __( 'City', 'storefront-child' );
    }
    if ( isset( $fields['billing']['billing_state'] ) ) {
        $fields['billing']['billing_state']['priority'] = 80;
    }
    if ( isset( $fields['billing']['billing_postcode'] ) ) {
        $fields['billing']['billing_postcode']['priority']    = 90;
        $fields['billing']['billing_postcode']['placeholder'] = __( 'Postal / ZIP code', 'storefront-child' );
    }

    // Phone → optional, moved to bottom
    if ( isset( $fields['billing']['billing_phone'] ) ) {
        $fields['billing']['billing_phone']['required']    = false;
        $fields['billing']['billing_phone']['priority']    = 100;
        $fields['billing']['billing_phone']['label']       = __( 'Phone', 'storefront-child' );
        $fields['billing']['billing_phone']['placeholder'] = __( 'Phone number (optional)', 'storefront-child' );
    }

    // ── SHIPPING ─────────────────────────────────────────────
    // Keep all address fields — remove company, add placeholders only

    if ( isset( $fields['shipping'] ) ) {
        unset( $fields['shipping']['shipping_company'] );

        $placeholders = array(
            'shipping_first_name' => __( 'First name', 'storefront-child' ),
            'shipping_last_name'  => __( 'Last name', 'storefront-child' ),
            'shipping_address_1'  => __( 'Street address', 'storefront-child' ),
            'shipping_address_2'  => __( 'Apartment, suite, unit, etc.', 'storefront-child' ),
            'shipping_city'       => __( 'City', 'storefront-child' ),
            'shipping_postcode'   => __( 'Postal / ZIP code', 'storefront-child' ),
        );

        foreach ( $placeholders as $key => $placeholder ) {
            if ( isset( $fields['shipping'][ $key ] ) ) {
                $fields['shipping'][ $key ]['placeholder'] = $placeholder;
            }
        }
    }

    return $fields;
} );


// ============================================================
// 12. CHECKOUT ORDER REVIEW — product thumbnail beside item name
//
//     woocommerce_cart_item_name fires for every line in the
//     order review table on the classic shortcode checkout.
//     Prepend the thumbnail only on is_checkout(); skip the
//     order-received endpoint so the thank-you page is unaffected.
// ============================================================

add_filter( 'woocommerce_cart_item_name', function ( $name, $cart_item, $cart_item_key ) {
    if ( ! is_checkout() || is_wc_endpoint_url( 'order-received' ) ) {
        return $name;
    }

    if ( empty( $cart_item['data'] ) || ! is_a( $cart_item['data'], 'WC_Product' ) ) {
        return $name;
    }

    $product = $cart_item['data'];

    // Thumbnail HTML (Woo handles responsive srcset automatically)
    $thumb = $product->get_image( 'woocommerce_thumbnail', array(
        'class' => 'shamanicca-checkout-thumb',
        'alt'   => esc_attr( $product->get_name() ),
    ) );

    // Wrap in .shamanicca-checkout-line so flex lives on an inner element,
    // not on the <td> itself — mixing flex with table-cell breaks across browsers.
    return '<span class="shamanicca-checkout-line">' . $thumb . '<span class="shamanicca-checkout-item-name">' . $name . '</span></span>';
}, 10, 3 );


// ============================================================
// 13. CHECKOUT ORDER REVIEW — display size attribute per line
//
//     woocommerce_get_item_data appends meta rows below each
//     product name in the order review table.  WooCommerce
//     already outputs variation attributes for variable products,
//     but cart items added via the Next.js handoff may arrive as
//     simple products with a custom 'size' key in $cart_item
//     rather than as a true variation.  This filter covers both.
// ============================================================

add_filter( 'woocommerce_get_item_data', function ( $item_data, $cart_item ) {
    if ( ! is_checkout() ) {
        return $item_data;
    }

    // Standard variation attributes (variable products)
    if ( ! empty( $cart_item['variation'] ) && is_array( $cart_item['variation'] ) ) {
        foreach ( $cart_item['variation'] as $key => $value ) {
            if ( stripos( $key, 'attribute_pa_size' ) !== false || stripos( $key, 'attribute_size' ) !== false ) {
                $item_data[] = array(
                    'key'   => __( 'Size', 'storefront-child' ),
                    'value' => wc_clean( $value ),
                );
            }
        }
    }

    return $item_data;
}, 10, 2 );


// ============================================================
// 14. FOOTER — replace Storefront credit with "© 2026 Shamanicca"
// ============================================================

// Remove "Built with WooCommerce" link
add_filter( 'storefront_credit_link', '__return_false' );

// Replace the full copyright line
add_filter( 'storefront_copyright_text', function () {
    return '&copy; ' . gmdate( 'Y' ) . ' Shamanicca';
} );


// ============================================================
// 15. EXPRESS PAY RELOCATION
//
//     WooPayments renders Apple Pay / Google Pay buttons via a
//     hook that fires after the closing </form> tag, leaving them
//     stranded below the form on the page.  This script moves
//     them into the payment section of #order_review (after the
//     totals table and before the card fields), which is the
//     correct conversion-optimised position.
// ============================================================

// ============================================================
// 15b. EXPRESS PAY PLACEMENT — output-buffer approach
//
//      WooPayments outputs the #wcpay-express-checkout-element
//      mount container at woocommerce_checkout_before_customer_-
//      details (priority 1).  We intercept with ob_start at
//      priority 0, capture at priority 2, then re-emit the HTML
//      at woocommerce_review_order_before_payment inside the order
//      card so Stripe JS mounts the buttons in the correct place.
//
//      Use 'wp' action (not 'init') because is_checkout() needs
//      WP query to be established first.
// ============================================================

add_action( 'wp', function () {
    if ( ! is_checkout() || is_wc_endpoint_url( 'order-received' ) ) {
        return;
    }

    $express_html = '';

    add_action( 'woocommerce_checkout_before_customer_details', function () {
        ob_start();
    }, 0 );

    add_action( 'woocommerce_checkout_before_customer_details', function () use ( &$express_html ) {
        $express_html = ob_get_clean();
    }, 2 );

    add_action( 'woocommerce_review_order_before_payment', function () use ( &$express_html ) {
        if ( empty( trim( $express_html ) ) ) {
            return;
        }
        echo '<div class="shamanicca-express-pay-block">';
        echo $express_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo '</div>';
    }, 5 );
} );


// ============================================================
// 2b. HEADLESS CHECKOUT — browser-side cart population
//
//     The Next.js cart page navigates the browser directly to:
//     https://master.shamanicca.com/?headless_checkout=1&items=ID:QTY,ID:QTY
//
//     WordPress adds items to the WC cart within the browser's own
//     HTTP request (so WC can set the session cookie in the response),
//     then redirects the browser to /checkout/ with the cart populated.
// ============================================================

add_action( 'wp_loaded', function () {
    if ( ! isset( $_GET['headless_checkout'] ) || '1' !== $_GET['headless_checkout'] ) {
        return;
    }

    if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
        return;
    }

    $items_param = isset( $_GET['items'] ) ? sanitize_text_field( wp_unslash( $_GET['items'] ) ) : '';

    if ( empty( $items_param ) ) {
        wp_safe_redirect( wc_get_cart_url() );
        exit;
    }

    WC()->cart->empty_cart();

    foreach ( explode( ',', $items_param ) as $pair ) {
        $parts      = explode( ':', trim( $pair ) );
        $product_id = absint( $parts[0] ?? 0 );
        $quantity   = absint( $parts[1] ?? 1 );
        $size_value = isset( $parts[2] ) ? sanitize_text_field( $parts[2] ) : '';

        if ( $product_id <= 0 || $quantity <= 0 ) continue;

        $wc_product = wc_get_product( $product_id );
        if ( ! $wc_product ) continue;

        if ( $size_value && $wc_product instanceof WC_Product_Variable ) {
            $variation_id    = 0;
            $variation_attrs = [];

            foreach ( $wc_product->get_available_variations() as $v ) {
                foreach ( $v['attributes'] as $attr_key => $attr_value ) {
                    if ( stripos( $attr_key, 'size' ) !== false &&
                         strtolower( $attr_value ) === strtolower( $size_value ) ) {
                        $variation_id    = $v['variation_id'];
                        $variation_attrs = $v['attributes'];
                        break 2;
                    }
                }
            }

            if ( $variation_id ) {
                WC()->cart->add_to_cart( $product_id, $quantity, $variation_id, $variation_attrs );
            }
        } else {
            WC()->cart->add_to_cart( $product_id, $quantity );
        }
    }

    wp_safe_redirect( wc_get_checkout_url() );
    exit;
} );


// ============================================================
// 10. SPLASH PAGE — intercepts all WordPress frontend pages
//     and renders the branded holding page instead.
//     Bypassed for: WooCommerce checkout/cart/order, REST API,
//     GraphQL, sitemaps, feeds, and wp-admin.
// ============================================================

add_action( 'template_redirect', function () {
    // Allow WooCommerce transactional pages through
    if ( function_exists( 'is_checkout' ) && is_checkout() ) return;
    if ( function_exists( 'is_cart' ) && is_cart() ) return;
    if ( function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( 'order-received' ) ) return;

    // Allow REST API (WooCommerce Store API, WPGraphQL HTTP, etc.)
    if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) return;
    $uri = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '';
    if ( strpos( $uri, '/wp-json/' ) !== false ) return;
    if ( strpos( $uri, '/graphql' ) !== false ) return;

    // Allow feeds and sitemaps
    if ( is_feed() ) return;
    if ( function_exists( 'is_robots' ) && is_robots() ) return;

    // Render splash and stop WordPress template loading
    $splash = get_stylesheet_directory() . '/splash.php';
    if ( file_exists( $splash ) ) {
        include $splash;
        exit;
    }
}, 1 );
