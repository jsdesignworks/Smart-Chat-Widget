<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$r = is_array( $health_report ) ? $health_report : array();

/**
 * @param array<string,mixed> $pairs
 */
$jsdw_kv_rows = static function ( array $pairs ) {
	echo '<table class="widefat striped"><tbody>';
	foreach ( $pairs as $label => $value ) {
		echo '<tr><th scope="row" style="width:220px;">' . esc_html( (string) $label ) . '</th><td>';
		if ( is_array( $value ) ) {
			echo '<table class="widefat striped"><tbody>';
			foreach ( $value as $sk => $sv ) {
				echo '<tr><th scope="row">' . esc_html( (string) $sk ) . '</th><td>' . esc_html( is_scalar( $sv ) ? (string) $sv : '…' ) . '</td></tr>';
			}
			echo '</tbody></table>';
		} elseif ( is_bool( $value ) ) {
			echo esc_html( $value ? __( 'yes', 'jsdw-ai-chat' ) : __( 'no', 'jsdw-ai-chat' ) );
		} else {
			echo esc_html( (string) $value );
		}
		echo '</td></tr>';
	}
	echo '</tbody></table>';
};

/**
 * @param array<string,int|string|float> $counts
 */
$jsdw_count_table = static function ( array $counts ) {
	if ( empty( $counts ) ) {
		echo '<p class="description">' . esc_html__( 'No entries.', 'jsdw-ai-chat' ) . '</p>';
		return;
	}
	echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Key', 'jsdw-ai-chat' ) . '</th><th>' . esc_html__( 'Count', 'jsdw-ai-chat' ) . '</th></tr></thead><tbody>';
	foreach ( $counts as $k => $v ) {
		echo '<tr><td><code>' . esc_html( (string) $k ) . '</code></td><td>' . esc_html( (string) $v ) . '</td></tr>';
	}
	echo '</tbody></table>';
};

$tables   = isset( $r['tables'] ) && is_array( $r['tables'] ) ? $r['tables'] : array();
$cron     = isset( $r['cron'] ) && is_array( $r['cron'] ) ? $r['cron'] : array();
$queue    = isset( $r['queue'] ) && is_array( $r['queue'] ) ? $r['queue'] : array();
$disc     = isset( $r['discovery'] ) && is_array( $r['discovery'] ) ? $r['discovery'] : array();
$content  = isset( $r['content_state'] ) && is_array( $r['content_state'] ) ? $r['content_state'] : array();
$know     = isset( $r['knowledge_state'] ) && is_array( $r['knowledge_state'] ) ? $r['knowledge_state'] : array();
$answer   = isset( $r['answer_state'] ) && is_array( $r['answer_state'] ) ? $r['answer_state'] : array();
$rest     = isset( $r['rest'] ) && is_array( $r['rest'] ) ? $r['rest'] : array();
$last_err = isset( $r['last_error'] ) && is_array( $r['last_error'] ) ? $r['last_error'] : array();
?>
<div class="jsdw-page">
	<h1><?php echo esc_html__( 'System Info', 'jsdw-ai-chat' ); ?></h1>

	<div id="poststuff">
		<div class="postbox">
			<h2 class="hndle"><?php echo esc_html__( 'Environment', 'jsdw-ai-chat' ); ?></h2>
			<div class="inside">
				<ul class="ul-disc">
					<li><?php echo esc_html__( 'PHP Version:', 'jsdw-ai-chat' ) . ' ' . esc_html( PHP_VERSION ); ?></li>
					<li><?php echo esc_html__( 'WordPress Version:', 'jsdw-ai-chat' ) . ' ' . esc_html( get_bloginfo( 'version' ) ); ?></li>
					<li><?php echo esc_html__( 'REST prefix:', 'jsdw-ai-chat' ) . ' ' . esc_html( rest_get_url_prefix() ? rest_get_url_prefix() : '—' ); ?></li>
					<li><?php echo esc_html__( 'WP-Cron enabled:', 'jsdw-ai-chat' ) . ' ' . esc_html( ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) ? __( 'no', 'jsdw-ai-chat' ) : __( 'yes', 'jsdw-ai-chat' ) ); ?></li>
					<li><?php echo esc_html__( 'JSON (wp_json_encode):', 'jsdw-ai-chat' ) . ' ' . esc_html( function_exists( 'wp_json_encode' ) ? __( 'yes', 'jsdw-ai-chat' ) : __( 'no', 'jsdw-ai-chat' ) ); ?></li>
					<li><?php echo esc_html__( 'mbstring:', 'jsdw-ai-chat' ) . ' ' . esc_html( extension_loaded( 'mbstring' ) ? __( 'yes', 'jsdw-ai-chat' ) : __( 'no', 'jsdw-ai-chat' ) ); ?></li>
					<li><?php echo esc_html__( 'External object cache:', 'jsdw-ai-chat' ) . ' ' . esc_html( wp_using_ext_object_cache() ? __( 'yes', 'jsdw-ai-chat' ) : __( 'no', 'jsdw-ai-chat' ) ); ?></li>
				</ul>
			</div>
		</div>

		<div class="postbox">
			<h2 class="hndle"><?php echo esc_html__( 'Plugin & schema', 'jsdw-ai-chat' ); ?></h2>
			<div class="inside">
				<?php
				$jsdw_kv_rows(
					array(
						__( 'Plugin version', 'jsdw-ai-chat' ) => isset( $r['plugin_version'] ) ? (string) $r['plugin_version'] : '—',
						__( 'DB schema version', 'jsdw-ai-chat' ) => isset( $r['schema_version'] ) ? (string) $r['schema_version'] : '—',
					)
				);
				?>
			</div>
		</div>

		<div class="postbox">
			<h2 class="hndle"><?php echo esc_html__( 'Database tables', 'jsdw-ai-chat' ); ?></h2>
			<div class="inside">
				<?php
				if ( empty( $tables ) ) {
					echo '<p class="description">' . esc_html__( 'No table data.', 'jsdw-ai-chat' ) . '</p>';
				} else {
					echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Table', 'jsdw-ai-chat' ) . '</th><th>' . esc_html__( 'Exists', 'jsdw-ai-chat' ) . '</th></tr></thead><tbody>';
					foreach ( $tables as $name => $ok ) {
						echo '<tr><td><code>' . esc_html( (string) $name ) . '</code></td><td>' . esc_html( ! empty( $ok ) ? __( 'yes', 'jsdw-ai-chat' ) : __( 'no', 'jsdw-ai-chat' ) ) . '</td></tr>';
					}
					echo '</tbody></table>';
				}
				?>
			</div>
		</div>

		<div class="postbox">
			<h2 class="hndle"><?php echo esc_html__( 'Scheduled events (cron hooks)', 'jsdw-ai-chat' ); ?></h2>
			<div class="inside">
				<?php
				$cron_labels = array(
					'hourly'                   => __( 'Hourly', 'jsdw-ai-chat' ),
					'daily'                    => __( 'Daily', 'jsdw-ai-chat' ),
					'queue_runner'             => __( 'Queue runner', 'jsdw-ai-chat' ),
					'discovery_full_scan'      => __( 'Discovery full scan', 'jsdw-ai-chat' ),
					'discovery_verify_missing' => __( 'Discovery verify missing', 'jsdw-ai-chat' ),
					'content_verification'     => __( 'Content verification', 'jsdw-ai-chat' ),
					'content_refresh'          => __( 'Content refresh', 'jsdw-ai-chat' ),
					'knowledge_verification'     => __( 'Knowledge verification', 'jsdw-ai-chat' ),
					'knowledge_refresh'          => __( 'Knowledge refresh', 'jsdw-ai-chat' ),
				);
				$rows = array();
				foreach ( $cron_labels as $key => $lab ) {
					$rows[ $lab ] = ! empty( $cron[ $key ] );
				}
				$jsdw_kv_rows( $rows );
				?>
				<p><strong><?php echo esc_html__( 'Last cron run (stored)', 'jsdw-ai-chat' ); ?></strong></p>
				<p><?php echo esc_html( isset( $r['last_cron_run'] ) && (string) $r['last_cron_run'] !== '' ? (string) $r['last_cron_run'] : '—' ); ?></p>
			</div>
		</div>

		<div class="postbox">
			<h2 class="hndle"><?php echo esc_html__( 'Job queue', 'jsdw-ai-chat' ); ?></h2>
			<div class="inside">
				<?php
				$jsdw_kv_rows(
					array(
						__( 'Locked', 'jsdw-ai-chat' )      => ! empty( $queue['locked'] ),
						__( 'Lock until (unix)', 'jsdw-ai-chat' ) => isset( $queue['lock_until'] ) ? (string) (int) $queue['lock_until'] : '0',
						__( 'Pending jobs (sample cap)', 'jsdw-ai-chat' ) => isset( $queue['pending'] ) ? (string) (int) $queue['pending'] : '0',
					)
				);
				?>
			</div>
		</div>

		<div class="postbox">
			<h2 class="hndle"><?php echo esc_html__( 'Discovery', 'jsdw-ai-chat' ); ?></h2>
			<div class="inside">
				<?php
				$jsdw_kv_rows(
					array(
						__( 'Discovery enabled (source types)', 'jsdw-ai-chat' ) => ! empty( $disc['enabled'] ),
						__( 'Pending reindex', 'jsdw-ai-chat' ) => isset( $disc['pending_reindex'] ) ? (string) (int) $disc['pending_reindex'] : '0',
						__( 'Last discovery scan', 'jsdw-ai-chat' ) => isset( $disc['last_discovery_scan_time'] ) ? (string) $disc['last_discovery_scan_time'] : '—',
					)
				);
				?>
				<p><strong><?php echo esc_html__( 'Source counts by type', 'jsdw-ai-chat' ); ?></strong></p>
				<?php $jsdw_count_table( isset( $disc['source_counts'] ) && is_array( $disc['source_counts'] ) ? $disc['source_counts'] : array() ); ?>
				<p><strong><?php echo esc_html__( 'Discovery queue counts', 'jsdw-ai-chat' ); ?></strong></p>
				<?php $jsdw_count_table( isset( $disc['queue_counts'] ) && is_array( $disc['queue_counts'] ) ? $disc['queue_counts'] : array() ); ?>
				<?php
				$scan_res = isset( $disc['last_discovery_scan_result'] ) && is_array( $disc['last_discovery_scan_result'] ) ? $disc['last_discovery_scan_result'] : array();
				if ( ! empty( $scan_res ) ) :
					?>
					<p><strong><?php echo esc_html__( 'Last discovery scan result', 'jsdw-ai-chat' ); ?></strong></p>
					<?php $jsdw_kv_rows( $scan_res ); ?>
				<?php endif; ?>
			</div>
		</div>

		<div class="postbox">
			<h2 class="hndle"><?php echo esc_html__( 'Content pipeline', 'jsdw-ai-chat' ); ?></h2>
			<div class="inside">
				<?php
				$jsdw_kv_rows(
					array(
						__( 'Last content verification run', 'jsdw-ai-chat' ) => isset( $content['last_content_verification_run'] ) ? (string) $content['last_content_verification_run'] : '—',
					)
				);
				?>
				<p><strong><?php echo esc_html__( 'Processing by status', 'jsdw-ai-chat' ); ?></strong></p>
				<?php $jsdw_count_table( isset( $content['status_counts'] ) && is_array( $content['status_counts'] ) ? $content['status_counts'] : array() ); ?>
				<p><strong><?php echo esc_html__( 'Material content change counts', 'jsdw-ai-chat' ); ?></strong></p>
				<?php $jsdw_count_table( isset( $content['material_content_change_counts'] ) && is_array( $content['material_content_change_counts'] ) ? $content['material_content_change_counts'] : array() ); ?>
				<p><strong><?php echo esc_html__( 'Content queue counts', 'jsdw-ai-chat' ); ?></strong></p>
				<?php $jsdw_count_table( isset( $content['queue_counts'] ) && is_array( $content['queue_counts'] ) ? $content['queue_counts'] : array() ); ?>
			</div>
		</div>

		<div class="postbox">
			<h2 class="hndle"><?php echo esc_html__( 'Knowledge pipeline', 'jsdw-ai-chat' ); ?></h2>
			<div class="inside">
				<?php
				$jsdw_kv_rows(
					array(
						__( 'Sources with active chunks', 'jsdw-ai-chat' ) => isset( $know['sources_with_active_chunks'] ) ? (string) (int) $know['sources_with_active_chunks'] : '0',
						__( 'Sources with active facts', 'jsdw-ai-chat' ) => isset( $know['sources_with_active_facts'] ) ? (string) (int) $know['sources_with_active_facts'] : '0',
						__( 'Last knowledge verification run', 'jsdw-ai-chat' ) => isset( $know['last_knowledge_verification_run'] ) ? (string) $know['last_knowledge_verification_run'] : '—',
					)
				);
				?>
				<p><strong><?php echo esc_html__( 'Knowledge status counts', 'jsdw-ai-chat' ); ?></strong></p>
				<?php $jsdw_count_table( isset( $know['knowledge_status_counts'] ) && is_array( $know['knowledge_status_counts'] ) ? $know['knowledge_status_counts'] : array() ); ?>
				<p><strong><?php echo esc_html__( 'Chunk status counts', 'jsdw-ai-chat' ); ?></strong></p>
				<?php $jsdw_count_table( isset( $know['chunk_status_counts'] ) && is_array( $know['chunk_status_counts'] ) ? $know['chunk_status_counts'] : array() ); ?>
				<p><strong><?php echo esc_html__( 'Knowledge queue counts', 'jsdw-ai-chat' ); ?></strong></p>
				<?php $jsdw_count_table( isset( $know['queue_counts'] ) && is_array( $know['queue_counts'] ) ? $know['queue_counts'] : array() ); ?>
			</div>
		</div>

		<div class="postbox">
			<h2 class="hndle"><?php echo esc_html__( 'Answer / chat', 'jsdw-ai-chat' ); ?></h2>
			<div class="inside">
				<?php
				$jsdw_kv_rows(
					array(
						__( 'Conversations (rows)', 'jsdw-ai-chat' ) => isset( $answer['conversations'] ) ? (string) (int) $answer['conversations'] : '0',
						__( 'Messages (rows)', 'jsdw-ai-chat' ) => isset( $answer['messages'] ) ? (string) (int) $answer['messages'] : '0',
						__( 'Public query endpoint allowed', 'jsdw-ai-chat' ) => ! empty( $answer['allow_public_query_endpoint'] ),
						__( 'AI phrase assist allowed', 'jsdw-ai-chat' ) => ! empty( $answer['allow_ai_phrase_assist'] ),
						__( 'Answer mode', 'jsdw-ai-chat' ) => isset( $answer['answer_mode'] ) ? (string) $answer['answer_mode'] : '—',
						__( 'Last answer request (stored)', 'jsdw-ai-chat' ) => isset( $answer['last_answer_request'] ) ? (string) $answer['last_answer_request'] : '—',
					)
				);
				?>
			</div>
		</div>

		<div class="postbox">
			<h2 class="hndle"><?php echo esc_html__( 'REST API', 'jsdw-ai-chat' ); ?></h2>
			<div class="inside">
				<?php
				$jsdw_kv_rows(
					array(
						__( 'Namespace', 'jsdw-ai-chat' ) => isset( $rest['namespace'] ) ? (string) $rest['namespace'] : '—',
						__( 'Ready', 'jsdw-ai-chat' )      => ! empty( $rest['ready'] ),
					)
				);
				?>
			</div>
		</div>

		<div class="postbox">
			<h2 class="hndle"><?php echo esc_html__( 'Last logged error', 'jsdw-ai-chat' ); ?></h2>
			<div class="inside">
				<?php
				if ( empty( $last_err ) ) {
					echo '<p class="description">' . esc_html__( 'None recorded.', 'jsdw-ai-chat' ) . '</p>';
				} else {
					$jsdw_kv_rows( $last_err );
				}
				?>
			</div>
		</div>

	</div>
</div>
