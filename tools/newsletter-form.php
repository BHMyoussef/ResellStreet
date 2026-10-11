<?php
/**
 * Footer "Sign up and save" = a WPForms form (email only, like the live store) in the newsletter_shortcode slot.
 * Sign-ups are saved by the theme (inc/subscribers.php → Users → Subscribers); no email per sign-up (store's choice).
 * Idempotent: reuses the form (option ci_newsletter_form_id), rewrites its fields/settings, sets the theme mod.
 *
 * Run: tools/wp eval-file tools/newsletter-form.php --user=1   (seed.sh does this when WPForms is active)
 *
 * @package CustomImpulse
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'wpforms' ) ) {
	WP_CLI::warning( 'WPForms is not active: footer newsletter form skipped.' );
	return;
}

$data = array(
	'fields'   => array(
		'1' => array(
			'id'          => '1',
			'type'        => 'email',
			'label'       => 'Enter your email',
			'label_hide'  => '1', // visually hidden, still announced by screen readers
			'placeholder' => 'Enter your email',
			'required'    => '1',
			'size'        => 'large',
		),
	),
	'field_id' => 2,
	'settings' => array(
		'form_title'             => 'Newsletter (footer)',
		'form_desc'              => '',
		'submit_text'            => 'Subscribe', // shown as the email icon (footer.css), read out as "Subscribe"
		'submit_text_processing' => 'Subscribing…',
		'submit_class'           => 'footer-newsletter__submit',
		'antispam_v3'            => '1',
		'ajax_submit'            => '1',
		'notification_enable'    => '0', // saved to the subscribers table instead
		'notifications'          => array(
			'1' => array(
				'notification_name' => 'Store owner',
				'email'             => '{admin_email}',
				'subject'           => 'Newsletter sign-up: {field_id="1"}',
				'sender_name'       => 'ResellStreet',
				'sender_address'    => '{admin_email}',
				'replyto'           => '{field_id="1"}',
				'message'           => '{all_fields}',
			),
		),
		'confirmations'          => array(
			'1' => array(
				'type'    => 'message',
				'message' => '<p>Thanks for subscribing!</p>',
			),
		),
	),
	'meta'     => array( 'template' => 'blank' ),
);

$form_id = (int) get_option( 'ci_newsletter_form_id' );
if ( ! $form_id || 'wpforms' !== get_post_type( $form_id ) ) {
	$form_id = (int) wpforms()->obj( 'form' )->add( 'Newsletter (footer)' );
	if ( ! $form_id ) {
		WP_CLI::error( 'Could not create the form (run with --user=<admin>).' );
	}
	update_option( 'ci_newsletter_form_id', $form_id, false );
}
$data['id'] = $form_id;
wpforms()->obj( 'form' )->update( $form_id, $data );

set_theme_mod( 'newsletter_shortcode', sprintf( '[wpforms id="%d" title="false"]', $form_id ) );

WP_CLI::success( sprintf( 'Footer newsletter form #%d (Customizer → Footer → Newsletter form shortcode)', $form_id ) );
