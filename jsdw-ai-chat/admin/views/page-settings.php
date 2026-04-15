<?php
/**
 * JSDW AI Chat — Settings (Phase 7.3C UX structure; option keys unchanged).
 *
 * @package JSDW_AI_Chat
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$g       = isset( $settings['general'] ) && is_array( $settings['general'] ) ? $settings['general'] : array();
$feat    = isset( $settings['features'] ) && is_array( $settings['features'] ) ? $settings['features'] : array();
$chat    = isset( $settings['chat'] ) && is_array( $settings['chat'] ) ? $settings['chat'] : array();
$privacy = isset( $settings['privacy'] ) && is_array( $settings['privacy'] ) ? $settings['privacy'] : array();
$logging = isset( $settings['logging'] ) && is_array( $settings['logging'] ) ? $settings['logging'] : array();
$index   = isset( $settings['indexing'] ) && is_array( $settings['indexing'] ) ? $settings['indexing'] : array();
$ai      = isset( $settings['ai'] ) && is_array( $settings['ai'] ) ? $settings['ai'] : array();
$wui     = isset( $settings['widget_ui'] ) && is_array( $settings['widget_ui'] ) ? $settings['widget_ui'] : array();
$wd      = isset( $settings['widget_design'] ) && is_array( $settings['widget_design'] ) ? $settings['widget_design'] : array();
$canned  = isset( $chat['canned_responses'] ) && is_array( $chat['canned_responses'] ) ? $chat['canned_responses'] : array();
$qr      = isset( $wd['quickReplies'] ) && is_array( $wd['quickReplies'] ) ? array_values( $wd['quickReplies'] ) : array();
$qr      = array_pad( array_slice( $qr, 0, 3 ), 3, '' );

$ai_status           = JSDW_AI_Chat_AI_Provider_Status::summarize( $settings );
$ai_provider_current = JSDW_AI_Chat_AI_Provider_Status::sanitize_provider( (string) ( $ai['provider'] ?? '' ) );
$ai_has_openai_key   = '' !== trim( (string) ( $ai['openai_api_key'] ?? '' ) );

$jsdw_canned_state_labels = array(
	'no_match'               => __( 'No match', 'jsdw-ai-chat' ),
	'low_confidence'         => __( 'Low confidence', 'jsdw-ai-chat' ),
	'requires_clarification' => __( 'Needs clarification', 'jsdw-ai-chat' ),
	'guard_rejected'         => __( 'Could not process request', 'jsdw-ai-chat' ),
	'failed'                 => __( 'Temporary error', 'jsdw-ai-chat' ),
	'unsupported'            => __( 'Unsupported request', 'jsdw-ai-chat' ),
);
$jsdw_tone_labels = array(
	'concise'  => __( 'Concise', 'jsdw-ai-chat' ),
	'neutral'  => __( 'Neutral', 'jsdw-ai-chat' ),
	'friendly' => __( 'Friendly', 'jsdw-ai-chat' ),
);
?>
<div class="jsdw-page jsdw-settings-page">
	<h1><?php echo esc_html__( 'JSDW AI Chat Settings', 'jsdw-ai-chat' ); ?></h1>

	<?php settings_errors( 'jsdw_ai_chat' ); ?>

	<form method="post">
		<?php wp_nonce_field( $nonce_action, $nonce_name ); ?>
		<p class="submit jsdw-settings-save-top">
			<button type="submit" name="jsdw_ai_chat_settings_save" class="button button-primary" value="1"><?php echo esc_html__( 'Save settings', 'jsdw-ai-chat' ); ?></button>
		</p>

		<div class="jsdw-card">
			<div class="jsdw-card-header">
				<h2><?php echo esc_html__( 'Core System', 'jsdw-ai-chat' ); ?></h2>
				<p class="jsdw-card-desc"><?php echo esc_html__( 'These settings power the core system. Disabling them may prevent the assistant from functioning properly.', 'jsdw-ai-chat' ); ?></p>
			</div>
			<div class="jsdw-card-body">
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php echo esc_html__( 'Plugin enabled', 'jsdw-ai-chat' ); ?></th>
						<td>
							<label><input type="checkbox" name="jsdw[general][enabled]" value="1" <?php checked( ! empty( $g['enabled'] ) ); ?> /> <?php echo esc_html__( 'Enable plugin', 'jsdw-ai-chat' ); ?></label>
							<p class="jsdw-field-help"><?php echo esc_html__( 'Master switch for the plugin.', 'jsdw-ai-chat' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( 'REST API', 'jsdw-ai-chat' ); ?></th>
						<td>
							<label><input type="checkbox" name="jsdw[features][enable_rest]" value="1" <?php checked( ! empty( $feat['enable_rest'] ) ); ?> /> <?php echo esc_html__( 'Enabled', 'jsdw-ai-chat' ); ?></label>
							<p class="jsdw-field-help"><?php echo esc_html__( 'Required for communication between the system and the frontend.', 'jsdw-ai-chat' ); ?></p>
							<p class="jsdw-field-hint"><?php echo esc_html__( 'Recommended: Enabled', 'jsdw-ai-chat' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( 'WP-Cron', 'jsdw-ai-chat' ); ?></th>
						<td>
							<label><input type="checkbox" name="jsdw[features][enable_cron]" value="1" <?php checked( ! empty( $feat['enable_cron'] ) ); ?> /> <?php echo esc_html__( 'Enabled', 'jsdw-ai-chat' ); ?></label>
							<p class="jsdw-field-help"><?php echo esc_html__( 'Used to run scheduled background tasks.', 'jsdw-ai-chat' ); ?></p>
							<p class="jsdw-field-hint"><?php echo esc_html__( 'Recommended: Enabled', 'jsdw-ai-chat' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Background processing queue', 'jsdw-ai-chat' ); ?></th>
						<td>
							<label><input type="checkbox" name="jsdw[features][enable_queue]" value="1" <?php checked( ! empty( $feat['enable_queue'] ) ); ?> /> <?php echo esc_html__( 'Enabled', 'jsdw-ai-chat' ); ?></label>
							<p class="jsdw-field-help"><?php echo esc_html__( 'Processes indexing and content jobs without blocking requests.', 'jsdw-ai-chat' ); ?></p>
							<p class="jsdw-field-hint"><?php echo esc_html__( 'Recommended: Enabled', 'jsdw-ai-chat' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Store conversations', 'jsdw-ai-chat' ); ?></th>
						<td>
							<label><input type="checkbox" name="jsdw[features][enable_chat_storage]" value="1" <?php checked( ! empty( $feat['enable_chat_storage'] ) ); ?> /> <?php echo esc_html__( 'Enabled', 'jsdw-ai-chat' ); ?></label>
							<p class="jsdw-field-help"><?php echo esc_html__( 'Allows the chat subsystem to persist session and message data when other settings permit.', 'jsdw-ai-chat' ); ?></p>
							<p class="jsdw-field-hint"><?php echo esc_html__( 'Recommended: Enabled', 'jsdw-ai-chat' ); ?></p>
						</td>
					</tr>
				</table>
			</div>
		</div>

		<div class="jsdw-card jsdw-card--ai-connection">
			<div class="jsdw-card-header">
				<h2><?php echo esc_html__( 'AI connection & usage', 'jsdw-ai-chat' ); ?></h2>
				<p class="jsdw-card-desc"><?php echo esc_html__( 'Answers are built from your indexed site content (local-first). Optional phrase assist, when enabled and configured, sends only the final local answer text and tone to the provider for wording polish — never raw chunks, retrieval metadata, or internal-only material.', 'jsdw-ai-chat' ); ?></p>
			</div>
			<div class="jsdw-card-body">
				<p class="jsdw-ai-status-row">
					<span class="jsdw-ai-status-pill jsdw-ai-status-pill--<?php echo esc_attr( (string) ( $ai_status['severity'] ?? 'neutral' ) ); ?>"><?php echo esc_html( (string) ( $ai_status['label'] ?? '' ) ); ?></span>
				</p>
				<p class="jsdw-field-help"><?php echo esc_html( (string) ( $ai_status['detail'] ?? '' ) ); ?></p>
				<?php
				$jsdw_ai_warn_stub = '' !== $ai_provider_current
					&& ! empty( $feat['enable_ai'] )
					&& in_array( $ai_provider_current, array( 'anthropic', 'google' ), true );
				?>
				<?php if ( $jsdw_ai_warn_stub ) : ?>
					<p class="jsdw-field-help jsdw-field-help--warning"><?php echo esc_html( sprintf( /* translators: %s: provider display name */ __( '%s phrase assist is not implemented yet. Choose OpenAI or turn off phrase assist.', 'jsdw-ai-chat' ), JSDW_AI_Chat_AI_Provider_Status::provider_label( $ai_provider_current ) ) ); ?></p>
				<?php endif; ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php echo esc_html__( 'AI features', 'jsdw-ai-chat' ); ?></th>
						<td>
							<label><input type="checkbox" name="jsdw[features][enable_ai]" value="1" <?php checked( ! empty( $feat['enable_ai'] ) ); ?> /> <?php echo esc_html__( 'Enable AI-related settings (optional capabilities)', 'jsdw-ai-chat' ); ?></label>
							<p class="jsdw-field-help"><?php echo esc_html__( 'Allows outbound phrase assist when provider credentials are valid and all runtime gates pass. Retrieval and confidence stay local.', 'jsdw-ai-chat' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="jsdw-ai-provider"><?php echo esc_html__( 'AI provider', 'jsdw-ai-chat' ); ?></label></th>
						<td>
							<select name="jsdw[ai][provider]" id="jsdw-ai-provider">
								<option value="" <?php selected( $ai_provider_current, '' ); ?>><?php echo esc_html( JSDW_AI_Chat_AI_Provider_Status::provider_label( '' ) ); ?></option>
								<option value="openai" <?php selected( $ai_provider_current, 'openai' ); ?>><?php echo esc_html( JSDW_AI_Chat_AI_Provider_Status::provider_label( 'openai' ) ); ?></option>
								<option value="anthropic" <?php selected( $ai_provider_current, 'anthropic' ); ?>><?php echo esc_html( JSDW_AI_Chat_AI_Provider_Status::provider_label( 'anthropic' ) ); ?></option>
								<option value="google" <?php selected( $ai_provider_current, 'google' ); ?>><?php echo esc_html( JSDW_AI_Chat_AI_Provider_Status::provider_label( 'google' ) ); ?></option>
							</select>
							<p class="jsdw-field-help"><?php echo esc_html__( 'OpenAI is supported for phrase assist with a saved API key. Other providers are validated but not implemented yet.', 'jsdw-ai-chat' ); ?></p>
						</td>
					</tr>
					<?php if ( 'openai' === $ai_provider_current ) : ?>
					<tr>
						<th scope="row"><label for="jsdw-openai-key"><?php echo esc_html__( 'OpenAI API key', 'jsdw-ai-chat' ); ?></label></th>
						<td>
							<input type="password" name="jsdw[ai][openai_api_key_new]" id="jsdw-openai-key" class="regular-text" value="" autocomplete="off" placeholder="<?php echo esc_attr__( 'Enter new key to replace stored key', 'jsdw-ai-chat' ); ?>" />
							<?php if ( $ai_has_openai_key ) : ?>
								<p class="jsdw-field-help"><?php echo esc_html__( 'A key is already stored. Leave blank to keep it, or enter a new key to replace it.', 'jsdw-ai-chat' ); ?></p>
							<?php endif; ?>
							<label><input type="checkbox" name="jsdw[ai][openai_api_key_clear]" value="1" /> <?php echo esc_html__( 'Remove stored API key', 'jsdw-ai-chat' ); ?></label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="jsdw-openai-model"><?php echo esc_html__( 'OpenAI model', 'jsdw-ai-chat' ); ?></label></th>
						<td>
							<input type="text" name="jsdw[ai][openai_model]" id="jsdw-openai-model" class="regular-text" value="<?php echo esc_attr( (string) ( $ai['openai_model'] ?? 'gpt-4o-mini' ) ); ?>" />
							<p class="jsdw-field-help"><?php echo esc_html__( 'Chat completions model slug (e.g. gpt-4o-mini). Invalid values fall back to the plugin default.', 'jsdw-ai-chat' ); ?></p>
						</td>
					</tr>
					<?php endif; ?>
					<tr>
						<th scope="row"><label for="jsdw-chat-answer-mode"><?php echo esc_html__( 'Answer mode', 'jsdw-ai-chat' ); ?></label></th>
						<td>
							<select name="jsdw[chat][answer_mode]" id="jsdw-chat-answer-mode">
								<option value="strict_local_only" <?php selected( (string) ( $chat['answer_mode'] ?? '' ), 'strict_local_only' ); ?>><?php echo esc_html__( 'Strict local only', 'jsdw-ai-chat' ); ?></option>
								<option value="local_with_optional_ai_phrase" <?php selected( (string) ( $chat['answer_mode'] ?? '' ), 'local_with_optional_ai_phrase' ); ?>><?php echo esc_html__( 'Local with optional AI phrase', 'jsdw-ai-chat' ); ?></option>
								<option value="debug_trace" <?php selected( (string) ( $chat['answer_mode'] ?? '' ), 'debug_trace' ); ?>><?php echo esc_html__( 'Debug trace', 'jsdw-ai-chat' ); ?></option>
							</select>
							<p class="jsdw-field-help"><?php echo esc_html__( 'Strict local only disables the optional phrase-assist path. Use “Local with optional AI phrase” or “Debug trace” to allow phrase refinement when AI features and provider credentials permit.', 'jsdw-ai-chat' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( 'AI-assisted phrasing', 'jsdw-ai-chat' ); ?></th>
						<td>
							<label><input type="checkbox" name="jsdw[chat][allow_ai_phrase_assist]" value="1" <?php checked( ! empty( $chat['allow_ai_phrase_assist'] ) ); ?> /> <?php echo esc_html__( 'Allow optional phrase assist when AI features, provider, and answer mode permit', 'jsdw-ai-chat' ); ?></label>
							<p class="jsdw-field-help"><?php echo esc_html__( 'Requires: AI features on, OpenAI selected with API key, answer mode other than strict local only, and high-confidence local answers. If the API fails, the local answer is returned unchanged.', 'jsdw-ai-chat' ); ?></p>
						</td>
					</tr>
				</table>
			</div>
		</div>


		<div class="jsdw-card">
			<div class="jsdw-card-header">
				<h2><?php echo esc_html__( 'Data & Processing', 'jsdw-ai-chat' ); ?></h2>
				<p class="jsdw-card-desc"><?php echo esc_html__( 'Controls how your content is processed and prepared for responses. The public chat widget only uses knowledge sources classified as public; internal or admin-only sources are never sent to public visitors.', 'jsdw-ai-chat' ); ?></p>
			</div>
			<div class="jsdw-card-body">
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php echo esc_html__( 'Indexing pipeline', 'jsdw-ai-chat' ); ?></th>
						<td>
							<label><input type="checkbox" name="jsdw[features][enable_indexing]" value="1" <?php checked( ! empty( $feat['enable_indexing'] ) ); ?> /> <?php echo esc_html__( 'Enabled', 'jsdw-ai-chat' ); ?></label>
							<p class="jsdw-field-help"><?php echo esc_html__( 'Turns on source discovery and knowledge indexing features.', 'jsdw-ai-chat' ); ?></p>
							<p class="jsdw-field-hint"><?php echo esc_html__( 'Recommended: Enabled', 'jsdw-ai-chat' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Auto reindex', 'jsdw-ai-chat' ); ?></th>
						<td>
							<label><input type="checkbox" name="jsdw[indexing][auto_reindex]" value="1" <?php checked( ! empty( $index['auto_reindex'] ) ); ?> /> <?php echo esc_html__( 'Enable', 'jsdw-ai-chat' ); ?></label>
							<p class="jsdw-field-help"><?php echo esc_html__( 'Automatically queue reindex work when content changes are detected.', 'jsdw-ai-chat' ); ?></p>
						</td>
					</tr>
				</table>
			</div>
		</div>

		<div class="jsdw-card">
			<div class="jsdw-card-header">
				<h2><?php echo esc_html__( 'Chat Behavior', 'jsdw-ai-chat' ); ?></h2>
				<p class="jsdw-card-desc"><?php echo esc_html__( 'Controls how the assistant responds to users.', 'jsdw-ai-chat' ); ?></p>
			</div>
			<div class="jsdw-card-body">
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="jsdw-chat-style"><?php echo esc_html__( 'Answer style', 'jsdw-ai-chat' ); ?></label></th>
						<td>
							<select name="jsdw[chat][answer_style]" id="jsdw-chat-style">
								<option value="concise" <?php selected( (string) ( $chat['answer_style'] ?? '' ), 'concise' ); ?>><?php echo esc_html__( 'Concise', 'jsdw-ai-chat' ); ?></option>
								<option value="neutral" <?php selected( (string) ( $chat['answer_style'] ?? '' ), 'neutral' ); ?>><?php echo esc_html__( 'Neutral', 'jsdw-ai-chat' ); ?></option>
								<option value="friendly" <?php selected( (string) ( $chat['answer_style'] ?? '' ), 'friendly' ); ?>><?php echo esc_html__( 'Friendly', 'jsdw-ai-chat' ); ?></option>
							</select>
							<p class="jsdw-field-help"><?php echo esc_html__( 'Sets the tone and length bias for answer text.', 'jsdw-ai-chat' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Clarification', 'jsdw-ai-chat' ); ?></th>
						<td>
							<label><input type="checkbox" name="jsdw[chat][clarification_enabled]" value="1" <?php checked( ! empty( $chat['clarification_enabled'] ) ); ?> /> <?php echo esc_html__( 'Enable clarification prompts', 'jsdw-ai-chat' ); ?></label>
							<p class="jsdw-field-help"><?php echo esc_html__( 'Asks follow-up questions when the query is ambiguous.', 'jsdw-ai-chat' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Debug trace (chat)', 'jsdw-ai-chat' ); ?></th>
						<td>
							<label><input type="checkbox" name="jsdw[chat][debug_trace_enabled]" value="1" <?php checked( ! empty( $chat['debug_trace_enabled'] ) ); ?> /> <?php echo esc_html__( 'Enabled', 'jsdw-ai-chat' ); ?></label>
							<p class="jsdw-field-help"><?php echo esc_html__( 'Includes diagnostic trace data in responses for administrators.', 'jsdw-ai-chat' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Store trace snapshots', 'jsdw-ai-chat' ); ?></th>
						<td>
							<label><input type="checkbox" name="jsdw[chat][store_trace_snapshots]" value="1" <?php checked( ! empty( $chat['store_trace_snapshots'] ) ); ?> /> <?php echo esc_html__( 'Enabled', 'jsdw-ai-chat' ); ?></label>
							<p class="jsdw-field-help"><?php echo esc_html__( 'Persists trace snapshots for support and debugging.', 'jsdw-ai-chat' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="jsdw-min-q"><?php echo esc_html__( 'Min query length', 'jsdw-ai-chat' ); ?></label></th>
						<td>
							<input type="number" name="jsdw[chat][min_query_length]" id="jsdw-min-q" value="<?php echo esc_attr( (string) ( $chat['min_query_length'] ?? 2 ) ); ?>" min="1" max="50" class="small-text" />
							<p class="jsdw-field-help"><?php echo esc_html__( 'Minimum characters before a query is accepted.', 'jsdw-ai-chat' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="jsdw-max-q"><?php echo esc_html__( 'Max query length', 'jsdw-ai-chat' ); ?></label></th>
						<td>
							<input type="number" name="jsdw[chat][max_query_length]" id="jsdw-max-q" value="<?php echo esc_attr( (string) ( $chat['max_query_length'] ?? 500 ) ); ?>" min="10" max="4000" class="small-text" />
							<p class="jsdw-field-help"><?php echo esc_html__( 'Upper limit on query length for safety and performance.', 'jsdw-ai-chat' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Save conversation transcripts', 'jsdw-ai-chat' ); ?></th>
						<td>
							<label><input type="checkbox" name="jsdw[privacy][store_conversations]" value="1" <?php checked( ! empty( $privacy['store_conversations'] ) ); ?> /> <?php echo esc_html__( 'Enable', 'jsdw-ai-chat' ); ?></label>
							<p class="jsdw-field-help"><?php echo esc_html__( 'Privacy setting: whether conversation transcripts are stored in the database.', 'jsdw-ai-chat' ); ?></p>
						</td>
					</tr>
				</table>
			</div>
		</div>

		<div class="jsdw-card">
			<div class="jsdw-card-header">
				<h2><?php echo esc_html__( 'Response copy', 'jsdw-ai-chat' ); ?></h2>
				<p class="jsdw-card-desc"><?php echo esc_html__( 'Fallback messages when the assistant cannot answer from site content. Fields show the effective text: recommended defaults until you customize. Saving text identical to the recommended default is stored as empty (built-in). Clear a field and save to revert to the built-in default for that tone. Uses the Answer style (Concise / Neutral / Friendly) above.', 'jsdw-ai-chat' ); ?></p>
			</div>
			<div class="jsdw-card-body">
				<?php $jsdw_canned_resolver = new JSDW_AI_Chat_Canned_Responses(); ?>
				<?php foreach ( $jsdw_canned_state_labels as $state_key => $state_label ) : ?>
					<fieldset class="jsdw-fieldset-canned" style="margin:0 0 20px;padding:12px 14px;border:1px solid var(--jsdw-color-border-card, #32323e);border-radius:8px;">
						<legend style="padding:0 6px;font-weight:600;"><?php echo esc_html( $state_label ); ?></legend>
						<table class="form-table" role="presentation">
							<?php foreach ( $jsdw_tone_labels as $tone_key => $tone_label ) : ?>
								<tr>
									<th scope="row"><label for="jsdw-canned-<?php echo esc_attr( $state_key . '-' . $tone_key ); ?>"><?php echo esc_html( $tone_label ); ?></label></th>
									<td>
										<textarea class="large-text" rows="2" name="jsdw[chat][canned_responses][<?php echo esc_attr( $state_key ); ?>][<?php echo esc_attr( $tone_key ); ?>]" id="jsdw-canned-<?php echo esc_attr( $state_key . '-' . $tone_key ); ?>"><?php
											$cv = '';
										if ( isset( $canned[ $state_key ] ) && is_array( $canned[ $state_key ] ) && isset( $canned[ $state_key ][ $tone_key ] ) ) {
											$cv = (string) $canned[ $state_key ][ $tone_key ];
										}
											echo esc_textarea( $jsdw_canned_resolver->resolve( $state_key, $tone_key, $cv ) );
										?></textarea>
									</td>
								</tr>
							<?php endforeach; ?>
						</table>
					</fieldset>
				<?php endforeach; ?>
			</div>
		</div>

		<div class="jsdw-card">
			<div class="jsdw-card-header">
				<h2><?php echo esc_html__( 'Quick Ask buttons', 'jsdw-ai-chat' ); ?></h2>
				<p class="jsdw-card-desc"><?php echo esc_html__( 'Short prompts shown in the chat (up to three). Same values as Design Studio and the front-end widget.', 'jsdw-ai-chat' ); ?></p>
			</div>
			<div class="jsdw-card-body">
				<table class="form-table" role="presentation">
					<?php foreach ( array( 0, 1, 2 ) as $qi ) : ?>
						<tr>
							<th scope="row"><label for="jsdw-quick-ask-<?php echo esc_attr( (string) $qi ); ?>"><?php echo esc_html( sprintf( /* translators: %d: button slot 1–3 */ __( 'Quick Ask %d', 'jsdw-ai-chat' ), $qi + 1 ) ); ?></label></th>
							<td>
								<input type="text" class="regular-text" name="jsdw[widget_design][quickReplies][<?php echo esc_attr( (string) $qi ); ?>]" id="jsdw-quick-ask-<?php echo esc_attr( (string) $qi ); ?>" value="<?php echo esc_attr( (string) ( $qr[ $qi ] ?? '' ) ); ?>" maxlength="80" />
							</td>
						</tr>
					<?php endforeach; ?>
				</table>
				<p class="description"><?php echo esc_html__( 'Empty slots are omitted in the widget when allowed by design settings.', 'jsdw-ai-chat' ); ?></p>
			</div>
		</div>

		<div class="jsdw-card">
			<div class="jsdw-card-header">
				<h2><?php echo esc_html__( 'Access & Security', 'jsdw-ai-chat' ); ?></h2>
				<p class="jsdw-card-desc"><?php echo esc_html__( 'Controls who can access your assistant.', 'jsdw-ai-chat' ); ?></p>
			</div>
			<div class="jsdw-card-body">
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php echo esc_html__( 'Allow public query endpoint', 'jsdw-ai-chat' ); ?></th>
						<td>
							<label><input type="checkbox" name="jsdw[chat][allow_public_query_endpoint]" value="1" <?php checked( ! empty( $chat['allow_public_query_endpoint'] ) ); ?> /> <?php echo esc_html__( 'Allow unauthenticated visitors to call the chat query endpoint (when nonce is valid)', 'jsdw-ai-chat' ); ?></label>
							<p class="jsdw-field-help jsdw-field-help--warning"><?php echo esc_html__( 'Enabling this allows public visitors to interact with the assistant.', 'jsdw-ai-chat' ); ?></p>
							<p class="jsdw-field-help"><?php echo esc_html__( 'Public requests are answered only from public-classified indexed content (never drafts, private posts, wp-admin, or internal-only sources).', 'jsdw-ai-chat' ); ?></p>
						</td>
					</tr>
				</table>
			</div>
		</div>

		<div class="jsdw-card">
			<div class="jsdw-card-header">
				<h2><?php echo esc_html__( 'Frontend Widget', 'jsdw-ai-chat' ); ?></h2>
				<p class="jsdw-card-desc"><?php echo esc_html__( 'Controls whether the chat widget appears on your site.', 'jsdw-ai-chat' ); ?></p>
			</div>
			<div class="jsdw-card-body">
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php echo esc_html__( 'Front-end widget', 'jsdw-ai-chat' ); ?></th>
						<td>
							<label><input type="checkbox" name="jsdw[features][enable_widget]" value="1" <?php checked( ! empty( $feat['enable_widget'] ) ); ?> /> <?php echo esc_html__( 'Enable widget feature', 'jsdw-ai-chat' ); ?></label>
							<p class="jsdw-field-help"><?php echo esc_html__( 'Turns on the widget subsystem; output still respects the option below and theme placement.', 'jsdw-ai-chat' ); ?></p>
							<p class="jsdw-field-hint"><?php echo esc_html__( 'Recommended: Enabled', 'jsdw-ai-chat' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Widget enabled', 'jsdw-ai-chat' ); ?></th>
						<td>
							<label><input type="checkbox" name="jsdw[widget_ui][widget_enabled]" value="1" <?php checked( ! empty( $wui['widget_enabled'] ) ); ?> /> <?php echo esc_html__( 'Output widget when plugin and feature flags allow', 'jsdw-ai-chat' ); ?></label>
							<p class="jsdw-field-help"><?php echo esc_html__( 'Actually renders the widget on the front end when all prerequisites are met.', 'jsdw-ai-chat' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="jsdw-wui-pos"><?php echo esc_html__( 'Widget position override', 'jsdw-ai-chat' ); ?></label></th>
						<td>
							<select name="jsdw[widget_ui][widget_position]" id="jsdw-wui-pos">
								<option value="" <?php selected( (string) ( $wui['widget_position'] ?? '' ), '' ); ?>><?php echo esc_html__( 'Use design default', 'jsdw-ai-chat' ); ?></option>
								<option value="bottom-right" <?php selected( (string) ( $wui['widget_position'] ?? '' ), 'bottom-right' ); ?>><?php echo esc_html__( 'Bottom right', 'jsdw-ai-chat' ); ?></option>
								<option value="bottom-left" <?php selected( (string) ( $wui['widget_position'] ?? '' ), 'bottom-left' ); ?>><?php echo esc_html__( 'Bottom left', 'jsdw-ai-chat' ); ?></option>
								<option value="top-right" <?php selected( (string) ( $wui['widget_position'] ?? '' ), 'top-right' ); ?>><?php echo esc_html__( 'Top right', 'jsdw-ai-chat' ); ?></option>
								<option value="top-left" <?php selected( (string) ( $wui['widget_position'] ?? '' ), 'top-left' ); ?>><?php echo esc_html__( 'Top left', 'jsdw-ai-chat' ); ?></option>
							</select>
							<p class="jsdw-field-help"><?php echo esc_html__( 'Overrides the default corner placement from Design Studio.', 'jsdw-ai-chat' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="jsdw-launcher-label"><?php echo esc_html__( 'Launcher label', 'jsdw-ai-chat' ); ?></label></th>
						<td>
							<input type="text" class="regular-text" name="jsdw[widget_ui][launcher_label]" id="jsdw-launcher-label" value="<?php echo esc_attr( (string) ( $wui['launcher_label'] ?? '' ) ); ?>" maxlength="80" />
							<p class="jsdw-field-help"><?php echo esc_html__( 'Optional text on the launcher button.', 'jsdw-ai-chat' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="jsdw-wui-welcome"><?php echo esc_html__( 'Welcome message override', 'jsdw-ai-chat' ); ?></label></th>
						<td>
							<textarea name="jsdw[widget_ui][welcome_message]" id="jsdw-wui-welcome" class="large-text" rows="3" maxlength="500"><?php echo esc_textarea( (string) ( $wui['welcome_message'] ?? '' ) ); ?></textarea>
							<p class="jsdw-field-help"><?php echo esc_html__( 'First message shown when the panel opens.', 'jsdw-ai-chat' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="jsdw-wui-ph"><?php echo esc_html__( 'Placeholder override', 'jsdw-ai-chat' ); ?></label></th>
						<td>
							<input type="text" class="regular-text" name="jsdw[widget_ui][placeholder_text]" id="jsdw-wui-ph" value="<?php echo esc_attr( (string) ( $wui['placeholder_text'] ?? '' ) ); ?>" maxlength="100" />
							<p class="jsdw-field-help"><?php echo esc_html__( 'Input placeholder text for the message field.', 'jsdw-ai-chat' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Show sources', 'jsdw-ai-chat' ); ?></th>
						<td>
							<label><input type="checkbox" name="jsdw[widget_ui][show_sources]" value="1" <?php checked( ! empty( $wui['show_sources'] ) ); ?> /> <?php echo esc_html__( 'When formatter includes sources', 'jsdw-ai-chat' ); ?></label>
							<p class="jsdw-field-help"><?php echo esc_html__( 'Shows source references in the widget when the answer includes them.', 'jsdw-ai-chat' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Allow reset conversation', 'jsdw-ai-chat' ); ?></th>
						<td>
							<label><input type="checkbox" name="jsdw[widget_ui][allow_reset_conversation]" value="1" <?php checked( ! empty( $wui['allow_reset_conversation'] ) ); ?> /> <?php echo esc_html__( 'Enable', 'jsdw-ai-chat' ); ?></label>
							<p class="jsdw-field-help"><?php echo esc_html__( 'Shows a control to start a fresh conversation.', 'jsdw-ai-chat' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Admin debug UI', 'jsdw-ai-chat' ); ?></th>
						<td>
							<label><input type="checkbox" name="jsdw[widget_ui][admin_debug_ui]" value="1" <?php checked( ! empty( $wui['admin_debug_ui'] ) ); ?> /> <?php echo esc_html__( 'For admins with capability', 'jsdw-ai-chat' ); ?></label>
							<p class="jsdw-field-help"><?php echo esc_html__( 'Extra debug controls for users who can manage the widget.', 'jsdw-ai-chat' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Auto footer injection', 'jsdw-ai-chat' ); ?></th>
						<td>
							<label><input type="checkbox" name="jsdw[widget_ui][auto_footer]" value="1" <?php checked( ! empty( $wui['auto_footer'] ) ); ?> /> <?php echo esc_html__( 'Inject mount when shortcode not used', 'jsdw-ai-chat' ); ?></label>
							<p class="jsdw-field-help"><?php echo esc_html__( 'Adds the widget mount to the footer automatically if no shortcode is present.', 'jsdw-ai-chat' ); ?></p>
						</td>
					</tr>
				</table>
			</div>
		</div>

		<div class="jsdw-card">
			<div class="jsdw-card-header">
				<h2><?php echo esc_html__( 'Logging', 'jsdw-ai-chat' ); ?></h2>
				<p class="jsdw-card-desc"><?php echo esc_html__( 'Internal log output for troubleshooting.', 'jsdw-ai-chat' ); ?></p>
			</div>
			<div class="jsdw-card-body">
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php echo esc_html__( 'Logging enabled', 'jsdw-ai-chat' ); ?></th>
						<td>
							<label><input type="checkbox" name="jsdw[logging][enabled]" value="1" <?php checked( ! empty( $logging['enabled'] ) ); ?> /> <?php echo esc_html__( 'Enable', 'jsdw-ai-chat' ); ?></label>
							<p class="jsdw-field-help"><?php echo esc_html__( 'Writes plugin log entries when conditions are met.', 'jsdw-ai-chat' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Mirror WP_DEBUG', 'jsdw-ai-chat' ); ?></th>
						<td>
							<label><input type="checkbox" name="jsdw[logging][mirror_wp_debug]" value="1" <?php checked( ! empty( $logging['mirror_wp_debug'] ) ); ?> /> <?php echo esc_html__( 'Enable', 'jsdw-ai-chat' ); ?></label>
							<p class="jsdw-field-help"><?php echo esc_html__( 'Aligns minimum level with WordPress debug mode when enabled.', 'jsdw-ai-chat' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="jsdw-log-level"><?php echo esc_html__( 'Minimum log level', 'jsdw-ai-chat' ); ?></label></th>
						<td>
							<select name="jsdw[logging][minimum_log_level]" id="jsdw-log-level">
								<?php foreach ( array( 'debug', 'info', 'warning', 'error', 'critical' ) as $lvl ) : ?>
									<option value="<?php echo esc_attr( $lvl ); ?>" <?php selected( (string) ( $logging['minimum_log_level'] ?? 'info' ), $lvl ); ?>><?php echo esc_html( $lvl ); ?></option>
								<?php endforeach; ?>
							</select>
							<p class="jsdw-field-help"><?php echo esc_html__( 'Only messages at this level or higher are recorded.', 'jsdw-ai-chat' ); ?></p>
						</td>
					</tr>
				</table>
			</div>
		</div>

		<div class="jsdw-card jsdw-card--danger">
			<div class="jsdw-card-header">
				<h2><?php echo esc_html__( 'Data & Cleanup', 'jsdw-ai-chat' ); ?></h2>
				<p class="jsdw-card-desc"><?php echo esc_html__( 'This action is irreversible.', 'jsdw-ai-chat' ); ?></p>
			</div>
			<div class="jsdw-card-body">
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php echo esc_html__( 'Remove data on uninstall', 'jsdw-ai-chat' ); ?></th>
						<td>
							<label><input type="checkbox" name="jsdw[features][cleanup_on_uninstall]" value="1" <?php checked( ! empty( $feat['cleanup_on_uninstall'] ) ); ?> /> <?php echo esc_html__( 'Enabled', 'jsdw-ai-chat' ); ?></label>
							<p class="jsdw-field-help"><?php echo esc_html__( 'Deletes plugin tables and options when the plugin is uninstalled.', 'jsdw-ai-chat' ); ?></p>
						</td>
					</tr>
				</table>
			</div>
		</div>

		<div class="jsdw-card">
			<div class="jsdw-card-header">
				<h2><?php echo esc_html__( 'Sources (reference)', 'jsdw-ai-chat' ); ?></h2>
			</div>
			<div class="jsdw-card-body">
				<p class="description"><?php echo esc_html__( 'Detailed source-type rules, allowlists, and URL patterns are stored in settings but are not edited on this screen. Use the Source Registry and indexing tools to manage sources.', 'jsdw-ai-chat' ); ?></p>
			</div>
		</div>

		<p class="submit">
			<button type="submit" name="jsdw_ai_chat_settings_save" class="button button-primary" value="1"><?php echo esc_html__( 'Save settings', 'jsdw-ai-chat' ); ?></button>
		</p>
	</form>
</div>
