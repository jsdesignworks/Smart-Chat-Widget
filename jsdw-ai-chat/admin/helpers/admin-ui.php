<?php
/**
 * Shared admin UI helpers (help tips, etc.).
 *
 * @package JSDW_AI_Chat
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Accessible disclosure pattern for plain-language help (no JS required).
 *
 * @param string $html_id Unique id for the details element.
 * @param string $label   Short label for the summary control.
 * @param string $body    Help body (HTML allowed — pass pre-escaped strings from views).
 */
function jsdw_ai_chat_help_tip( $html_id, $label, $body ) {
	$id = sanitize_html_class( $html_id );
	if ( '' === $id ) {
		$id = 'jsdw-help-' . wp_unique_id();
	}
	?>
	<details class="jsdw-help-tip" id="<?php echo esc_attr( $id ); ?>">
		<summary class="jsdw-help-tip__summary">
			<span class="dashicons dashicons-info" aria-hidden="true"></span>
			<span class="jsdw-help-tip__label"><?php echo esc_html( $label ); ?></span>
		</summary>
		<div class="jsdw-help-tip__body">
			<?php echo wp_kses_post( $body ); ?>
		</div>
	</details>
	<?php
}

/**
 * Render normalized job queue breakdown (from JSDW_AI_Chat_Queue::normalize_queue_count_rows).
 *
 * @param array<string,mixed>|mixed $normalized Array with keys rows, total_jobs.
 */
function jsdw_ai_chat_render_job_queue_table( $normalized ) {
	if ( ! is_array( $normalized ) || empty( $normalized['rows'] ) || ! is_array( $normalized['rows'] ) ) {
		echo '<p class="description">' . esc_html__( 'No jobs in this queue right now.', 'jsdw-ai-chat' ) . '</p>';
		return;
	}
	echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Job type', 'jsdw-ai-chat' ) . '</th><th>' . esc_html__( 'Status', 'jsdw-ai-chat' ) . '</th><th>' . esc_html__( 'Count', 'jsdw-ai-chat' ) . '</th></tr></thead><tbody>';
	foreach ( $normalized['rows'] as $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}
		$jt = isset( $row['job_type'] ) ? (string) $row['job_type'] : '';
		$st = isset( $row['status'] ) ? (string) $row['status'] : '';
		$tot = isset( $row['total'] ) ? absint( $row['total'] ) : 0;
		echo '<tr><td><code>' . esc_html( $jt ) . '</code></td><td>' . esc_html( $st ) . '</td><td>' . esc_html( (string) $tot ) . '</td></tr>';
	}
	echo '</tbody></table>';
	if ( isset( $normalized['total_jobs'] ) ) {
		echo '<p class="description">' . esc_html( sprintf( /* translators: %d: total job row count */ __( 'Total across statuses: %d', 'jsdw-ai-chat' ), absint( $normalized['total_jobs'] ) ) ) . '</p>';
	}
}
