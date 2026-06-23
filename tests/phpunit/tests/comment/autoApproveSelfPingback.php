<?php

/**
 * @group comment
 * @group pingback
 *
 * @covers ::wp_auto_approve_self_pingback
 */
class Tests_Comment_AutoApproveSelfPingback extends WP_UnitTestCase {

	/**
	 * Post that pingbacks target, used to build a self-pingback source URL.
	 *
	 * @var int
	 */
	public static $post_id;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$post_id = $factory->post->create();
	}

	/**
	 * Builds comment data for a pingback whose source URL points to a local post.
	 *
	 * @param string $comment_type Comment type. Default 'pingback'.
	 * @return array Comment data.
	 */
	private function get_self_pingback_data( $comment_type = 'pingback' ) {
		return array(
			'comment_post_ID'    => self::$post_id,
			'comment_author'     => 'Self Site',
			'comment_author_url' => get_permalink( self::$post_id ),
			'comment_content'    => 'A link from this site.',
			'comment_type'       => $comment_type,
		);
	}

	public function test_approves_pending_self_pingback_when_option_enabled() {
		update_option( 'auto_approve_self_pingbacks', 1 );

		$approved = wp_auto_approve_self_pingback( 0, $this->get_self_pingback_data() );

		$this->assertSame( 1, $approved );
	}

	public function test_leaves_self_pingback_pending_when_option_disabled() {
		update_option( 'auto_approve_self_pingbacks', 0 );

		$approved = wp_auto_approve_self_pingback( 0, $this->get_self_pingback_data() );

		$this->assertSame( 0, $approved );
	}

	public function test_ignores_non_pingback_comment_types() {
		update_option( 'auto_approve_self_pingbacks', 1 );

		$approved = wp_auto_approve_self_pingback( 0, $this->get_self_pingback_data( 'comment' ) );

		$this->assertSame( 0, $approved );
	}

	public function test_does_not_approve_pingback_from_external_url() {
		update_option( 'auto_approve_self_pingbacks', 1 );

		$data                       = $this->get_self_pingback_data();
		$data['comment_author_url'] = 'https://example.org/some-other-post/';

		$approved = wp_auto_approve_self_pingback( 0, $data );

		$this->assertSame( 0, $approved );
	}

	/**
	 * Spam, trash, approved, and error verdicts must never be downgraded or overridden.
	 *
	 * @dataProvider data_non_pending_statuses
	 *
	 * @param mixed $status A non-pending approval status.
	 */
	public function test_does_not_override_non_pending_status( $status ) {
		update_option( 'auto_approve_self_pingbacks', 1 );

		$approved = wp_auto_approve_self_pingback( $status, $this->get_self_pingback_data() );

		$this->assertSame( $status, $approved );
	}

	public function data_non_pending_statuses() {
		return array(
			'already approved' => array( 1 ),
			'spam'             => array( 'spam' ),
			'trash'            => array( 'trash' ),
		);
	}

	public function test_does_not_override_wp_error_status() {
		update_option( 'auto_approve_self_pingbacks', 1 );

		$error    = new WP_Error( 'disallowed', 'Comment is not allowed.' );
		$approved = wp_auto_approve_self_pingback( $error, $this->get_self_pingback_data() );

		$this->assertSame( $error, $approved );
	}

	/**
	 * The callback must be wired to the `pre_comment_approved` filter by default,
	 * so a pending self-pingback is approved through the real filter chain.
	 */
	public function test_filter_is_registered_and_approves_self_pingback() {
		$this->assertSame(
			10,
			has_filter( 'pre_comment_approved', 'wp_auto_approve_self_pingback' ),
			'wp_auto_approve_self_pingback should be hooked to pre_comment_approved at priority 10.'
		);

		update_option( 'auto_approve_self_pingbacks', 1 );

		$approved = apply_filters( 'pre_comment_approved', 0, $this->get_self_pingback_data() );

		$this->assertSame( 1, $approved );
	}
}
