<?php
/**
 * Common functions.
 */

/**
 * Get formatted redirect url.
 *
 * @param INT $page_id The page ID.
 */
function ets_get_ultimatemember_discord_formated_discord_redirect_url( $page_id ) {
	$url    = esc_url( get_permalink( $page_id ) );
	$parsed = parse_url( $url, PHP_URL_QUERY );
	if ( $parsed === null ) {
		return $url .= '?via=ultimate-discord';
	} else {
		if ( stristr( $url, 'via=ultimate-discord' ) !== false ) {
			return $url;
		} else {
			return $url .= '&via=ultimate-discord';
		}
	}
}
/**
 * Get current screen URL
 *
 * @param NONE
 * @return STRING $url
 */
function ultimatemember_discord_get_current_screen_url() {
	$parts       = parse_url( home_url() );
	$current_uri = "{$parts['scheme']}://{$parts['host']}" . ( isset( $parts['port'] ) ? ':' . $parts['port'] : '' ) . add_query_arg( null, null );

		return $current_uri;
}

/**
 * To check settings values saved or not
 *
 * @param NONE
 * @return BOOL $status
 */
function ultimatemember_discord_check_saved_settings_status() {
	$ets_ultimatemember_discord_client_id     = get_option( 'ets_ultimatemember_discord_client_id' );
	$ets_ultimatemember_discord_client_secret = get_option( 'ets_ultimatemember_discord_client_secret' );
	$ets_ultimatemember_discord_bot_token     = get_option( 'ets_ultimatemember_discord_bot_token' );
	$ets_ultimatemember_discord_redirect_url  = get_option( 'ets_ultimatemember_discord_redirect_url' );
	$ets_ultimatemember_discord_server_id     = get_option( 'ets_ultimatemember_discord_server_id' );

	if ( $ets_ultimatemember_discord_client_id && $ets_ultimatemember_discord_client_secret && $ets_ultimatemember_discord_bot_token && $ets_ultimatemember_discord_redirect_url && $ets_ultimatemember_discord_server_id ) {
			$status = true;
	} else {
		$status = false;
	}
	return $status;
}

 /**
  * Log API call response.
  *
  * @param INT          $user_id
  * @param STRING       $api_url
  * @param ARRAY        $api_args
  * @param ARRAY|OBJECT $api_response
  */
function ets_ultimatemember_discord_log_api_response( $user_id, $api_url = '', $api_args = array(), $api_response = '' ) {
	$log_api_response = get_option( 'ets_ultimatemember_discord_log_api_response' );
	if ( $log_api_response == true ) {
		$log_string  = '==>' . $api_url;
		$log_string .= '-::-' . serialize( $api_args );
		$log_string .= '-::-' . serialize( $api_response );

		$logs = new Ultimate_Member_Discord_Add_On_Logs();
		$logs->write_api_response_logs( $log_string, $user_id );
	}
}

/**
 * Get  current Role Level id
 *
 * @param INT $user_id
 * @return INT|NULL $curr_level_id
 */
function ets_ultimatemember_discord_get_current_level_id( $user_id ) {
	// um_fetch_user( $user_id );
	$curr_level_id = substr( UM()->roles()->get_um_user_role( $user_id ), 3 );
	if ( $curr_level_id ) {
		$curr_level_id = sanitize_text_field( trim( $curr_level_id ) );
		return $curr_level_id;

	} else {
		return null;
	}
}

/**
 * Check API call response and detect conditions which can cause of action failure and retry should be attemped.
 *
 * @param ARRAY|OBJECT $api_response
 * @param BOOLEAN
 */
function ets_ultimatemember_discord_check_api_errors( $api_response ) {
	// check if response code is a WordPress error.
	if ( is_wp_error( $api_response ) ) {
		return true;
	}

	// First Check if response contain codes which should not get re-try.
	$body = json_decode( wp_remote_retrieve_body( $api_response ), true );
	if ( isset( $body['code'] ) && in_array( $body['code'], ETS_ULTIMATE_MEMBER_DISCORD_DONOT_RETRY_THESE_API_CODES ) ) {
		return false;
	}

	$response_code = strval( $api_response['response']['code'] );
	if ( isset( $api_response['response']['code'] ) && in_array( $response_code, ETS_ULTIMATE_MEMBER_DISCORD_DONOT_RETRY_HTTP_CODES ) ) {
		return false;
	}

	// check if response code is in the range of HTTP error.
	if ( ( 400 <= absint( $response_code ) ) && ( absint( $response_code ) <= 599 ) ) {
		return true;
	}
}

/**
 * Get formatted message to send in DM.
 *
 * @param INT $user_id
 * Merge fields: [MEMBER_USERNAME], [MEMBER_EMAIL], [MEMBER_ROLE], [SITE_URL], [BLOG_NAME]</small>
 */
function ets_ultimatemember_discord_get_formatted_dm( $user_id, $um_role_id, $message ) {

	$user_obj        = get_user_by( 'id', $user_id );
	$MEMBER_USERNAME = $user_obj->user_login;
	$MEMBER_EMAIL    = $user_obj->user_email;
	$MEMBER_ROLE     = '';
	if ( is_array( UM()->roles()->get_roles() ) && array_key_exists( 'um_' . $um_role_id, UM()->roles()->get_roles() ) ) {
		$MEMBER_ROLE = UM()->roles()->get_roles()[ 'um_' . $um_role_id ];
	}

	$SITE_URL  = get_bloginfo( 'url' );
	$BLOG_NAME = get_bloginfo( 'name' );

		$find    = array(
			'[MEMBER_USERNAME]',
			'[MEMBER_EMAIL]',
			'[MEMBER_ROLE]',
			'[SITE_URL]',
			'[BLOG_NAME]',
		);
		$replace = array(
			$MEMBER_USERNAME,
			$MEMBER_EMAIL,
			$MEMBER_ROLE,
			$SITE_URL,
			$BLOG_NAME,
		);

		return str_replace( $find, $replace, $message );

}

/**
 * Get the highest available last attempt schedule time.
 */
function ets_ultimatemember_discord_get_highest_last_attempt_timestamp() {
	global $wpdb;
	$result = $wpdb->get_results( $wpdb->prepare( 'SELECT aa.last_attempt_gmt FROM ' . $wpdb->prefix . 'actionscheduler_actions as aa INNER JOIN ' . $wpdb->prefix . 'actionscheduler_groups as ag ON aa.group_id = ag.group_id WHERE ag.slug = %s ORDER BY aa.last_attempt_gmt DESC limit 1', ETS_UM_DISCORD_AS_GROUP_NAME ), ARRAY_A );

	if ( ! empty( $result ) ) {
		return strtotime( $result['0']['last_attempt_gmt'] );
	} else {
		return false;
	}
}

/**
 * Get randon integer between a predefined range.
 *
 * @param INT $add_upon
 */
function ets_ultimatemember_discord_get_random_timestamp( $add_upon = '' ) {
	if ( $add_upon != '' && $add_upon !== false ) {
		return $add_upon + random_int( 5, 15 );
	} else {
		return strtotime( 'now' ) + random_int( 5, 15 );
	}
}

/**
 * Get pending jobs for group ETS_UM_DISCORD_AS_GROUP_NAME.
 */
function ets_ultimatemember_discord_get_all_pending_actions() {
	global $wpdb;
	$result = $wpdb->get_results( $wpdb->prepare( 'SELECT aa.* FROM ' . $wpdb->prefix . 'actionscheduler_actions as aa INNER JOIN ' . $wpdb->prefix . 'actionscheduler_groups as ag ON aa.group_id = ag.group_id WHERE ag.slug = %s AND aa.status="pending" ', ETS_UM_DISCORD_AS_GROUP_NAME ), ARRAY_A );

	if ( ! empty( $result ) ) {
		return $result['0'];
	} else {
		return false;
	}
}

/**
 * Get Action data from table `actionscheduler_actions`
 *
 * @param INT $action_id
 */
function ets_ultimatemember_discord_as_get_action_data( $action_id ) {
	global $wpdb;
	$result = $wpdb->get_results( $wpdb->prepare( 'SELECT aa.hook, aa.status, aa.args, ag.slug AS as_group FROM ' . $wpdb->prefix . 'actionscheduler_actions as aa INNER JOIN ' . $wpdb->prefix . 'actionscheduler_groups as ag ON aa.group_id=ag.group_id WHERE `action_id`=%d AND ag.slug=%s', $action_id, ETS_UM_DISCORD_AS_GROUP_NAME ), ARRAY_A );

	if ( ! empty( $result ) ) {
		return $result[0];
	} else {
		return false;
	}
}

/**
 * Get how many times a hook is failed in a particular day.
 *
 * @param STRING $hook
 */
function ets_ultimatemember_discord_count_of_hooks_failures( $hook ) {
	global $wpdb;
	$result = $wpdb->get_results( $wpdb->prepare( 'SELECT count(last_attempt_gmt) as hook_failed_count FROM ' . $wpdb->prefix . 'actionscheduler_actions WHERE `hook`=%s AND status="failed" AND DATE(last_attempt_gmt) = %s', $hook, date( 'Y-m-d' ) ), ARRAY_A );
	if ( ! empty( $result ) ) {
		return $result['0']['hook_failed_count'];
	} else {
		return false;
	}
}

/**
 * Delete user's meta data.
 *
 * @param INT $user_id The User's ID.
 */
function ets_ultimatemember_discord_remove_usermeta( $user_id ) {

	global $wpdb;

	$usermeta_table      = $wpdb->prefix . 'usermeta';
	$usermeta_sql        = 'DELETE FROM ' . $usermeta_table . " WHERE `user_id` = %d AND  `meta_key` LIKE '_ets_ultimatemember_discord%'; ";
	$delete_usermeta_sql = $wpdb->prepare( $usermeta_sql, $user_id );
	$wpdb->query( $delete_usermeta_sql );

}
/**
 * Get member' role id.
 *
 * @param INT $user_id
 * @return STRING $role
 */
function ets_ultimatemember_discord_get_user_roles( $user_id ) {
	global $wpdb;

	$usermeta_table    = $wpdb->prefix . 'usermeta';
	$user_role_sql     = 'SELECT * FROM ' . $usermeta_table . " WHERE `user_id` = %d AND  ( `meta_key` = '_ets_ultimatemember_discord_role_id' OR `meta_key` = '_ets_ultimatemember_discord_default_role' ) ; ";
	$user_role_prepare = $wpdb->prepare( $user_role_sql, $user_id );

	$user_role = $wpdb->get_results( $user_role_prepare, ARRAY_A );

	if ( is_array( $user_role ) && count( $user_role ) ) {

		return $user_role;

	} else {

		return null;
	}

}

/**
 * Update the Bot name option.
 */
function ets_ultimatemember_discord_update_bot_name_option() {

	$guild_id          = sanitize_text_field( trim( get_option( 'ets_ultimatemember_discord_server_id' ) ) );
	$discord_bot_token = sanitize_text_field( trim( get_option( 'ets_ultimatemember_discord_bot_token' ) ) );
	if ( $guild_id && $discord_bot_token ) {

		$discod_current_user_api = ETS_UM_DISCORD_API_URL . 'users/@me';

		$app_args = array(
			'method'  => 'GET',
			'headers' => array(
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bot ' . $discord_bot_token,
			),
		);

		$app_response = wp_remote_post( $discod_current_user_api, $app_args );

		$response_arr = json_decode( wp_remote_retrieve_body( $app_response ), true );

		if ( is_array( $response_arr ) && array_key_exists( 'username', $response_arr ) ) {

			update_option( 'ets_ultimatemember_discord_connected_bot_name', $response_arr ['username'] );
		} else {
			delete_option( 'ets_ultimatemember_discord_connected_bot_name' );
		}
	}

}

/**
 * The list of pages to define as Discord redirect page.
 *
 * @param INT $ets_ultimatemember_discord_redirect_page_id The page ID.
 */
function ets_ultimatemember_discord_pages_list( $ets_ultimatemember_discord_redirect_page_id ) {
	$args    = array(
		'sort_order'   => 'asc',
		'sort_column'  => 'post_title',
		'hierarchical' => 1,
		'exclude'      => '',
		'include'      => '',
		'meta_key'     => '',
		'meta_value'   => '',
		'exclude_tree' => '',
		'number'       => '',
		'offset'       => 0,
		'post_type'    => 'page',
		'post_status'  => 'publish',
	);
	$pages   = get_pages( $args );
	$options = '<option value="-" >-</option>';
	if ( is_array( $pages ) ) {
		foreach ( $pages as $page ) {
			$selected = ( esc_attr( $page->ID ) == $ets_ultimatemember_discord_redirect_page_id ) ? ' selected="selected"' : '';
			$options .= '<option data-page-url="' . ets_get_ultimatemember_discord_formated_discord_redirect_url( $page->ID ) . '" value="' . esc_attr( $page->ID ) . '" ' . $selected . '> ' . $page->post_title . ' </option>';
		}
	}
	return $options;
}

/**
 * Send a Discord Rich message.
 *
 * @param STRING $message The message to send.
 */
function ets_ultimatemember_discord_get_rich_embed_message( $message ) {
	$blog_logo_full      = '';
	$blog_logo_thumbnail = '';
	if ( is_array( wp_get_attachment_image_src( get_theme_mod( 'custom_logo' ), 'full' ) ) ) {
		$blog_logo_full = esc_url( wp_get_attachment_image_src( get_theme_mod( 'custom_logo' ), 'full' )[0] );
	}

	if ( is_array( wp_get_attachment_image_src( get_theme_mod( 'custom_logo' ), 'thumbnail' ) ) ) {
		$blog_logo_thumbnail = esc_url( wp_get_attachment_image_src( get_theme_mod( 'custom_logo' ), 'thumbnail' )[0] );
	}

	$SITE_URL         = get_bloginfo( 'url' );
	$BLOG_NAME        = get_bloginfo( 'name' );
	$BLOG_DESCRIPTION = get_bloginfo( 'description' );

	$timestamp     = date( 'c', strtotime( 'now' ) );
	$convert_lines = preg_split( '/\[LINEBREAK\]/', $message );
	$fields        = array();
	if ( is_array( $convert_lines ) ) {
		for ( $i = 0; $i < count( $convert_lines ); $i++ ) {
			array_push(
				$fields,
				array(
					'name'   => '.',
					'value'  => $convert_lines[ $i ],
					'inline' => false,
				)
			);
		}
	}

	$rich_embed_message = json_encode(
		array(
			'content'    => '',
			'username'   => $BLOG_NAME,
			'avatar_url' => $blog_logo_thumbnail,
			'tts'        => false,
			'embeds'     => array(
				array(
					'title'       => '',
					'type'        => 'rich',
					'description' => $BLOG_DESCRIPTION,
					'url'         => '',
					'timestamp'   => $timestamp,
					'color'       => hexdec( '3366ff' ),
					'footer'      => array(
						'text'     => $BLOG_NAME,
						'icon_url' => $blog_logo_thumbnail,
					),
					'image'       => array(
						'url' => $blog_logo_full,
					),
					'thumbnail'   => array(
						'url' => $blog_logo_thumbnail,
					),
					'author'      => array(
						'name' => $BLOG_NAME,
						'url'  => $SITE_URL,
					),
					'fields'      => $fields,

				),
			),

		),
		JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
	);

	return $rich_embed_message;
}

/**
 * Displays a message of assigned roles.
 *
 * @param STRING $mapped_role_name
 * @param STRING $default_role_name
 * @param STRING $restrictcontent_discord
 */
function ets_ultimatemember_discord_roles_assigned_message( $mapped_role_name, $default_role_name, $restrictcontent_discord ) {

	if ( $mapped_role_name ) {
		$restrictcontent_discord .= '<p class="ets_assigned_role">';

		$restrictcontent_discord .= esc_html__( 'Following Roles will be assigned to you in Discord: ', 'ultimate-member-discord-add-on' );
		$restrictcontent_discord .= ets_ultimatemember_discord_allowed_html( $mapped_role_name );
		if ( $default_role_name ) {
			$restrictcontent_discord .= ets_ultimatemember_discord_allowed_html( $default_role_name );

		}

		$restrictcontent_discord .= '</p>';
	} elseif ( $default_role_name ) {
		$restrictcontent_discord .= '<p class="ets_assigned_role">';

		$restrictcontent_discord .= esc_html__( 'Following Role will be assigned to you in Discord: ', 'ultimate-member-discord-add-on' );
		$restrictcontent_discord .= ets_ultimatemember_discord_allowed_html( $default_role_name );

		$restrictcontent_discord .= '</p>';

	}
	return $restrictcontent_discord;
}

/**
 * Allowed html.
 *
 * @param STRING $html_message The html message.
 */
function ets_ultimatemember_discord_allowed_html( $html_message ) {
	$allowed_html = array(
		'span' => array(),
		'i'    => array(
			'style' => array(),
		),
		'img'  => array(
			'src'   => array(),
			'class' => array(),
		),
	);

	return wp_kses( $html_message, $allowed_html );
}

/**
 * Get the discord user avatar.
 *
 * @param INTI   $discord_user_id
 * @param INT    $user_avatar
 * @param STRING $restrictcontent_discord
 */
function ets_ultimatemember_discord_get_user_avatar( $discord_user_id, $user_avatar, $restrictcontent_discord ) {
	if ( $user_avatar ) {
		$avatar_url               = '<img class="ets_discord_user_avatar" src="https://cdn.discordapp.com/avatars/' . $discord_user_id . '/' . $user_avatar . '.png" />';
		$restrictcontent_discord .= ets_ultimatemember_discord_allowed_html( $avatar_url );
	}
	return $restrictcontent_discord;
}
