<?php
/**
 * Design Studio admin page shell.
 *
 * @package JSDW_AI_Chat
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="jsdw-page jsdw-ds-wrap" id="jsdw-ai-chat-design-studio">
	<div class="jsdw-ds-topbar">
		<h1 class="jsdw-ds-title"><?php echo esc_html__( 'Chat Design Studio', 'jsdw-ai-chat' ); ?></h1>
		<div class="jsdw-ds-tabs" role="tablist">
			<button type="button" class="jsdw-ds-tab is-active" data-tab="appearance" role="tab" aria-selected="true"><?php echo esc_html__( 'Appearance', 'jsdw-ai-chat' ); ?></button>
			<button type="button" class="jsdw-ds-tab" data-tab="behavior" role="tab" aria-selected="false"><?php echo esc_html__( 'Behavior', 'jsdw-ai-chat' ); ?></button>
			<button type="button" class="jsdw-ds-tab" data-tab="content" role="tab" aria-selected="false"><?php echo esc_html__( 'Content', 'jsdw-ai-chat' ); ?></button>
		</div>
		<button type="button" class="button button-primary jsdw-ds-save" id="jsdw-ds-save"><?php echo esc_html__( 'Save Settings', 'jsdw-ai-chat' ); ?></button>
		<span class="jsdw-ds-save-msg" id="jsdw-ds-save-msg" aria-live="polite"></span>
	</div>

	<div class="jsdw-ds-body">
		<div class="jsdw-ds-sidebar">
			<div class="jsdw-ds-panel is-active" data-panel="appearance" role="tabpanel">
				<h2 class="jsdw-ds-section-title"><?php echo esc_html__( 'Quick Theme', 'jsdw-ai-chat' ); ?></h2>
				<div class="jsdw-ds-theme-grid" id="jsdw-ds-themes"></div>

				<h2 class="jsdw-ds-section-title"><?php echo esc_html__( 'Colors', 'jsdw-ai-chat' ); ?></h2>
				<?php
				$swatches = array( '#6c63ff', '#e94560', '#276749', '#ff6b6b', '#0077b6', '#495057', '#111111', '#ffffff' );
				$color_fields = array(
					'primaryColor'   => __( 'Primary / Header', 'jsdw-ai-chat' ),
					'chatBg'         => __( 'Chat Background', 'jsdw-ai-chat' ),
					'botBubbleColor' => __( 'Bot Bubble', 'jsdw-ai-chat' ),
				);
				foreach ( $color_fields as $field => $label ) {
					?>
					<div class="jsdw-ds-color-field" data-color-field="<?php echo esc_attr( $field ); ?>">
						<label class="jsdw-ds-label"><?php echo esc_html( $label ); ?></label>
						<div class="jsdw-ds-swatches">
							<?php foreach ( $swatches as $sw ) : ?>
								<button type="button" class="jsdw-ds-swatch" data-hex="<?php echo esc_attr( $sw ); ?>" style="background:<?php echo esc_attr( $sw ); ?>" aria-label="<?php echo esc_attr( $sw ); ?>"></button>
							<?php endforeach; ?>
						</div>
						<div class="jsdw-ds-color-row">
							<input type="color" class="jsdw-ds-color-picker" value="#6c63ff" aria-label="<?php echo esc_attr( $label ); ?>" />
							<input type="text" class="jsdw-ds-hex-input" maxlength="7" pattern="^#[0-9A-Fa-f]{6}$" />
						</div>
					</div>
					<?php
				}
				?>

				<h2 class="jsdw-ds-section-title"><?php echo esc_html__( 'Typography', 'jsdw-ai-chat' ); ?></h2>
				<label class="jsdw-ds-label"><?php echo esc_html__( 'Font Family', 'jsdw-ai-chat' ); ?></label>
				<select class="jsdw-ds-select" id="jsdw-fontFamily">
					<option>Instrument Sans</option>
					<option>Inter</option>
					<option>DM Sans</option>
					<option>Georgia</option>
					<option>System UI</option>
				</select>
				<div class="jsdw-ds-range-row">
					<label class="jsdw-ds-label" for="jsdw-fontSize"><?php echo esc_html__( 'Font Size', 'jsdw-ai-chat' ); ?> <span class="jsdw-ds-val" id="jsdw-fontSize-val">13</span>px</label>
					<input type="range" id="jsdw-fontSize" min="11" max="16" step="1" value="13" class="jsdw-ds-range" />
				</div>

				<h2 class="jsdw-ds-section-title"><?php echo esc_html__( 'Shape & Size', 'jsdw-ai-chat' ); ?></h2>
				<div class="jsdw-ds-range-row">
					<label class="jsdw-ds-label"><?php echo esc_html__( 'Widget Border Radius', 'jsdw-ai-chat' ); ?> <span class="jsdw-ds-val" id="jsdw-borderRadius-val">18</span>px</label>
					<input type="range" id="jsdw-borderRadius" min="0" max="28" step="1" value="18" class="jsdw-ds-range" />
				</div>
				<div class="jsdw-ds-range-row">
					<label class="jsdw-ds-label"><?php echo esc_html__( 'Chat Width', 'jsdw-ai-chat' ); ?> <span class="jsdw-ds-val" id="jsdw-chatWidth-val">360</span>px</label>
					<input type="range" id="jsdw-chatWidth" min="280" max="440" step="10" value="360" class="jsdw-ds-range" />
				</div>
				<div class="jsdw-ds-range-row">
					<label class="jsdw-ds-label"><?php echo esc_html__( 'Chat Height', 'jsdw-ai-chat' ); ?> <span class="jsdw-ds-val" id="jsdw-chatHeight-val">520</span>px</label>
					<input type="range" id="jsdw-chatHeight" min="380" max="640" step="10" value="520" class="jsdw-ds-range" />
				</div>
				<div class="jsdw-ds-range-row">
					<label class="jsdw-ds-label"><?php echo esc_html__( 'Widget Button Size', 'jsdw-ai-chat' ); ?> <span class="jsdw-ds-val" id="jsdw-widgetSize-val">56</span>px</label>
					<input type="range" id="jsdw-widgetSize" min="40" max="72" step="4" value="56" class="jsdw-ds-range" />
				</div>

				<h2 class="jsdw-ds-section-title"><?php echo esc_html__( 'Widget Icon', 'jsdw-ai-chat' ); ?></h2>
				<div class="jsdw-ds-emoji-grid" id="jsdw-widget-icons" data-field="widgetIcon"></div>
			</div>

			<div class="jsdw-ds-panel" data-panel="behavior" role="tabpanel" hidden>
				<h2 class="jsdw-ds-section-title"><?php echo esc_html__( 'Widget Position', 'jsdw-ai-chat' ); ?></h2>
				<div class="jsdw-ds-pos-grid" id="jsdw-position-grid">
					<button type="button" class="jsdw-ds-pos-cell" data-pos="top-left" aria-label="<?php echo esc_attr__( 'Top Left', 'jsdw-ai-chat' ); ?>"></button>
					<button type="button" class="jsdw-ds-pos-cell is-disabled" disabled tabindex="-1" aria-hidden="true"></button>
					<button type="button" class="jsdw-ds-pos-cell" data-pos="top-right" aria-label="<?php echo esc_attr__( 'Top Right', 'jsdw-ai-chat' ); ?>"></button>
					<button type="button" class="jsdw-ds-pos-cell is-disabled" disabled tabindex="-1" aria-hidden="true"></button>
					<button type="button" class="jsdw-ds-pos-cell is-disabled" disabled tabindex="-1" aria-hidden="true"></button>
					<button type="button" class="jsdw-ds-pos-cell is-disabled" disabled tabindex="-1" aria-hidden="true"></button>
					<button type="button" class="jsdw-ds-pos-cell" data-pos="bottom-left" aria-label="<?php echo esc_attr__( 'Bottom Left', 'jsdw-ai-chat' ); ?>"></button>
					<button type="button" class="jsdw-ds-pos-cell is-disabled" disabled tabindex="-1" aria-hidden="true"></button>
					<button type="button" class="jsdw-ds-pos-cell" data-pos="bottom-right" aria-label="<?php echo esc_attr__( 'Bottom Right', 'jsdw-ai-chat' ); ?>"></button>
				</div>
				<p class="jsdw-ds-hint"><?php echo esc_html__( 'Corners only: anchor the floating widget.', 'jsdw-ai-chat' ); ?></p>

				<h2 class="jsdw-ds-section-title"><?php echo esc_html__( 'Open Behavior', 'jsdw-ai-chat' ); ?></h2>
				<div class="jsdw-ds-pill-row">
					<span class="jsdw-ds-label"><?php echo esc_html__( 'Default State', 'jsdw-ai-chat' ); ?></span>
					<div class="jsdw-ds-pills" data-field="defaultState" role="group">
						<button type="button" class="jsdw-ds-pill" data-value="open"><?php echo esc_html__( 'Open', 'jsdw-ai-chat' ); ?></button>
						<button type="button" class="jsdw-ds-pill" data-value="closed"><?php echo esc_html__( 'Closed', 'jsdw-ai-chat' ); ?></button>
					</div>
				</div>
				<div class="jsdw-ds-range-row">
					<label class="jsdw-ds-label"><?php echo esc_html__( 'Auto-open Delay', 'jsdw-ai-chat' ); ?> <span class="jsdw-ds-val" id="jsdw-autoOpenDelay-val">0</span>s</label>
					<input type="range" id="jsdw-autoOpenDelay" min="0" max="30" step="1" value="0" class="jsdw-ds-range" />
				</div>
				<label class="jsdw-ds-label"><?php echo esc_html__( 'Open Trigger', 'jsdw-ai-chat' ); ?></label>
				<select class="jsdw-ds-select" id="jsdw-openTrigger">
					<option value="page-load"><?php echo esc_html__( 'On page load', 'jsdw-ai-chat' ); ?></option>
					<option value="scroll-50"><?php echo esc_html__( 'On scroll (50%)', 'jsdw-ai-chat' ); ?></option>
					<option value="exit-intent"><?php echo esc_html__( 'On exit intent', 'jsdw-ai-chat' ); ?></option>
					<option value="button-only"><?php echo esc_html__( 'On button click only', 'jsdw-ai-chat' ); ?></option>
					<option value="time-delay"><?php echo esc_html__( 'After time delay', 'jsdw-ai-chat' ); ?></option>
				</select>

				<h2 class="jsdw-ds-section-title"><?php echo esc_html__( 'Animation', 'jsdw-ai-chat' ); ?></h2>
				<div class="jsdw-ds-pill-row">
					<span class="jsdw-ds-label"><?php echo esc_html__( 'Open Animation', 'jsdw-ai-chat' ); ?></span>
					<div class="jsdw-ds-pills" data-field="animation" role="group">
						<button type="button" class="jsdw-ds-pill" data-value="slide"><?php echo esc_html__( 'Slide', 'jsdw-ai-chat' ); ?></button>
						<button type="button" class="jsdw-ds-pill" data-value="fade"><?php echo esc_html__( 'Fade', 'jsdw-ai-chat' ); ?></button>
						<button type="button" class="jsdw-ds-pill" data-value="pop"><?php echo esc_html__( 'Pop', 'jsdw-ai-chat' ); ?></button>
					</div>
				</div>
				<div class="jsdw-ds-range-row">
					<label class="jsdw-ds-label"><?php echo esc_html__( 'Animation Speed', 'jsdw-ai-chat' ); ?> <span class="jsdw-ds-val" id="jsdw-animationSpeed-val">0.3</span>s</label>
					<input type="range" id="jsdw-animationSpeed" min="0.1" max="0.8" step="0.05" value="0.3" class="jsdw-ds-range" />
				</div>

				<h2 class="jsdw-ds-section-title"><?php echo esc_html__( 'Visibility Rules', 'jsdw-ai-chat' ); ?></h2>
				<p class="jsdw-ds-hint"><?php echo esc_html__( 'Preview simulates viewport width and “hidden on page” overlays; production behavior still depends on the live site.', 'jsdw-ai-chat' ); ?></p>
				<?php
				$vis = array(
					'showOnMobile'  => __( 'Show on Mobile', 'jsdw-ai-chat' ),
					'showOnDesktop' => __( 'Show on Desktop', 'jsdw-ai-chat' ),
				);
				foreach ( $vis as $key => $lab ) {
					?>
					<label class="jsdw-ds-toggle-row">
						<span><?php echo esc_html( $lab ); ?></span>
						<input type="checkbox" class="jsdw-ds-toggle" data-field="<?php echo esc_attr( $key ); ?>" />
						<span class="jsdw-ds-switch"></span>
					</label>
					<?php
				}
				?>
				<label class="jsdw-ds-toggle-row">
					<span><?php echo esc_html__( 'Hide on specific pages', 'jsdw-ai-chat' ); ?></span>
					<input type="checkbox" class="jsdw-ds-toggle" data-field="hideOnPages" id="jsdw-hideOnPages-toggle" />
					<span class="jsdw-ds-switch"></span>
				</label>
				<div id="jsdw-hide-pages-picker" class="jsdw-hide-pages-picker" style="display:none;margin-top:8px;">
					<input type="text" id="jsdw-hide-pages-search" class="jsdw-ds-input" placeholder="<?php echo esc_attr__( 'Search pages…', 'jsdw-ai-chat' ); ?>" autocomplete="off" />
					<div id="jsdw-hide-pages-results" class="jsdw-hide-pages-results" style="margin-top:6px;"></div>
					<div id="jsdw-hide-pages-tags" class="jsdw-hide-pages-tags" style="margin-top:8px;display:flex;flex-wrap:wrap;gap:6px;"></div>
					<input type="hidden" id="jsdw-hide-pages-ids" value="" />
				</div>
				<label class="jsdw-ds-toggle-row">
					<span><?php echo esc_html__( 'Only show to logged-in users', 'jsdw-ai-chat' ); ?></span>
					<input type="checkbox" class="jsdw-ds-toggle" data-field="loggedInOnly" />
					<span class="jsdw-ds-switch"></span>
				</label>
				<p class="jsdw-ds-hint"><?php echo esc_html__( 'Preview shows a “logged-in only” badge on the mock page when enabled.', 'jsdw-ai-chat' ); ?></p>

				<h2 class="jsdw-ds-section-title"><?php echo esc_html__( 'Features', 'jsdw-ai-chat' ); ?></h2>
				<?php
				$feat = array(
					'showBadge'             => __( 'Show notification badge', 'jsdw-ai-chat' ),
					'showQuickReplies'      => __( 'Quick reply chips', 'jsdw-ai-chat' ),
					'showTypingIndicator'   => __( 'Typing indicator', 'jsdw-ai-chat' ),
					'soundEnabled'          => __( 'Sound notification', 'jsdw-ai-chat' ),
					'showTimestamps'        => __( 'Show message timestamps', 'jsdw-ai-chat' ),
					'showBranding'          => __( 'Show "Powered by" branding', 'jsdw-ai-chat' ),
				);
				foreach ( $feat as $key => $lab ) {
					?>
					<label class="jsdw-ds-toggle-row">
						<span><?php echo esc_html( $lab ); ?></span>
						<input type="checkbox" class="jsdw-ds-toggle" data-field="<?php echo esc_attr( $key ); ?>" />
						<span class="jsdw-ds-switch"></span>
					</label>
					<?php
				}
				?>
			</div>

			<div class="jsdw-ds-panel" data-panel="content" role="tabpanel" hidden>
				<h2 class="jsdw-ds-section-title"><?php echo esc_html__( 'Launcher label', 'jsdw-ai-chat' ); ?></h2>
				<label class="jsdw-ds-label" for="jsdw-launcherLabel"><?php echo esc_html__( 'Text beside the launcher icon (optional)', 'jsdw-ai-chat' ); ?></label>
				<input type="text" class="jsdw-ds-input" id="jsdw-launcherLabel" maxlength="80" />
				<p class="jsdw-ds-hint"><?php echo esc_html__( 'Saved with design settings; updates the preview immediately.', 'jsdw-ai-chat' ); ?></p>

				<h2 class="jsdw-ds-section-title"><?php echo esc_html__( 'Bot Identity', 'jsdw-ai-chat' ); ?></h2>
				<label class="jsdw-ds-label"><?php echo esc_html__( 'Bot Name', 'jsdw-ai-chat' ); ?></label>
				<input type="text" class="jsdw-ds-input" id="jsdw-botName" />
				<label class="jsdw-ds-label"><?php echo esc_html__( 'Status Text', 'jsdw-ai-chat' ); ?></label>
				<input type="text" class="jsdw-ds-input" id="jsdw-statusText" />
				<p class="jsdw-ds-label"><?php echo esc_html__( 'Header Avatar', 'jsdw-ai-chat' ); ?></p>
				<div class="jsdw-ds-emoji-grid" id="jsdw-bot-avatars" data-field="botAvatar"></div>

				<h2 class="jsdw-ds-section-title"><?php echo esc_html__( 'Welcome Message', 'jsdw-ai-chat' ); ?></h2>
				<textarea class="jsdw-ds-textarea" id="jsdw-welcomeMessage" rows="3"></textarea>

				<h2 class="jsdw-ds-section-title"><?php echo esc_html__( 'Quick Reply Suggestions', 'jsdw-ai-chat' ); ?></h2>
				<label class="jsdw-ds-label"><?php echo esc_html__( 'Chip 1', 'jsdw-ai-chat' ); ?></label>
				<input type="text" class="jsdw-ds-input" id="jsdw-qr0" />
				<label class="jsdw-ds-label"><?php echo esc_html__( 'Chip 2', 'jsdw-ai-chat' ); ?></label>
				<input type="text" class="jsdw-ds-input" id="jsdw-qr1" />
				<label class="jsdw-ds-label"><?php echo esc_html__( 'Chip 3', 'jsdw-ai-chat' ); ?></label>
				<input type="text" class="jsdw-ds-input" id="jsdw-qr2" />

				<h2 class="jsdw-ds-section-title"><?php echo esc_html__( 'Input Placeholder', 'jsdw-ai-chat' ); ?></h2>
				<input type="text" class="jsdw-ds-input" id="jsdw-inputPlaceholder" />
			</div>
		</div>

		<div class="jsdw-ds-preview-col">
			<div class="jsdw-ds-preview-inner">
				<p class="jsdw-ds-preview-kicker" aria-hidden="true"><?php echo esc_html__( 'Configurator preview', 'jsdw-ai-chat' ); ?></p>
				<div class="jsdw-ds-browser" id="jsdw-ds-browser">
					<div class="jsdw-ds-browser-chrome">
						<span class="jsdw-ds-dots"><span></span><span></span><span></span></span>
						<span class="jsdw-ds-url">yoursite.com</span>
					</div>
					<div class="jsdw-ds-fake-page">
						<div class="jsdw-fake-nav">
							<span class="jsdw-fake-logo"></span>
							<span class="jsdw-fake-links"></span>
						</div>
						<div class="jsdw-fake-hero">
							<span class="jsdw-fake-h1"></span>
							<span class="jsdw-fake-line"></span>
							<span class="jsdw-fake-line short"></span>
						</div>
						<div class="jsdw-fake-cards">
							<span class="jsdw-fake-card"></span>
							<span class="jsdw-fake-card"></span>
							<span class="jsdw-fake-card"></span>
						</div>
					</div>
					<div class="jsdw-preview-root" id="jsdw-preview-root">
						<!-- Preview widget injected by JS (clone structure) -->
					</div>
				</div>
				<p class="jsdw-ds-hint" style="margin-top:12px;max-width:900px;">
					<?php esc_html_e( 'Local preview only — no chat requests. Open triggers, delays, and viewport cues here are simulated; save, then confirm behavior on the live site.', 'jsdw-ai-chat' ); ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=jsdw-ai-chat-settings' ) ); ?>"><?php esc_html_e( 'Widget UI (Settings)', 'jsdw-ai-chat' ); ?></a>
				</p>
			</div>
		</div>
	</div>
</div>
