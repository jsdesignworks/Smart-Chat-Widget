<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$r = is_array( $health_report ) ? $health_report : array();
$disc    = isset( $r['discovery'] ) && is_array( $r['discovery'] ) ? $r['discovery'] : array();
$content = isset( $r['content_state'] ) && is_array( $r['content_state'] ) ? $r['content_state'] : array();
$know    = isset( $r['knowledge_state'] ) && is_array( $r['knowledge_state'] ) ? $r['knowledge_state'] : array();

$q_disc    = isset( $disc['queue_counts'] ) && is_array( $disc['queue_counts'] ) ? $disc['queue_counts'] : array();
$q_content = isset( $content['queue_counts'] ) && is_array( $content['queue_counts'] ) ? $content['queue_counts'] : array();
$q_know    = isset( $know['queue_counts'] ) && is_array( $know['queue_counts'] ) ? $know['queue_counts'] : array();

$recent_logs = isset( $recent_logs ) && is_array( $recent_logs ) ? $recent_logs : array();
$url_sources = isset( $url_sources ) ? (string) $url_sources : admin_url( 'admin.php?page=jsdw-ai-chat-sources' );
$url_set     = isset( $url_settings ) ? (string) $url_settings : admin_url( 'admin.php?page=jsdw-ai-chat-settings' );

$jobs_intro = sprintf(
	/* translators: 1: Sources URL, 2: Settings URL */
	__( 'Background <strong>jobs</strong> find your site content, turn it into searchable text, and build the knowledge the chat uses. You usually do not need this screen unless something looks stuck—then check the queues below and recent activity. For actions, use <a href="%1$s">Sources</a> or <a href="%2$s">Settings</a>.', 'jsdw-ai-chat' ),
	esc_url( $url_sources ),
	esc_url( $url_set )
);
?>
<div class="jsdw-page jsdw-jobs-page">
	<h1><?php echo esc_html__( 'Jobs & Logs', 'jsdw-ai-chat' ); ?></h1>
	<p class="jsdw-page-lead"><?php echo wp_kses_post( $jobs_intro ); ?></p>

	<?php
	jsdw_ai_chat_help_tip(
		'jsdw-help-jobs-purpose',
		__( 'What is this page for?', 'jsdw-ai-chat' ),
		'<p>' . esc_html__( 'WordPress runs small scheduled tasks (WP-Cron) that pick up jobs from a queue: scanning pages, extracting text, and building “chunks” and facts for answers. Numbers here show how many jobs exist by type and status (for example waiting vs running).', 'jsdw-ai-chat' ) . '</p>'
		. '<p>' . esc_html__( 'If logging is enabled under Settings, the “Recent activity” section shows the latest lines from the plugin log.', 'jsdw-ai-chat' ) . '</p>'
	);
	?>

	<div class="jsdw-card jsdw-jobs-queue-card">
		<div class="jsdw-card-header">
			<h2><?php echo esc_html__( 'Queue runner', 'jsdw-ai-chat' ); ?></h2>
			<?php
			jsdw_ai_chat_help_tip(
				'jsdw-help-jobs-runner',
				__( 'Help', 'jsdw-ai-chat' ),
				'<p>' . esc_html__( '“Locked” means a run is in progress. “Pending jobs” is how many jobs are waiting (capped sample). If pending stays high, ensure WP-Cron runs on your host or use “Run queue now” on the Sources page.', 'jsdw-ai-chat' ) . '</p>'
			);
			?>
		</div>
		<div class="jsdw-card-body">
			<table class="widefat striped" style="max-width:720px;">
				<tbody>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Locked', 'jsdw-ai-chat' ); ?></th>
						<td><?php echo esc_html( ! empty( $r['queue']['locked'] ) ? __( 'yes', 'jsdw-ai-chat' ) : __( 'no', 'jsdw-ai-chat' ) ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Pending jobs (sample)', 'jsdw-ai-chat' ); ?></th>
						<td><?php echo esc_html( isset( $r['queue']['pending'] ) ? (string) (int) $r['queue']['pending'] : '0' ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Logs table ready', 'jsdw-ai-chat' ); ?></th>
						<td><?php echo esc_html( ! empty( $r['tables']['logs'] ) ? __( 'yes', 'jsdw-ai-chat' ) : __( 'no', 'jsdw-ai-chat' ) ); ?></td>
					</tr>
				</tbody>
			</table>
		</div>
	</div>

	<div class="jsdw-jobs-sections">
		<section class="jsdw-card" aria-labelledby="jsdw-jobs-discovery-heading">
			<div class="jsdw-card-header">
				<h2 id="jsdw-jobs-discovery-heading"><?php echo esc_html__( 'Discovery jobs', 'jsdw-ai-chat' ); ?></h2>
				<?php
				jsdw_ai_chat_help_tip(
					'jsdw-help-jobs-discovery',
					__( 'Help', 'jsdw-ai-chat' ),
					'<p>' . esc_html__( 'Discovery finds posts, pages, and other configured sources and registers them in the source list. Use “Run full scan” on the Sources page to queue a discovery job.', 'jsdw-ai-chat' ) . '</p>'
					. '<p><a href="' . esc_url( $url_sources ) . '">' . esc_html__( 'Open Sources', 'jsdw-ai-chat' ) . '</a></p>'
				);
				?>
			</div>
			<div class="jsdw-card-body">
				<?php jsdw_ai_chat_render_job_queue_table( $q_disc ); ?>
			</div>
		</section>

		<section class="jsdw-card" aria-labelledby="jsdw-jobs-content-heading">
			<div class="jsdw-card-header">
				<h2 id="jsdw-jobs-content-heading"><?php echo esc_html__( 'Content processing jobs', 'jsdw-ai-chat' ); ?></h2>
				<?php
				jsdw_ai_chat_help_tip(
					'jsdw-help-jobs-content',
					__( 'Help', 'jsdw-ai-chat' ),
					'<p>' . esc_html__( 'These jobs read page text from each active source and normalize it. A source must be “active” (not excluded by your rules) before content jobs can run.', 'jsdw-ai-chat' ) . '</p>'
					. '<p><a href="' . esc_url( $url_sources ) . '">' . esc_html__( 'Open Sources', 'jsdw-ai-chat' ) . '</a></p>'
				);
				?>
			</div>
			<div class="jsdw-card-body">
				<?php jsdw_ai_chat_render_job_queue_table( $q_content ); ?>
			</div>
		</section>

		<section class="jsdw-card" aria-labelledby="jsdw-jobs-knowledge-heading">
			<div class="jsdw-card-header">
				<h2 id="jsdw-jobs-knowledge-heading"><?php echo esc_html__( 'Knowledge indexing jobs', 'jsdw-ai-chat' ); ?></h2>
				<?php
				jsdw_ai_chat_help_tip(
					'jsdw-help-jobs-knowledge',
					__( 'Help', 'jsdw-ai-chat' ),
					'<p>' . esc_html__( 'After content is OK, these jobs build searchable chunks and facts so the assistant can answer from your site. Knowledge stays “pending” until content reaches OK.', 'jsdw-ai-chat' ) . '</p>'
					. '<p><a href="' . esc_url( $url_settings ) . '">' . esc_html__( 'Indexing settings', 'jsdw-ai-chat' ) . '</a></p>'
				);
				?>
			</div>
			<div class="jsdw-card-body">
				<?php jsdw_ai_chat_render_job_queue_table( $q_know ); ?>
			</div>
		</section>
	</div>

	<details class="jsdw-help-tip jsdw-jobs-glossary">
		<summary class="jsdw-help-tip__summary">
			<span class="dashicons dashicons-book-alt" aria-hidden="true"></span>
			<span class="jsdw-help-tip__label"><?php echo esc_html__( 'Job types glossary', 'jsdw-ai-chat' ); ?></span>
		</summary>
		<div class="jsdw-help-tip__body jsdw-jobs-glossary__grid">
			<div>
				<h3><?php echo esc_html__( 'Discovery', 'jsdw-ai-chat' ); ?></h3>
				<p><?php echo esc_html__( 'Full scan / sync / verify: finds or updates what content exists and whether URLs still work.', 'jsdw-ai-chat' ); ?></p>
			</div>
			<div>
				<h3><?php echo esc_html__( 'Content', 'jsdw-ai-chat' ); ?></h3>
				<p><?php echo esc_html__( 'Pulls HTML or text for a source and prepares a normalized snapshot for indexing.', 'jsdw-ai-chat' ); ?></p>
			</div>
			<div>
				<h3><?php echo esc_html__( 'Knowledge', 'jsdw-ai-chat' ); ?></h3>
				<p><?php echo esc_html__( 'Splits normalized text into chunks and facts your chat can retrieve.', 'jsdw-ai-chat' ); ?></p>
			</div>
		</div>
	</details>

	<section class="jsdw-card jsdw-jobs-recent" aria-labelledby="jsdw-jobs-recent-heading">
		<div class="jsdw-card-header">
			<h2 id="jsdw-jobs-recent-heading"><?php echo esc_html__( 'Recent activity', 'jsdw-ai-chat' ); ?></h2>
			<?php
			jsdw_ai_chat_help_tip(
				'jsdw-help-jobs-logs',
				__( 'Help', 'jsdw-ai-chat' ),
				'<p>' . esc_html__( 'These lines come from the plugin log table when logging is enabled. They help confirm that scans and jobs are running.', 'jsdw-ai-chat' ) . '</p>'
			);
			?>
		</div>
		<div class="jsdw-card-body">
			<?php if ( empty( $recent_logs ) ) : ?>
				<p class="description"><?php echo esc_html__( 'No log rows found, or logging is disabled under Settings.', 'jsdw-ai-chat' ); ?></p>
			<?php else : ?>
				<div class="jsdw-table-scroll">
					<table class="widefat striped jsdw-jobs-log-table">
						<thead>
							<tr>
								<th><?php echo esc_html__( 'Time (UTC)', 'jsdw-ai-chat' ); ?></th>
								<th><?php echo esc_html__( 'Level', 'jsdw-ai-chat' ); ?></th>
								<th><?php echo esc_html__( 'Event', 'jsdw-ai-chat' ); ?></th>
								<th><?php echo esc_html__( 'Message', 'jsdw-ai-chat' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $recent_logs as $log_row ) : ?>
								<?php
								if ( ! is_array( $log_row ) ) {
									continue;
								}
								?>
								<tr>
									<td><?php echo esc_html( isset( $log_row['created_at'] ) ? (string) $log_row['created_at'] : '' ); ?></td>
									<td><code><?php echo esc_html( isset( $log_row['level'] ) ? (string) $log_row['level'] : '' ); ?></code></td>
									<td><code><?php echo esc_html( isset( $log_row['event_type'] ) ? (string) $log_row['event_type'] : '' ); ?></code></td>
									<td><?php echo esc_html( isset( $log_row['message'] ) ? (string) $log_row['message'] : '' ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php endif; ?>
		</div>
	</section>
</div>
