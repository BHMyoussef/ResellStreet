<?php
/**
 * Meta Pixel + Conversions API for WooCommerce, no plugin.
 *
 * Drop in your theme as inc/meta-tracking.php and load it from functions.php:
 *   require_once get_theme_file_path( 'inc/meta-tracking.php' );
 *
 * Config goes in wp-config.php (keeps the token out of git):
 *   define( 'NV_META_PIXEL_ID',   '000000000000000' );
 *   define( 'NV_META_CAPI_TOKEN', 'EAAG...' );
 *   define( 'NV_META_TEST_CODE',  'TEST12345' );   // only while testing, delete after
 *   define( 'NV_META_REQUIRE_CONSENT', true );     // UK: keep true, wire your cookie banner to nvMeta.grant()
 *
 * Events:
 *   PageView, ViewContent, InitiateCheckout -> browser fires with a client-side event ID,
 *                                              then relays to /wp-json/nv-meta/v1/event -> CAPI (page-cache safe)
 *   AddToCart -> server hook sends CAPI, queues the browser event with the same ID
 *   Purchase  -> server sends CAPI once when the order is paid; thank-you page fires the
 *                browser event with the same deterministic ID (purchase_{order_id})
 */

defined( 'ABSPATH' ) || exit;

define( 'NV_META_GRAPH', 'v24.0' ); // bump to the current Graph API version
define( 'NV_META_CONSENT_COOKIE', 'nv_mkt_consent' );

/* ---------------------------------------------------------------- helpers */

function nv_meta_on() {
    return defined( 'NV_META_PIXEL_ID' ) && NV_META_PIXEL_ID && class_exists( 'WooCommerce' );
}

function nv_meta_requires_consent() {
    return ! defined( 'NV_META_REQUIRE_CONSENT' ) || NV_META_REQUIRE_CONSENT;
}

function nv_meta_consented() {
    return ! nv_meta_requires_consent()
        || ( isset( $_COOKIE[ NV_META_CONSENT_COOKIE ] ) && '1' === $_COOKIE[ NV_META_CONSENT_COOKIE ] );
}

function nv_meta_hash( $v ) {
    $v = strtolower( trim( (string) $v ) );
    return '' === $v ? '' : hash( 'sha256', $v );
}

function nv_meta_phone( $phone, $country ) {
    $d = preg_replace( '/\D/', '', (string) $phone );
    if ( $d && 'GB' === $country && 0 === strpos( $d, '0' ) ) {
        $d = '44' . substr( $d, 1 ); // 07xxx -> 447xxx
    }
    return $d;
}

/** Must match the IDs in your catalog if you ever sync one. */
function nv_meta_content_id( WC_Product $p ) {
    return (string) ( $p->get_sku() ?: $p->get_id() );
}

function nv_meta_cookie( $name ) {
    return isset( $_COOKIE[ $name ] ) ? wc_clean( wp_unslash( $_COOKIE[ $name ] ) ) : '';
}

/** User data from the current request (IP, UA, Meta cookies, logged-in email). */
function nv_meta_request_user( $fbc_fallback = '' ) {
    $fbc = nv_meta_cookie( '_fbc' );
    if ( ! $fbc && preg_match( '/^fb\.1\.\d+\.[\w-]+$/', (string) $fbc_fallback ) ) {
        $fbc = $fbc_fallback;
    }
    $ud = [
        'client_ip_address' => WC_Geolocation::get_ip_address(),
        'client_user_agent' => isset( $_SERVER['HTTP_USER_AGENT'] ) ? wc_clean( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '',
        'fbp'               => nv_meta_cookie( '_fbp' ),
        'fbc'               => $fbc,
    ];
    if ( is_user_logged_in() ) {
        $u                 = wp_get_current_user();
        $ud['em']          = [ nv_meta_hash( $u->user_email ) ];
        $ud['external_id'] = [ nv_meta_hash( (string) $u->ID ) ];
    }
    return $ud;
}

function nv_meta_clean_user( array $u ) {
    foreach ( $u as $k => $v ) {
        if ( is_array( $v ) ) {
            $v = array_values( array_filter( $v ) );
        }
        if ( empty( $v ) ) {
            unset( $u[ $k ] );
        } else {
            $u[ $k ] = $v;
        }
    }
    return $u;
}

/** Whitelist params coming from the browser relay. */
function nv_meta_clean_params( array $p ) {
    $out = [];
    if ( isset( $p['content_ids'] ) && is_array( $p['content_ids'] ) ) {
        $out['content_ids'] = array_slice( array_map( 'sanitize_text_field', array_map( 'strval', $p['content_ids'] ) ), 0, 50 );
    }
    foreach ( [ 'content_type', 'content_name', 'currency' ] as $k ) {
        if ( isset( $p[ $k ] ) && is_scalar( $p[ $k ] ) ) {
            $out[ $k ] = sanitize_text_field( (string) $p[ $k ] );
        }
    }
    if ( isset( $p['value'] ) && is_numeric( $p['value'] ) ) {
        $out['value'] = round( (float) $p['value'], 2 );
    }
    if ( isset( $p['num_items'] ) && is_numeric( $p['num_items'] ) ) {
        $out['num_items'] = (int) $p['num_items'];
    }
    return $out;
}

/** Send one event to the Conversions API. */
function nv_meta_capi( $event, $id, array $params, array $user, $url ) {
    if ( ! defined( 'NV_META_CAPI_TOKEN' ) || ! NV_META_CAPI_TOKEN ) {
        return;
    }
    $body = [
        'data' => [
            [
                'event_name'       => $event,
                'event_time'       => time(),
                'event_id'         => $id,
                'action_source'    => 'website',
                'event_source_url' => $url ?: home_url( '/' ),
                'user_data'        => nv_meta_clean_user( $user ),
                'custom_data'      => (object) $params,
            ],
        ],
    ];
    if ( defined( 'NV_META_TEST_CODE' ) && NV_META_TEST_CODE ) {
        $body['test_event_code'] = NV_META_TEST_CODE;
    }
    $res = wp_remote_post(
        'https://graph.facebook.com/' . NV_META_GRAPH . '/' . NV_META_PIXEL_ID . '/events?access_token=' . rawurlencode( NV_META_CAPI_TOKEN ),
        [
            'timeout' => 3,
            'headers' => [ 'Content-Type' => 'application/json' ],
            'body'    => wp_json_encode( $body ),
        ]
    );
    if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG && ( is_wp_error( $res ) || 200 !== wp_remote_retrieve_response_code( $res ) ) ) {
        error_log( '[nv-meta] ' . $event . ' failed: ' . ( is_wp_error( $res ) ? $res->get_error_message() : wp_remote_retrieve_body( $res ) ) );
    }
}

/* ------------------------------------------------- browser event queue */

function nv_meta_queue( $event, array $params, $id ) {
    if ( ! WC()->session ) {
        return;
    }
    $q   = (array) WC()->session->get( 'nv_meta_q', [] );
    $q[] = [ 'e' => $event, 'p' => $params, 'id' => $id ];
    WC()->session->set( 'nv_meta_q', array_slice( $q, -10 ) );
}

function nv_meta_queue_html() {
    $q = [];
    if ( function_exists( 'WC' ) && WC()->session ) {
        $q = (array) WC()->session->get( 'nv_meta_q', [] );
        if ( $q ) {
            WC()->session->set( 'nv_meta_q', [] );
        }
    }
    return '<div class="nv-meta-q" hidden data-q="' . esc_attr( wp_json_encode( $q ) ) . '"></div>';
}

// AJAX add-to-cart: deliver queued events through the fragments response.
add_filter( 'woocommerce_add_to_cart_fragments', function ( $fragments ) {
    if ( nv_meta_on() ) {
        $fragments['div.nv-meta-q'] = nv_meta_queue_html();
    }
    return $fragments;
} );

/* ------------------------------------------------------- base pixel */

add_action( 'wp_head', function () {
    if ( ! nv_meta_on() ) {
        return;
    }
    $cfg = [
        'pixel'   => NV_META_PIXEL_ID,
        'rest'    => esc_url_raw( rest_url( 'nv-meta/v1/event' ) ),
        'cookie'  => NV_META_CONSENT_COOKIE,
        'consent' => nv_meta_requires_consent(),
    ];
    ?>
<script>
(function(w,d){
var C=<?php echo wp_json_encode( $cfg ); ?>;
!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(w,d,'script','https://connect.facebook.net/en_US/fbevents.js');
function ok(){return !C.consent||d.cookie.indexOf(C.cookie+'=1')>-1;}
function seen(id){try{var s=JSON.parse(sessionStorage.getItem('nvMetaSeen')||'[]');if(s.indexOf(id)>-1)return true;s.push(id);sessionStorage.setItem('nvMetaSeen',JSON.stringify(s.slice(-50)));}catch(e){}return false;}
function fbc(){var m=d.cookie.match(/(?:^|; )_fbc=([^;]+)/);if(m)return m[1];var c=new URLSearchParams(location.search).get('fbclid');return c?'fb.1.'+Date.now()+'.'+c:'';}
function relay(ev,id,p){if(!ok())return;try{fetch(C.rest,{method:'POST',keepalive:true,credentials:'same-origin',headers:{'Content-Type':'application/json'},body:JSON.stringify({event:ev,id:id,params:p||{},url:location.href,fbc:fbc()})});}catch(e){}}
if(!ok())fbq('consent','revoke');
fbq('init',C.pixel);
w.nvMeta={
 track:function(ev,p,id,r){id=id||ev.toLowerCase()+'_'+Date.now().toString(36)+Math.random().toString(36).slice(2,10);if(seen(id))return;fbq('track',ev,p||{},{eventID:id});if(r)relay(ev,id,p);},
 grant:function(){d.cookie=C.cookie+'=1; path=/; max-age=31536000; SameSite=Lax';fbq('consent','grant');},
 drain:function(){var el=d.querySelector('.nv-meta-q');if(!el)return;try{JSON.parse(el.getAttribute('data-q')||'[]').forEach(function(x){w.nvMeta.track(x.e,x.p,x.id,false);});}catch(e){}}
};
w.nvMeta.track('PageView',{},null,true);
d.addEventListener('DOMContentLoaded',function(){w.nvMeta.drain();if(w.jQuery)jQuery(d.body).on('added_to_cart wc_fragments_refreshed wc_fragments_loaded',w.nvMeta.drain);});
})(window,document);
</script>
    <?php
}, 1 );

/* -------------------------------------- ViewContent / InitiateCheckout */

add_action( 'wp_footer', function () {
    if ( ! nv_meta_on() ) {
        return;
    }
    $ev = null;
    $p  = [];

    if ( is_product() ) {
        $product = wc_get_product( get_queried_object_id() );
        if ( $product ) {
            $ev = 'ViewContent';
            $p  = [
                'content_ids'  => [ nv_meta_content_id( $product ) ],
                'content_type' => 'product',
                'content_name' => $product->get_name(),
                'value'        => round( (float) wc_get_price_to_display( $product ), 2 ),
                'currency'     => get_woocommerce_currency(),
            ];
        }
    } elseif ( is_checkout() && ! is_wc_endpoint_url( 'order-received' ) && ! is_wc_endpoint_url( 'order-pay' ) && WC()->cart && ! WC()->cart->is_empty() ) {
        $ids = [];
        foreach ( WC()->cart->get_cart() as $item ) {
            $ids[] = nv_meta_content_id( $item['data'] );
        }
        $ev = 'InitiateCheckout';
        $p  = [
            'content_ids'  => $ids,
            'content_type' => 'product',
            'num_items'    => WC()->cart->get_cart_contents_count(),
            'value'        => round( (float) WC()->cart->get_total( 'edit' ), 2 ),
            'currency'     => get_woocommerce_currency(),
        ];
    }

    if ( $ev ) {
        printf( '<script>nvMeta.track(%s,%s,null,true);</script>', wp_json_encode( $ev ), wp_json_encode( $p ) );
    }
    echo nv_meta_queue_html(); // phpcs:ignore WordPress.Security.EscapeOutput
}, 20 );

/* ------------------------------------------------------------ AddToCart */

add_action( 'woocommerce_add_to_cart', function ( $key, $product_id, $qty, $variation_id ) {
    if ( ! nv_meta_on() || ! nv_meta_consented() ) {
        return;
    }
    $product = wc_get_product( $variation_id ?: $product_id );
    if ( ! $product ) {
        return;
    }
    $id     = 'addtocart_' . str_replace( '-', '', wp_generate_uuid4() );
    $params = [
        'content_ids'  => [ nv_meta_content_id( $product ) ],
        'content_type' => 'product',
        'content_name' => $product->get_name(),
        'value'        => round( (float) wc_get_price_to_display( $product ) * $qty, 2 ),
        'currency'     => get_woocommerce_currency(),
    ];
    nv_meta_capi( 'AddToCart', $id, $params, nv_meta_request_user(), wp_get_referer() ?: home_url( '/' ) );
    nv_meta_queue( 'AddToCart', $params, $id );
}, 10, 4 );

/* ------------------------------------------------------------- Purchase */

// Store browser IDs + consent on the order (classic and block checkout).
function nv_meta_stamp_order( $order ) {
    foreach ( [ '_fbp', '_fbc' ] as $c ) {
        $v = nv_meta_cookie( $c );
        if ( $v ) {
            $order->update_meta_data( '_nv' . $c, $v );
        }
    }
    $order->update_meta_data( '_nv_meta_consent', nv_meta_consented() ? 'yes' : 'no' );
}
add_action( 'woocommerce_checkout_create_order', 'nv_meta_stamp_order' );
add_action( 'woocommerce_store_api_checkout_update_order_from_request', 'nv_meta_stamp_order' );

function nv_meta_order_params( WC_Order $order ) {
    $ids = [];
    $n   = 0;
    foreach ( $order->get_items() as $item ) {
        $prod = $item->get_product();
        if ( $prod ) {
            $ids[] = nv_meta_content_id( $prod );
        }
        $n += $item->get_quantity();
    }
    return [
        'content_ids'  => $ids,
        'content_type' => 'product',
        'num_items'    => $n,
        'value'        => round( (float) $order->get_total(), 2 ),
        'currency'     => $order->get_currency(),
        'order_id'     => (string) $order->get_order_number(),
    ];
}

// Server side: once, when the order is actually paid (works even if they never see the thank-you page).
function nv_meta_purchase_capi( $order_id ) {
    $order = wc_get_order( $order_id );
    if ( ! $order || ! nv_meta_on() || $order->get_meta( '_nv_meta_capi_sent' ) ) {
        return;
    }
    if ( nv_meta_requires_consent() && 'yes' !== $order->get_meta( '_nv_meta_consent' ) ) {
        return;
    }
    $country = $order->get_billing_country();
    $ud      = [
        'em'                => [ nv_meta_hash( $order->get_billing_email() ) ],
        'ph'                => [ nv_meta_hash( nv_meta_phone( $order->get_billing_phone(), $country ) ) ],
        'fn'                => [ nv_meta_hash( $order->get_billing_first_name() ) ],
        'ln'                => [ nv_meta_hash( $order->get_billing_last_name() ) ],
        'ct'                => [ nv_meta_hash( str_replace( ' ', '', $order->get_billing_city() ) ) ],
        'zp'                => [ nv_meta_hash( str_replace( ' ', '', $order->get_billing_postcode() ) ) ],
        'country'           => [ nv_meta_hash( $country ) ],
        'external_id'       => [ nv_meta_hash( $order->get_customer_id() ? (string) $order->get_customer_id() : $order->get_billing_email() ) ],
        'client_ip_address' => $order->get_customer_ip_address(),
        'client_user_agent' => $order->get_customer_user_agent(),
        'fbp'               => $order->get_meta( '_nv_fbp' ),
        'fbc'               => $order->get_meta( '_nv_fbc' ),
    ];
    nv_meta_capi( 'Purchase', 'purchase_' . $order->get_id(), nv_meta_order_params( $order ), $ud, $order->get_checkout_order_received_url() );
    $order->update_meta_data( '_nv_meta_capi_sent', 1 );
    $order->save();
}
add_action( 'woocommerce_order_status_processing', 'nv_meta_purchase_capi' );
add_action( 'woocommerce_order_status_completed', 'nv_meta_purchase_capi' );

// Browser side: same event ID so Meta deduplicates. Fires once per order.
add_action( 'woocommerce_thankyou', function ( $order_id ) {
    $order = $order_id ? wc_get_order( $order_id ) : null;
    if ( ! $order || ! nv_meta_on() || $order->has_status( 'failed' ) || $order->get_meta( '_nv_meta_px_sent' ) ) {
        return;
    }
    $order->update_meta_data( '_nv_meta_px_sent', 1 );
    $order->save();
    printf(
        '<script>nvMeta.track("Purchase",%s,%s,false);</script>',
        wp_json_encode( nv_meta_order_params( $order ) ),
        wp_json_encode( 'purchase_' . $order->get_id() )
    );
} );

/* ------------------------------------------------ browser -> CAPI relay */

add_action( 'rest_api_init', function () {
    register_rest_route( 'nv-meta/v1', '/event', [
        'methods'             => 'POST',
        'permission_callback' => '__return_true',
        'callback'            => function ( WP_REST_Request $r ) {
            $event = (string) $r->get_param( 'event' );
            $id    = (string) $r->get_param( 'id' );
            if ( ! nv_meta_on() || ! nv_meta_consented()
                || ! in_array( $event, [ 'PageView', 'ViewContent', 'InitiateCheckout' ], true )
                || ! preg_match( '/^[a-z0-9_]{6,64}$/i', $id ) ) {
                return new WP_REST_Response( null, 204 );
            }
            $url = esc_url_raw( (string) $r->get_param( 'url' ) );
            if ( wp_parse_url( $url, PHP_URL_HOST ) !== wp_parse_url( home_url(), PHP_URL_HOST ) ) {
                $url = home_url( '/' );
            }
            $params = $r->get_param( 'params' );
            nv_meta_capi(
                $event,
                $id,
                nv_meta_clean_params( is_array( $params ) ? $params : [] ),
                nv_meta_request_user( (string) $r->get_param( 'fbc' ) ),
                $url
            );
            return new WP_REST_Response( null, 204 );
        },
    ] );
} );
