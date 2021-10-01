<?php
/**
 * The public-facing functionality of the plugin.
 *
 * @link       https://www.expresstechsoftwares.com
 * @since      1.0.0
 *
 * @package    Memberpress_Discord
 * @subpackage Memberpress_Discord/public
 */

class Memberpress_Discord_Public {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param    string $plugin_name       The name of the plugin.
	 * @param    string $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version     = $version;

	}

	/**
	 * Register the stylesheets for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {

		wp_enqueue_style( $this->plugin_name . 'public_css', plugin_dir_url( __FILE__ ) . 'css/memberpress-discord-public.min.css', array(), $this->version, 'all' );

	}

	/**
	 * Register the JavaScript for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {

		wp_enqueue_script( $this->plugin_name . 'public_js', plugin_dir_url( __FILE__ ) . 'js/memberpress-discord-public.min.js', array( 'jquery' ), $this->version, false );
		$script_params = array(
			'admin_ajax'                           => admin_url( 'admin-ajax.php' ),
			'permissions_const'                    => MEMBERPRESS_DISCORD_BOT_PERMISSIONS,
			'ets_memberpress_discord_public_nonce' => wp_create_nonce( 'ets-memberpress-discord-public-ajax-nonce' ),
		);

		wp_localize_script( $this->plugin_name . 'public_js', 'etsMemberpresspublicParams', $script_params );

	}

	/**
	 * Add discord connection buttons.
	 *
	 * @since    1.0.0
	 */
	public function ets_memberpress_discord_add_connect_button() {
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( 'Unauthorized user', 401 );
			exit();
		}
		$user_id                              = sanitize_text_field( trim( get_current_user_id() ) );
		$access_token                         = sanitize_text_field( trim( get_user_meta( $user_id, '_ets_memberpress_discord_access_token', true ) ) );
		$allow_none_member                    = sanitize_text_field( trim( get_option( 'ets_memberpress_allow_none_member' ) ) );
		$default_role                         = sanitize_text_field( trim( get_option( 'ets_memberpress_discord_default_role_id' ) ) );
		$ets_memberpress_discord_role_mapping = json_decode( get_option( 'ets_memberpress_discord_role_mapping' ), true );
		$all_roles                            = json_decode( get_option( 'ets_memberpress_discord_all_roles' ), true );
		$active_memberships                   = ets_memberpress_discord_get_active_memberships( $user_id );
		$mapped_role_names                    = array();
		if ( $active_memberships && is_array( $all_roles ) ) {
			foreach ( $active_memberships as $active_membership ) {
				if ( is_array( $ets_memberpress_discord_role_mapping ) && array_key_exists( 'level_id_' . $active_membership->product_id, $ets_memberpress_discord_role_mapping ) ) {
					$mapped_role_id = $ets_memberpress_discord_role_mapping[ 'level_id_' . $active_membership->product_id ];
					if ( array_key_exists( $mapped_role_id, $all_roles ) ) {
						array_push( $mapped_role_names, $all_roles[ $mapped_role_id ] );
					}
				}
			}
		}
		$default_role_name = '';
		if ( 'none' !== $default_role && is_array( $all_roles ) && array_key_exists( $default_role, $all_roles ) ) {
			$default_role_name = $all_roles[ $default_role ];
		}
		if ( ets_memberpress_discord_check_saved_settings_status() ) {
			if ( $access_token ) {
				?>
				<label class="ets-connection-lbl"><?php echo __( 'Discord connection', 'memberpress-discord-add-on' ); ?></label>
				<a href="#" class="ets-btn btn-disconnect" id="disconnect-discord" data-user-id="<?php echo esc_attr( $user_id ); ?>"><?php echo __( 'Disconnect From Discord ', 'memberpress-discord-add-on' ); ?><i class='fab fa-discord'></i></a>
				<span class="ets-spinner"></span>
				<?php
			} elseif ( current_user_can( 'memberpress_authorized' ) && $mapped_role_names || $allow_none_member == 'yes' ) {
				?>
				<label class="ets-connection-lbl"><?php echo __( 'Discord connection', 'memberpress-discord-add-on' ); ?></label>
				<a href="?action=memberpress-discord-login" class="btn-connect ets-btn" ><?php echo __( 'Connect To Discord', 'memberpress-discord-add-on' ); ?> <i class='fab fa-discord'></i></a>
				<?php if ( $mapped_role_names ) { ?>
					<p class="ets_assigned_role">
					<?php
					echo __( 'Following Roles will be assigned to you in Discord: ', 'memberpress-discord-add-on' );
					foreach ( $mapped_role_names as $mapped_role_name ) {
						echo esc_html( $mapped_role_name ) . ', ';
					}
					if ( $default_role_name ) {
						echo esc_html( $default_role_name );
					}
					?>
					</p>
				<?php } ?>
				<?php
			}
		}
	}

	/**
	 * For authorization process call discord API
	 *
	 * @param NONE
	 * @return OBJECT REST API response
	 */
	public function ets_memberpress_discord_discord_api_callback() {
		if ( is_user_logged_in() ) {
			$user_id = get_current_user_id();
			if ( isset( $_GET['action'] ) && 'memberpress-discord-login' === $_GET['action'] ) {
				$params                    = array(
					'client_id'     => sanitize_text_field( trim( get_option( 'ets_memberpress_discord_client_id' ) ) ),
					'redirect_uri'  => sanitize_text_field( trim( get_option( 'ets_memberpress_discord_redirect_url' ) ) ),
					'response_type' => 'code',
					'scope'         => 'identify email connections guilds guilds.join messages.read',
				);
				$discord_authorise_api_url = MEMBERPRESS_DISCORD_API_URL . 'oauth2/authorize?' . http_build_query( $params );

				wp_redirect( $discord_authorise_api_url, 302, get_site_url() );
				exit;
			}

			if ( isset( $_GET['action'] ) && 'discord-connectToBot' === $_GET['action'] ) {
				$params                    = array(
					'client_id'   => sanitize_text_field( trim( get_option( 'ets_memberpress_discord_client_id' ) ) ),
					'permissions' => MEMBERPRESS_DISCORD_BOT_PERMISSIONS,
					'scope'       => 'bot',
					'guild_id'    => sanitize_text_field( trim( get_option( 'ets_memberpress_discord_server_id' ) ) ),
				);
				$discord_authorise_api_url = MEMBERPRESS_DISCORD_API_URL . 'oauth2/authorize?' . http_build_query( $params );

				wp_redirect( $discord_authorise_api_url, 302, get_site_url() );
				exit;
			}
			if ( isset( $_GET['code'] ) && isset( $_GET['via'] ) ) {
				$membership_private_obj = ets_memberpress_discord_get_active_memberships( $user_id );
				$active_memberships     = array();
				if ( ! empty( $membership_private_obj ) ) {
					foreach ( $membership_private_obj as $memberships ) {
						$membership_arr = array(
							'product_id' => $memberships->product_id,
							'txn_number' => $memberships->trans_num,
							'created_at' => $memberships->created_at,
							'expires_at' => $memberships->expires_at,
						);
						array_push( $active_memberships, $membership_arr );
					}
				}
				$code     = sanitize_text_field( trim( $_GET['code'] ) );
				$response = $this->ets_memberpress_create_discord_auth_token( $code, $user_id, $active_memberships );

				if ( ! empty( $response ) && ! is_wp_error( $response ) ) {
					$res_body              = json_decode( wp_remote_retrieve_body( $response ), true );
					$discord_exist_user_id = sanitize_text_field( trim( get_user_meta( $user_id, '_ets_memberpress_discord_user_id', true ) ) );
					if ( is_array( $res_body ) ) {
						if ( array_key_exists( 'access_token', $res_body ) ) {
							$access_token = sanitize_text_field( trim( $res_body['access_token'] ) );
							update_user_meta( $user_id, '_ets_memberpress_discord_access_token', $access_token );
							if ( array_key_exists( 'refresh_token', $res_body ) ) {
								$refresh_token = sanitize_text_field( trim( $res_body['refresh_token'] ) );
								update_user_meta( $user_id, '_ets_memberpress_discord_refresh_token', $refresh_token );
							}
							if ( array_key_exists( 'expires_in', $res_body ) ) {
								$expires_in = $res_body['expires_in'];
								$date       = new DateTime();
								$date->add( DateInterval::createFromDateString( '' . $expires_in . ' seconds' ) );
								$token_expiry_time = $date->getTimestamp();
								update_user_meta( $user_id, '_ets_memberpress_discord_expires_in', $token_expiry_time );
							}
							$user_body = $this->get_discord_current_user( $access_token );

							if ( is_array( $user_body ) && array_key_exists( 'discriminator', $user_body ) ) {
								$discord_user_number           = $user_body['discriminator'];
								$discord_user_name             = $user_body['username'];
								$discord_user_name_with_number = $discord_user_name . '#' . $discord_user_number;
								update_user_meta( $user_id, '_ets_memberpress_discord_username', $discord_user_name_with_number );
							}
							if ( is_array( $user_body ) && array_key_exists( 'id', $user_body ) ) {
								$_ets_memberpress_discord_user_id = sanitize_text_field( trim( $user_body['id'] ) );
								if ( $discord_exist_user_id === $_ets_memberpress_discord_user_id ) {
									foreach ( $active_memberships as $active_membership ) {
										$_ets_memberpress_discord_role_id = get_user_meta( $user_id, '_ets_memberpress_discord_role_id_for_' . $active_membership->trans_num, true );
										if ( ! empty( $_ets_memberpress_discord_role_id ) && $_ets_memberpress_discord_role_id['role_id'] != 'none' ) {
											$this->memberpress_delete_discord_role( $user_id, $_ets_memberpress_discord_role_id['role_id'] );
										}
									}
								}
								update_user_meta( $user_id, '_ets_memberpress_discord_user_id', $_ets_memberpress_discord_user_id );
								$this->ets_memberpress_discord_add_member_in_guild( $_ets_memberpress_discord_user_id, $user_id, $access_token, $active_memberships );
							}
						}
					}
				}
			}
		}
	}

	/**
	 * Add new member into discord guild
	 *
	 * @param INT    $_ets_memberpress_discord_user_id
	 * @param INT    $user_id
	 * @param STRING $access_token
	 * @param ARRAY  $active_memberships
	 * @return NONE
	 */
	public function ets_memberpress_discord_add_member_in_guild( $_ets_memberpress_discord_user_id, $user_id, $access_token, $active_memberships ) {
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( 'Unauthorized user', 401 );
			exit();
		}
		$allow_none_member = sanitize_text_field( trim( get_option( 'ets_memberpress_allow_none_member' ) ) );
		if ( ! empty( $active_memberships ) || 'yes' === $allow_none_member ) {
			// It is possible that we may exhaust API rate limit while adding members to guild, so handling off the job to queue.
			as_schedule_single_action( ets_memberpress_discord_get_random_timestamp( ets_memberpress_discord_get_highest_last_attempt_timestamp() ), 'ets_memberpress_discord_as_handle_add_member_to_guild', array( $_ets_memberpress_discord_user_id, $user_id, $access_token, $active_memberships ), MEMBERPRESS_DISCORD_AS_GROUP_NAME );
		}
	}

	/**
	 * Method to add new members to discord guild.
	 *
	 * @param INT    $_ets_memberpress_discord_user_id
	 * @param INT    $user_id
	 * @param STRING $access_token
	 * @param ARRAY  $active_memberships
	 * @return NONE
	 */
	public function ets_memberpress_discord_as_handler_add_member_to_guild( $_ets_memberpress_discord_user_id, $user_id, $access_token, $active_memberships ) {
		// Since we using a queue to delay the API call, there may be a condition when a member is delete from DB. so put a check.
		if ( get_userdata( $user_id ) === false ) {
			return;
		}
		$guild_id                                = sanitize_text_field( trim( get_option( 'ets_memberpress_discord_server_id' ) ) );
		$discord_bot_token                       = sanitize_text_field( trim( get_option( 'ets_memberpress_discord_bot_token' ) ) );
		$default_role                            = sanitize_text_field( trim( get_option( 'ets_memberpress_discord_default_role_id' ) ) );
		$ets_memberpress_discord_role_mapping    = json_decode( get_option( 'ets_memberpress_discord_role_mapping' ), true );
		$discord_role                            = '';
		$ets_memberpress_discord_send_welcome_dm = sanitize_text_field( trim( get_option( 'ets_memberpress_discord_send_welcome_dm' ) ) );
		$discord_roles                           = array();
		if ( is_array( $active_memberships ) ) {
			foreach ( $active_memberships as $active_membership ) {
				if ( is_array( $ets_memberpress_discord_role_mapping ) && array_key_exists( 'level_id_' . $active_membership['product_id'], $ets_memberpress_discord_role_mapping ) ) {
						$discord_role = sanitize_text_field( trim( $ets_memberpress_discord_role_mapping[ 'level_id_' . $active_membership['product_id'] ] ) );
						array_push( $discord_roles, $discord_role );
				}
			}
		}

		$guilds_memeber_api_url = MEMBERPRESS_DISCORD_API_URL . 'guilds/' . $guild_id . '/members/' . $_ets_memberpress_discord_user_id;
		$guild_args             = array(
			'method'  => 'PUT',
			'headers' => array(
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bot ' . $discord_bot_token,
			),
			'body'    => wp_json_encode(
				array(
					'access_token' => $access_token,
				)
			),
		);
		$guild_response         = wp_remote_post( $guilds_memeber_api_url, $guild_args );
		ets_memberpress_discord_log_api_response( $user_id, $guilds_memeber_api_url, $guild_args, $guild_response );
		if ( ets_memberpress_discord_check_api_errors( $guild_response ) ) {

			$response_arr = json_decode( wp_remote_retrieve_body( $guild_response ), true );
			write_api_response_logs( $response_arr, $user_id, debug_backtrace()[0] );
			// this should be catch by Action schedule failed action.
			throw new Exception( 'Failed in function ets_as_handler_add_member_to_guild' );
		}
		foreach ( $discord_roles as $key => $discord_role ) {
			$assigned_role = array(
				'role_id' => $discord_role,
				'product_id'  => $active_memberships[ $key ]['product_id'],
			);
			update_user_meta( $user_id, '_ets_memberpress_discord_role_id_for_' . $active_memberships[ $key ]['txn_number'], $assigned_role );
			if ( $discord_role && $discord_role != 'none' && isset( $user_id ) ) {
				$this->put_discord_role_api( $user_id, $discord_role );
			}
		}

		if ( $default_role && 'none' !== $default_role && isset( $user_id ) ) {
			$this->put_discord_role_api( $user_id, $default_role );
			update_user_meta( $user_id, '_ets_memberpress_discord_default_role_id', $default_role );
		}
		if ( empty( get_user_meta( $user_id, '_ets_memberpress_discord_join_date', true ) ) ) {
			update_user_meta( $user_id, '_ets_memberpress_discord_join_date', current_time( 'Y-m-d H:i:s' ) );
		}

		// Send welcome message.
		if ( true == $ets_memberpress_discord_send_welcome_dm ) {
			foreach ( $active_memberships as $active_membership ) {
				as_schedule_single_action( ets_memberpress_discord_get_random_timestamp( ets_memberpress_discord_get_highest_last_attempt_timestamp() ), 'ets_memberpress_discord_as_send_welcome_dm', array( $user_id, $active_membership, 'welcome' ), MEMBERPRESS_DISCORD_AS_GROUP_NAME );
			}
		}
	}

	/**
	 * API call to change discord user role
	 *
	 * @param INT  $user_id
	 * @param INT  $role_id
	 * @param BOOL $is_schedule
	 * @return object API response
	 */
	public function put_discord_role_api( $user_id, $role_id, $is_schedule = true ) {
		if ( $is_schedule ) {
			as_schedule_single_action( ets_memberpress_discord_get_random_timestamp( ets_memberpress_discord_get_highest_last_attempt_timestamp() ), 'ets_memberpress_discord_as_schedule_member_put_role', array( $user_id, $role_id, $is_schedule ), MEMBERPRESS_DISCORD_AS_GROUP_NAME );
		} else {
			$this->ets_memberpress_discord_as_handler_put_memberrole( $user_id, $role_id, $is_schedule );
		}
	}

	/**
	 * Action Schedule handler for mmeber change role discord.
	 *
	 * @param INT  $user_id
	 * @param INT  $role_id
	 * @param BOOL $is_schedule
	 * @return object API response
	 */
	public function ets_memberpress_discord_as_handler_put_memberrole( $user_id, $role_id, $is_schedule ) {
		$access_token                     = sanitize_text_field( trim( get_user_meta( $user_id, '_ets_memberpress_discord_access_token', true ) ) );
		$guild_id                         = sanitize_text_field( trim( get_option( 'ets_memberpress_discord_server_id' ) ) );
		$_ets_memberpress_discord_user_id = sanitize_text_field( trim( get_user_meta( $user_id, '_ets_memberpress_discord_user_id', true ) ) );
		$discord_bot_token                = sanitize_text_field( trim( get_option( 'ets_memberpress_discord_bot_token' ) ) );
		$discord_change_role_api_url      = MEMBERPRESS_DISCORD_API_URL . 'guilds/' . $guild_id . '/members/' . $_ets_memberpress_discord_user_id . '/roles/' . $role_id;

		if ( $access_token && $_ets_memberpress_discord_user_id ) {
			$param = array(
				'method'  => 'PUT',
				'headers' => array(
					'Content-Type'   => 'application/json',
					'Authorization'  => 'Bot ' . $discord_bot_token,
					'Content-Length' => 0,
				),
			);

			$response = wp_remote_get( $discord_change_role_api_url, $param );

			ets_memberpress_discord_log_api_response( $user_id, $discord_change_role_api_url, $param, $response );
			if ( ets_memberpress_discord_check_api_errors( $response ) ) {
				$response_arr = json_decode( wp_remote_retrieve_body( $response ), true );
				write_api_response_logs( $response_arr, $user_id, debug_backtrace()[0] );
				if ( $is_schedule ) {
					// this exception should be catch by action scheduler.
					throw new Exception( 'Failed in function ets_memberpress_discord_as_handler_put_memberrole' );
				}
			}
		}
	}

	/**
	 * Get Discord user details from API
	 *
	 * @param STRING $access_token
	 * @return OBJECT REST API response
	 */
	public function get_discord_current_user( $access_token ) {
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( 'Unauthorized user', 401 );
			exit();
		}
		$user_id = get_current_user_id();

		$discord_cuser_api_url = MEMBERPRESS_DISCORD_API_URL . 'users/@me';
		$param                 = array(
			'headers' => array(
				'Content-Type'  => 'application/x-www-form-urlencoded',
				'Authorization' => 'Bearer ' . $access_token,
			),
		);
		$user_response         = wp_remote_get( $discord_cuser_api_url, $param );
		ets_memberpress_discord_log_api_response( $user_id, $discord_cuser_api_url, $param, $user_response );

		$response_arr = json_decode( wp_remote_retrieve_body( $user_response ), true );
		write_api_response_logs( $response_arr, $user_id, debug_backtrace()[0] );
		$user_body = json_decode( wp_remote_retrieve_body( $user_response ), true );
		return $user_body;

	}

	/**
	 * Create authentication token for discord API
	 *
	 * @param STRING $code
	 * @param INT    $user_id
	 * @param ARRAY  $active_memberships
	 * @return OBJECT API response
	 */
	public function ets_memberpress_create_discord_auth_token( $code, $user_id, $active_memberships ) {
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( 'Unauthorized user', 401 );
			exit();
		}

		// stop users who having the direct URL of discord Oauth.
		// We must check IF NONE members is set to NO and user having no active membership.
		$allow_none_member = sanitize_text_field( trim( get_option( 'ets_memberpress_allow_none_member' ) ) );
		if ( empty( $active_memberships ) && 'no' === $allow_none_member ) {
			return;
		}
		$response              = '';
		$refresh_token         = sanitize_text_field( trim( get_user_meta( $user_id, '_ets_memberpress_discord_refresh_token', true ) ) );
		$token_expiry_time     = sanitize_text_field( trim( get_user_meta( $user_id, '_ets_memberpress_discord_expires_in', true ) ) );
		$discord_token_api_url = MEMBERPRESS_DISCORD_API_URL . 'oauth2/token';
		if ( $refresh_token ) {
			$date              = new DateTime();
			$current_timestamp = $date->getTimestamp();

			if ( $current_timestamp > $token_expiry_time ) {
				$args     = array(
					'method'  => 'POST',
					'headers' => array(
						'Content-Type' => 'application/x-www-form-urlencoded',
					),
					'body'    => array(
						'client_id'     => sanitize_text_field( trim( get_option( 'ets_memberpress_discord_client_id' ) ) ),
						'client_secret' => sanitize_text_field( trim( get_option( 'ets_memberpress_discord_client_secret' ) ) ),
						'grant_type'    => 'refresh_token',
						'refresh_token' => $refresh_token,
						'redirect_uri'  => sanitize_text_field( trim( get_option( 'ets_memberpress_discord_redirect_url' ) ) ),
						'scope'         => MEMBERPRESS_DISCORD_OAUTH_SCOPES,
					),
				);
				$response = wp_remote_post( $discord_token_api_url, $args );
				ets_memberpress_discord_log_api_response( $user_id, $discord_token_api_url, $args, $response );
				if ( ets_memberpress_discord_check_api_errors( $response ) ) {
					$response_arr = json_decode( wp_remote_retrieve_body( $response ), true );
					write_api_response_logs( $response_arr, $user_id, debug_backtrace()[0] );
				}
			}
		} else {
			$args     = array(
				'method'  => 'POST',
				'headers' => array(
					'Content-Type' => 'application/x-www-form-urlencoded',
				),
				'body'    => array(
					'client_id'     => sanitize_text_field( trim( get_option( 'ets_memberpress_discord_client_id' ) ) ),
					'client_secret' => sanitize_text_field( trim( get_option( 'ets_memberpress_discord_client_secret' ) ) ),
					'grant_type'    => 'authorization_code',
					'code'          => $code,
					'redirect_uri'  => sanitize_text_field( trim( get_option( 'ets_memberpress_discord_redirect_url' ) ) ),
					'scope'         => MEMBERPRESS_DISCORD_OAUTH_SCOPES,
				),
			);
			$response = wp_remote_post( $discord_token_api_url, $args );

			ets_memberpress_discord_log_api_response( $user_id, $discord_token_api_url, $args, $response );
			if ( ets_memberpress_discord_check_api_errors( $response ) ) {
				$response_arr = json_decode( wp_remote_retrieve_body( $response ), true );
				write_api_response_logs( $response_arr, $user_id, debug_backtrace()[0] );
			}
		}

		return $response;
	}

	/**
	 * Disconnect user from discord
	 *
	 * @param NONE
	 * @return OBJECT JSON response
	 */
	public function ets_memberpress_disconnect_from_discord() {
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( 'Unauthorized user', 401 );
			exit();
		}
		// Check for nonce security
		if ( isset( $_POST['ets_memberpress_discord_public_nonce'] ) && ! wp_verify_nonce( $_POST['ets_memberpress_discord_public_nonce'], 'ets-memberpress-discord-public-ajax-nonce' ) ) {
				wp_send_json_error( 'You do not have sufficient rights', 403 );
				exit();
		}
		$user_id = sanitize_text_field( trim( $_POST['user_id'] ) );
		if ( $user_id ) {
			$this->memberpress_delete_member_from_guild( $user_id, false );
			delete_user_meta( $user_id, '_ets_memberpress_discord_access_token' );
		}
		$event_res = array(
			'status'  => 1,
			'message' => 'Successfully disconnected',
		);
		echo wp_json_encode( $event_res );
		die();
	}

	/**
	 * Schedule delete existing user from guild
	 *
	 * @param INT  $user_id
	 * @param BOOL $is_schedule
	 */
	public function memberpress_delete_member_from_guild( $user_id, $is_schedule = true ) {
		if ( $is_schedule && isset( $user_id ) ) {
			as_schedule_single_action( ets_memberpress_discord_get_random_timestamp( ets_memberpress_discord_get_highest_last_attempt_timestamp() ), 'ets_memberpress_discord_as_schedule_delete_member', array( $user_id, $is_schedule ), MEMBERPRESS_DISCORD_AS_GROUP_NAME );
		} else {
			if ( isset( $user_id ) ) {
				$this->ets_memberpress_discord_as_handler_delete_member_from_guild( $user_id, $is_schedule );
			}
		}
	}

	/**
	 * AS Handling member delete from huild
	 *
	 * @param INT  $user_id
	 * @param BOOL $is_schedule
	 * @return OBJECT API response
	 */
	public function ets_memberpress_discord_as_handler_delete_member_from_guild( $user_id, $is_schedule ) {
		$guild_id                         = sanitize_text_field( trim( get_option( 'ets_memberpress_discord_server_id' ) ) );
		$discord_bot_token                = sanitize_text_field( trim( get_option( 'ets_memberpress_discord_bot_token' ) ) );
		$_ets_memberpress_discord_user_id = sanitize_text_field( trim( get_user_meta( $user_id, '_ets_memberpress_discord_user_id', true ) ) );
		$active_memberships               = ets_memberpress_discord_get_active_memberships( $user_id );
		$guilds_delete_memeber_api_url    = MEMBERPRESS_DISCORD_API_URL . 'guilds/' . $guild_id . '/members/' . $_ets_memberpress_discord_user_id;
		$guild_args                       = array(
			'method'  => 'DELETE',
			'headers' => array(
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bot ' . $discord_bot_token,
			),
		);
		$guild_response                   = wp_remote_post( $guilds_delete_memeber_api_url, $guild_args );
		ets_memberpress_discord_log_api_response( $user_id, $guilds_delete_memeber_api_url, $guild_args, $guild_response );
		if ( ets_memberpress_discord_check_api_errors( $guild_response ) ) {
			$response_arr = json_decode( wp_remote_retrieve_body( $guild_response ), true );
			write_api_response_logs( $response_arr, $user_id, debug_backtrace()[0] );
			if ( $is_schedule ) {
				// this exception should be catch by action scheduler.
				throw new Exception( 'Failed in function ets_memberpress_discord_as_handler_delete_member_from_guild' );
			}
		}

		/*Delete all usermeta related to discord connection*/
		delete_user_meta( $user_id, '_ets_memberpress_discord_user_id' );
		delete_user_meta( $user_id, '_ets_memberpress_discord_access_token' );
		delete_user_meta( $user_id, '_ets_memberpress_discord_refresh_token' );
		if ( is_array( $active_memberships ) ) {
			foreach ( $active_memberships as $active_membership ) {
				delete_user_meta( $user_id, '_ets_memberpress_discord_role_id_for_' . $active_membership->trans_num );
			}
		}
		delete_user_meta( $user_id, '_ets_memberpress_discord_default_role_id' );
		delete_user_meta( $user_id, '_ets_memberpress_discord_username' );
		delete_user_meta( $user_id, '_ets_memberpress_discord_expires_in' );
	}
}
