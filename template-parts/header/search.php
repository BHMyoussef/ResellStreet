<?php
/**
 * Search overlay (header). Opened by [data-search-open] (search.js); without JS the icon links to the results page.
 *
 * @package CustomImpulse
 */

defined( 'ABSPATH' ) || exit;
?>
<dialog class="search-dialog" data-search-dialog aria-label="<?php esc_attr_e( 'Search', 'customimpulse' ); ?>">
	<div class="search-dialog__bar">
		<form class="search-dialog__form page-width" role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>">
			<button type="submit" class="search-dialog__submit">
				<?php echo ci_icon( 'search' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				<span class="visually-hidden"><?php esc_html_e( 'Search', 'customimpulse' ); ?></span>
			</button>
			<label class="visually-hidden" for="search-dialog-input"><?php esc_html_e( 'Search our store', 'customimpulse' ); ?></label>
			<input
				type="search"
				id="search-dialog-input"
				class="search-dialog__input"
				name="s"
				placeholder="<?php esc_attr_e( 'Search our store', 'customimpulse' ); ?>"
				autocomplete="off"
				autocapitalize="off"
				spellcheck="false"
				enterkeyhint="search"
				role="combobox"
				aria-autocomplete="list"
				aria-expanded="false"
				aria-controls="search-dialog-results"
			>
			<input type="hidden" name="post_type" value="product">
			<button type="button" class="search-dialog__close" data-search-close>
				<?php echo ci_icon( 'close' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				<span class="visually-hidden"><?php esc_html_e( 'Close search', 'customimpulse' ); ?></span>
			</button>
		</form>
	</div>
	<div class="search-dialog__results" id="search-dialog-results" data-search-results hidden></div>
	<p class="visually-hidden" data-search-status aria-live="polite"></p>
</dialog>
