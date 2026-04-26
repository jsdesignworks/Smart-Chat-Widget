<?php
/**
 * Source registry management (responsive table, filters, queue actions).
 *
 * @package JSDW_AI_Chat
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$health_report = isset( $health_report ) && is_array( $health_report ) ? $health_report : array();
$q_pending     = isset( $health_report['queue']['pending'] ) ? absint( $health_report['queue']['pending'] ) : 0;

$lifecycle_totals = isset( $lifecycle_totals ) && is_array( $lifecycle_totals ) ? $lifecycle_totals : array();
$total_sources    = isset( $total_sources ) ? absint( $total_sources ) : 0;
$pending_reindex  = isset( $pending_reindex ) ? absint( $pending_reindex ) : 0;

$content_status_counts   = isset( $content_status_counts ) && is_array( $content_status_counts ) ? $content_status_counts : array();
$knowledge_status_counts = isset( $knowledge_status_counts ) && is_array( $knowledge_status_counts ) ? $knowledge_status_counts : array();

$c_failed = isset( $content_status_counts['failed'] ) ? absint( $content_status_counts['failed'] ) : 0;
$k_failed = isset( $knowledge_status_counts['failed'] ) ? absint( $knowledge_status_counts['failed'] ) : 0;

$visibility_counts = isset( $visibility_counts ) && is_array( $visibility_counts ) ? $visibility_counts : array();
$vis_public        = isset( $visibility_counts['public'] ) ? absint( $visibility_counts['public'] ) : 0;
$vis_internal      = isset( $visibility_counts['internal'] ) ? absint( $visibility_counts['internal'] ) : 0;
$vis_admin         = isset( $visibility_counts['admin_only'] ) ? absint( $visibility_counts['admin_only'] ) : 0;

$c_pending = isset( $content_status_counts['pending'] ) ? absint( $content_status_counts['pending'] ) : 0;
$k_pending = isset( $knowledge_status_counts['pending'] ) ? absint( $knowledge_status_counts['pending'] ) : 0;
$k_ready   = isset( $knowledge_status_counts['ready'] ) ? absint( $knowledge_status_counts['ready'] ) : 0;
$lc_excl   = isset( $lifecycle_totals['excluded'] ) ? absint( $lifecycle_totals['excluded'] ) : 0;

$url_jobs = admin_url( 'admin.php?page=jsdw-ai-chat-jobs' );
$url_set  = admin_url( 'admin.php?page=jsdw-ai-chat-settings' );

$filter_type             = isset( $filter_type ) ? (string) $filter_type : '';
$filter_status           = isset( $filter_status ) ? (string) $filter_status : '';
$filter_content_status   = isset( $filter_content_status ) ? (string) $filter_content_status : '';
$filter_knowledge_status = isset( $filter_knowledge_status ) ? (string) $filter_knowledge_status : '';
$filter_failed_any       = ! empty( $filter_failed_any );
$filter_needs_reindex    = isset( $filter_needs_reindex ) ? (string) $filter_needs_reindex : '';

$knowledge_row_counts   = isset( $knowledge_row_counts ) && is_array( $knowledge_row_counts ) ? $knowledge_row_counts : array();
$eligibility_preview    = isset( $eligibility_preview ) && is_array( $eligibility_preview ) ? $eligibility_preview : null;
$sources_cron_stale     = ! empty( $sources_cron_stale );
$url_system_info        = isset( $url_system_info ) ? (string) $url_system_info : admin_url( 'admin.php?page=jsdw-ai-chat-system-info' );

$lifecycle_opts = array(
	''         => __( 'Any lifecycle', 'jsdw-ai-chat' ),
	'active'   => __( 'Active', 'jsdw-ai-chat' ),
	'inactive' => __( 'Inactive', 'jsdw-ai-chat' ),
	'excluded' => __( 'Excluded', 'jsdw-ai-chat' ),
	'missing'  => __( 'Missing', 'jsdw-ai-chat' ),
	'disabled' => __( 'Disabled', 'jsdw-ai-chat' ),
	'pending'  => __( 'Pending (discovery)', 'jsdw-ai-chat' ),
);
$content_opts = array(
	''             => __( 'Any content state', 'jsdw-ai-chat' ),
	'pending'      => __( 'Content: pending', 'jsdw-ai-chat' ),
	'ok'           => __( 'Content: OK', 'jsdw-ai-chat' ),
	'failed'       => __( 'Content: failed', 'jsdw-ai-chat' ),
	'unsupported'  => __( 'Content: unsupported', 'jsdw-ai-chat' ),
	'unavailable'  => __( 'Content: unavailable', 'jsdw-ai-chat' ),
);
$knowledge_opts = array(
	''        => __( 'Any knowledge state', 'jsdw-ai-chat' ),
	'pending' => __( 'Knowledge: pending', 'jsdw-ai-chat' ),
	'ready'   => __( 'Knowledge: ready', 'jsdw-ai-chat' ),
	'failed'  => __( 'Knowledge: failed', 'jsdw-ai-chat' ),
);
?>
<div class="jsdw-page jsdw-sources-page">
	<h1><?php echo esc_html__( 'Source registry', 'jsdw-ai-chat' ); ?></h1>
	<p class="jsdw-page-lead"><?php echo esc_html__( 'Discovered URLs and posts flow through discovery, text extraction, then knowledge indexing. Numbers below are snapshots — use Jobs when you need to confirm queue activity.', 'jsdw-ai-chat' ); ?></p>
	<?php
	jsdw_ai_chat_help_tip(
		'jsdw-sources-help',
		__( 'What this page is for', 'jsdw-ai-chat' ),
		'<p>' . esc_html__( 'Use this list to see lifecycle state (included vs excluded), content/knowledge badges, and to queue background work. Extraction and indexing run as jobs — they are not instant.', 'jsdw-ai-chat' ) . '</p>'
	);
	?>

	<?php if ( ! empty( $notice ) ) : ?>
		<div class="jsdw-shell-flash"><p><?php echo esc_html( $notice ); ?></p></div>
	<?php endif; ?>

	<?php if ( $sources_cron_stale ) : ?>
		<div class="notice notice-warning jsdw-sources-cron-notice">
			<p>
				<?php echo esc_html__( 'Background queue activity may be delayed: the last WP-Cron tick for this plugin is missing or older than 15 minutes. If you use DISABLE_WP_CRON, add a system cron that hits wp-cron.php.', 'jsdw-ai-chat' ); ?>
				<a href="<?php echo esc_url( $url_jobs ); ?>"><?php echo esc_html__( 'Jobs & logs', 'jsdw-ai-chat' ); ?></a>
				<?php echo esc_html( ' · ' ); ?>
				<a href="<?php echo esc_url( $url_system_info ); ?>"><?php echo esc_html__( 'System info', 'jsdw-ai-chat' ); ?></a>
			</p>
		</div>
	<?php endif; ?>

	<div class="jsdw-sources-summary jsdw-sources-summary--stats">
		<div class="jsdw-stat-card">
			<div class="jsdw-stat-label"><?php echo esc_html__( 'Total sources', 'jsdw-ai-chat' ); ?></div>
			<div class="jsdw-stat-value"><?php echo esc_html( (string) $total_sources ); ?></div>
			<div class="jsdw-stat-meta"><?php echo esc_html__( 'All rows in registry', 'jsdw-ai-chat' ); ?></div>
		</div>
		<div class="jsdw-stat-card">
			<div class="jsdw-stat-label"><?php echo esc_html__( 'Pending reindex', 'jsdw-ai-chat' ); ?></div>
			<div class="jsdw-stat-value"><?php echo esc_html( (string) $pending_reindex ); ?></div>
			<div class="jsdw-stat-meta"><?php echo esc_html__( 'Flagged needs_reindex', 'jsdw-ai-chat' ); ?></div>
		</div>
		<div class="jsdw-stat-card">
			<div class="jsdw-stat-label"><?php echo esc_html__( 'Content pending', 'jsdw-ai-chat' ); ?></div>
			<div class="jsdw-stat-value"><?php echo esc_html( (string) $c_pending ); ?></div>
			<div class="jsdw-stat-meta"><?php echo esc_html__( 'Awaiting extraction', 'jsdw-ai-chat' ); ?></div>
		</div>
		<div class="jsdw-stat-card">
			<div class="jsdw-stat-label"><?php echo esc_html__( 'Indexed (knowledge ready)', 'jsdw-ai-chat' ); ?></div>
			<div class="jsdw-stat-value"><?php echo esc_html( (string) $k_ready ); ?></div>
			<div class="jsdw-stat-meta"><?php echo esc_html__( 'Sources with ready knowledge', 'jsdw-ai-chat' ); ?></div>
		</div>
		<div class="jsdw-stat-card">
			<div class="jsdw-stat-label"><?php echo esc_html__( 'Knowledge pending', 'jsdw-ai-chat' ); ?></div>
			<div class="jsdw-stat-value"><?php echo esc_html( (string) $k_pending ); ?></div>
			<div class="jsdw-stat-meta"><?php echo esc_html__( 'Chunks/facts not ready', 'jsdw-ai-chat' ); ?></div>
		</div>
		<div class="jsdw-stat-card">
			<div class="jsdw-stat-label"><?php echo esc_html__( 'Excluded', 'jsdw-ai-chat' ); ?></div>
			<div class="jsdw-stat-value"><?php echo esc_html( (string) $lc_excl ); ?></div>
			<div class="jsdw-stat-meta"><?php echo esc_html__( 'Lifecycle excluded', 'jsdw-ai-chat' ); ?></div>
		</div>
		<div class="jsdw-stat-card">
			<div class="jsdw-stat-label"><?php echo esc_html__( 'Failed (any)', 'jsdw-ai-chat' ); ?></div>
			<div class="jsdw-stat-value"><?php echo esc_html( (string) ( $c_failed + $k_failed ) ); ?></div>
			<div class="jsdw-stat-meta"><?php echo esc_html__( 'Content or knowledge failed', 'jsdw-ai-chat' ); ?></div>
		</div>
		<div class="jsdw-stat-card">
			<div class="jsdw-stat-label"><?php echo esc_html__( 'Queue jobs', 'jsdw-ai-chat' ); ?></div>
			<div class="jsdw-stat-value"><?php echo esc_html( (string) $q_pending ); ?></div>
			<div class="jsdw-stat-meta"><a href="<?php echo esc_url( $url_jobs ); ?>"><?php echo esc_html__( 'View jobs', 'jsdw-ai-chat' ); ?></a></div>
		</div>
	</div>

	<div class="jsdw-sources-actions-card">
		<h2 class="jsdw-sources-actions-card__title"><?php echo esc_html__( 'Actions', 'jsdw-ai-chat' ); ?></h2>
		<p class="jsdw-sources-actions-card__desc"><?php echo esc_html__( 'Run discovery, drain pending work, or open jobs and settings.', 'jsdw-ai-chat' ); ?></p>
		<div class="jsdw-sources-toolbar">
			<form method="post">
				<?php wp_nonce_field( $nonce_action, $nonce_name ); ?>
				<button type="submit" class="button button-primary" name="jsdw_rescan_sources" value="1"><?php echo esc_html__( 'Run full scan', 'jsdw-ai-chat' ); ?></button>
			</form>
			<form method="post">
				<?php wp_nonce_field( $nonce_action, $nonce_name ); ?>
				<button type="submit" class="button" name="jsdw_process_pending_content" value="1"><?php echo esc_html__( 'Process pending content (up to 20)', 'jsdw-ai-chat' ); ?></button>
			</form>
			<form method="post">
				<?php wp_nonce_field( $nonce_action, $nonce_name ); ?>
				<button type="submit" class="button" name="jsdw_process_pending_knowledge" value="1"><?php echo esc_html__( 'Reindex pending knowledge (up to 20)', 'jsdw-ai-chat' ); ?></button>
			</form>
			<form method="post">
				<?php wp_nonce_field( $nonce_action, $nonce_name ); ?>
				<button type="submit" class="button" name="jsdw_run_queue_now" value="1"><?php echo esc_html__( 'Run queue now', 'jsdw-ai-chat' ); ?></button>
			</form>
			<a class="button" href="<?php echo esc_url( $url_jobs ); ?>"><?php echo esc_html__( 'Jobs & logs', 'jsdw-ai-chat' ); ?></a>
			<a class="button" href="<?php echo esc_url( $url_set ); ?>"><?php echo esc_html__( 'Plugin settings', 'jsdw-ai-chat' ); ?></a>
		</div>
	</div>

	<div class="jsdw-sources-filters">
		<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
			<input type="hidden" name="page" value="jsdw-ai-chat-sources" />
			<div class="jsdw-sources-filters__grid">
				<label>
					<?php echo esc_html__( 'Source type', 'jsdw-ai-chat' ); ?>
					<input type="text" name="source_type" value="<?php echo esc_attr( $filter_type ); ?>" placeholder="<?php echo esc_attr__( 'e.g. post, page', 'jsdw-ai-chat' ); ?>" />
				</label>
				<label>
					<?php echo esc_html__( 'Lifecycle', 'jsdw-ai-chat' ); ?>
					<select name="filter_status">
						<?php foreach ( $lifecycle_opts as $val => $lab ) : ?>
							<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $filter_status, $val ); ?>><?php echo esc_html( $lab ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<label>
					<?php echo esc_html__( 'Content', 'jsdw-ai-chat' ); ?>
					<select name="filter_content">
						<?php foreach ( $content_opts as $val => $lab ) : ?>
							<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $filter_content_status, $val ); ?>><?php echo esc_html( $lab ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<label>
					<?php echo esc_html__( 'Knowledge', 'jsdw-ai-chat' ); ?>
					<select name="filter_knowledge">
						<?php foreach ( $knowledge_opts as $val => $lab ) : ?>
							<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $filter_knowledge_status, $val ); ?>><?php echo esc_html( $lab ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<label>
					<span><?php echo esc_html__( 'Quick filters', 'jsdw-ai-chat' ); ?></span>
					<label style="flex-direction:row;align-items:center;gap:6px;">
						<input type="checkbox" name="filter_failed" value="1" <?php checked( $filter_failed_any ); ?> />
						<?php echo esc_html__( 'Failed only', 'jsdw-ai-chat' ); ?>
					</label>
				</label>
				<label>
					<?php echo esc_html__( 'Needs reindex', 'jsdw-ai-chat' ); ?>
					<select name="filter_reindex">
						<option value=""><?php echo esc_html__( 'Any', 'jsdw-ai-chat' ); ?></option>
						<option value="1" <?php selected( $filter_needs_reindex, '1' ); ?>><?php echo esc_html__( 'Yes', 'jsdw-ai-chat' ); ?></option>
						<option value="0" <?php selected( $filter_needs_reindex, '0' ); ?>><?php echo esc_html__( 'No', 'jsdw-ai-chat' ); ?></option>
					</select>
				</label>
				<label>
					<span>&nbsp;</span>
					<button type="submit" class="button button-primary"><?php echo esc_html__( 'Apply filters', 'jsdw-ai-chat' ); ?></button>
				</label>
			</div>
		</form>
	</div>

	<div class="jsdw-sources-preview-card">
		<h2 class="jsdw-sources-preview-card__title"><?php echo esc_html__( 'Eligibility preview', 'jsdw-ai-chat' ); ?></h2>
		<p class="jsdw-sources-preview-card__desc"><?php echo esc_html__( 'Evaluate current plugin rules against a URL or post ID. Nothing is written to the database.', 'jsdw-ai-chat' ); ?></p>
		<form method="post" class="jsdw-sources-preview-form">
			<?php wp_nonce_field( $nonce_action, $nonce_name ); ?>
			<label class="jsdw-sources-preview-field">
				<span><?php echo esc_html__( 'URL', 'jsdw-ai-chat' ); ?></span>
				<input type="url" name="jsdw_preview_url" class="regular-text" placeholder="https://…" />
			</label>
			<label class="jsdw-sources-preview-field">
				<span><?php echo esc_html__( 'Post ID', 'jsdw-ai-chat' ); ?></span>
				<input type="number" min="1" name="jsdw_preview_post_id" class="small-text" placeholder="<?php echo esc_attr__( 'e.g. 42', 'jsdw-ai-chat' ); ?>" />
			</label>
			<button type="submit" class="button" name="jsdw_eligibility_preview" value="1"><?php echo esc_html__( 'Preview rules', 'jsdw-ai-chat' ); ?></button>
		</form>
		<?php if ( is_array( $eligibility_preview ) ) : ?>
			<div class="jsdw-sources-preview-result" role="status">
				<p class="jsdw-sources-preview-result__target">
					<?php
					$pf = isset( $eligibility_preview['preview_for'] ) ? (string) $eligibility_preview['preview_for'] : '';
					echo esc_html( sprintf( /* translators: %s: title or URL */ __( 'Preview for: %s', 'jsdw-ai-chat' ), $pf ) );
					?>
				</p>
				<ul class="jsdw-sources-preview-result__list">
					<li><?php echo esc_html( ! empty( $eligibility_preview['allowed'] ) ? __( 'Verdict: allowed', 'jsdw-ai-chat' ) : __( 'Verdict: not allowed', 'jsdw-ai-chat' ) ); ?></li>
					<?php if ( ! empty( $eligibility_preview['reason_code'] ) ) : ?>
						<li><?php echo esc_html( sprintf( /* translators: %s: machine reason */ __( 'Reason code: %s', 'jsdw-ai-chat' ), (string) $eligibility_preview['reason_code'] ) ); ?></li>
					<?php endif; ?>
					<?php if ( ! empty( $eligibility_preview['reason_message'] ) ) : ?>
						<li><?php echo esc_html( (string) $eligibility_preview['reason_message'] ); ?></li>
					<?php endif; ?>
					<?php if ( ! empty( $eligibility_preview['matched_rule'] ) ) : ?>
						<li><?php echo esc_html( sprintf( /* translators: %s: rule key or pattern */ __( 'Matched rule: %s', 'jsdw-ai-chat' ), (string) $eligibility_preview['matched_rule'] ) ); ?></li>
					<?php endif; ?>
				</ul>
				<p class="jsdw-sources-preview-result__link">
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=jsdw-ai-chat-settings' ) ); ?>"><?php echo esc_html__( 'Open plugin settings', 'jsdw-ai-chat' ); ?></a>
				</p>
			</div>
		<?php endif; ?>
	</div>

	<p class="description">
		<?php
		echo esc_html(
			sprintf(
				/* translators: 1: public count, 2: internal count, 3: admin-only count */
				__( 'Visibility snapshot: %1$d public · %2$d internal · %3$d admin-only (retrieval boundaries unchanged).', 'jsdw-ai-chat' ),
				$vis_public,
				$vis_internal,
				$vis_admin
			)
		);
		?>
	</p>

	<?php if ( ! empty( $lifecycle_totals ) ) : ?>
		<p class="description">
			<?php
			$parts = array();
			foreach ( $lifecycle_totals as $st => $num ) {
				$parts[] = (string) $st . ': ' . (string) absint( $num );
			}
			echo esc_html( __( 'Lifecycle totals: ', 'jsdw-ai-chat' ) . implode( ' · ', $parts ) );
			?>
		</p>
	<?php endif; ?>

	<div class="jsdw-sources-table-heading-row">
		<h2 class="jsdw-sources-table-heading"><?php echo esc_html__( 'Sources', 'jsdw-ai-chat' ); ?></h2>
		<?php
		jsdw_ai_chat_help_tip(
			'jsdw-sources-stats-help',
			__( 'What these numbers mean', 'jsdw-ai-chat' ),
			'<p>' . esc_html__( 'Pipeline shows rules eligibility, then content extraction, then knowledge. Index size counts active chunks and facts. “Indexed” is only the last knowledge run; “Checked” is the last content pass.', 'jsdw-ai-chat' ) . '</p>'
		);
		?>
	</div>
	<div class="jsdw-table-scroll jsdw-table-scroll--sources">
		<table class="widefat striped jsdw-sources-table">
			<thead>
				<tr>
					<th class="jsdw-col-source"><?php echo esc_html__( 'Title', 'jsdw-ai-chat' ); ?></th>
					<th class="jsdw-col-type"><?php echo esc_html__( 'Type', 'jsdw-ai-chat' ); ?></th>
					<th class="jsdw-col-pipeline"><?php echo esc_html__( 'Pipeline', 'jsdw-ai-chat' ); ?></th>
					<th class="jsdw-col-counts"><?php echo esc_html__( 'Index size', 'jsdw-ai-chat' ); ?></th>
					<th class="jsdw-col-vis"><?php echo esc_html__( 'Exclusion / note', 'jsdw-ai-chat' ); ?></th>
					<th class="jsdw-col-activity"><?php echo esc_html__( 'Timestamps', 'jsdw-ai-chat' ); ?></th>
					<th class="jsdw-col-actions"><?php echo esc_html__( 'Actions', 'jsdw-ai-chat' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php if ( empty( $sources ) ) : ?>
				<tr><td colspan="7"><?php echo esc_html__( 'No sources match the current filters.', 'jsdw-ai-chat' ); ?></td></tr>
			<?php else : ?>
				<?php
				foreach ( $sources as $row ) :
					if ( ! is_array( $row ) ) {
						continue;
					}
					$sid   = isset( $row['id'] ) ? absint( $row['id'] ) : 0;
					$title = isset( $row['title'] ) && '' !== (string) $row['title'] ? (string) $row['title'] : __( '(no title)', 'jsdw-ai-chat' );
					$stype = isset( $row['source_type'] ) ? (string) $row['source_type'] : '';
					$url   = isset( $row['source_url'] ) ? (string) $row['source_url'] : '';
					$lc    = isset( $row['status'] ) ? (string) $row['status'] : '';

					$elig = isset( $row['eligibility'] ) ? sanitize_key( (string) $row['eligibility'] ) : 'unknown';
					$e_class = 'jsdw-badge--neutral';
					if ( 'eligible' === $elig ) {
						$e_class = 'jsdw-badge--ok';
					} elseif ( 'ineligible' === $elig ) {
						$e_class = 'jsdw-badge--err';
					} elseif ( 'unknown' === $elig ) {
						$e_class = 'jsdw-badge--warn';
					}

					$cst     = isset( $row['content_processing_status'] ) ? (string) $row['content_processing_status'] : '';
					$c_class = 'jsdw-badge--neutral';
					if ( JSDW_AI_Chat_DB::CONTENT_PROC_STATUS_OK === $cst ) {
						$c_class = 'jsdw-badge--ok';
					} elseif ( JSDW_AI_Chat_DB::CONTENT_PROC_STATUS_PENDING === $cst ) {
						$c_class = 'jsdw-badge--warn';
					} elseif ( in_array( $cst, array( JSDW_AI_Chat_DB::CONTENT_PROC_STATUS_FAILED, JSDW_AI_Chat_DB::CONTENT_PROC_STATUS_UNSUPPORTED, JSDW_AI_Chat_DB::CONTENT_PROC_STATUS_UNAVAILABLE ), true ) ) {
						$c_class = 'jsdw-badge--err';
					}

					$kst     = isset( $row['knowledge_processing_status'] ) ? (string) $row['knowledge_processing_status'] : '';
					$k_class = 'jsdw-badge--neutral';
					if ( JSDW_AI_Chat_Knowledge_Constants::KNOWLEDGE_STATUS_READY === $kst ) {
						$k_class = 'jsdw-badge--ok';
					} elseif ( JSDW_AI_Chat_Knowledge_Constants::KNOWLEDGE_STATUS_PENDING === $kst ) {
						$k_class = 'jsdw-badge--warn';
					} elseif ( JSDW_AI_Chat_Knowledge_Constants::KNOWLEDGE_STATUS_FAILED === $kst ) {
						$k_class = 'jsdw-badge--err';
					}

					$can_k = JSDW_AI_Chat_Source_Admin_Presenter::can_queue_knowledge( $row );
					$lk    = ! empty( $row['last_knowledge_processing_gmt'] ) ? (string) $row['last_knowledge_processing_gmt'] : '';
					$lcg   = ! empty( $row['last_content_check_gmt'] ) ? (string) $row['last_content_check_gmt'] : '';

					$kc        = isset( $knowledge_row_counts[ $sid ] ) && is_array( $knowledge_row_counts[ $sid ] ) ? $knowledge_row_counts[ $sid ] : array( 'chunks' => 0, 'facts' => 0 );
					$chunk_n   = isset( $kc['chunks'] ) ? absint( $kc['chunks'] ) : 0;
					$fact_n    = isset( $kc['facts'] ) ? absint( $kc['facts'] ) : 0;
					$idx_class = ( JSDW_AI_Chat_Knowledge_Constants::KNOWLEDGE_STATUS_READY === $kst && 0 === $chunk_n ) ? 'jsdw-source-index jsdw-source-index--warn' : 'jsdw-source-index';

					$cr_label       = JSDW_AI_Chat_Source_Admin_Presenter::label_change_reason( $row );
					$cr_guide       = JSDW_AI_Chat_Source_Admin_Presenter::change_reason_guidance( $row );
					$show_include   = ( JSDW_AI_Chat_DB::SOURCE_STATUS_EXCLUDED === $lc );
					$show_redisc    = ( JSDW_AI_Chat_DB::SOURCE_STATUS_MISSING === $lc );
					$show_reenable  = ( JSDW_AI_Chat_DB::SOURCE_STATUS_DISABLED === $lc );
					$show_activate  = ( JSDW_AI_Chat_DB::SOURCE_STATUS_INACTIVE === $lc );
					$show_k_force     = ( JSDW_AI_Chat_Knowledge_Constants::KNOWLEDGE_STATUS_FAILED === $kst );
					$content_reason_t = JSDW_AI_Chat_Source_Admin_Presenter::label_content_reason( $row );
					$know_reason_t    = JSDW_AI_Chat_Source_Admin_Presenter::label_knowledge_reason( $row );
					$show_c_reason    = in_array( $cst, array( JSDW_AI_Chat_DB::CONTENT_PROC_STATUS_FAILED, JSDW_AI_Chat_DB::CONTENT_PROC_STATUS_UNSUPPORTED, JSDW_AI_Chat_DB::CONTENT_PROC_STATUS_UNAVAILABLE ), true ) && '' !== $content_reason_t;
					$show_k_reason    = ( JSDW_AI_Chat_Knowledge_Constants::KNOWLEDGE_STATUS_FAILED === $kst ) && '' !== $know_reason_t;
					$c_guide          = JSDW_AI_Chat_Source_Admin_Presenter::content_processing_reason_guidance( $row );
					$k_guide          = JSDW_AI_Chat_Source_Admin_Presenter::knowledge_processing_reason_guidance( $row );
					$reindex_title      = $can_k
						? __( 'Queue a knowledge indexing job (not instant).', 'jsdw-ai-chat' )
						: JSDW_AI_Chat_Source_Admin_Presenter::reindex_button_blocked_title( $row );
					$ext_m              = isset( $row['extraction_method'] ) ? (string) $row['extraction_method'] : '';
					?>
					<tr>
						<td class="jsdw-col-source" data-colname="<?php echo esc_attr__( 'Title', 'jsdw-ai-chat' ); ?>">
							<div class="jsdw-source-title" title="<?php echo esc_attr( $title ); ?>"><?php echo esc_html( $title ); ?></div>
							<p class="jsdw-source-meta">
								<?php
								echo esc_html(
									sprintf(
										/* translators: 1: source id, 2: lifecycle label */
										__( 'ID %1$s · %2$s', 'jsdw-ai-chat' ),
										(string) $sid,
										JSDW_AI_Chat_Source_Admin_Presenter::label_lifecycle_status( $row )
									)
								);
								?>
							</p>
							<p class="jsdw-pipeline-line"><?php echo esc_html( JSDW_AI_Chat_Source_Admin_Presenter::get_pipeline_summary( $row, $chunk_n, $fact_n ) ); ?></p>
							<details class="jsdw-sources-details">
								<summary><?php echo esc_html__( 'Body / extraction', 'jsdw-ai-chat' ); ?></summary>
								<p class="jsdw-source-hint">
									<?php
									echo esc_html(
										sprintf(
											/* translators: 1: normalized length, 2: extraction method */
											__( 'Normalized length: %1$s · Method: %2$s', 'jsdw-ai-chat' ),
											JSDW_AI_Chat_Source_Admin_Presenter::format_normalized_length( $row ),
											'' !== $ext_m ? $ext_m : '—'
										)
									);
									?>
								</p>
							</details>
						</td>
						<td class="jsdw-col-type" data-colname="<?php echo esc_attr__( 'Type', 'jsdw-ai-chat' ); ?>"><?php echo esc_html( $stype ); ?></td>
						<td class="jsdw-col-pipeline" data-colname="<?php echo esc_attr__( 'Pipeline', 'jsdw-ai-chat' ); ?>">
							<div class="jsdw-pipeline-badges">
								<span class="jsdw-badge <?php echo esc_attr( $e_class ); ?>"><?php echo esc_html( JSDW_AI_Chat_Source_Admin_Presenter::label_eligibility_status( $row ) ); ?></span>
								<span class="jsdw-badge <?php echo esc_attr( $c_class ); ?>"><?php echo esc_html( JSDW_AI_Chat_Source_Admin_Presenter::label_content_status( $row ) ); ?></span>
								<?php if ( $show_c_reason ) : ?>
									<span class="jsdw-pipeline-inline-reason"><?php echo esc_html( $content_reason_t ); ?></span>
									<?php if ( '' !== $c_guide ) : ?>
										<span class="jsdw-source-hint jsdw-pipeline-inline-hint"><?php echo esc_html( $c_guide ); ?></span>
									<?php endif; ?>
								<?php endif; ?>
								<br />
								<span class="jsdw-badge <?php echo esc_attr( $k_class ); ?>"><?php echo esc_html( JSDW_AI_Chat_Source_Admin_Presenter::label_knowledge_status( $row ) ); ?></span>
								<?php if ( $show_k_reason ) : ?>
									<span class="jsdw-pipeline-inline-reason"><?php echo esc_html( $know_reason_t ); ?></span>
									<?php if ( '' !== $k_guide ) : ?>
										<span class="jsdw-source-hint jsdw-pipeline-inline-hint"><?php echo esc_html( $k_guide ); ?></span>
									<?php endif; ?>
								<?php endif; ?>
								<?php if ( ! empty( $row['needs_reindex'] ) ) : ?>
									<br /><span class="jsdw-badge jsdw-badge--warn"><?php echo esc_html__( 'Needs reindex', 'jsdw-ai-chat' ); ?></span>
								<?php endif; ?>
							</div>
							<?php if ( 'ineligible' === $elig ) : ?>
								<p class="jsdw-source-hint">
									<a href="<?php echo esc_url( JSDW_AI_Chat_Source_Admin_Presenter::eligibility_settings_admin_url() ); ?>"><?php echo esc_html__( 'Adjust in Settings', 'jsdw-ai-chat' ); ?></a>
								</p>
							<?php endif; ?>
						</td>
						<td class="jsdw-col-counts" data-colname="<?php echo esc_attr__( 'Index size', 'jsdw-ai-chat' ); ?>">
							<span class="<?php echo esc_attr( $idx_class ); ?>"><?php echo esc_html( (string) $chunk_n . ' / ' . (string) $fact_n ); ?></span>
							<span class="jsdw-source-hint"><?php echo esc_html__( 'chunks / facts', 'jsdw-ai-chat' ); ?></span>
						</td>
						<td class="jsdw-col-vis" data-colname="<?php echo esc_attr__( 'Exclusion / note', 'jsdw-ai-chat' ); ?>">
							<?php if ( '' !== $cr_label ) : ?>
								<div class="jsdw-source-reason"><?php echo esc_html( $cr_label ); ?></div>
							<?php endif; ?>
							<?php if ( '' !== $cr_guide ) : ?>
								<p class="jsdw-source-hint"><?php echo esc_html( $cr_guide ); ?></p>
							<?php elseif ( '' === $cr_label ) : ?>
								<span class="jsdw-source-hint">—</span>
							<?php endif; ?>
						</td>
						<td class="jsdw-col-activity" data-colname="<?php echo esc_attr__( 'Timestamps', 'jsdw-ai-chat' ); ?>">
							<div class="jsdw-source-ts-line"><?php echo esc_html__( 'Indexed:', 'jsdw-ai-chat' ); ?> <?php echo esc_html( '' !== $lk ? $lk : '—' ); ?></div>
							<div class="jsdw-source-ts-line"><?php echo esc_html__( 'Checked:', 'jsdw-ai-chat' ); ?> <?php echo esc_html( '' !== $lcg ? $lcg : '—' ); ?></div>
						</td>
						<td class="jsdw-sources-actions-cell jsdw-col-actions" data-colname="<?php echo esc_attr__( 'Actions', 'jsdw-ai-chat' ); ?>">
							<?php if ( '' !== $url ) : ?>
								<a class="button button-small" href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html__( 'View', 'jsdw-ai-chat' ); ?></a>
							<?php endif; ?>
							<button type="button" class="button button-small jsdw-source-inspector-open" data-source-id="<?php echo esc_attr( (string) $sid ); ?>"><?php echo esc_html__( 'Details', 'jsdw-ai-chat' ); ?></button>
							<?php if ( $show_redisc ) : ?>
								<form method="post" class="jsdw-sources-inline-form">
									<?php wp_nonce_field( $nonce_action, $nonce_name ); ?>
									<input type="hidden" name="jsdw_source_id" value="<?php echo esc_attr( (string) $sid ); ?>" />
									<button type="submit" class="button button-small" name="jsdw_rediscover_missing" value="1" title="<?php echo esc_attr__( 'Queues discovery to re-check this item (or a site-wide pass if there is no linked post).', 'jsdw-ai-chat' ); ?>"><?php echo esc_html__( 'Re-discover', 'jsdw-ai-chat' ); ?></button>
								</form>
							<?php endif; ?>
							<?php if ( $show_reenable ) : ?>
								<form method="post" class="jsdw-sources-inline-form">
									<?php wp_nonce_field( $nonce_action, $nonce_name ); ?>
									<input type="hidden" name="jsdw_source_id" value="<?php echo esc_attr( (string) $sid ); ?>" />
									<button type="submit" class="button button-small" name="jsdw_reenable_manual" value="1"><?php echo esc_html__( 'Re-enable', 'jsdw-ai-chat' ); ?></button>
								</form>
							<?php endif; ?>
							<?php if ( $show_activate ) : ?>
								<form method="post" class="jsdw-sources-inline-form">
									<?php wp_nonce_field( $nonce_action, $nonce_name ); ?>
									<input type="hidden" name="jsdw_source_id" value="<?php echo esc_attr( (string) $sid ); ?>" />
									<button type="submit" class="button button-small" name="jsdw_activate_inactive" value="1"><?php echo esc_html__( 'Activate', 'jsdw-ai-chat' ); ?></button>
								</form>
							<?php endif; ?>
							<?php if ( $show_include ) : ?>
								<form method="post" class="jsdw-sources-inline-form">
									<?php wp_nonce_field( $nonce_action, $nonce_name ); ?>
									<input type="hidden" name="jsdw_source_id" value="<?php echo esc_attr( (string) $sid ); ?>" />
									<button type="submit" class="button button-small button-primary" name="jsdw_manual_include_source" value="1"><?php echo esc_html__( 'Include & extract', 'jsdw-ai-chat' ); ?></button>
								</form>
							<?php endif; ?>
							<form method="post" class="jsdw-sources-inline-form">
								<?php wp_nonce_field( $nonce_action, $nonce_name ); ?>
								<input type="hidden" name="jsdw_source_id" value="<?php echo esc_attr( (string) $sid ); ?>" />
								<button type="submit" class="button button-small" name="jsdw_queue_content_one" value="1" title="<?php echo esc_attr__( 'Queues a background job to extract text (not instant).', 'jsdw-ai-chat' ); ?>"><?php echo esc_html__( 'Extract text', 'jsdw-ai-chat' ); ?></button>
							</form>
							<form method="post" class="jsdw-sources-inline-form">
								<?php wp_nonce_field( $nonce_action, $nonce_name ); ?>
								<input type="hidden" name="jsdw_source_id" value="<?php echo esc_attr( (string) $sid ); ?>" />
								<button type="submit" class="button button-small" name="jsdw_queue_knowledge_one" value="1" <?php disabled( ! $can_k ); ?> title="<?php echo esc_attr( $reindex_title ); ?>"><?php echo esc_html__( 'Reindex', 'jsdw-ai-chat' ); ?></button>
							</form>
							<?php if ( $show_k_force ) : ?>
								<form method="post" class="jsdw-sources-inline-form">
									<?php wp_nonce_field( $nonce_action, $nonce_name ); ?>
									<input type="hidden" name="jsdw_source_id" value="<?php echo esc_attr( (string) $sid ); ?>" />
									<button type="submit" class="button button-small" name="jsdw_queue_knowledge_force" value="1" title="<?php echo esc_attr__( 'Queues knowledge even when content is not OK — may fail again until content is fixed.', 'jsdw-ai-chat' ); ?>"><?php echo esc_html__( 'Retry knowledge (forced)', 'jsdw-ai-chat' ); ?></button>
								</form>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
			</tbody>
		</table>
	</div>

	<p class="jsdw-sources-note">
		<?php echo esc_html__( 'If rows stay in content pending, ensure WP-Cron runs (or use Run queue now) and check the Jobs screen for failures. Excluded or rule-filtered sources are managed under plugin settings, not on this page.', 'jsdw-ai-chat' ); ?>
	</p>

	<div id="jsdw-source-inspector-modal" class="jsdw-source-inspector-modal" style="display:none;" aria-hidden="true">
		<div class="jsdw-source-inspector-backdrop" tabindex="-1"></div>
		<div class="jsdw-source-inspector-panel" role="dialog" aria-modal="true" aria-labelledby="jsdw-source-inspector-title">
			<div class="jsdw-source-inspector-panel__head">
				<h2 id="jsdw-source-inspector-title" class="jsdw-source-inspector-title"><?php echo esc_html__( 'Source details', 'jsdw-ai-chat' ); ?></h2>
				<button type="button" class="button jsdw-source-inspector-close"><?php echo esc_html__( 'Close', 'jsdw-ai-chat' ); ?></button>
			</div>
			<div class="jsdw-source-inspector-body"></div>
		</div>
	</div>
</div>
