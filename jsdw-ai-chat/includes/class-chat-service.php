<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class JSDW_AI_Chat_Chat_Service {
	/**
	 * @var JSDW_AI_Chat_Settings
	 */
	private $settings;

	/**
	 * @var JSDW_AI_Chat_Logger
	 */
	private $logger;

	/**
	 * @var JSDW_AI_Chat_Query_Guard
	 */
	private $query_guard;

	/**
	 * @var JSDW_AI_Chat_Answer_Engine
	 */
	private $answer_engine;

	/**
	 * @var JSDW_AI_Chat_Answer_Formatter
	 */
	private $answer_formatter;

	/**
	 * @var JSDW_AI_Chat_Conversation_Service
	 */
	private $conversation_service;

	/**
	 * @var JSDW_AI_Chat_Fallback_Responses
	 */
	private $fallback_responses;

	public function __construct(
		JSDW_AI_Chat_Settings $settings,
		JSDW_AI_Chat_Logger $logger,
		JSDW_AI_Chat_Query_Guard $query_guard,
		JSDW_AI_Chat_Answer_Engine $answer_engine,
		JSDW_AI_Chat_Answer_Formatter $answer_formatter,
		JSDW_AI_Chat_Conversation_Service $conversation_service,
		JSDW_AI_Chat_Fallback_Responses $fallback_responses
	) {
		$this->settings             = $settings;
		$this->logger               = $logger;
		$this->query_guard          = $query_guard;
		$this->answer_engine        = $answer_engine;
		$this->answer_formatter     = $answer_formatter;
		$this->conversation_service = $conversation_service;
		$this->fallback_responses   = $fallback_responses;
	}

	/**
	 * @param string $query
	 * @param string $session_key
	 * @param int $conversation_id
	 * @param bool $debug
	 * @param string $retrieval_context One of JSDW_AI_Chat_Knowledge_Constants::RETRIEVAL_*.
	 * @return array<string,mixed>
	 */
	public function handle_query( $query, $session_key = '', $conversation_id = 0, $debug = false, $retrieval_context = JSDW_AI_Chat_Knowledge_Constants::RETRIEVAL_INTERNAL ) {
		$settings = $this->settings->get_all();
		$this->logger->info( 'chat_query_received', 'Chat query received.' );

		$guard = $this->query_guard->validate( $query, $settings, $session_key );
		if ( ! empty( $guard['rejected'] ) ) {
			$this->logger->warning( 'query_guard_rejected', 'Chat query rejected by guard.', $guard );
		}

		$user_id      = get_current_user_id();
		$conversation = null;
		if ( $conversation_id > 0 ) {
			$conversation = $this->conversation_service->get_conversation( $conversation_id );
		}
		if ( ! is_array( $conversation ) ) {
			$conversation = $this->conversation_service->get_or_create_conversation( $session_key, $user_id );
		} elseif ( $conversation_id > 0 ) {
			$sk     = sanitize_text_field( (string) $session_key );
			$row_sk = isset( $conversation['session_key'] ) ? (string) $conversation['session_key'] : '';
			if ( '' !== $sk && '' !== $row_sk && $sk !== $row_sk ) {
				$conversation = $this->conversation_service->get_or_create_conversation( $session_key, $user_id );
			}
		}

		$storage_on = ! empty( $settings['privacy']['store_conversations'] ) && ! empty( $settings['features']['enable_chat_storage'] ) && is_array( $conversation );
		$agent_on   = $storage_on && ! empty( $conversation['agent_connected'] );

		if ( $agent_on && empty( $guard['rejected'] ) ) {
			$this->conversation_service->add_message(
				absint( $conversation['id'] ),
				'user',
				array(
					'message_text'       => (string) $query,
					'normalized_message' => '',
				)
			);
			$handoff_text = __( 'A live agent has joined the conversation. A team member can reply here when they are available.', 'jsdw-ai-chat' );
			$engine_result = array(
				'query'                  => (string) $query,
				'normalized_query'       => '',
				'response_mode'          => (string) ( $settings['chat']['answer_mode'] ?? 'strict_local_only' ),
				'confidence'             => JSDW_AI_Chat_Knowledge_Constants::CONF_ANSWERABLE_LOCALLY,
				'answer_status'          => JSDW_AI_Chat_Answer_Constants::ANSWER_STATUS_ANSWERED_LOCALLY,
				'answer_text'            => $handoff_text,
				'answer_type'            => JSDW_AI_Chat_Answer_Constants::ANSWER_TYPE_LIVE_AGENT_HANDOFF,
				'answer_strategy'        => JSDW_AI_Chat_Answer_Constants::ANSWER_STRATEGY_LOCAL_TEMPLATE,
				'clarification_question' => '',
				'sources'                => array(),
				'chunks'                 => array(),
				'facts'                  => array(),
				'retrieval_stats'        => array( 'hit_count' => 0, 'best_score' => 0.0, 'has_title_hit' => false ),
				'trace'                  => array( 'live_agent' => true ),
				'snapshot'               => array(
					'query'            => (string) $query,
					'normalized_query' => '',
					'retrieval_stats'  => array(),
					'confidence'       => JSDW_AI_Chat_Knowledge_Constants::CONF_ANSWERABLE_LOCALLY,
					'answer_status'    => JSDW_AI_Chat_Answer_Constants::ANSWER_STATUS_ANSWERED_LOCALLY,
					'sources'          => array(),
					'chunks'           => array(),
					'facts'            => array(),
					'trace'            => array( 'live_agent' => true ),
					'meta'             => array(
						'engine_version' => JSDW_AI_CHAT_DB_SCHEMA_VERSION,
						'timestamp'      => gmdate( 'c' ),
					),
				),
				'generated_at'           => gmdate( 'c' ),
				'used_ai_phrase_assist'  => false,
				'guard'                  => $guard,
			);
			$formatted = $this->answer_formatter->format( $engine_result, $debug );
			$conversation = $this->conversation_service->get_conversation( absint( $conversation['id'] ) );
			update_option( JSDW_AI_CHAT_OPTION_LAST_ANSWER_REQUEST, current_time( 'mysql', true ), false );
			$this->logger->info( 'chat_live_agent_handoff', 'Query handled in live-agent mode (no automated answer).', array( 'conversation_id' => (int) ( $conversation['id'] ?? 0 ) ) );
			return array(
				'conversation'        => $conversation,
				'result'              => $formatted,
				'latest_message_id'   => $this->conversation_service->get_latest_message_id( absint( $conversation['id'] ) ),
			);
		}

		try {
			$engine_result = $this->answer_engine->answer( $query, $settings, $guard, $retrieval_context );
		} catch ( Throwable $throwable ) {
			$this->logger->error(
				'answer_pipeline_failed',
				'Answer pipeline failed.',
				array( 'error' => $throwable->getMessage() )
			);
			$engine_result = array(
				'query'                  => (string) $query,
				'normalized_query'       => '',
				'response_mode'          => (string) ( $settings['chat']['answer_mode'] ?? 'strict_local_only' ),
				'confidence'             => JSDW_AI_Chat_Knowledge_Constants::CONF_REQUIRES_FUTURE_AI_ASSIST,
				'answer_status'          => JSDW_AI_Chat_Answer_Constants::ANSWER_STATUS_FAILED,
				'answer_text'            => $this->fallback_responses->get_text( JSDW_AI_Chat_Answer_Constants::ANSWER_STATUS_FAILED, $settings ),
				'answer_type'            => JSDW_AI_Chat_Answer_Constants::ANSWER_TYPE_ERROR_RESPONSE,
				'answer_strategy'        => JSDW_AI_Chat_Answer_Constants::ANSWER_STRATEGY_FALLBACK_STANDARD,
				'clarification_question' => '',
				'sources'                => array(),
				'chunks'                 => array(),
				'facts'                  => array(),
				'retrieval_stats'        => array( 'hit_count' => 0, 'best_score' => 0.0, 'has_title_hit' => false ),
				'trace'                  => array( 'error' => 'pipeline_failure' ),
				'snapshot'               => array(
					'query'            => (string) $query,
					'normalized_query' => '',
					'retrieval_stats'  => array(),
					'confidence'       => JSDW_AI_Chat_Knowledge_Constants::CONF_REQUIRES_FUTURE_AI_ASSIST,
					'answer_status'    => JSDW_AI_Chat_Answer_Constants::ANSWER_STATUS_FAILED,
					'sources'          => array(),
					'chunks'           => array(),
					'facts'            => array(),
					'trace'            => array( 'error' => 'pipeline_failure' ),
					'meta'             => array(
						'engine_version' => JSDW_AI_CHAT_DB_SCHEMA_VERSION,
						'timestamp'      => gmdate( 'c' ),
					),
				),
				'generated_at'           => gmdate( 'c' ),
				'used_ai_phrase_assist'  => false,
				'guard'                  => $guard,
			);
		}

		$formatted = $this->answer_formatter->format( $engine_result, $debug );

		if ( $storage_on ) {
			$this->conversation_service->add_message(
				absint( $conversation['id'] ),
				'user',
				array(
					'message_text'       => (string) $query,
					'normalized_message' => (string) ( $engine_result['normalized_query'] ?? '' ),
				)
			);

			$snapshot = wp_json_encode( $engine_result['snapshot'] ?? array() );
			$this->conversation_service->add_message(
				absint( $conversation['id'] ),
				'assistant',
				array(
					'answer_text'          => (string) ( $engine_result['answer_text'] ?? '' ),
					'answer_status'        => (string) ( $engine_result['answer_status'] ?? '' ),
					'answer_type'          => (string) ( $engine_result['answer_type'] ?? '' ),
					'answer_strategy'      => (string) ( $engine_result['answer_strategy'] ?? '' ),
					'source_snapshot_json' => false !== $snapshot ? $snapshot : '{}',
					'confidence_score'     => (float) ( $engine_result['retrieval_stats']['best_score'] ?? 0.0 ),
					'used_ai_phrase_assist'=> ! empty( $engine_result['used_ai_phrase_assist'] ),
				)
			);
		}

		update_option( JSDW_AI_CHAT_OPTION_LAST_ANSWER_REQUEST, current_time( 'mysql', true ), false );
		$this->logger->info(
			'answer_engine_completed',
			'Answer engine completed query.',
			array(
				'answer_status' => (string) ( $engine_result['answer_status'] ?? '' ),
				'confidence'    => (string) ( $engine_result['confidence'] ?? '' ),
			)
		);

		$latest_mid = 0;
		if ( is_array( $conversation ) && isset( $conversation['id'] ) ) {
			$latest_mid = $this->conversation_service->get_latest_message_id( absint( $conversation['id'] ) );
		}

		return array(
			'conversation'      => $conversation,
			'result'              => $formatted,
			'latest_message_id'   => $latest_mid,
		);
	}

	public function list_conversations( $limit = 50, $offset = 0 ) {
		return $this->conversation_service->list_conversations( $limit, $offset );
	}

	public function get_conversation( $conversation_id ) {
		return $this->conversation_service->get_conversation( $conversation_id );
	}

	public function list_messages( $conversation_id, $limit = 200, $offset = 0 ) {
		return $this->conversation_service->list_messages( $conversation_id, $limit, $offset );
	}

	/**
	 * @return bool
	 */
	public function chat_storage_enabled() {
		$s = $this->settings->get_all();
		return ! empty( $s['privacy']['store_conversations'] ) && ! empty( $s['features']['enable_chat_storage'] );
	}
}
