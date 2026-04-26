<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class JSDW_AI_Chat_Conversation_Service {
	/**
	 * @var JSDW_AI_Chat_DB
	 */
	private $db;

	public function __construct( JSDW_AI_Chat_DB $db ) {
		$this->db = $db;
	}

	/**
	 * @param string $provided_session_key
	 * @param int $user_id
	 * @return array<string,mixed>|null
	 */
	public function get_or_create_conversation( $provided_session_key, $user_id = 0 ) {
		global $wpdb;
		if ( ! ( $wpdb instanceof wpdb ) ) {
			return null;
		}

		$session_key = $this->normalize_session_key( $provided_session_key, absint( $user_id ) );
		$table       = $this->db->get_table_name( 'conversations' );
		$row         = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE session_key = %s LIMIT 1", $session_key ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.NotPrepared
		if ( is_array( $row ) ) {
			return $row;
		}

		$wpdb->insert(
			$table,
			array(
				'session_key'    => $session_key,
				'user_id'        => $user_id > 0 ? $user_id : null,
				'visitor_hash'   => md5( $session_key ),
				'channel'        => 'web',
				'status'         => 'open',
				'started_at'     => current_time( 'mysql', true ),
				'last_active_at' => current_time( 'mysql', true ),
				'created_at'     => current_time( 'mysql', true ),
				'updated_at'     => current_time( 'mysql', true ),
			),
			array( '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		$id = absint( $wpdb->insert_id );
		if ( $id <= 0 ) {
			return null;
		}
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d LIMIT 1", $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.NotPrepared
		return is_array( $row ) ? $row : null;
	}

	/**
	 * @param int $conversation_id
	 * @param string $role
	 * @param array<string,mixed> $data
	 * @return int
	 */
	public function add_message( $conversation_id, $role, array $data ) {
		global $wpdb;
		if ( ! ( $wpdb instanceof wpdb ) ) {
			return 0;
		}
		$messages_table = $this->db->get_table_name( 'messages' );
		$conversations_table = $this->db->get_table_name( 'conversations' );

		$snapshot = isset( $data['source_snapshot_json'] ) ? (string) $data['source_snapshot_json'] : '';
		$wpdb->insert(
			$messages_table,
			array(
				'conversation_id'     => absint( $conversation_id ),
				'role'                => sanitize_key( (string) $role ),
				'message_text'        => isset( $data['message_text'] ) ? (string) $data['message_text'] : null,
				'normalized_message'  => isset( $data['normalized_message'] ) ? (string) $data['normalized_message'] : null,
				'answer_text'         => isset( $data['answer_text'] ) ? (string) $data['answer_text'] : null,
				'answer_status'       => isset( $data['answer_status'] ) ? sanitize_key( (string) $data['answer_status'] ) : null,
				'answer_type'         => isset( $data['answer_type'] ) ? sanitize_key( (string) $data['answer_type'] ) : null,
				'answer_strategy'     => isset( $data['answer_strategy'] ) ? sanitize_key( (string) $data['answer_strategy'] ) : null,
				'ai_used'             => ! empty( $data['used_ai_phrase_assist'] ) ? 'phrase_assist' : null,
				'source_snapshot_json'=> '' !== $snapshot ? $snapshot : null,
				'confidence_score'    => isset( $data['confidence_score'] ) ? (float) $data['confidence_score'] : null,
				'created_at'          => current_time( 'mysql', true ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%f', '%s' )
		);
		$message_id = absint( $wpdb->insert_id );

		$wpdb->update(
			$conversations_table,
			array(
				'last_active_at' => current_time( 'mysql', true ),
				'updated_at'     => current_time( 'mysql', true ),
			),
			array( 'id' => absint( $conversation_id ) ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		return $message_id;
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public function list_conversations( $limit = 50, $offset = 0 ) {
		global $wpdb;
		$conversations_table = $this->db->get_table_name( 'conversations' );
		$messages_table      = $this->db->get_table_name( 'messages' );
		$lim                 = max( 1, min( 200, absint( $limit ) ) );
		$off                 = max( 0, absint( $offset ) );

		$sql = "SELECT c.*,
			( SELECT COUNT(*) FROM {$messages_table} mx WHERE mx.conversation_id = c.id ) AS message_count,
			( SELECT m2.created_at FROM {$messages_table} m2 WHERE m2.conversation_id = c.id ORDER BY m2.created_at DESC, m2.id DESC LIMIT 1 ) AS last_message_at,
			( SELECT m3.role FROM {$messages_table} m3 WHERE m3.conversation_id = c.id ORDER BY m3.created_at DESC, m3.id DESC LIMIT 1 ) AS last_message_role,
			( SELECT
				CASE
					WHEN m4.role = 'assistant' THEN LEFT( COALESCE( NULLIF( TRIM( m4.answer_text ), '' ), TRIM( m4.message_text ) ), 140 )
					ELSE LEFT( COALESCE( NULLIF( TRIM( m4.message_text ), '' ), TRIM( m4.answer_text ) ), 140 )
				END
				FROM {$messages_table} m4 WHERE m4.conversation_id = c.id ORDER BY m4.created_at DESC, m4.id DESC LIMIT 1
			) AS last_preview,
			( SELECT COUNT(*) FROM {$messages_table} mu
				WHERE mu.conversation_id = c.id AND mu.role = 'user'
				AND mu.id > COALESCE( c.last_read_message_id_admin, 0 )
			) AS admin_unread_user_count
			FROM {$conversations_table} c
			ORDER BY c.last_active_at DESC
			LIMIT %d OFFSET %d";
		return (array) $wpdb->get_results( $wpdb->prepare( $sql, $lim, $off ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Set admin read cursor to the latest message id (admin has seen the thread through that id).
	 * New visitor (user) messages with higher ids become unread again.
	 *
	 * @param int $conversation_id Conversation id.
	 * @return bool
	 */
	public function mark_admin_inbox_read( $conversation_id ) {
		global $wpdb;
		if ( ! ( $wpdb instanceof wpdb ) ) {
			return false;
		}
		$id = absint( $conversation_id );
		if ( $id <= 0 ) {
			return false;
		}
		$latest = $this->get_latest_message_id( $id );
		$table  = $this->db->get_table_name( 'conversations' );
		$ok     = $wpdb->update(
			$table,
			array(
				'last_read_message_id_admin' => $latest,
				'updated_at'                 => current_time( 'mysql', true ),
			),
			array( 'id' => $id ),
			array( '%d', '%s' ),
			array( '%d' )
		);
		return false !== $ok;
	}

	/**
	 * Number of conversations with at least one visitor message not yet “seen” by admin (id > last_read_message_id_admin).
	 *
	 * @return int
	 */
	public function count_conversations_admin_unread() {
		global $wpdb;
		if ( ! ( $wpdb instanceof wpdb ) ) {
			return 0;
		}
		$conversations_table = $this->db->get_table_name( 'conversations' );
		$messages_table      = $this->db->get_table_name( 'messages' );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table names from plugin registry.
		$sql = "SELECT COUNT(*) FROM {$conversations_table} c
			WHERE EXISTS (
				SELECT 1 FROM {$messages_table} m
				WHERE m.conversation_id = c.id AND m.role = 'user'
				AND m.id > COALESCE( c.last_read_message_id_admin, 0 )
			)";
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$n = $wpdb->get_var( $sql );
		return $n ? absint( $n ) : 0;
	}

	/**
	 * Primary attention slug for inbox triage (internal, not shown raw to visitors).
	 *
	 * @param array<string,mixed> $row Conversation list row.
	 * @return string closed|unread|live_agent|waiting_on_admin|waiting_on_visitor|neutral
	 */
	public function get_attention_state( array $row ) {
		$status = isset( $row['status'] ) ? (string) $row['status'] : 'open';
		if ( 'closed' === $status ) {
			return 'closed';
		}
		$unread_count = isset( $row['admin_unread_user_count'] ) ? absint( $row['admin_unread_user_count'] ) : 0;
		if ( $unread_count > 0 ) {
			return 'unread';
		}
		if ( ! empty( $row['agent_connected'] ) ) {
			return 'live_agent';
		}
		$lr = isset( $row['last_message_role'] ) ? sanitize_key( (string) $row['last_message_role'] ) : '';
		if ( 'user' === $lr ) {
			return 'waiting_on_admin';
		}
		if ( 'agent' === $lr || 'assistant' === $lr ) {
			return 'waiting_on_visitor';
		}
		return 'neutral';
	}

	/**
	 * Human-readable label for an attention state slug.
	 *
	 * @param string $slug From get_attention_state().
	 * @return string
	 */
	public function attention_state_label( $slug ) {
		switch ( (string) $slug ) {
			case 'closed':
				return __( 'Closed', 'jsdw-ai-chat' );
			case 'unread':
				return __( 'New visitor messages', 'jsdw-ai-chat' );
			case 'live_agent':
				return __( 'Live agent', 'jsdw-ai-chat' );
			case 'waiting_on_admin':
				return __( 'Waiting on you', 'jsdw-ai-chat' );
			case 'waiting_on_visitor':
				return __( 'Waiting on visitor', 'jsdw-ai-chat' );
			default:
				return __( 'Recent activity', 'jsdw-ai-chat' );
		}
	}

	/**
	 * @return array<string,mixed>|null
	 */
	public function get_conversation( $conversation_id ) {
		global $wpdb;
		$table = $this->db->get_table_name( 'conversations' );
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d LIMIT 1", absint( $conversation_id ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.NotPrepared
		return is_array( $row ) ? $row : null;
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public function list_messages( $conversation_id, $limit = 200, $offset = 0 ) {
		global $wpdb;
		$table = $this->db->get_table_name( 'messages' );
		$lim   = max( 1, min( 500, absint( $limit ) ) );
		$off   = max( 0, absint( $offset ) );
		$sql   = "SELECT * FROM {$table} WHERE conversation_id = %d ORDER BY created_at ASC, id ASC LIMIT %d OFFSET %d";
		return (array) $wpdb->get_results( $wpdb->prepare( $sql, absint( $conversation_id ), $lim, $off ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Messages newer than a message id (for session polling).
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function list_messages_since( $conversation_id, $after_message_id, $limit = 100 ) {
		global $wpdb;
		$table = $this->db->get_table_name( 'messages' );
		$lim   = max( 1, min( 200, absint( $limit ) ) );
		$after = max( 0, absint( $after_message_id ) );
		$sql   = "SELECT * FROM {$table} WHERE conversation_id = %d AND id > %d ORDER BY id ASC LIMIT %d";
		return (array) $wpdb->get_results( $wpdb->prepare( $sql, absint( $conversation_id ), $after, $lim ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Store visitor name/email for a conversation (widget). Verifies session_key matches the row.
	 *
	 * @param int    $conversation_id Conversation id.
	 * @param string $session_key     Session key from the widget.
	 * @param string $display_name    Visitor display name.
	 * @param string $email           Visitor email.
	 * @return true|\WP_Error
	 */
	public function update_visitor_identity( $conversation_id, $session_key, $display_name, $email ) {
		global $wpdb;
		if ( ! ( $wpdb instanceof wpdb ) ) {
			return new WP_Error( 'jsdw_ai_chat_db', __( 'Database unavailable.', 'jsdw-ai-chat' ), array( 'status' => 500 ) );
		}
		$id = absint( $conversation_id );
		if ( $id <= 0 ) {
			return new WP_Error( 'jsdw_ai_chat_invalid_conversation', __( 'Invalid conversation.', 'jsdw-ai-chat' ), array( 'status' => 400 ) );
		}
		$sk = sanitize_text_field( (string) $session_key );
		if ( '' === $sk ) {
			return new WP_Error( 'jsdw_ai_chat_bad_session', __( 'Invalid session.', 'jsdw-ai-chat' ), array( 'status' => 400 ) );
		}
		$name = sanitize_text_field( (string) $display_name );
		$name = substr( trim( $name ), 0, 191 );
		$mail = sanitize_email( (string) $email );
		if ( '' === $name ) {
			return new WP_Error( 'jsdw_ai_chat_name_required', __( 'Please enter your name.', 'jsdw-ai-chat' ), array( 'status' => 400 ) );
		}
		if ( '' === $mail || ! is_email( $mail ) ) {
			return new WP_Error( 'jsdw_ai_chat_email_invalid', __( 'Please enter a valid email address.', 'jsdw-ai-chat' ), array( 'status' => 400 ) );
		}
		$table = $this->db->get_table_name( 'conversations' );
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT id, session_key FROM {$table} WHERE id = %d LIMIT 1", $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.NotPrepared
		if ( ! is_array( $row ) || ( isset( $row['session_key'] ) && (string) $row['session_key'] !== $sk ) ) {
			return new WP_Error( 'jsdw_ai_chat_forbidden', __( 'Conversation not found.', 'jsdw-ai-chat' ), array( 'status' => 403 ) );
		}
		$ok = $wpdb->update(
			$table,
			array(
				'visitor_display_name' => $name,
				'visitor_email'        => $mail,
				'updated_at'           => current_time( 'mysql', true ),
			),
			array( 'id' => $id ),
			array( '%s', '%s', '%s' ),
			array( '%d' )
		);
		if ( false === $ok ) {
			return new WP_Error( 'jsdw_ai_chat_save_failed', __( 'Could not save visitor details.', 'jsdw-ai-chat' ), array( 'status' => 500 ) );
		}
		return true;
	}

	/**
	 * @param int  $conversation_id
	 * @param bool $on
	 * @return bool
	 */
	public function set_agent_connected( $conversation_id, $on ) {
		global $wpdb;
		if ( ! ( $wpdb instanceof wpdb ) ) {
			return false;
		}
		$table = $this->db->get_table_name( 'conversations' );
		$ok    = $wpdb->update(
			$table,
			array(
				'agent_connected' => $on ? 1 : 0,
				'updated_at'      => current_time( 'mysql', true ),
				'last_active_at'    => current_time( 'mysql', true ),
			),
			array( 'id' => absint( $conversation_id ) ),
			array( '%d', '%s', '%s' ),
			array( '%d' )
		);
		return false !== $ok;
	}

	/**
	 * Admin/agent reply line (role agent).
	 *
	 * @param int    $conversation_id
	 * @param string $message
	 * @return int Message id or 0.
	 */
	/**
	 * Highest message id for a conversation (for client polling cursors).
	 */
	public function get_latest_message_id( $conversation_id ) {
		global $wpdb;
		if ( ! ( $wpdb instanceof wpdb ) ) {
			return 0;
		}
		$table = $this->db->get_table_name( 'messages' );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$v = $wpdb->get_var( $wpdb->prepare( "SELECT MAX(id) FROM {$table} WHERE conversation_id = %d", absint( $conversation_id ) ) );
		return $v ? absint( $v ) : 0;
	}

	public function add_agent_message( $conversation_id, $message ) {
		$text = sanitize_textarea_field( (string) $message );
		$text = substr( $text, 0, 4000 );
		if ( '' === trim( $text ) ) {
			return 0;
		}
		return $this->add_message(
			absint( $conversation_id ),
			'agent',
			array(
				'message_text' => $text,
			)
		);
	}

	/**
	 * @param string $provided
	 * @param int $user_id
	 * @return string
	 */
	private function normalize_session_key( $provided, $user_id ) {
		$raw = sanitize_text_field( (string) $provided );
		if ( $user_id > 0 ) {
			if ( '' === $raw ) {
				return 'u:' . $user_id . ':default';
			}
			return 'u:' . $user_id . ':' . preg_replace( '/[^a-zA-Z0-9:_-]/', '', $raw );
		}
		if ( '' === $raw ) {
			$raw = wp_generate_uuid4();
		}
		return 'a:' . preg_replace( '/[^a-zA-Z0-9:_-]/', '', $raw );
	}
}
