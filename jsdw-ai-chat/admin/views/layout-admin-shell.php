<?php
/**
 * Phase 7.2 — App shell wrapper for all plugin admin screens.
 *
 * Variables (set by JSDW_AI_Chat_Admin::render_admin_shell() before include):
 * - string $jsdw_admin_inner_view Absolute path to the inner view file.
 * - array  $jsdw_shell_nav_groups From get_admin_shell_nav_groups().
 * - string $jsdw_shell_current_page Sanitized $_GET['page'].
 * - string $jsdw_shell_ui_mode dark-violet|warm-clay (read-only display).
 * Inner view variables are passed via the optional $vars array and extracted into the same scope before this layout is included.
 *
 * @package JSDW_AI_Chat
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$jsdw_shell_brand = __( 'AI Chat Widget', 'jsdw-ai-chat' );
?>
<div id="jsdw-design-studio-wrap">
	<div class="jsdw-shell">
		<header class="jsdw-topbar" role="banner">
			<div class="jsdw-topbar__brand jsdw-u-ellipsis"><?php echo esc_html( $jsdw_shell_brand ); ?></div>
			<div class="jsdw-topbar__actions">
				<span class="jsdw-shell-status-pill" title="<?php echo esc_attr__( 'Status', 'jsdw-ai-chat' ); ?>"><?php echo esc_html__( 'OK', 'jsdw-ai-chat' ); ?></span>
				<button
					type="button"
					class="jsdw-shell-mode-toggle"
					disabled
					data-jsdw-ui-mode="<?php echo esc_attr( $jsdw_shell_ui_mode ); ?>"
					aria-label="<?php echo esc_attr__( 'Theme mode (coming soon)', 'jsdw-ai-chat' ); ?>"
				>
					<span class="dashicons dashicons-admin-appearance" aria-hidden="true"></span>
				</button>
			</div>
		</header>
		<aside class="jsdw-sidebar" role="navigation" aria-label="<?php echo esc_attr__( 'AI Chat Widget', 'jsdw-ai-chat' ); ?>">
			<?php foreach ( $jsdw_shell_nav_groups as $group ) : ?>
				<?php
				if ( ! is_array( $group ) || empty( $group['items'] ) || ! is_array( $group['items'] ) ) {
					continue;
				}
				$visible = array();
				foreach ( $group['items'] as $item ) {
					if ( ! is_array( $item ) || empty( $item['slug'] ) || empty( $item['cap'] ) ) {
						continue;
					}
					if ( current_user_can( $item['cap'] ) ) {
						$visible[] = $item;
					}
				}
				if ( empty( $visible ) ) {
					continue;
				}
				$group_label = isset( $group['label'] ) ? (string) $group['label'] : '';
				?>
				<div class="jsdw-shell-nav__group">
					<?php if ( '' !== $group_label ) : ?>
						<div class="jsdw-shell-nav__heading"><?php echo esc_html( $group_label ); ?></div>
					<?php endif; ?>
					<ul class="jsdw-shell-nav__list" role="list">
						<?php foreach ( $visible as $item ) : ?>
							<?php
							$slug   = (string) $item['slug'];
							$label  = isset( $item['label'] ) ? (string) $item['label'] : $slug;
							$url    = isset( $item['url'] ) ? (string) $item['url'] : '';
							$active = ( $jsdw_shell_current_page === $slug );
							?>
							<li class="jsdw-shell-nav__item">
								<a
									class="jsdw-shell-nav__link<?php echo $active ? ' is-active' : ''; ?>"
									href="<?php echo esc_url( $url ); ?>"
									<?php echo $active ? ' aria-current="page"' : ''; ?>
								><?php echo esc_html( $label ); ?></a>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endforeach; ?>
		</aside>
		<div class="jsdw-content">
			<?php include $jsdw_admin_inner_view; ?>
		</div>
	</div>
</div>
