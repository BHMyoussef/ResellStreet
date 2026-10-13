<?php
/**
 * Contact Us page = a WPForms form with the live store's fields (Name, Email, Message; "Send").
 * Idempotent: reuses the form (option ci_contact_form_id), rewrites its fields/settings, puts it on the page.
 *
 * Run: tools/wp eval-file tools/contact-form.php --user=1   (seed.sh does this when WPForms is active)
 *
 * @package CustomImpulse
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'wpforms' ) ) {
	WP_CLI::warning( 'WPForms is not active: Contact Us form skipped.' );
	return;
}

$data = array(
	'fields'   => array(
		'1' => array(
			'id'       => '1',
			'type'     => 'name',
			'format'   => 'simple',
			'label'    => 'Name',
			'required' => '1',
			'size'     => 'large',
		),
		'2' => array(
			'id'       => '2',
			'type'     => 'email',
			'label'    => 'Email',
			'required' => '1',
			'size'     => 'large',
		),
		'3' => array(
			'id'       => '3',
			'type'     => 'textarea',
			'label'    => 'Message',
			'required' => '1',
			'size'     => 'large',
		),
	),
	'field_id' => 4,
	'settings' => array(
		'form_title'             => 'Contact Us',
		'form_desc'              => '',
		'submit_text'            => 'Send',
		'submit_text_processing' => 'Sending…',
		'submit_class'           => 'btn', // the theme's button
		'antispam_v3'            => '1',
		'ajax_submit'            => '1',
		'notification_enable'    => '1',
		'notifications'          => array(
			'1' => array(
				'notification_name' => 'Store owner',
				// Goes to the site admin email (Settings → General) until it is changed in WPForms → Notifications.
				'email'             => '{admin_email}',
				'subject'           => 'New message from {field_id="1"} ({site_name} contact form)',
				'sender_name'       => '{site_name}',
				'sender_address'    => '{admin_email}',
				'replyto'           => '{field_id="2"}',
				'message'           => '{all_fields}',
			),
		),
		'confirmations'          => array(
			'1' => array(
				'type'           => 'message',
				'message'        => '<p>Thanks for contacting us. We’ll get back to you as soon as possible.</p>',
				'message_scroll' => '1',
			),
		),
	),
	'meta'     => array( 'template' => 'simple-contact-form-template' ),
);

$form_id = (int) get_option( 'ci_contact_form_id' );
if ( ! $form_id || 'wpforms' !== get_post_type( $form_id ) ) {
	$form_id = (int) wpforms()->obj( 'form' )->add( 'Contact Us' );
	if ( ! $form_id ) {
		WP_CLI::error( 'Could not create the form (run with --user=<admin>).' );
	}
	update_option( 'ci_contact_form_id', $form_id, false );
}
$data['id'] = $form_id;
wpforms()->obj( 'form' )->update( $form_id, $data );

// WPForms → Settings → General → Include Form Styling: "Base styles only" (2). Colours/type come from the theme
// (assets/css/forms.css), so forms match the store instead of WPForms' blue default.
$settings                = (array) get_option( 'wpforms_settings', array() );
$settings['disable-css'] = '2';
update_option( 'wpforms_settings', $settings );

$page = get_page_by_path( 'contact-us' );
if ( $page ) {
	wp_update_post(
		array(
			'ID'           => $page->ID,
			'post_content' => sprintf( '[wpforms id="%d" title="false"]', $form_id ),
		)
	);
}

WP_CLI::success( sprintf( 'Contact form #%d on %s', $form_id, $page ? get_permalink( $page ) : '(no contact-us page)' ) );
