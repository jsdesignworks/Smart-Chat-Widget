<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class JSDW_AI_Chat_Plugin {
	/**
	 * @var JSDW_AI_Chat_Loader
	 */
	private $loader;

	/**
	 * @var JSDW_AI_Chat_Container
	 */
	private $container;

	public function __construct() {
		$this->loader    = new JSDW_AI_Chat_Loader();
		$this->container = new JSDW_AI_Chat_Container();

		$this->register_services();
		$this->define_hooks();
	}

	private function register_services() {
		$db           = new JSDW_AI_Chat_DB();
		$settings     = new JSDW_AI_Chat_Settings();
		$logger       = new JSDW_AI_Chat_Logger( $db, $settings );
		$migrations   = new JSDW_AI_Chat_Migrations( $db, $logger );
		$capabilities = new JSDW_AI_Chat_Capabilities();
		$cron         = new JSDW_AI_Chat_Cron( $logger );
		$jobs         = new JSDW_AI_Chat_Job_Repository( $db, $logger );
		$queue        = new JSDW_AI_Chat_Queue( $jobs, $logger );
		$source_rules = new JSDW_AI_Chat_Source_Rules();
		$source_repo  = new JSDW_AI_Chat_Source_Repository( $db );
		$chunk_repo   = new JSDW_AI_Chat_Chunk_Repository( $db );
		$fact_repo    = new JSDW_AI_Chat_Fact_Repository( $db );
		$source_discovery = new JSDW_AI_Chat_Source_Discovery( $settings, $source_rules, $source_repo );
		$source_registry  = new JSDW_AI_Chat_Source_Registry( $source_discovery, $source_rules, $source_repo, $settings, $logger, $queue );
		$content_normalizer = new JSDW_AI_Chat_Content_Normalizer();
		$content_fingerprint = new JSDW_AI_Chat_Content_Fingerprint();
		$content_comparator  = new JSDW_AI_Chat_Content_State_Comparator();
		$content_builder     = new JSDW_AI_Chat_Source_Content_Builder( $source_repo );
		$content_chunker     = new JSDW_AI_Chat_Content_Chunker();
		$fact_extractor      = new JSDW_AI_Chat_Fact_Extractor();
		$query_normalizer    = new JSDW_AI_Chat_Query_Normalizer();
		$confidence_policy   = new JSDW_AI_Chat_Confidence_Policy();
		$answer_context_builder = new JSDW_AI_Chat_Answer_Context_Builder();
		$knowledge_retriever = new JSDW_AI_Chat_Knowledge_Retriever( $db, $chunk_repo, $fact_repo, $source_repo, $query_normalizer );
		$answer_status_mapper = new JSDW_AI_Chat_Answer_Status_Mapper();
		$canned_responses    = new JSDW_AI_Chat_Canned_Responses();
		$fallback_responses   = new JSDW_AI_Chat_Fallback_Responses( $canned_responses );
		$answer_policy        = new JSDW_AI_Chat_Answer_Policy();
		$local_answer_builder = new JSDW_AI_Chat_Local_Answer_Builder();
		$ai_openai            = new JSDW_AI_Chat_AI_Provider_OpenAI();
		$ai_anthropic         = new JSDW_AI_Chat_AI_Provider_Anthropic();
		$ai_google            = new JSDW_AI_Chat_AI_Provider_Google();
		$ai_provider_client   = new JSDW_AI_Chat_AI_Provider_Client( $ai_openai, $ai_anthropic, $ai_google );
		$ai_phrase_assist     = new JSDW_AI_Chat_AI_Phrase_Assist( $ai_provider_client, $logger );
		$answer_trace         = new JSDW_AI_Chat_Answer_Trace();
		$answer_formatter     = new JSDW_AI_Chat_Answer_Formatter();
		$query_guard          = new JSDW_AI_Chat_Query_Guard();
		$answer_engine        = new JSDW_AI_Chat_Answer_Engine(
			$knowledge_retriever,
			$answer_context_builder,
			$confidence_policy,
			$query_normalizer,
			$answer_policy,
			$answer_status_mapper,
			$local_answer_builder,
			$fallback_responses,
			$ai_phrase_assist,
			$answer_trace
		);
		$conversation_service = new JSDW_AI_Chat_Conversation_Service( $db );
		$chat_service         = new JSDW_AI_Chat_Chat_Service( $settings, $logger, $query_guard, $answer_engine, $answer_formatter, $conversation_service, $fallback_responses );
		$knowledge_processor = new JSDW_AI_Chat_Source_Knowledge_Processor( $source_repo, $source_rules, $settings, $content_builder, $content_normalizer, $content_chunker, $chunk_repo, $fact_extractor, $fact_repo, $logger );
		$content_processor   = new JSDW_AI_Chat_Source_Content_Processor( $source_repo, $source_rules, $settings, $content_builder, $content_normalizer, $content_fingerprint, $content_comparator, $logger, $queue, $chunk_repo );
		$cron->set_dependencies( $queue, $source_registry, $content_processor, $knowledge_processor );
		$health       = new JSDW_AI_Chat_Health( $db, $cron, $logger, $queue, $source_repo, $settings, $chunk_repo, $fact_repo, $conversation_service );
		$rest         = new JSDW_AI_Chat_REST( $health, $queue, $logger, $source_repo, $source_registry, $content_processor, $settings, $knowledge_processor, $chunk_repo, $fact_repo, $chat_service, $conversation_service );
		$widget_renderer = new JSDW_AI_Chat_Widget_Renderer( $settings );
		$widget_renderer->register_hooks();
		$public       = new JSDW_AI_Chat_Public( $settings, $widget_renderer );
		$admin        = new JSDW_AI_Chat_Admin( $health, $settings, $logger, $source_repo, $source_registry, $chunk_repo, $fact_repo, $conversation_service, $queue, $cron );

		$this->container->set( 'db', $db );
		$this->container->set( 'settings', $settings );
		$this->container->set( 'logger', $logger );
		$this->container->set( 'migrations', $migrations );
		$this->container->set( 'capabilities', $capabilities );
		$this->container->set( 'cron', $cron );
		$this->container->set( 'jobs', $jobs );
		$this->container->set( 'queue', $queue );
		$this->container->set( 'source_rules', $source_rules );
		$this->container->set( 'source_repository', $source_repo );
		$this->container->set( 'source_discovery', $source_discovery );
		$this->container->set( 'source_registry', $source_registry );
		$this->container->set( 'content_processor', $content_processor );
		$this->container->set( 'knowledge_processor', $knowledge_processor );
		$this->container->set( 'chunk_repository', $chunk_repo );
		$this->container->set( 'fact_repository', $fact_repo );
		$this->container->set( 'answer_status_mapper', $answer_status_mapper );
		$this->container->set( 'canned_responses', $canned_responses );
		$this->container->set( 'fallback_responses', $fallback_responses );
		$this->container->set( 'answer_policy', $answer_policy );
		$this->container->set( 'local_answer_builder', $local_answer_builder );
		$this->container->set( 'ai_phrase_assist', $ai_phrase_assist );
		$this->container->set( 'answer_trace', $answer_trace );
		$this->container->set( 'answer_formatter', $answer_formatter );
		$this->container->set( 'query_guard', $query_guard );
		$this->container->set( 'answer_engine', $answer_engine );
		$this->container->set( 'conversation_service', $conversation_service );
		$this->container->set( 'chat_service', $chat_service );
		$this->container->set( 'health', $health );
		$this->container->set( 'rest', $rest );
		$this->container->set( 'public', $public );
		$this->container->set( 'admin', $admin );
	}

	private function define_hooks() {
		$rest   = $this->container->get( 'rest' );
		$admin  = $this->container->get( 'admin' );
		$cron   = $this->container->get( 'cron' );
		$db     = $this->container->get( 'db' );
		$mig    = $this->container->get( 'migrations' );
		$logger = $this->container->get( 'logger' );
		$source_registry = $this->container->get( 'source_registry' );
		$public            = $this->container->get( 'public' );

		$this->loader->add_action( 'init', $db, 'init' );
		$this->loader->add_action( 'plugins_loaded', $mig, 'maybe_migrate' );
		$this->loader->add_action( 'init', $cron, 'register_handlers' );
		$this->loader->add_action( 'rest_api_init', $rest, 'register_routes' );
		$this->loader->add_action( 'admin_menu', $admin, 'register_menu' );
		$this->loader->add_action( 'save_post', $source_registry, 'on_save_post', 10, 2 );
		$this->loader->add_action( 'deleted_post', $source_registry, 'on_post_deleted', 10, 1 );
		$this->loader->add_action( 'trashed_post', $source_registry, 'on_post_deleted', 10, 1 );
		$this->loader->add_action( 'untrashed_post', $source_registry, 'on_post_deleted', 10, 1 );
		$this->loader->add_action( 'transition_post_status', $source_registry, 'on_transition_post_status', 10, 3 );
		$this->loader->add_action( 'edited_term', $source_registry, 'on_term_changed', 10, 1 );
		$this->loader->add_action( 'created_term', $source_registry, 'on_term_changed', 10, 1 );
		$this->loader->add_action( 'delete_term', $source_registry, 'on_term_changed', 10, 1 );
		$this->loader->add_action( 'wp_update_nav_menu', $source_registry, 'on_menu_updated', 10, 1 );

		$this->loader->add_action( 'wp_enqueue_scripts', $public, 'enqueue_assets' );

		$this->loader->add_action( 'admin_init', $logger, 'mark_rest_ready' );
	}

	public function run() {
		$this->loader->run();
	}
}
