<?php declare(strict_types=1);

namespace Tribe\Plugin;

/**
 * Class Tribe_Glomar_Admin
 */
class Tribe_Glomar_Admin {

	public const SLUG = 'tribe_glomar_settings';

	public function add_hooks() {
		if ( is_multisite() ) {
			add_action( 'network_admin_menu', [ $this, 'register_admin_page' ], 10, 0 );
			add_action( 'network_admin_edit_' . self::SLUG, [ $this, 'save_network_admin_page' ], 10, 0 );
		} else {
			add_action( 'admin_menu', [ $this, 'register_admin_page' ], 10, 0 );
		}
	}

	public function register_admin_page() {
		add_submenu_page(
			is_multisite() ? 'settings.php' : 'options-general.php',
			esc_html__( 'Glomar', 'tribe-glomar' ),
			esc_html__( 'Glomar', 'tribe-glomar' ),
			is_multisite() ? 'manage_network' : 'manage_options',
			self::SLUG,
			[ $this, 'display_admin_page' ]
		);

		add_settings_section(
			'glomar-settings',
			esc_html__( 'Access Settings', 'tribe-glomar' ),
			'__return_false',
			self::SLUG
		);

		add_settings_field(
			'glomar-ip-whitelist',
			esc_html__( 'Permitted IP Address', 'tribe-glomar' ),
			[ $this, 'display_ip_field' ],
			self::SLUG,
			'glomar-settings'
		);

		add_settings_field(
			'glomar-secret',
			esc_html__( 'Secret URL Parameter', 'tribe-glomar' ),
			[ $this, 'display_secret_field' ],
			self::SLUG,
			'glomar-settings'
		);

		add_settings_field(
			'glomar-action',
			esc_html__( 'Redirect the user to', 'tribe-glomar' ),
			[ $this, 'display_action_field' ],
			self::SLUG,
			'glomar-settings'
		);

		add_settings_field(
			'glomar-message',
			esc_html__( 'Message to display to users.', 'tribe-glomar' ),
			[ $this, 'display_message_field' ],
			self::SLUG,
			'glomar-settings'
		);

		if ( is_multisite() ) {
			return;
		}

		register_setting(
			self::SLUG,
			'glomar-ip-whitelist',
			[ $this, 'sanitize_ip_list' ]
		);

		register_setting(
			self::SLUG,
			'glomar-secret',
			[ $this, 'sanitize_secret' ]
		);

		register_setting(
			self::SLUG,
			'glomar-action',
			[ $this, 'sanitize_action' ]
		);

		register_setting(
			self::SLUG,
			'glomar-message',
			[ $this, 'sanitize_message' ]
		);
	}

	public function display_admin_page() {
		$title = __( 'Glomar Settings', 'tribe-glomar' );
		if ( is_multisite() ) {
			$action = network_admin_url( 'edit.php?action=' . self::SLUG );
		} else {
			$action = admin_url( 'options.php' );
		}
		ob_start();
		echo "<form action='" . $action . "' method='post'>";
		$this->settings_fields();
		do_settings_sections( self::SLUG );
		submit_button();
		echo '</form>';
		$content = ob_get_clean();
		require_once 'views/settings-page-wrapper.php';
	}

	private function settings_fields() {
		if ( is_multisite() ) {
			echo '<input type="hidden" name="action" value="' . self::SLUG . '" />';
			wp_nonce_field( self::SLUG . '-options' );
		} else {
			settings_fields( self::SLUG );
		}
	}

	public function save_network_admin_page() {
		// settings API doesn't work at the network level, so we save it ourselves
		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], self::SLUG . '-options' ) ) {
			return;
		}

		$this->save_ip_field();
		$this->save_secret_field();
		$this->save_action_field();
		$this->save_message_field();

		wp_redirect(
			add_query_arg(
				[
					'page'    => self::SLUG,
					'updated' => 'true',
				],
				network_admin_url( 'settings.php' )
			)
		);
		exit;
	}

	/******** IP Address Field ********/

	public function display_ip_field() {
		$ip = implode( "\n", $this->allowed_ip_addresses() );
		printf( '<textarea name="%s" rows="6" cols="30">%s</textarea>', 'glomar-ip-whitelist', esc_textarea( $ip ) );
		echo '<p class="description">';
		_e( 'Include one IP address per line. Only logged in users will be able to view the site from other IP addresses.', 'tribe-glomar' );
		if ( isset( $_SERVER['REMOTE_ADDR'] ) ) {
			printf( ' ' . __( 'Your current IP address is <code>%s</code>.', 'tribe-glomar' ), $_SERVER['REMOTE_ADDR'] );
		}
		echo '</p>';
	}

	private function save_ip_field() {
		if ( ! isset( $_POST['glomar-ip-whitelist'] ) ) {
			return;
		}

		$addresses = $this->sanitize_ip_list( $_POST['glomar-ip-whitelist'] );
		$this->set_option( 'glomar-ip-whitelist', $addresses );
	}

	public function sanitize_ip_list( $list ): array {
		if ( empty( $list ) ) {
			return [];
		}

		if ( is_string( $list ) ) {
			$list = explode( "\n", (string) $list );
		}

		$save = [];

		foreach ( $list as $a ) {
			$a = trim( $a );
			if ( ! preg_match( '!^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$!', $a ) ) {
				continue;
			}

			$save[] = $a;
		}

		return $save;
	}

	public function allowed_ip_addresses(): array {
		return (array) $this->get_option( 'glomar-ip-whitelist', [ '127.0.0.1' ] );
	}

	/******** Secret Field ********/

	public function display_secret_field() {
		$secret = $this->get_secret();
		printf( '<input name="%s" value="%s">', 'glomar-secret', $secret );
		printf( '<p class="description">%s</p>', __( 'Enter an optional secret string that can be added to a url to bypass glomar.', 'tribe-glomar' ) );
	}

	public function display_action_field() {
		$saved_action = $this->get_action();
		$actions      = $this->get_possible_actions();

		echo '<select name="glomar-action">';

		foreach ( $actions as $action => $label ) {
			printf( '<option value="%s" %s>%s</option>', $action, selected( $saved_action, $action, false ), $label );
		}

		echo '</select>';
	}

	/**
	 * Field for custom message.
	 *
	 * @return void
	 */
	public function display_message_field(): void {
		wp_editor(
			$this->get_message(),
			'glomar-message',
			[
				'media_buttons' => true,
				'textarea_name' => 'glomar-message',
				'textarea_rows' => 12,
				'tinymce'       => true,
				'quicktags'     => apply_filters(
					'glomar_message_editor_quicktags',
					[
						'buttons' => 'strong,em,link,block,del,ins,img,ol,ul,li,code,close', // This is default list minus the 'more' tag button.
					],
				),
			]
		);
	}

	public function get_possible_actions(): array {
		// Give a chance to individual sites to implement their own actions.
		return apply_filters(
			'tribe_glomar_actions',
			[
				'glomar' => esc_html__( 'Glomar Template', 'tribe-glomar' ),
				'login'  => esc_html__( 'Login Page', 'tribe-glomar' ),
			]
		);
	}

	private function save_secret_field(): void {
		if ( empty( $_POST['glomar-secret'] ) ) {
			return;
		}

		$secret = $this->sanitize_secret( $_POST['glomar-secret'] );
		$this->set_option( 'glomar-secret', $secret );
	}

	private function save_action_field() {
		if ( ! isset( $_POST['glomar-action'] ) ) {
			return;
		}

		$action = $this->sanitize_action( $_POST['glomar-action'] );
		$this->set_option( 'glomar-action', $action );
	}

	private function save_message_field() {
		if ( ! isset( $_POST['glomar-message'] ) ) {
			return;
		}

		$message = $this->sanitize_message( $_POST['glomar-message'] );
		$this->set_option( 'glomar-message', $message );
	}

	public function sanitize_secret( $secret ): string {
		$secret = sanitize_title( $secret );

		return $secret;
	}

	public function sanitize_action( $action ) {
		$actions = $this->get_possible_actions();

		if ( ! array_key_exists( $action, $actions ) ) {
			$action = 'glomar';
		}

		return $action;
	}

	public function sanitize_message( $message ): string {
		return wp_kses_post( $message );
	}

	public function get_secret(): string {
		$secret = (string) $this->get_option( 'glomar-secret' );

		if ( empty( $secret ) ) {
			$secret = 'secret';
		}

		return $secret;
	}

	/**
	 * @return string
	 */
	public function get_action(): string {
		$action = (string) $this->get_option( 'glomar-action' );

		if ( empty( $action ) ) {
			$action = 'glomar';
		}

		return $action;
	}

	/**
	 * Returns the frontend messaging for Glomar.
	 *
	 * @return string Frontend messaging.
	 */
	public function get_message(): string {
		$message = (string) $this->get_option( 'glomar-message' );

		// Set the default message if a custom message is not set.
		if ( empty( $message ) ) {
			$message =
			'<h1>' . __( 'Access Denied', 'tribe' ) . '</h1>' .
			'<p>' . sprintf(
				wp_kses_post(
					__( 'You are not allowed to access this site. Please <a href="%s" title="Login">login</a> or request access from an administrator', 'tribe' )
				),
				wp_login_url()
			)
			. '</p>';
		}

		return $message;
	}

	/******** Options Management ********/

	private function get_option( $option, $default = false ) {
		if ( is_multisite() ) {
			return get_site_option( $option, $default );
		}

		return get_option( $option, $default );
	}

	private function set_option( $option, $value ) {
		if ( is_multisite() ) {
			update_site_option( $option, $value );
		} else {
			update_option( $option, $value );
		}
	}

}
