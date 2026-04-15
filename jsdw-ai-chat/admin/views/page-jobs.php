<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$r = is_array( $health_report ) ? $health_report : array();
$disc = isset( $r['discovery'] ) && is_array( $r['discovery'] ) ? $r['discovery'] : array();
$qcnt = isset( $disc['queue_counts'] ) && is_array( $disc['queue_counts'] ) ? $disc['queue_counts'] : array();
?>
<div class="jsdw-page">
	<h1><?php echo esc_html__( 'Jobs & Logs', 'jsdw-ai-chat' ); ?></h1>
	<p><?php echo esc_html__( 'Queue status and discovery job counts.', 'jsdw-ai-chat' ); ?></p>

	<div class="postbox" style="max-width:720px;">
		<h2 class="hndle"><?php echo esc_html__( 'Queue', 'jsdw-ai-chat' ); ?></h2>
		<div class="inside">
			<table class="widefat striped">
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

	<h2><?php echo esc_html__( 'Discovery queue counts', 'jsdw-ai-chat' ); ?></h2>
	<?php if ( empty( $qcnt ) ) : ?>
		<p class="description"><?php echo esc_html__( 'No queued discovery jobs reported.', 'jsdw-ai-chat' ); ?></p>
	<?php else : ?>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php echo esc_html__( 'Job type / key', 'jsdw-ai-chat' ); ?></th>
					<th><?php echo esc_html__( 'Count', 'jsdw-ai-chat' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $qcnt as $qk => $qv ) : ?>
					<tr>
						<td><code><?php echo esc_html( (string) $qk ); ?></code></td>
						<td><?php echo esc_html( (string) $qv ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
