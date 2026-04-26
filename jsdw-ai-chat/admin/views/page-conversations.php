<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @param mixed $mysql
 */
$jsdw_fmt_mysql_dt = static function ( $mysql ) {
	if ( ! is_string( $mysql ) || '' === $mysql ) {
		return '—';
	}
	$out = mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $mysql );
	return '' !== $out ? $out : $mysql;
};

$storage_on = ! empty( $storage_on );
$selected_id = isset( $selected_id ) ? absint( $selected_id ) : 0;
$conversations = isset( $conversations ) && is_array( $conversations ) ? $conversations : array();
$selected      = isset( $selected ) && is_array( $selected ) ? $selected : null;
$messages      = isset( $messages ) && is_array( $messages ) ? $messages : array();
$inbox_unread_total = isset( $inbox_unread_total ) ? absint( $inbox_unread_total ) : 0;
$thread_max_message_id = isset( $thread_max_message_id ) ? absint( $thread_max_message_id ) : 0;
$attention_label    = isset( $attention_label ) ? (string) $attention_label : '';

$agent_connected_sel = $selected && ! empty( $selected['agent_connected'] );
$conv_status         = $selected && isset( $selected['status'] ) ? (string) $selected['status'] : '';
$sel_session_key     = $selected && isset( $selected['session_key'] ) ? (string) $selected['session_key'] : '';

$last_msg_role = '';
if ( ! empty( $messages ) ) {
	$last_row = end( $messages );
	if ( is_array( $last_row ) && isset( $last_row['role'] ) ) {
		$last_msg_role = sanitize_key( (string) $last_row['role'] );
	}
}
if ( '' === $last_msg_role ) {
	$activity_line = __( 'No messages yet.', 'jsdw-ai-chat' );
} elseif ( 'user' === $last_msg_role ) {
	$activity_line = __( 'Last message from visitor', 'jsdw-ai-chat' );
} elseif ( 'agent' === $last_msg_role ) {
	$activity_line = $agent_connected_sel
		? __( 'Waiting for visitor — your reply was last', 'jsdw-ai-chat' )
		: __( 'Last message from agent', 'jsdw-ai-chat' );
} elseif ( 'assistant' === $last_msg_role ) {
	$activity_line = __( 'Last message from assistant (automated)', 'jsdw-ai-chat' );
} else {
	$activity_line = __( 'Last message from chat', 'jsdw-ai-chat' );
}
?>
<div class="jsdw-page jsdw-conv-page">
	<h1>
		<?php echo esc_html__( 'Conversations', 'jsdw-ai-chat' ); ?>
		<span class="jsdw-conv-page-unread" id="jsdw-conv-page-unread" <?php echo $inbox_unread_total <= 0 ? 'hidden' : ''; ?>>(<?php echo $inbox_unread_total > 0 ? esc_html( (string) min( 99, $inbox_unread_total ) ) : '0'; ?>)</span>
	</h1>
	<p class="description"><?php echo esc_html__( 'Open a thread to read messages in view-only mode. Use “Join conversation” when you intend to reply as a live agent — then automated answers pause for that thread until you end the session.', 'jsdw-ai-chat' ); ?></p>
	<?php
	jsdw_ai_chat_help_tip(
		'jsdw-conv-help',
		__( 'About live agent mode', 'jsdw-ai-chat' ),
		'<p>' . esc_html__( 'Joining switches that conversation to human-handling: the widget shows a live banner and your replies appear as the agent. If settings require visitor name and email, the visitor must submit the in-widget form before you can join.', 'jsdw-ai-chat' ) . '</p>'
	);
	?>

	<?php if ( ! $storage_on ) : ?>
		<div class="notice notice-warning"><p><?php echo esc_html__( 'Conversation storage is disabled in settings. Enable storage to send agent messages and use live-agent mode.', 'jsdw-ai-chat' ); ?></p></div>
	<?php endif; ?>

	<div
		class="jsdw-conv-layout"
		data-conversation-id="<?php echo esc_attr( (string) ( $selected_id > 0 ? $selected_id : 0 ) ); ?>"
		data-storage-on="<?php echo $storage_on ? '1' : '0'; ?>"
		data-agent-connected="<?php echo ( $selected_id > 0 && $agent_connected_sel ) ? '1' : '0'; ?>"
		<?php if ( $selected_id > 0 ) : ?>
		data-last-message-id="<?php echo esc_attr( (string) $thread_max_message_id ); ?>"
		<?php endif; ?>
	>
		<aside class="jsdw-conv-sidebar" aria-label="<?php echo esc_attr__( 'Conversation list', 'jsdw-ai-chat' ); ?>">
			<div class="jsdw-conv-sidebar__head" id="jsdw-conv-inbox-head">
				<?php echo esc_html__( 'Inbox', 'jsdw-ai-chat' ); ?>
				<span class="jsdw-conv-inbox-badge" id="jsdw-conv-inbox-badge" <?php echo $inbox_unread_total <= 0 ? 'hidden' : ''; ?>><?php echo $inbox_unread_total > 0 ? esc_html( (string) min( 99, $inbox_unread_total ) ) : '0'; ?></span>
			</div>
			<ul class="jsdw-conv-list" id="jsdw-conv-list">
				<?php if ( empty( $conversations ) ) : ?>
					<li class="jsdw-conv-list__item"><span class="jsdw-conv-list__link" style="cursor:default;"><?php echo esc_html__( 'No conversations yet.', 'jsdw-ai-chat' ); ?></span></li>
				<?php else : ?>
					<?php foreach ( $conversations as $row ) : ?>
						<?php
						if ( ! is_array( $row ) ) {
							continue;
						}
						$cid   = isset( $row['id'] ) ? absint( $row['id'] ) : 0;
						$active = $cid === $selected_id;
						$last_role = isset( $row['last_message_role'] ) ? (string) $row['last_message_role'] : '';
						$uc        = isset( $row['admin_unread_user_count'] ) ? absint( $row['admin_unread_user_count'] ) : 0;
						$unread    = $uc > 0;
						$live      = ! empty( $row['agent_connected'] );
						$att_lbl   = isset( $row['attention_label'] ) ? (string) $row['attention_label'] : '';
						$preview = isset( $row['last_preview'] ) ? (string) $row['last_preview'] : '';
						if ( '' === $preview ) {
							$preview = '—';
						}
						$uid = isset( $row['user_id'] ) && $row['user_id'] ? (string) $row['user_id'] : '';
						$sk  = isset( $row['session_key'] ) ? (string) $row['session_key'] : '';
						$vname = isset( $row['visitor_display_name'] ) ? trim( (string) $row['visitor_display_name'] ) : '';
						$title = '' !== $vname
							? $vname
							: ( '' !== $uid
								? sprintf( /* translators: %s: user id */ __( 'User %s', 'jsdw-ai-chat' ), $uid )
								: ( '' !== $sk ? wp_trim_words( $sk, 4, '…' ) : '#' . (string) $cid ) );
						$last_at = isset( $row['last_message_at'] ) ? $row['last_message_at'] : ( $row['last_active_at'] ?? '' );
						?>
						<li class="jsdw-conv-list__item">
							<a class="jsdw-conv-list__link<?php echo $active ? ' is-active' : ''; ?><?php echo $unread ? ' jsdw-conv-list__link--unread' : ''; ?><?php echo $live ? ' jsdw-conv-list__link--live' : ''; ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=jsdw-ai-chat-conversations&conversation_id=' . $cid ) ); ?>" data-conversation-id="<?php echo esc_attr( (string) $cid ); ?>" data-unread-count="<?php echo esc_attr( (string) $uc ); ?>" data-live="<?php echo $live ? '1' : '0'; ?>">
								<div class="jsdw-conv-list__title">
									<?php if ( $unread ) : ?>
										<span class="jsdw-conv-list__dot" aria-hidden="true"></span>
										<?php if ( $uc > 1 ) : ?>
											<span class="jsdw-conv-list__unread-count" aria-label="<?php echo esc_attr__( 'Unread visitor messages', 'jsdw-ai-chat' ); ?>"><?php echo esc_html( $uc > 9 ? '9+' : (string) min( 9, $uc ) ); ?></span>
										<?php endif; ?>
									<?php endif; ?>
									<?php if ( $live ) : ?>
										<span class="jsdw-conv-list__live"><?php echo esc_html__( 'Live', 'jsdw-ai-chat' ); ?></span>
									<?php endif; ?>
									<?php echo esc_html( $title ); ?>
								</div>
								<div class="jsdw-conv-list__preview"><?php echo esc_html( $preview ); ?></div>
								<div class="jsdw-conv-list__meta"><?php echo esc_html( $jsdw_fmt_mysql_dt( $last_at ) ); ?> · #<?php echo esc_html( (string) $cid ); ?></div>
								<?php if ( '' !== $att_lbl ) : ?>
									<div class="jsdw-conv-list__attention"><?php echo esc_html( $att_lbl ); ?></div>
								<?php endif; ?>
							</a>
						</li>
					<?php endforeach; ?>
				<?php endif; ?>
			</ul>
		</aside>

		<section class="jsdw-conv-main" aria-label="<?php echo esc_attr__( 'Thread', 'jsdw-ai-chat' ); ?>">
			<?php if ( ! is_array( $selected ) || $selected_id <= 0 ) : ?>
				<div class="jsdw-conv-main__empty">
					<p><?php echo esc_html__( 'Select a conversation to view messages and reply as an agent.', 'jsdw-ai-chat' ); ?></p>
				</div>
			<?php else : ?>
				<div class="jsdw-conv-toolbar" id="jsdw-conv-toolbar">
					<div class="jsdw-conv-toolbar__info">
						<div class="jsdw-conv-toolbar__pills">
							<span class="jsdw-conv-pill jsdw-conv-pill--<?php echo 'closed' === $conv_status ? 'closed' : 'open'; ?>"><?php echo esc_html( '' !== $conv_status ? $conv_status : __( 'open', 'jsdw-ai-chat' ) ); ?></span>
							<span class="jsdw-conv-pill jsdw-conv-pill--viewing" id="jsdw-conv-pill-viewing" <?php echo $agent_connected_sel ? 'hidden' : ''; ?>><?php echo esc_html__( 'Viewing only', 'jsdw-ai-chat' ); ?></span>
							<span class="jsdw-conv-pill jsdw-conv-pill--agent" id="jsdw-conv-pill-agent" <?php echo $agent_connected_sel ? '' : 'hidden'; ?>><?php echo esc_html__( 'Agent connected', 'jsdw-ai-chat' ); ?></span>
						</div>
						<div class="jsdw-conv-toolbar__activity" id="jsdw-conv-activity"><?php echo esc_html( $activity_line ); ?></div>
						<div class="jsdw-conv-toolbar__attention" id="jsdw-conv-attention"><?php echo esc_html( $attention_label ); ?></div>
						<div class="jsdw-conv-toolbar__session"><?php echo esc_html( $sel_session_key ); ?></div>
					</div>
					<div class="jsdw-conv-toolbar__actions">
						<button type="button" class="button button-primary" id="jsdw-conv-join" <?php echo ( ! $storage_on || $agent_connected_sel ) ? 'hidden' : ''; ?>><?php echo esc_html__( 'Join conversation', 'jsdw-ai-chat' ); ?></button>
						<button type="button" class="button" id="jsdw-conv-release" <?php echo ! $agent_connected_sel ? 'disabled' : ''; ?>><?php echo esc_html__( 'End agent session', 'jsdw-ai-chat' ); ?></button>
					</div>
				</div>

				<div class="jsdw-conv-tools">
					<label><input type="checkbox" id="jsdw-conv-debug-toggle" /> <?php echo esc_html__( 'Show technical details', 'jsdw-ai-chat' ); ?></label>
				</div>

				<div class="jsdw-conv-thread" id="jsdw-conv-thread">
					<?php foreach ( $messages as $msg ) : ?>
						<?php
						if ( ! is_array( $msg ) ) {
							continue;
						}
						$role = isset( $msg['role'] ) ? sanitize_key( (string) $msg['role'] ) : '';
						$t    = $jsdw_fmt_mysql_dt( $msg['created_at'] ?? '' );
						if ( 'user' === $role ) {
							$body = isset( $msg['message_text'] ) ? (string) $msg['message_text'] : '';
							$mid  = isset( $msg['id'] ) ? absint( $msg['id'] ) : 0;
							?>
							<div class="jsdw-conv-bubble jsdw-conv-bubble--user"<?php echo $mid > 0 ? ' data-message-id="' . esc_attr( (string) $mid ) . '"' : ''; ?>>
								<div class="jsdw-conv-bubble__label"><?php echo esc_html__( 'Visitor', 'jsdw-ai-chat' ); ?></div>
								<div><?php echo esc_html( $body ); ?></div>
								<div class="jsdw-conv-bubble__time"><?php echo esc_html( $t ); ?></div>
							</div>
							<?php
							continue;
						}
						if ( 'agent' === $role ) {
							$body = isset( $msg['message_text'] ) ? (string) $msg['message_text'] : '';
							$mid  = isset( $msg['id'] ) ? absint( $msg['id'] ) : 0;
							?>
							<div class="jsdw-conv-bubble jsdw-conv-bubble--agent"<?php echo $mid > 0 ? ' data-message-id="' . esc_attr( (string) $mid ) . '"' : ''; ?>>
								<div class="jsdw-conv-bubble__label"><?php echo esc_html__( 'Agent', 'jsdw-ai-chat' ); ?></div>
								<div><?php echo esc_html( $body ); ?></div>
								<div class="jsdw-conv-bubble__time"><?php echo esc_html( $t ); ?></div>
							</div>
							<?php
							continue;
						}
						if ( 'assistant' === $role ) {
							$body = isset( $msg['answer_text'] ) ? (string) $msg['answer_text'] : '';
							if ( '' === $body && isset( $msg['message_text'] ) ) {
								$body = (string) $msg['message_text'];
							}
							$ast = isset( $msg['answer_status'] ) ? (string) $msg['answer_status'] : '';
							$cf  = isset( $msg['confidence_score'] ) && null !== $msg['confidence_score'] && '' !== (string) $msg['confidence_score'] ? (string) $msg['confidence_score'] : '';
							$mid = isset( $msg['id'] ) ? absint( $msg['id'] ) : 0;
							?>
							<div class="jsdw-conv-bubble jsdw-conv-bubble--assistant"<?php echo $mid > 0 ? ' data-message-id="' . esc_attr( (string) $mid ) . '"' : ''; ?>>
								<div class="jsdw-conv-bubble__label"><?php echo esc_html__( 'Assistant', 'jsdw-ai-chat' ); ?></div>
								<div><?php echo esc_html( $body ); ?></div>
								<div class="jsdw-conv-bubble__time"><?php echo esc_html( $t ); ?></div>
								<?php if ( '' !== $ast || '' !== $cf ) : ?>
									<div class="jsdw-conv-bubble__debug">
										<?php if ( '' !== $ast ) : ?>
											<div><?php echo esc_html__( 'Answer status:', 'jsdw-ai-chat' ); ?> <?php echo esc_html( $ast ); ?></div>
										<?php endif; ?>
										<?php if ( '' !== $cf ) : ?>
											<div><?php echo esc_html__( 'Confidence:', 'jsdw-ai-chat' ); ?> <?php echo esc_html( $cf ); ?></div>
										<?php endif; ?>
									</div>
								<?php endif; ?>
							</div>
							<?php
							continue;
						}
						$fallback = isset( $msg['message_text'] ) ? (string) $msg['message_text'] : '';
						if ( '' === $fallback && isset( $msg['answer_text'] ) ) {
							$fallback = (string) $msg['answer_text'];
						}
						$mid_fb = isset( $msg['id'] ) ? absint( $msg['id'] ) : 0;
						?>
						<div class="jsdw-conv-bubble jsdw-conv-bubble--assistant"<?php echo $mid_fb > 0 ? ' data-message-id="' . esc_attr( (string) $mid_fb ) . '"' : ''; ?>>
							<div class="jsdw-conv-bubble__label"><?php echo esc_html( $role ? $role : __( 'Message', 'jsdw-ai-chat' ) ); ?></div>
							<div><?php echo esc_html( $fallback ); ?></div>
							<div class="jsdw-conv-bubble__time"><?php echo esc_html( $t ); ?></div>
						</div>
					<?php endforeach; ?>
				</div>

				<div class="jsdw-conv-composer" id="jsdw-conv-composer">
					<?php if ( $storage_on ) : ?>
						<p class="jsdw-conv-composer__hint description" id="jsdw-conv-composer-hint" <?php echo $agent_connected_sel ? 'hidden' : ''; ?>><?php echo esc_html__( 'Join conversation to reply as a live agent. Once connected, automated replies will pause for this conversation.', 'jsdw-ai-chat' ); ?></p>
						<div class="jsdw-conv-composer__row">
							<label class="screen-reader-text" for="jsdw-conv-input"><?php echo esc_html__( 'Agent message', 'jsdw-ai-chat' ); ?></label>
							<textarea id="jsdw-conv-input" class="large-text" rows="2" placeholder="<?php echo esc_attr__( 'Type a reply to the visitor…', 'jsdw-ai-chat' ); ?>" <?php echo ! $agent_connected_sel ? 'disabled' : ''; ?>></textarea>
							<button type="button" class="button button-primary" id="jsdw-conv-send" <?php echo ! $agent_connected_sel ? 'disabled' : ''; ?>><?php echo esc_html__( 'Send', 'jsdw-ai-chat' ); ?></button>
						</div>
					<?php else : ?>
						<p class="description"><?php echo esc_html__( 'Enable conversation storage to send messages.', 'jsdw-ai-chat' ); ?></p>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</section>
	</div>
</div>
