<?php
/**
 * Phase 7.3B — Dashboard (data from $health_report only).
 *
 * @package JSDW_AI_Chat
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$r = isset( $health_report ) && is_array( $health_report ) ? $health_report : array();

$disc = isset( $r['discovery'] ) && is_array( $r['discovery'] ) ? $r['discovery'] : array();
$content_state = isset( $r['content_state'] ) && is_array( $r['content_state'] ) ? $r['content_state'] : array();
$knowledge_state = isset( $r['knowledge_state'] ) && is_array( $r['knowledge_state'] ) ? $r['knowledge_state'] : array();
$answer_state = isset( $r['answer_state'] ) && is_array( $r['answer_state'] ) ? $r['answer_state'] : array();
$ai_sum        = isset( $answer_state['ai_status_summary'] ) && is_array( $answer_state['ai_status_summary'] ) ? $answer_state['ai_status_summary'] : array();
$ai_sum_label  = isset( $ai_sum['label'] ) ? (string) $ai_sum['label'] : '';
$ai_sum_detail = isset( $ai_sum['detail'] ) ? (string) $ai_sum['detail'] : '';
$ai_sum_sev    = isset( $ai_sum['severity'] ) ? (string) $ai_sum['severity'] : 'neutral';

$source_rows = isset( $disc['source_counts'] ) && is_array( $disc['source_counts'] ) ? $disc['source_counts'] : array();
$total_sources = 0;
foreach ( $source_rows as $row ) {
	if ( is_array( $row ) && isset( $row['count_total'] ) ) {
		$total_sources += absint( $row['count_total'] );
	}
}

$pending_reindex = isset( $disc['pending_reindex'] ) ? absint( $disc['pending_reindex'] ) : 0;

$K = isset( $knowledge_state['knowledge_status_counts'] ) && is_array( $knowledge_state['knowledge_status_counts'] ) ? $knowledge_state['knowledge_status_counts'] : array();
$knowledge_total = 0;
foreach ( $K as $kv ) {
	$knowledge_total += absint( $kv );
}
$knowledge_keys = array_keys( $K );
sort( $knowledge_keys, SORT_STRING );
$knowledge_meta_parts = array();
foreach ( $knowledge_keys as $kk ) {
	$knowledge_meta_parts[] = (string) $kk . ': ' . (string) absint( isset( $K[ $kk ] ) ? $K[ $kk ] : 0 );
}
$knowledge_meta = implode( ' · ', $knowledge_meta_parts );

$conversations_count = isset( $answer_state['conversations'] ) ? absint( $answer_state['conversations'] ) : 0;

/**
 * Sum numeric values in an associative array.
 *
 * @param array<mixed,mixed> $arr
 */
$jsdw_sum_counts = static function ( array $arr ) {
	$s = 0;
	foreach ( $arr as $v ) {
		$s += absint( $v );
	}
	return $s;
};

$disc_enabled = ! empty( $disc['enabled'] );
$disc_q = isset( $disc['queue_counts'] ) && is_array( $disc['queue_counts'] ) ? $disc['queue_counts'] : array();
$disc_q_sum = $jsdw_sum_counts( $disc_q );

$C = isset( $content_state['status_counts'] ) && is_array( $content_state['status_counts'] ) ? $content_state['status_counts'] : array();
$content_q = isset( $content_state['queue_counts'] ) && is_array( $content_state['queue_counts'] ) ? $content_state['queue_counts'] : array();
$content_q_sum = $jsdw_sum_counts( $content_q );

$know_q = isset( $knowledge_state['queue_counts'] ) && is_array( $knowledge_state['queue_counts'] ) ? $knowledge_state['queue_counts'] : array();
$know_q_sum = $jsdw_sum_counts( $know_q );

$last_error = isset( $r['last_error'] ) ? $r['last_error'] : array();

// Pipeline dot states (deterministic; see Phase 7.3B plan).
$pipe_discovery = 'ok';
if ( ! $disc_enabled ) {
	$pipe_discovery = 'inactive';
} elseif ( $pending_reindex > 0 || ( ! empty( $disc_q ) && $disc_q_sum > 0 ) ) {
	$pipe_discovery = 'warn';
}

$pipe_content = 'neutral';
if ( isset( $C['pending'] ) && (int) $C['pending'] > 0 ) {
	$pipe_content = 'warn';
} elseif ( isset( $C['failed'] ) && (int) $C['failed'] > 0 ) {
	$pipe_content = 'warn';
} elseif ( ! empty( $content_q ) && $content_q_sum > 0 ) {
	$pipe_content = 'warn';
} elseif ( ! empty( $C ) ) {
	$pipe_content = 'ok';
}

$pipe_knowledge = 'neutral';
if ( isset( $K['pending'] ) && (int) $K['pending'] > 0 ) {
	$pipe_knowledge = 'warn';
} elseif ( isset( $K['failed'] ) && (int) $K['failed'] > 0 ) {
	$pipe_knowledge = 'warn';
} elseif ( ! empty( $know_q ) && $know_q_sum > 0 ) {
	$pipe_knowledge = 'warn';
} elseif ( ! empty( $K ) ) {
	$pipe_knowledge = 'ok';
}

$pipe_answering = 'ok';
if ( ! empty( $last_error ) ) {
	$pipe_answering = 'warn';
}

$schema_version = isset( $r['schema_version'] ) ? (string) $r['schema_version'] : '';

$url_sources = admin_url( 'admin.php?page=jsdw-ai-chat-sources' );
$url_jobs    = admin_url( 'admin.php?page=jsdw-ai-chat-jobs' );
$url_conv    = admin_url( 'admin.php?page=jsdw-ai-chat-conversations' );
$url_settings = admin_url( 'admin.php?page=jsdw-ai-chat-settings' );

// Pipeline one-line summaries (factual).
$sum_c = $jsdw_sum_counts( $C );
$disc_line = sprintf(
	/* translators: 1: yes/no, 2: pending reindex count, 3: discovery queue job sum */
	__( 'Enabled: %1$s · Pending reindex: %2$d · Discovery queue jobs: %3$d', 'jsdw-ai-chat' ),
	$disc_enabled ? __( 'yes', 'jsdw-ai-chat' ) : __( 'no', 'jsdw-ai-chat' ),
	$pending_reindex,
	$disc_q_sum
);
$content_line = sprintf(
	/* translators: 1: total sources in content status buckets, 2: content queue job sum */
	__( 'Sources in content status buckets: %1$d · Content queue jobs: %2$d', 'jsdw-ai-chat' ),
	$sum_c,
	$content_q_sum
);
$k_line_parts = array();
foreach ( $knowledge_keys as $kk ) {
	$k_line_parts[] = (string) $kk . ': ' . (string) absint( isset( $K[ $kk ] ) ? $K[ $kk ] : 0 );
}
$knowledge_line = implode( ' · ', $k_line_parts );
if ( '' === $knowledge_line ) {
	$knowledge_line = __( 'No knowledge status rows.', 'jsdw-ai-chat' );
}
$knowledge_line .= ' · ' . sprintf(
	/* translators: %d: knowledge queue job sum */
	__( 'Knowledge queue jobs: %d', 'jsdw-ai-chat' ),
	$know_q_sum
);

$messages_count = isset( $answer_state['messages'] ) ? absint( $answer_state['messages'] ) : 0;
$answer_mode    = isset( $answer_state['answer_mode'] ) ? (string) $answer_state['answer_mode'] : '';
$answering_line = sprintf(
	/* translators: 1: conversations count, 2: messages count, 3: answer mode slug */
	__( 'Conversations: %1$d · Messages: %2$d · Mode: %3$s', 'jsdw-ai-chat' ),
	$conversations_count,
	$messages_count,
	'' !== $answer_mode ? $answer_mode : '—'
);
if ( ! empty( $last_error ) ) {
	$answering_line .= ' · ' . __( 'Last error payload present', 'jsdw-ai-chat' );
}
?>
<div class="jsdw-page jsdw-dashboard">
	<h1><?php echo esc_html__( 'JSDW AI Chat Dashboard', 'jsdw-ai-chat' ); ?></h1>

	<div class="jsdw-dashboard-grid">
		<a class="jsdw-stat-card" href="<?php echo esc_url( $url_sources ); ?>">
			<div class="jsdw-stat-label"><?php echo esc_html__( 'Total Sources', 'jsdw-ai-chat' ); ?></div>
			<div class="jsdw-stat-value"><?php echo esc_html( (string) $total_sources ); ?></div>
			<div class="jsdw-stat-meta"><?php echo esc_html__( 'All registered sources', 'jsdw-ai-chat' ); ?></div>
		</a>
		<a class="jsdw-stat-card" href="<?php echo esc_url( $url_sources ); ?>">
			<div class="jsdw-stat-label"><?php echo esc_html__( 'Pending Reindex', 'jsdw-ai-chat' ); ?></div>
			<div class="jsdw-stat-value"><?php echo esc_html( (string) $pending_reindex ); ?></div>
			<div class="jsdw-stat-meta"><?php echo esc_html__( 'Sources flagged for reindex', 'jsdw-ai-chat' ); ?></div>
		</a>
		<a class="jsdw-stat-card" href="<?php echo esc_url( $url_sources ); ?>">
			<div class="jsdw-stat-label"><?php echo esc_html__( 'Knowledge Status', 'jsdw-ai-chat' ); ?></div>
			<div class="jsdw-stat-value"><?php echo esc_html( (string) $knowledge_total ); ?></div>
			<div class="jsdw-stat-meta"><?php echo $knowledge_meta ? esc_html( $knowledge_meta ) : esc_html__( 'No knowledge status rows.', 'jsdw-ai-chat' ); ?></div>
		</a>
		<a class="jsdw-stat-card" href="<?php echo esc_url( $url_conv ); ?>">
			<div class="jsdw-stat-label"><?php echo esc_html__( 'Conversations', 'jsdw-ai-chat' ); ?></div>
			<div class="jsdw-stat-value"><?php echo esc_html( (string) $conversations_count ); ?></div>
			<div class="jsdw-stat-meta"><?php echo esc_html__( 'Stored conversations (all time)', 'jsdw-ai-chat' ); ?></div>
		</a>
	</div>

	<div class="jsdw-card jsdw-dashboard-pipeline">
		<div class="jsdw-card__title"><?php echo esc_html__( 'Pipeline status', 'jsdw-ai-chat' ); ?></div>
		<ul class="jsdw-pipeline-list">
			<li class="jsdw-pipeline-row">
				<span class="jsdw-pipeline-dot jsdw-pipeline-dot--<?php echo esc_attr( $pipe_discovery ); ?>" aria-hidden="true"></span>
				<div class="jsdw-pipeline-body">
					<div class="jsdw-pipeline-label"><?php echo esc_html__( 'Discovery', 'jsdw-ai-chat' ); ?></div>
					<div class="jsdw-pipeline-summary"><?php echo esc_html( $disc_line ); ?></div>
				</div>
			</li>
			<li class="jsdw-pipeline-row">
				<span class="jsdw-pipeline-dot jsdw-pipeline-dot--<?php echo esc_attr( $pipe_content ); ?>" aria-hidden="true"></span>
				<div class="jsdw-pipeline-body">
					<div class="jsdw-pipeline-label"><?php echo esc_html__( 'Content', 'jsdw-ai-chat' ); ?></div>
					<div class="jsdw-pipeline-summary"><?php echo esc_html( $content_line ); ?></div>
				</div>
			</li>
			<li class="jsdw-pipeline-row">
				<span class="jsdw-pipeline-dot jsdw-pipeline-dot--<?php echo esc_attr( $pipe_knowledge ); ?>" aria-hidden="true"></span>
				<div class="jsdw-pipeline-body">
					<div class="jsdw-pipeline-label"><?php echo esc_html__( 'Knowledge', 'jsdw-ai-chat' ); ?></div>
					<div class="jsdw-pipeline-summary"><?php echo esc_html( $knowledge_line ); ?></div>
				</div>
			</li>
			<li class="jsdw-pipeline-row">
				<span class="jsdw-pipeline-dot jsdw-pipeline-dot--<?php echo esc_attr( $pipe_answering ); ?>" aria-hidden="true"></span>
				<div class="jsdw-pipeline-body">
					<div class="jsdw-pipeline-label"><?php echo esc_html__( 'Answering', 'jsdw-ai-chat' ); ?></div>
					<div class="jsdw-pipeline-summary"><?php echo esc_html( $answering_line ); ?></div>
				</div>
			</li>
		</ul>
	</div>

	<div class="jsdw-card jsdw-dashboard-ai">
		<div class="jsdw-card__title"><?php echo esc_html__( 'AI configuration', 'jsdw-ai-chat' ); ?></div>
		<p class="jsdw-ai-status-row">
			<span class="jsdw-ai-status-pill jsdw-ai-status-pill--<?php echo esc_attr( $ai_sum_sev ); ?>"><?php echo esc_html( '' !== $ai_sum_label ? $ai_sum_label : __( 'Unknown', 'jsdw-ai-chat' ) ); ?></span>
		</p>
		<p class="jsdw-dashboard-ai__detail"><?php echo esc_html( '' !== $ai_sum_detail ? $ai_sum_detail : __( 'No summary available.', 'jsdw-ai-chat' ) ); ?></p>
		<p class="jsdw-dashboard-ai__detail"><a href="<?php echo esc_url( $url_settings ); ?>"><?php echo esc_html__( 'Edit in Settings', 'jsdw-ai-chat' ); ?></a></p>
	</div>

	<div class="jsdw-card jsdw-dashboard-actions">
		<div class="jsdw-card__title"><?php echo esc_html__( 'Quick links', 'jsdw-ai-chat' ); ?></div>
		<div class="jsdw-dashboard-actions__row">
			<a class="button jsdw-dashboard-action" href="<?php echo esc_url( $url_sources ); ?>"><?php echo esc_html__( 'Review sources', 'jsdw-ai-chat' ); ?></a>
			<a class="button jsdw-dashboard-action" href="<?php echo esc_url( $url_jobs ); ?>"><?php echo esc_html__( 'Open jobs & logs', 'jsdw-ai-chat' ); ?></a>
			<a class="button jsdw-dashboard-action" href="<?php echo esc_url( $url_settings ); ?>"><?php echo esc_html__( 'Open plugin settings', 'jsdw-ai-chat' ); ?></a>
		</div>
	</div>

	<p class="jsdw-dashboard-footnote">
		<?php
		echo esc_html(
			sprintf(
				/* translators: 1: plugin version, 2: schema version */
				__( 'Plugin %1$s · Schema %2$s', 'jsdw-ai-chat' ),
				JSDW_AI_CHAT_VERSION,
				'' !== $schema_version ? $schema_version : '—'
			)
		);
		?>
	</p>
</div>
