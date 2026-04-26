<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class JSDW_AI_Chat_REST {
	/**
	 * @var JSDW_AI_Chat_Health
	 */
	private $health;

	/**
	 * @var JSDW_AI_Chat_Queue
	 */
	private $queue;

	/**
	 * @var JSDW_AI_Chat_Logger
	 */
	private $logger;

	/**
	 * @var JSDW_AI_Chat_Source_Repository
	 */
	private $source_repository;

	/**
	 * @var JSDW_AI_Chat_Source_Registry
	 */
	private $source_registry;

	/**
	 * @var JSDW_AI_Chat_Source_Content_Processor
	 */
	private $content_processor;

	/**
	 * @var JSDW_AI_Chat_Settings
	 */
	private $settings;

	/**
	 * @var JSDW_AI_Chat_Source_Knowledge_Processor
	 */
	private $knowledge_processor;

	/**
	 * @var JSDW_AI_Chat_Chunk_Repository
	 */
	private $chunk_repository;

	/**
	 * @var JSDW_AI_Chat_Fact_Repository
	 */
	private $fact_repository;

	/**
	 * @var JSDW_AI_Chat_Chat_Service
	 */
	private $chat_service;

	/**
	 * @var JSDW_AI_Chat_Conversation_Service
	 */
	private $conversation_service;

	public function __construct( JSDW_AI_Chat_Health $health, JSDW_AI_Chat_Queue $queue, JSDW_AI_Chat_Logger $logger, JSDW_AI_Chat_Source_Repository $source_repository, JSDW_AI_Chat_Source_Registry $source_registry, JSDW_AI_Chat_Source_Content_Processor $content_processor, JSDW_AI_Chat_Settings $settings, JSDW_AI_Chat_Source_Knowledge_Processor $knowledge_processor, JSDW_AI_Chat_Chunk_Repository $chunk_repository, JSDW_AI_Chat_Fact_Repository $fact_repository, JSDW_AI_Chat_Chat_Service $chat_service, JSDW_AI_Chat_Conversation_Service $conversation_service ) {
		$this->health = $health;
		$this->queue  = $queue;
		$this->logger = $logger;
		$this->source_repository = $source_repository;
		$this->source_registry   = $source_registry;
		$this->content_processor = $content_processor;
		$this->settings          = $settings;
		$this->knowledge_processor = $knowledge_processor;
		$this->chunk_repository    = $chunk_repository;
		$this->fact_repository     = $fact_repository;
		$this->chat_service           = $chat_service;
		$this->conversation_service = $conversation_service;
	}

	public function register_routes() {
		register_rest_route(
			'ai-chat-widget/v1',
			'/health',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'health' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			'ai-chat-widget/v1',
			'/status',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'status' ),
				'permission_callback' => array( $this, 'admin_permission' ),
			)
		);

		register_rest_route(
			'ai-chat-widget/v1',
			'/test',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'test' ),
				'permission_callback' => array( $this, 'admin_permission' ),
			)
		);

		register_rest_route(
			'ai-chat-widget/v1',
			'/sources',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'sources' ),
				'permission_callback' => array( $this, 'admin_permission' ),
			)
		);

		register_rest_route(
			'ai-chat-widget/v1',
			'/sources/process-content',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'sources_process_content' ),
				'permission_callback' => array( $this, 'admin_permission' ),
			)
		);

		register_rest_route(
			'ai-chat-widget/v1',
			'/sources/process-content-single',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'sources_process_content_single' ),
				'permission_callback' => array( $this, 'admin_permission' ),
			)
		);

		register_rest_route(
			'ai-chat-widget/v1',
			'/sources/(?P<id>[\d]+)/content-state',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'source_content_state' ),
				'permission_callback' => array( $this, 'admin_permission' ),
				'args'                => array(
					'id' => array(
						'validate_callback' => 'is_numeric',
					),
				),
			)
		);

		register_rest_route(
			'ai-chat-widget/v1',
			'/sources/(?P<id>[\d]+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'source_by_id' ),
				'permission_callback' => array( $this, 'admin_permission' ),
				'args'                => array(
					'id' => array(
						'validate_callback' => 'is_numeric',
					),
				),
			)
		);

		register_rest_route(
			'ai-chat-widget/v1',
			'/sources/rescan',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'sources_rescan' ),
				'permission_callback' => array( $this, 'admin_permission' ),
			)
		);

		register_rest_route(
			'ai-chat-widget/v1',
			'/sources/sync-single',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'sources_sync_single' ),
				'permission_callback' => array( $this, 'admin_permission' ),
				'args'                => array(
					'post_id' => array(
						'required'          => true,
						'validate_callback' => 'is_numeric',
					),
				),
			)
		);

		register_rest_route(
			'ai-chat-widget/v1',
			'/settings/widget-design',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_widget_design' ),
					'permission_callback' => array( $this, 'settings_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'save_widget_design' ),
					'permission_callback' => array( $this, 'settings_permission' ),
				),
			)
		);

		register_rest_route(
			'ai-chat-widget/v1',
			'/sources/(?P<id>[\d]+)/chunks',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'source_chunks' ),
				'permission_callback' => array( $this, 'admin_permission' ),
				'args'                => array(
					'id' => array( 'validate_callback' => 'is_numeric' ),
				),
			)
		);

		register_rest_route(
			'ai-chat-widget/v1',
			'/sources/(?P<id>[\d]+)/facts',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'source_facts' ),
				'permission_callback' => array( $this, 'admin_permission' ),
				'args'                => array(
					'id' => array( 'validate_callback' => 'is_numeric' ),
				),
			)
		);

		register_rest_route(
			'ai-chat-widget/v1',
			'/sources/process-knowledge',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'sources_process_knowledge' ),
				'permission_callback' => array( $this, 'admin_permission' ),
			)
		);

		register_rest_route(
			'ai-chat-widget/v1',
			'/sources/process-knowledge-single',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'sources_process_knowledge_single' ),
				'permission_callback' => array( $this, 'admin_permission' ),
			)
		);

		register_rest_route(
			'ai-chat-widget/v1',
			'/retrieval/test',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'retrieval_test' ),
				'permission_callback' => array( $this, 'admin_permission' ),
			)
		);

		register_rest_route(
			'ai-chat-widget/v1',
			'/chat/query',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'chat_query' ),
				'permission_callback' => array( $this, 'chat_query_permission' ),
			)
		);

		register_rest_route(
			'ai-chat-widget/v1',
			'/chat/visitor-identity',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'chat_visitor_identity' ),
				'permission_callback' => array( $this, 'chat_query_permission' ),
			)
		);

		register_rest_route(
			'ai-chat-widget/v1',
			'/chat/query-debug',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'chat_query_debug' ),
				'permission_callback' => array( $this, 'admin_permission' ),
			)
		);

		register_rest_route(
			'ai-chat-widget/v1',
			'/chat/conversations',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'chat_conversations' ),
				'permission_callback' => array( $this, 'conversations_permission' ),
			)
		);

		register_rest_route(
			'ai-chat-widget/v1',
			'/chat/conversations/(?P<id>[\d]+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'chat_conversation' ),
				'permission_callback' => array( $this, 'conversations_permission' ),
			)
		);

		register_rest_route(
			'ai-chat-widget/v1',
			'/chat/conversations/(?P<id>[\d]+)/messages',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'chat_conversation_messages' ),
				'permission_callback' => array( $this, 'conversations_permission' ),
			)
		);

		register_rest_route(
			'ai-chat-widget/v1',
			'/chat/conversations/(?P<id>[\d]+)/mark-read',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'chat_conversation_mark_read' ),
				'permission_callback' => array( $this, 'conversations_permission' ),
			)
		);

		register_rest_route(
			'ai-chat-widget/v1',
			'/chat/inbox-summary',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'chat_inbox_summary' ),
				'permission_callback' => array( $this, 'conversations_permission' ),
			)
		);

		register_rest_route(
			'ai-chat-widget/v1',
			'/chat/agent-join',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'chat_agent_join' ),
				'permission_callback' => array( $this, 'conversations_permission' ),
			)
		);

		register_rest_route(
			'ai-chat-widget/v1',
			'/chat/agent-reply',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'chat_agent_reply' ),
				'permission_callback' => array( $this, 'conversations_permission' ),
			)
		);

		register_rest_route(
			'ai-chat-widget/v1',
			'/chat/agent-release',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'chat_agent_release' ),
				'permission_callback' => array( $this, 'conversations_permission' ),
			)
		);

		register_rest_route(
			'ai-chat-widget/v1',
			'/chat/session-messages',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'chat_session_messages' ),
				'permission_callback' => array( $this, 'chat_query_permission' ),
				'args'                => array(
					'conversation_id' => array(
						'required'          => true,
						'validate_callback' => static function ( $v ) {
							return absint( $v ) > 0;
						},
					),
					'session_key'       => array(
						'required' => true,
						'type'     => 'string',
					),
					'since_id'          => array(
						'default' => 0,
						'type'    => 'integer',
					),
				),
			)
		);

		$this->logger->info( 'rest_registered', 'REST routes registered.' );
	}

	public function admin_permission( WP_REST_Request $request ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		if ( current_user_can( 'manage_ai_chat_widget' ) ) {
			return true;
		}

		return new WP_Error(
			'jsdw_ai_chat_forbidden',
			__( 'You do not have permission to access this route.', 'jsdw-ai-chat' ),
			array( 'status' => rest_authorization_required_code() )
		);
	}

	public function settings_permission( WP_REST_Request $request ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		return current_user_can( 'manage_ai_chat_widget_settings' )
			|| current_user_can( 'manage_options' );
	}

	public function get_widget_design() {
		$all = $this->settings->get_all();
		$wd  = isset( $all['widget_design'] ) && is_array( $all['widget_design'] ) ? $all['widget_design'] : $this->settings->get_default_widget_design();
		return rest_ensure_response(
			array(
				'ok'   => true,
				'data' => array( 'widget_design' => $wd ),
			)
		);
	}

	public function save_widget_design( WP_REST_Request $request ) {
		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = array();
		}
		$incoming = isset( $params['widget_design'] ) && is_array( $params['widget_design'] ) ? $params['widget_design'] : array();
		$all      = $this->settings->get_all();
		$current  = isset( $all['widget_design'] ) && is_array( $all['widget_design'] ) ? $all['widget_design'] : array();
		$all['widget_design'] = wp_parse_args( $incoming, $current );

		$incoming_wu = isset( $params['widget_ui'] ) && is_array( $params['widget_ui'] ) ? $params['widget_ui'] : array();
		if ( array_key_exists( 'widget_ui', $params ) && is_array( $params['widget_ui'] ) ) {
			$wu_base = isset( $all['widget_ui'] ) && is_array( $all['widget_ui'] ) ? $all['widget_ui'] : $this->settings->get_default_widget_ui();
			$all['widget_ui'] = wp_parse_args( $incoming_wu, $wu_base );
		}

		$clean = $this->settings->sanitize_settings( $all );
		update_option( JSDW_AI_CHAT_OPTION_SETTINGS, $clean, false );
		$this->logger->info( 'widget_design_saved', 'Widget design settings saved via REST.' );
		return rest_ensure_response(
			array(
				'ok'   => true,
				'data' => array(
					'widget_design' => $clean['widget_design'],
					'widget_ui'     => isset( $clean['widget_ui'] ) && is_array( $clean['widget_ui'] ) ? $clean['widget_ui'] : $this->settings->get_default_widget_ui(),
				),
			)
		);
	}

	public function health() {
		if ( current_user_can( 'manage_ai_chat_widget' ) ) {
			return rest_ensure_response(
				array(
					'ok'   => true,
					'data' => $this->health->get_report(),
				)
			);
		}
		return rest_ensure_response(
			array(
				'ok'   => true,
				'data' => $this->health->get_public_ping(),
			)
		);
	}

	public function status() {
		return rest_ensure_response(
			array(
				'ok'   => true,
				'data' => array(
					'health' => $this->health->get_report(),
					'queue'  => $this->queue->get_status(),
				),
			)
		);
	}

	public function test() {
		return rest_ensure_response(
			array(
				'ok'      => true,
				'message' => 'JSDW AI Chat test route is available.',
				'time'    => current_time( 'mysql', true ),
			)
		);
	}

	public function sources( WP_REST_Request $request ) {
		$source_type = sanitize_text_field( (string) $request->get_param( 'source_type' ) );
		$status      = sanitize_text_field( (string) $request->get_param( 'status' ) );
		$limit       = absint( $request->get_param( 'limit' ) );
		$offset      = absint( $request->get_param( 'offset' ) );
		if ( $limit <= 0 ) {
			$limit = 50;
		}

		$filters = array();
		if ( '' !== $source_type ) {
			$filters['source_type'] = $source_type;
		}
		if ( '' !== $status ) {
			$filters['status'] = $status;
		}

		return rest_ensure_response(
			array(
				'ok'   => true,
				'data' => $this->source_repository->list_sources( $filters, $limit, $offset ),
			)
		);
	}

	public function source_by_id( WP_REST_Request $request ) {
		$source = $this->source_repository->get_source_by_id( absint( $request->get_param( 'id' ) ) );
		if ( ! is_array( $source ) ) {
			return new WP_Error( 'jsdw_ai_chat_not_found', __( 'Source not found.', 'jsdw-ai-chat' ), array( 'status' => 404 ) );
		}
		return rest_ensure_response( array( 'ok' => true, 'data' => $source ) );
	}

	public function sources_rescan() {
		$this->source_registry->queue_full_scan();
		return rest_ensure_response(
			array(
				'ok'      => true,
				'message' => 'Source rescan queued.',
			)
		);
	}

	public function sources_sync_single( WP_REST_Request $request ) {
		$post_id = absint( $request->get_param( 'post_id' ) );
		if ( $post_id <= 0 ) {
			return new WP_Error( 'jsdw_ai_chat_invalid_post_id', __( 'Invalid post ID.', 'jsdw-ai-chat' ), array( 'status' => 400 ) );
		}
		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			return new WP_Error( 'jsdw_ai_chat_post_not_found', __( 'Post not found.', 'jsdw-ai-chat' ), array( 'status' => 404 ) );
		}
		$this->source_registry->queue_single_post( $post_id );
		return rest_ensure_response(
			array(
				'ok'      => true,
				'message' => 'Source single-sync queued.',
				'post_id' => $post_id,
			)
		);
	}

	public function source_content_state( WP_REST_Request $request ) {
		$source = $this->source_repository->get_source_by_id( absint( $request->get_param( 'id' ) ) );
		if ( ! is_array( $source ) ) {
			return new WP_Error( 'jsdw_ai_chat_not_found', __( 'Source not found.', 'jsdw-ai-chat' ), array( 'status' => 404 ) );
		}
		$keys = array(
			'id',
			'content_hash',
			'title_hash',
			'structure_hash',
			'metadata_hash',
			'last_content_check_gmt',
			'last_content_change_gmt',
			'normalized_length',
			'extraction_method',
			'content_processing_status',
			'content_processing_reason',
			'material_content_change',
			'content_version',
			'needs_reindex',
		);
		$out = array();
		foreach ( $keys as $k ) {
			if ( isset( $source[ $k ] ) ) {
				$out[ $k ] = $source[ $k ];
			}
		}
		return rest_ensure_response( array( 'ok' => true, 'data' => $out ) );
	}

	public function sources_process_content( WP_REST_Request $request ) {
		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = array();
		}
		$ids = isset( $params['source_ids'] ) && is_array( $params['source_ids'] ) ? $params['source_ids'] : array();
		$ids = array_filter( array_map( 'absint', $ids ) );
		if ( empty( $ids ) ) {
			return new WP_Error( 'jsdw_ai_chat_invalid_body', __( 'Provide source_ids array.', 'jsdw-ai-chat' ), array( 'status' => 400 ) );
		}
		$queued = array();
		foreach ( $ids as $sid ) {
			if ( $sid <= 0 ) {
				continue;
			}
			$jid = $this->queue->add_job( JSDW_AI_Chat_Job_Repository::TYPE_SOURCE_CONTENT_PROCESS, array( 'source_id' => $sid ), 14 );
			if ( $jid > 0 ) {
				$queued[] = $sid;
				$this->logger->info( 'content_queued', 'Content process job queued via REST.', array( 'source_id' => $sid, 'job_id' => $jid ) );
			}
		}
		return rest_ensure_response(
			array(
				'ok'       => true,
				'message'  => 'Content processing jobs queued.',
				'queued'   => $queued,
			)
		);
	}

	public function sources_process_content_single( WP_REST_Request $request ) {
		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = array();
		}
		$source_id = isset( $params['source_id'] ) ? absint( $params['source_id'] ) : 0;
		if ( $source_id <= 0 ) {
			return new WP_Error( 'jsdw_ai_chat_invalid_source_id', __( 'Invalid source_id.', 'jsdw-ai-chat' ), array( 'status' => 400 ) );
		}
		$jid = $this->queue->add_job( JSDW_AI_Chat_Job_Repository::TYPE_SOURCE_CONTENT_PROCESS, array( 'source_id' => $source_id ), 14 );
		$this->logger->info( 'content_queued', 'Content process job queued via REST (single).', array( 'source_id' => $source_id, 'job_id' => $jid ) );
		return rest_ensure_response(
			array(
				'ok'        => true,
				'message'   => 'Content processing job queued.',
				'source_id' => $source_id,
				'job_id'    => $jid,
			)
		);
	}

	public function source_chunks( WP_REST_Request $request ) {
		$sid = absint( $request->get_param( 'id' ) );
		if ( ! is_array( $this->source_repository->get_source_by_id( $sid ) ) ) {
			return new WP_Error( 'jsdw_ai_chat_not_found', __( 'Source not found.', 'jsdw-ai-chat' ), array( 'status' => 404 ) );
		}
		$rows = $this->chunk_repository->get_active_chunks_by_source( $sid );
		return rest_ensure_response( array( 'ok' => true, 'data' => $rows ) );
	}

	public function source_facts( WP_REST_Request $request ) {
		$sid = absint( $request->get_param( 'id' ) );
		if ( ! is_array( $this->source_repository->get_source_by_id( $sid ) ) ) {
			return new WP_Error( 'jsdw_ai_chat_not_found', __( 'Source not found.', 'jsdw-ai-chat' ), array( 'status' => 404 ) );
		}
		$rows = $this->fact_repository->get_active_facts_by_source( $sid );
		return rest_ensure_response( array( 'ok' => true, 'data' => $rows ) );
	}

	public function sources_process_knowledge( WP_REST_Request $request ) {
		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = array();
		}
		$ids = isset( $params['source_ids'] ) && is_array( $params['source_ids'] ) ? $params['source_ids'] : array();
		$ids = array_filter( array_map( 'absint', $ids ) );
		if ( empty( $ids ) ) {
			return new WP_Error( 'jsdw_ai_chat_invalid_body', __( 'Provide source_ids array.', 'jsdw-ai-chat' ), array( 'status' => 400 ) );
		}
		$queued = array();
		foreach ( $ids as $sid ) {
			if ( $sid <= 0 ) {
				continue;
			}
			$jid = $this->queue->add_job( JSDW_AI_Chat_Job_Repository::TYPE_SOURCE_KNOWLEDGE_PROCESS, array( 'source_id' => $sid ), 14 );
			if ( $jid > 0 ) {
				$queued[] = $sid;
				$this->logger->info( 'knowledge_processing_queued', 'Knowledge job queued via REST.', array( 'source_id' => $sid, 'job_id' => $jid ) );
			}
		}
		return rest_ensure_response( array( 'ok' => true, 'message' => 'Knowledge jobs queued.', 'queued' => $queued ) );
	}

	public function sources_process_knowledge_single( WP_REST_Request $request ) {
		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = array();
		}
		$source_id = isset( $params['source_id'] ) ? absint( $params['source_id'] ) : 0;
		if ( $source_id <= 0 ) {
			return new WP_Error( 'jsdw_ai_chat_invalid_source_id', __( 'Invalid source_id.', 'jsdw-ai-chat' ), array( 'status' => 400 ) );
		}
		$jid = $this->queue->add_job( JSDW_AI_Chat_Job_Repository::TYPE_SOURCE_KNOWLEDGE_PROCESS, array( 'source_id' => $source_id ), 14 );
		$this->logger->info( 'knowledge_processing_queued', 'Knowledge job queued via REST (single).', array( 'source_id' => $source_id, 'job_id' => $jid ) );
		return rest_ensure_response( array( 'ok' => true, 'message' => 'Knowledge job queued.', 'source_id' => $source_id, 'job_id' => $jid ) );
	}

	public function retrieval_test( WP_REST_Request $request ) {
		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = array();
		}
		$query = isset( $params['query'] ) ? (string) $params['query'] : '';
		if ( '' === trim( $query ) ) {
			return new WP_Error( 'jsdw_ai_chat_invalid_query', __( 'Provide query string.', 'jsdw-ai-chat' ), array( 'status' => 400 ) );
		}
		$response = $this->chat_service->handle_query( $query, (string) ( $params['session_key'] ?? '' ), 0, true, JSDW_AI_Chat_Knowledge_Constants::RETRIEVAL_INTERNAL );
		$this->logger->info( 'retrieval_test_executed', 'Retrieval test.' );
		return rest_ensure_response(
			array(
				'ok' => true,
				'data' => $response['result'],
			)
		);
	}

	public function chat_query_permission( WP_REST_Request $request ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		if ( current_user_can( 'manage_ai_chat_widget' ) ) {
			return true;
		}
		$all = $this->settings->get_all();
		$allow_public = ! empty( $all['chat']['allow_public_query_endpoint'] );
		if ( ! $allow_public ) {
			return new WP_Error( 'jsdw_ai_chat_public_query_disabled', __( 'Public query endpoint is disabled.', 'jsdw-ai-chat' ), array( 'status' => 403 ) );
		}
		$nonce = isset( $_SERVER['HTTP_X_WP_NONCE'] ) ? sanitize_text_field( (string) $_SERVER['HTTP_X_WP_NONCE'] ) : '';
		if ( '' !== $nonce && wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return true;
		}
		return new WP_Error( 'jsdw_ai_chat_forbidden', __( 'Missing or invalid nonce.', 'jsdw-ai-chat' ), array( 'status' => 403 ) );
	}

	public function conversations_permission( WP_REST_Request $request ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		if ( current_user_can( 'manage_ai_chat_widget_conversations' ) ) {
			return true;
		}
		return new WP_Error(
			'jsdw_ai_chat_forbidden',
			__( 'You do not have permission to access conversation data.', 'jsdw-ai-chat' ),
			array( 'status' => rest_authorization_required_code() )
		);
	}

	public function chat_query( WP_REST_Request $request ) {
		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = array();
		}
		$query = isset( $params['query'] ) ? sanitize_textarea_field( (string) $params['query'] ) : '';
		if ( '' === trim( $query ) ) {
			return new WP_Error( 'jsdw_ai_chat_invalid_query', __( 'Provide query string.', 'jsdw-ai-chat' ), array( 'status' => 400 ) );
		}
		$session_key = isset( $params['session_key'] ) ? sanitize_text_field( (string) $params['session_key'] ) : '';
		$conversation_id = isset( $params['conversation_id'] ) ? absint( $params['conversation_id'] ) : 0;
		$retrieval_context = current_user_can( 'manage_ai_chat_widget' )
			? JSDW_AI_Chat_Knowledge_Constants::RETRIEVAL_INTERNAL
			: JSDW_AI_Chat_Knowledge_Constants::RETRIEVAL_PUBLIC_SAFE;
		$response = $this->chat_service->handle_query( $query, $session_key, $conversation_id, false, $retrieval_context );

		return rest_ensure_response(
			array(
				'ok'   => true,
				'data' => array(
					'conversation'        => $response['conversation'],
					'result'              => $response['result'],
					'latest_message_id'   => isset( $response['latest_message_id'] ) ? absint( $response['latest_message_id'] ) : 0,
				),
			)
		);
	}

	public function chat_query_debug( WP_REST_Request $request ) {
		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = array();
		}
		$query = isset( $params['query'] ) ? sanitize_textarea_field( (string) $params['query'] ) : '';
		if ( '' === trim( $query ) ) {
			return new WP_Error( 'jsdw_ai_chat_invalid_query', __( 'Provide query string.', 'jsdw-ai-chat' ), array( 'status' => 400 ) );
		}
		$session_key = isset( $params['session_key'] ) ? sanitize_text_field( (string) $params['session_key'] ) : '';
		$conversation_id = isset( $params['conversation_id'] ) ? absint( $params['conversation_id'] ) : 0;
		$response = $this->chat_service->handle_query( $query, $session_key, $conversation_id, true, JSDW_AI_Chat_Knowledge_Constants::RETRIEVAL_ADMIN_DEBUG );

		return rest_ensure_response( array( 'ok' => true, 'data' => $response ) );
	}

	public function chat_conversations( WP_REST_Request $request ) {
		$limit  = absint( $request->get_param( 'limit' ) );
		$offset = absint( $request->get_param( 'offset' ) );
		$data   = $this->chat_service->list_conversations( $limit > 0 ? $limit : 50, $offset );
		return rest_ensure_response( array( 'ok' => true, 'data' => $data ) );
	}

	public function chat_conversation( WP_REST_Request $request ) {
		$id   = absint( $request->get_param( 'id' ) );
		$data = $this->chat_service->get_conversation( $id );
		if ( ! is_array( $data ) ) {
			return new WP_Error( 'jsdw_ai_chat_not_found', __( 'Conversation not found.', 'jsdw-ai-chat' ), array( 'status' => 404 ) );
		}
		return rest_ensure_response( array( 'ok' => true, 'data' => $data ) );
	}

	public function chat_conversation_messages( WP_REST_Request $request ) {
		$id     = absint( $request->get_param( 'id' ) );
		$since  = absint( $request->get_param( 'since_id' ) );
		$limit  = absint( $request->get_param( 'limit' ) );
		$offset = absint( $request->get_param( 'offset' ) );
		if ( $since > 0 ) {
			$lim  = $limit > 0 ? min( 200, max( 1, $limit ) ) : 100;
			$data = $this->conversation_service->list_messages_since( $id, $since, $lim );
		} else {
			$data = $this->chat_service->list_messages( $id, $limit > 0 ? $limit : 200, $offset );
		}
		return rest_ensure_response( array( 'ok' => true, 'data' => $data ) );
	}

	/**
	 * Mark conversation read for admin inbox (cursor = latest message id).
	 */
	public function chat_conversation_mark_read( WP_REST_Request $request ) {
		$id = absint( $request->get_param( 'id' ) );
		if ( $id <= 0 ) {
			return new WP_Error( 'jsdw_ai_chat_invalid_conversation', __( 'Invalid conversation.', 'jsdw-ai-chat' ), array( 'status' => 400 ) );
		}
		$conv = $this->conversation_service->get_conversation( $id );
		if ( ! is_array( $conv ) ) {
			return new WP_Error( 'jsdw_ai_chat_not_found', __( 'Conversation not found.', 'jsdw-ai-chat' ), array( 'status' => 404 ) );
		}
		$this->conversation_service->mark_admin_inbox_read( $id );
		$conv = $this->conversation_service->get_conversation( $id );
		return rest_ensure_response( array( 'ok' => true, 'data' => array( 'conversation' => $conv ) ) );
	}

	/**
	 * Lightweight inbox state for admin polling (unread counts + attention labels).
	 */
	public function chat_inbox_summary() {
		$unread_total = $this->conversation_service->count_conversations_admin_unread();
		$rows         = $this->conversation_service->list_conversations( 100, 0 );
		$items        = array();
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$cid = isset( $row['id'] ) ? absint( $row['id'] ) : 0;
			if ( $cid <= 0 ) {
				continue;
			}
			$uc = isset( $row['admin_unread_user_count'] ) ? absint( $row['admin_unread_user_count'] ) : 0;
			$st = $this->conversation_service->get_attention_state( $row );
			$items[] = array(
				'id'                      => $cid,
				'last_preview'            => isset( $row['last_preview'] ) ? (string) $row['last_preview'] : '',
				'last_message_at'         => isset( $row['last_message_at'] ) ? (string) $row['last_message_at'] : '',
				'last_message_role'       => isset( $row['last_message_role'] ) ? (string) $row['last_message_role'] : '',
				'admin_unread'            => $uc > 0,
				'admin_unread_user_count' => $uc,
				'agent_connected'         => ! empty( $row['agent_connected'] ),
				'attention_state'         => $st,
				'attention_label'         => $this->conversation_service->attention_state_label( $st ),
			);
		}
		return rest_ensure_response(
			array(
				'ok'   => true,
				'data' => array(
					'unread_total'    => $unread_total,
					'conversations'   => $items,
				),
			)
		);
	}

	/**
	 * Public widget: save visitor name/email on a conversation (session_key required).
	 */
	public function chat_visitor_identity( WP_REST_Request $request ) {
		if ( ! $this->chat_service->chat_storage_enabled() ) {
			return new WP_Error(
				'jsdw_ai_chat_storage_off',
				__( 'Conversation storage is disabled.', 'jsdw-ai-chat' ),
				array( 'status' => 400 )
			);
		}
		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = array();
		}
		$id   = isset( $params['conversation_id'] ) ? absint( $params['conversation_id'] ) : 0;
		$sk   = isset( $params['session_key'] ) ? sanitize_text_field( (string) $params['session_key'] ) : '';
		$name = isset( $params['visitor_name'] ) ? (string) $params['visitor_name'] : '';
		$mail = isset( $params['visitor_email'] ) ? (string) $params['visitor_email'] : '';
		$ok   = $this->conversation_service->update_visitor_identity( $id, $sk, $name, $mail );
		if ( is_wp_error( $ok ) ) {
			return $ok;
		}
		$conv = $this->conversation_service->get_conversation( $id );
		return rest_ensure_response( array( 'ok' => true, 'data' => array( 'conversation' => $conv ) ) );
	}

	/**
	 * Admin joins as live agent (sets agent_connected; does not post a message).
	 */
	public function chat_agent_join( WP_REST_Request $request ) {
		if ( ! $this->chat_service->chat_storage_enabled() ) {
			return new WP_Error(
				'jsdw_ai_chat_storage_off',
				__( 'Conversation storage is disabled; live-agent mode is unavailable.', 'jsdw-ai-chat' ),
				array( 'status' => 400 )
			);
		}
		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = array();
		}
		$id = isset( $params['conversation_id'] ) ? absint( $params['conversation_id'] ) : 0;
		if ( $id <= 0 ) {
			return new WP_Error( 'jsdw_ai_chat_invalid_conversation', __( 'Invalid conversation.', 'jsdw-ai-chat' ), array( 'status' => 400 ) );
		}
		$conv = $this->conversation_service->get_conversation( $id );
		if ( ! is_array( $conv ) ) {
			return new WP_Error( 'jsdw_ai_chat_not_found', __( 'Conversation not found.', 'jsdw-ai-chat' ), array( 'status' => 404 ) );
		}
		$settings = $this->settings->get_all();
		if ( ! empty( $settings['chat']['require_visitor_identity_for_handoff'] ) ) {
			$vn = isset( $conv['visitor_display_name'] ) ? trim( (string) $conv['visitor_display_name'] ) : '';
			$ve = isset( $conv['visitor_email'] ) ? trim( (string) $conv['visitor_email'] ) : '';
			if ( '' === $vn || '' === $ve || ! is_email( $ve ) ) {
				return new WP_Error(
					'jsdw_visitor_identity_required',
					__( 'The visitor must share their name and email before you can join as a live agent. Ask them to complete the form in the chat widget.', 'jsdw-ai-chat' ),
					array( 'status' => 400, 'code' => 'visitor_identity_required' )
				);
			}
		}
		$this->conversation_service->set_agent_connected( $id, true );
		$conv = $this->conversation_service->get_conversation( $id );
		return rest_ensure_response( array( 'ok' => true, 'data' => array( 'conversation' => $conv ) ) );
	}

	/**
	 * Admin posts an agent line into a conversation.
	 */
	public function chat_agent_reply( WP_REST_Request $request ) {
		if ( ! $this->chat_service->chat_storage_enabled() ) {
			return new WP_Error(
				'jsdw_ai_chat_storage_off',
				__( 'Conversation storage is disabled; agent replies cannot be saved.', 'jsdw-ai-chat' ),
				array( 'status' => 400 )
			);
		}
		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = array();
		}
		$id  = isset( $params['conversation_id'] ) ? absint( $params['conversation_id'] ) : 0;
		$msg = isset( $params['message'] ) ? sanitize_textarea_field( (string) $params['message'] ) : '';
		if ( $id <= 0 ) {
			return new WP_Error( 'jsdw_ai_chat_invalid_conversation', __( 'Invalid conversation.', 'jsdw-ai-chat' ), array( 'status' => 400 ) );
		}
		if ( '' === trim( $msg ) ) {
			return new WP_Error( 'jsdw_ai_chat_empty_message', __( 'Message cannot be empty.', 'jsdw-ai-chat' ), array( 'status' => 400 ) );
		}
		$conv = $this->conversation_service->get_conversation( $id );
		if ( ! is_array( $conv ) ) {
			return new WP_Error( 'jsdw_ai_chat_not_found', __( 'Conversation not found.', 'jsdw-ai-chat' ), array( 'status' => 404 ) );
		}
		if ( empty( $conv['agent_connected'] ) ) {
			return new WP_Error(
				'jsdw_ai_chat_not_connected',
				__( 'Join the conversation before sending a reply.', 'jsdw-ai-chat' ),
				array( 'status' => 409 )
			);
		}
		$mid = $this->conversation_service->add_agent_message( $id, $msg );
		if ( $mid <= 0 ) {
			return new WP_Error( 'jsdw_ai_chat_save_failed', __( 'Could not save message.', 'jsdw-ai-chat' ), array( 'status' => 500 ) );
		}
		$this->conversation_service->mark_admin_inbox_read( $id );
		$messages = $this->conversation_service->list_messages( $id, 500, 0 );
		$conv     = $this->conversation_service->get_conversation( $id );
		return rest_ensure_response(
			array(
				'ok'   => true,
				'data' => array(
					'conversation' => $conv,
					'messages'     => $messages,
					'message_id'   => $mid,
				),
			)
		);
	}

	/**
	 * Clear live-agent mode for a conversation.
	 */
	public function chat_agent_release( WP_REST_Request $request ) {
		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = array();
		}
		$id = isset( $params['conversation_id'] ) ? absint( $params['conversation_id'] ) : 0;
		if ( $id <= 0 ) {
			return new WP_Error( 'jsdw_ai_chat_invalid_conversation', __( 'Invalid conversation.', 'jsdw-ai-chat' ), array( 'status' => 400 ) );
		}
		$conv = $this->conversation_service->get_conversation( $id );
		if ( ! is_array( $conv ) ) {
			return new WP_Error( 'jsdw_ai_chat_not_found', __( 'Conversation not found.', 'jsdw-ai-chat' ), array( 'status' => 404 ) );
		}
		$this->conversation_service->set_agent_connected( $id, false );
		$conv = $this->conversation_service->get_conversation( $id );
		return rest_ensure_response( array( 'ok' => true, 'data' => array( 'conversation' => $conv ) ) );
	}

	/**
	 * Session-bound poll for new messages (public widget).
	 */
	public function chat_session_messages( WP_REST_Request $request ) {
		$id  = absint( $request->get_param( 'conversation_id' ) );
		$sk  = sanitize_text_field( (string) $request->get_param( 'session_key' ) );
		$since = max( 0, absint( $request->get_param( 'since_id' ) ) );
		if ( $id <= 0 || '' === $sk ) {
			return new WP_Error( 'jsdw_ai_chat_bad_request', __( 'Invalid parameters.', 'jsdw-ai-chat' ), array( 'status' => 400 ) );
		}
		$conv = $this->conversation_service->get_conversation( $id );
		if ( ! is_array( $conv ) || ( isset( $conv['session_key'] ) && (string) $conv['session_key'] !== $sk ) ) {
			return new WP_Error( 'jsdw_ai_chat_forbidden', __( 'Conversation not found.', 'jsdw-ai-chat' ), array( 'status' => 403 ) );
		}
		$rows = $this->conversation_service->list_messages_since( $id, $since, 100 );
		$out  = array();
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$out[] = array(
				'id'           => isset( $row['id'] ) ? absint( $row['id'] ) : 0,
				'role'         => isset( $row['role'] ) ? sanitize_key( (string) $row['role'] ) : '',
				'message_text' => isset( $row['message_text'] ) ? (string) $row['message_text'] : '',
				'answer_text'  => isset( $row['answer_text'] ) ? (string) $row['answer_text'] : '',
				'created_at'   => isset( $row['created_at'] ) ? (string) $row['created_at'] : '',
			);
		}
		return rest_ensure_response(
			array(
				'ok'   => true,
				'data' => array(
					'messages'          => $out,
					'agent_connected'   => ! empty( $conv['agent_connected'] ),
				),
			)
		);
	}
}
