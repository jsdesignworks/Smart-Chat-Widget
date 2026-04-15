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
	<p class="description"><?php echo esc_html__( 'Manage discovered sources, pipeline states, and processing jobs. Content and knowledge each have their own status; knowledge stays pending until content reaches OK.', 'jsdw-ai-chat' ); ?></p>

	<?php if ( ! empty( $notice ) ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php echo esc_html( $notice ); ?></p></div>
	<?php endif; ?>

	<div class="jsdw-sources-summary">
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

	<h2 class="jsdw-sources-table-heading"><?php echo esc_html__( 'Sources', 'jsdw-ai-chat' ); ?></h2>
	<div class="jsdw-table-scroll jsdw-table-scroll--sources">
		<table class="widefat striped jsdw-sources-table jsdw-sources-table--simple">
			<thead>
				<tr>
					<th><?php echo esc_html__( 'Title', 'jsdw-ai-chat' ); ?></th>
					<th><?php echo esc_html__( 'Type', 'jsdw-ai-chat' ); ?></th>
					<th><?php echo esc_html__( 'Status', 'jsdw-ai-chat' ); ?></th>
					<th><?php echo esc_html__( 'Last indexed', 'jsdw-ai-chat' ); ?></th>
					<th><?php echo esc_html__( 'Actions', 'jsdw-ai-chat' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php if ( empty( $sources ) ) : ?>
				<tr><td colspan="5"><?php echo esc_html__( 'No sources match the current filters.', 'jsdw-ai-chat' ); ?></td></tr>
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

					$lc_class = 'jsdw-badge--neutral';
					if ( 'active' === $lc ) {
						$lc_class = 'jsdw-badge--ok';
					} elseif ( in_array( $lc, array( 'excluded', 'missing', 'disabled' ), true ) ) {
						$lc_class = 'jsdw-badge--err';
					} elseif ( 'pending' === $lc ) {
						$lc_class = 'jsdw-badge--warn';
					}

					$kst     = isset( $row['knowledge_processing_status'] ) ? (string) $row['knowledge_processing_status'] : '';
					$k_class = 'jsdw-badge--neutral';
					if ( 'ready' === $kst ) {
						$k_class = 'jsdw-badge--ok';
					} elseif ( 'pending' === $kst ) {
						$k_class = 'jsdw-badge--warn';
					} elseif ( 'failed' === $kst ) {
						$k_class = 'jsdw-badge--err';
					}

					$can_k = JSDW_AI_Chat_Source_Admin_Presenter::can_queue_knowledge( $row );
					$act   = JSDW_AI_Chat_Source_Admin_Presenter::last_activity_gmt( $row );
					$lk    = ! empty( $row['last_knowledge_processing_gmt'] ) ? (string) $row['last_knowledge_processing_gmt'] : '';
					$lcg   = ! empty( $row['last_content_check_gmt'] ) ? (string) $row['last_content_check_gmt'] : '';
					$last_ix = '' !== $lk ? $lk : $lcg;
					?>
					<tr>
						<td data-colname="<?php echo esc_attr__( 'Title', 'jsdw-ai-chat' ); ?>">
							<div class="jsdw-source-title" title="<?php echo esc_attr( $title ); ?>"><?php echo esc_html( $title ); ?></div>
							<p class="jsdw-source-meta"><?php echo esc_html( sprintf( /* translators: %s: id */ __( 'ID %s', 'jsdw-ai-chat' ), (string) $sid ) ); ?></p>
						</td>
						<td data-colname="<?php echo esc_attr__( 'Type', 'jsdw-ai-chat' ); ?>"><?php echo esc_html( $stype ); ?></td>
						<td data-colname="<?php echo esc_attr__( 'Status', 'jsdw-ai-chat' ); ?>">
							<span class="jsdw-badge <?php echo esc_attr( $lc_class ); ?>"><?php echo esc_html( JSDW_AI_Chat_Source_Admin_Presenter::label_lifecycle_status( $row ) ); ?></span>
							<span class="jsdw-badge <?php echo esc_attr( $k_class ); ?>"><?php echo esc_html( JSDW_AI_Chat_Source_Admin_Presenter::label_knowledge_status( $row ) ); ?></span>
							<?php if ( ! empty( $row['needs_reindex'] ) ) : ?>
								<span class="jsdw-badge jsdw-badge--warn"><?php echo esc_html__( 'Reindex', 'jsdw-ai-chat' ); ?></span>
							<?php endif; ?>
						</td>
						<td data-colname="<?php echo esc_attr__( 'Last indexed', 'jsdw-ai-chat' ); ?>"><?php echo esc_html( '' !== $last_ix ? $last_ix : ( '' !== $act ? $act : '—' ) ); ?></td>
						<td class="jsdw-sources-actions-cell" data-colname="<?php echo esc_attr__( 'Actions', 'jsdw-ai-chat' ); ?>">
							<?php if ( '' !== $url ) : ?>
								<a class="button button-small" href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html__( 'View', 'jsdw-ai-chat' ); ?></a>
							<?php endif; ?>
							<form method="post" class="jsdw-sources-inline-form">
								<?php wp_nonce_field( $nonce_action, $nonce_name ); ?>
								<input type="hidden" name="jsdw_source_id" value="<?php echo esc_attr( (string) $sid ); ?>" />
								<button type="submit" class="button button-small" name="jsdw_queue_content_one" value="1"><?php echo esc_html__( 'Content', 'jsdw-ai-chat' ); ?></button>
							</form>
							<form method="post" class="jsdw-sources-inline-form">
								<?php wp_nonce_field( $nonce_action, $nonce_name ); ?>
								<input type="hidden" name="jsdw_source_id" value="<?php echo esc_attr( (string) $sid ); ?>" />
								<button type="submit" class="button button-small" name="jsdw_queue_knowledge_one" value="1" <?php disabled( ! $can_k ); ?> title="<?php echo esc_attr( $can_k ? '' : __( 'Knowledge runs after content is OK.', 'jsdw-ai-chat' ) ); ?>"><?php echo esc_html__( 'Reindex', 'jsdw-ai-chat' ); ?></button>
							</form>
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
</div>
