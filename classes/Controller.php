<?php

namespace WalkBikeBus;

class Controller {

	const API_KEY = 'LhBDgx9AFH4eqp';

	public $action = '';
	public $data = '';
	public $return = '';
	public $lat;
	public $lng;
	public $error;
	public $current_page = '';
	public $errors;

	public function init()
	{
		if ( !session_id() )
		{
			session_start();
		}

		$temp = explode('?', $_SERVER['REQUEST_URI']);
		$this->current_page = $temp[0];

		/**
		 * load banners
		 */
		if (get_option('wbb_show_banners', 0) == 1 && get_option('wbb_shortcode_page', 0) != 0)
		{
			if ( ! is_admin() )
			{
				wp_enqueue_style( 'wbb-styles', plugin_dir_url( dirname( __FILE__ ) ) . 'css/wbb.css', '', time() );
				wp_enqueue_style( 'wbb-banners', plugin_dir_url( dirname( __FILE__ ) ) . 'css/banners.css', '', time() );
				if (get_current_user_id() == 0)
				{
					wp_enqueue_style( 'wbb-banners', plugin_dir_url( dirname( __FILE__ ) ) . 'css/banners-2.css', '', time() );
				}

				wp_enqueue_script( 'wbb-banners', plugin_dir_url( dirname( __FILE__ ) ) . 'js/banners.js', '', time(), TRUE );
			}
		}

		wp_enqueue_script( 'wbb-calendar', plugin_dir_url( dirname( __FILE__ ) ) . 'js/calendar.js', '', time(), TRUE );
		wp_localize_script( 'wbb-calendar', 'WbbAjax', array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'entry_nonce' => wp_create_nonce( 'entry-nonce' )
		) );

		wp_enqueue_script( 'wbb-common', plugin_dir_url( dirname( __FILE__ ) ) . 'js/common.js', '', time(), TRUE );
		wp_localize_script( 'wbb-common', 'wbb', array(
			'shortcode_page_id' => get_option('wbb_shortcode_page'),
			'wp_user_id' => get_current_user_id(),
			'plugin_dir' => plugin_dir_url( dirname( __FILE__ ) )
		) );
	}

	public function admin_init()
	{

	}

	public function activate()
	{
		require_once( ABSPATH . '/wp-admin/includes/upgrade.php' );
		global $wpdb;

		$table_name = $wpdb->prefix . 'wbb_locations';
		if( $wpdb->get_var( "SHOW TABLES LIKE '$db_table_name'" ) != $db_table_name )
		{
			if ( ! empty( $wpdb->charset ) )
			{
				$charset_collate = "DEFAULT CHARACTER SET $wpdb->charset";
			}
			if ( ! empty( $wpdb->collate ) )
			{
				$charset_collate .= " COLLATE $wpdb->collate";
			}

			$sql = "
				CREATE TABLE " . $table_name . "
				(
					`id` int(11) unsigned NOT NULL AUTO_INCREMENT,
					`user_id` int(11) NOT NULL,
					`title` varchar(50) NOT NULL DEFAULT '',
					`miles` decimal(11,2) NOT NULL,
					`created_at` datetime DEFAULT NULL,
					PRIMARY KEY (`id`),
					KEY `user_id` (`user_id`)
				) " . $charset_collate . ";";
			dbDelta( $sql );
		}

		$table_name = $wpdb->prefix . 'wbb_entries';
		if( $wpdb->get_var( "SHOW TABLES LIKE '$db_table_name'" ) != $db_table_name )
		{
			if ( ! empty( $wpdb->charset ) )
			{
				$charset_collate = "DEFAULT CHARACTER SET $wpdb->charset";
			}
			if ( ! empty( $wpdb->collate ) )
			{
				$charset_collate .= " COLLATE $wpdb->collate";
			}

			$sql = "
				CREATE TABLE " . $table_name . "
				(
					`id` int(11) unsigned NOT NULL AUTO_INCREMENT,
					`user_id` int(11) NOT NULL,
					`location_id` int(11) NOT NULL,
					`entry_date` date NOT NULL,
					`mode_type` enum('walk','bike','bus') DEFAULT NULL,
					`miles` decimal(11,2) NOT NULL,
					`created_at` datetime NOT NULL,
					`updated_at` datetime NOT NULL,
					PRIMARY KEY (`id`),
					KEY `user_id` (`user_id`),
					KEY `location_id` (`location_id`),
					KEY `mode_type` (`mode_type`)
				) " . $charset_collate . ";";
			dbDelta( $sql );
		}
	}

	public function create_post_types()
	{
		$labels = array (
			'name' => __( 'WBB Neighborhoods' ),
			'singular_name' => __( 'WBB Neighborhood' ),
			'add_new_item' => __( 'Add New Neighborhood' ),
			'edit_item' => __( 'Edit Neighborhood' ),
			'new_item' => __( 'New Neighborhood' ),
			'view_item' => __( 'View Neighborhood' ),
			'search_items' => __( 'Search Neighborhoods' ),
			'not_found' => __( 'No neighborhoods found.' )
		);

		$args = array (
			'labels' => $labels,
			'hierarchical' => FALSE,
			'description' => 'Neighborhoods',
			'supports' => array('title', 'editor'),
			'public' => TRUE,
			'show_ui' => TRUE,
			'show_in_menu' => TRUE,
			'show_in_nav_menus' => TRUE,
			'publicly_queryable' => TRUE,
			'exclude_from_search' => FALSE,
			'has_archive' => TRUE
		);

		register_post_type('wbb_neighborhood', $args);

		$labels = array (
			'name' => __( 'Subscribers' ),
			'singular_name' => __( 'Subscriber' ),
			'add_new_item' => __( 'Add New Subscriber' ),
			'edit_item' => __( 'Edit Subscriber' ),
			'new_item' => __( 'New Subscriber' ),
			'view_item' => __( 'View Subscriber' ),
			'search_items' => __( 'Search Subscribers' ),
			'not_found' => __( 'No subscribers found.' )
		);

		$args = array (
			'labels' => $labels,
			'hierarchical' => FALSE,
			'description' => 'Subscribers',
			'supports' => array('title', 'editor'),
			'public' => FALSE,
			'show_ui' => TRUE,
			'show_in_menu' => FALSE,
			'show_in_nav_menus' => FALSE,
			'publicly_queryable' => FALSE,
			'exclude_from_search' => FALSE,
			'has_archive' => TRUE
		);

		register_post_type('wbb_subscriber', $args);
	}

	public function add_menus()
	{
		add_menu_page('Walk Bike Bus Settings', 'Walk Bike Bus', 'manage_options', 'walk_bike_bus', array($this, 'plugin_settings_page'), '');
		add_submenu_page('walk_bike_bus', 'Walk Bike Bus Settings', 'Settings', 'manage_options', 'walk_bike_bus', array($this, 'plugin_settings_page'));
		add_submenu_page('walk_bike_bus', 'Walk Bike Bus Users', 'Users', 'manage_options', 'walk_bike_bus_users', array($this, 'users_page'));
		//add_submenu_page('walk_bike_bus', 'Walk Bike Bus Neighborhoods', 'Neighborhoods', 'manage_options', 'edit.php?post_type=wbb_neighborhood');
		add_submenu_page('walk_bike_bus', 'Walk Bike Bus Subscribers', 'Subscribers', 'manage_options', 'edit.php?post_type=wbb_subscriber');
	}

	public function register_settings()
	{
		register_setting( 'wbb-settings', 'wbb_show_banners', 'intval');
		register_setting( 'wbb-settings', 'wbb_shortcode_page', 'intval' );
	}

	public function plugin_settings_page()
	{
		include(dirname(__DIR__) . '/walk-bike-bus-settings.php');
	}

	public function users_page()
	{
		include(dirname(__DIR__) . '/walk-bike-bus-users.php');
	}

	public function query_vars( $vars )
	{
		$vars[] = 'wbb_action';
		$vars[] = 'wbb_data';
		return $vars;
	}

	public function short_code()
	{
		$this->action = get_query_var('wbb_action');
		$this->data = get_query_var('wbb_data');

		switch ( $this->action )
		{
			case 'address':

				return $this->showAddressPage();
				break;

			case 'register':

				return $this->showRegisterForm();
				break;

			default:

				return $this->showMainPage();
				break;
		}
	}

	public function showAddressPage()
	{
		if ( strlen($this->data) == 0 )
		{
			return $this->showAddressForm();
		}
		else
		{
			$this->getLatLon();
			if (strlen($this->error) > 0)
			{
				$this->return .= '<p class="wbb-alert wbb-alert-danger">' . $this->error . '</p>';
				return $this->showAddressForm();
			}
			else
			{
				$data = Neighborhood::getNeighborhoodFromLatLng($this->lat, $this->lng);
				if ($data['id'] == 0)
				{
					if (strlen($data['expires_at']) > 0 && strtotime($data['expires_at']) < strtotime(date('Y-m-d')))
					{
						$this->return .= "
							<script>

								var wbb_popup_width = 450;
								var wbb_popup_height = 300;
								var wbb_popup_html = 'We are sorry, but registration has ended for the " . $data['title'] . " neighborhood.<br><br>\\
								Sign up for the Walk Bike Bus<br>newsletter to stay informed.<br><br>\\
								Email Address:\\
								<form id=\"wbb-newsletter-form\">\\
								<input name=\"email\"><br>\\
								<button class=\"submit\">Submit</button>\\
								</form>';

							</script>
						";
					}
					else
					{
						$this->return .= "
							<script>

								var wbb_popup_width = 450;
								var wbb_popup_height = 300;
								var wbb_popup_html = '<img src=\"" . plugin_dir_url(dirname(__FILE__)) . "/images/sorry.png\"><br><br>\\
								Sign up for the Walk Bike Bus<br>newsletter to stay informed.<br><br>\\
								Email Address:\\
								<form id=\"wbb-newsletter-form\">\\
								<input name=\"email\"><br>\\
								<button class=\"submit\">Submit</button>\\
								</form>';

							</script>
						";
					}
					//$this->return .= '<p class="wbb-alert wbb-alert-danger">The address you entered does not lie within one of our approved areas.</p>';
					return $this->showAddressForm();
				}
				else
				{
					$this->return .= "
						<script>

							var wbb_popup_width = 450;
							var wbb_popup_height = 200;
							var wbb_popup_html = '<img src=\"" . plugin_dir_url( dirname( __FILE__ ) ) . "/images/congrats.png\"><br><br>\\
							You are part of a very small number of people in " . $data['title'] . " that are eligible to participate in our Walk Bike Bus pilot program!';

						</script>
					";
					//$this->return .= '<p class="wbb-alert wbb-alert-success">Congrats! You are eligible to register for the ' . $data['title'] . ' neighborhood!</p>';
					$_SESSION['wbb_register_neighborhood_id'] = $data['id'];
					$_SESSION['wbb_register_neighborhood_title'] = $data['title'];
					return $this->showRegisterForm();
				}
			}
		}
	}

	public function showMainPage()
	{
		if ( is_user_logged_in() )
		{
			return $this->return . $this->returnOutputFromPage('/display/calendar.php');
		}
		else
		{
			return $this->return . $this->returnOutputFromPage('/display/login-page.php') . $this->returnOutputFromPage('/display/address-form.php');
		}
	}

	public function showAddressForm()
	{
		return $this->return . $this->returnOutputFromPage('/display/address-form.php');
	}

	public function showRegisterForm()
	{
		return $this->return . $this->returnOutputFromPage('/display/register-form.php');
	}

	private function returnOutputFromPage($page)
	{
		ob_start();
		include(dirname(__DIR__) . $page);
		return ob_get_clean();
	}

	private function getLatLon()
	{
		$address = $this->data;
		$_SESSION['wbb_address'] = $address;
		$pos = (strpos(strtoupper($address), 'SPOKANE'));
		if ($pos === FALSE)
		{
			$address .= ' Spokane, WA';
		}

		$url = 'https://maps.googleapis.com/maps/api/geocode/json?address=' . urlencode($address);
		$url = str_replace('#', 'STE+', $url);

		$options = array(
			CURLOPT_RETURNTRANSFER => TRUE,     // return web page
			CURLOPT_HEADER         => FALSE,    // don't return headers
			CURLOPT_FOLLOWLOCATION => TRUE,     // follow redirects
			CURLOPT_ENCODING       => '',       // handle all encodings
			CURLOPT_USERAGENT      => '', 		// who am i
			CURLOPT_AUTOREFERER    => TRUE,     // set referer on redirect
			CURLOPT_CONNECTTIMEOUT => 120,      // timeout on connect
			CURLOPT_TIMEOUT        => 120,      // timeout on response
			CURLOPT_MAXREDIRS      => 10,       // stop after 10 redirects
		);

		$ch = curl_init($url);
		curl_setopt_array ($ch, $options);
		$content = curl_exec($ch);
		curl_close($ch);

		$result = json_decode($content, TRUE);
		if ($result['status'] != 'OK')
		{
			$this->error = 'We could not locate that address. Make sure you enter your complete address, including city, state and zip code.';
		}
		else
		{
			$this->lat = $result['results'][0]['geometry']['location']['lat'];
			$this->lng = $result['results'][0]['geometry']['location']['lng'];
		}
	}

	public function form_capture()
	{
		header('Content-Type: application/json');
		global $wpdb;

		/* custom cross-domain GET for mycommute.org import */
		if ( isset( $_GET['wbb_import'] ) && isset( $_GET['api_key'] ) )
		{
			if ( $_GET['api_key'] != self::API_KEY )
			{
				echo json_encode( array( 'success' => 0, 'error' => 'Incorrect API Key' ) );
			}
			else
			{
				$neighborhood_id = ( isset( $_GET['neighborhood_id'] ) && is_numeric( $_GET['neighborhood_id'] ) ) ? abs( round( $_GET['neighborhood_id'] ) ) : 0;
				$user_id = ( isset( $_GET['user_id'] ) && is_numeric( $_GET['user_id'] ) ) ? abs( round( $_GET['user_id'] ) ) : 0;
				$location_id = ( isset( $_GET['location_id'] ) && is_numeric( $_GET['location_id'] ) ) ? abs( round( $_GET['location_id'] ) ) : 0;
				$entry_id = ( isset( $_GET['entry_id'] ) && is_numeric( $_GET['entry_id'] ) ) ? abs( round( $_GET['entry_id'] ) ) : 0;

				$return = array(
					'success' => 1,
					'neighborhoods' => array(),
					'users' => array(),
					'locations' => array(),
					'entries' => array()
				);

				/* neighborhoods */
				$sql = "
					SELECT
						ID AS id,
						post_title AS title
					FROM
						" . $wpdb->prefix . "posts
					WHERE
						post_type = 'wbb_neighborhood'
						AND ID > " . $neighborhood_id . "
					ORDER BY
						ID ASC";
				$results = $wpdb->get_results($sql);
				foreach ($results as $result)
				{
					$return['neighborhoods'][] = $result;
				}

				/* users */
				$sql = "
					SELECT
						u.ID AS id,
						u.user_email AS email,
						fn.meta_value AS first_name,
						ln.meta_value AS last_name,
						COALESCE(a.meta_value, '') AS address,
						um.meta_value AS neighborhood_id
					FROM
						" . $wpdb->prefix . "users u
					JOIN
						" . $wpdb->prefix . "usermeta um
						ON u.ID = um.user_id
					JOIN
						(
							SELECT
								user_id,
								meta_value
							FROM
								" . $wpdb->prefix . "usermeta
							WHERE
								meta_key = 'first_name'
						) fn
						ON u.ID = fn.user_id
					JOIN
						(
							SELECT
								user_id,
								meta_value
							FROM
								" . $wpdb->prefix . "usermeta
							WHERE
								meta_key = 'last_name'
						) ln
						ON u.ID = ln.user_id
					LEFT OUTER JOIN
						(
							SELECT
								user_id,
								meta_value
							FROM
								" . $wpdb->prefix . "usermeta
							WHERE
								meta_key = 'address'
						) a
						ON u.ID = a.user_id
					WHERE
						um.meta_key = 'neighborhood_id'
						AND um.meta_value != '0'
						AND um.meta_value != ''
						AND u.ID > " . $user_id . "
					ORDER BY
						ID ASC";
				$results = $wpdb->get_results($sql);
				foreach ($results as $result)
				{
					$return['users'][] = $result;
				}

				/* locations */
				$sql = "
					SELECT
						id,
						user_id AS wbb_user_id,
						title,
						miles
					FROM
						" . $wpdb->prefix . "wbb_locations
					WHERE
						id > " . $location_id . "
					ORDER BY
						id ASC";
				$results = $wpdb->get_results($sql);
				foreach ($results as $result)
				{
					$result->title = stripslashes( $result->title );
					$return['locations'][] = $result;
				}

				/* entries */
				$sql = "
					SELECT
						id,
						user_id AS wbb_user_id,
						location_id AS wbb_location_id,
						entry_date,
						mode_type,
						miles
					FROM
						" . $wpdb->prefix . "wbb_entries
					WHERE
						id > " . $entry_id . "
					ORDER BY
						id ASC";
				$results = $wpdb->get_results($sql);
				foreach ($results as $result)
				{
					$return['entries'][] = $result;
				}

				echo json_encode( $return );
			}

			exit;
		}

		if ( isset( $_POST['wbb_action'] ) )
		{
			if ( isset($_POST['wbb_nonce']) && wp_verify_nonce( $_POST['wbb_nonce'], 'wbb_' . $_POST['wbb_action'] ) )
			{
				switch ( $_POST['wbb_action'] )
				{
					case 'address':

						header('Location:'.$this->current_page.'?wbb_action=address&wbb_data='.urlencode($_POST['address']));
						exit;
						break;

					case 'register':

						$this->errors = new \WP_Error;

						if ( empty( $_POST['username'] ) || empty( $_POST['password'] ) || empty( $_POST['email'] ) || empty( $_POST['fname'] ) || empty( $_POST['lname'] ) )
						{
							$this->errors->add('field', 'Required form field is missing');
						}

						elseif ( 4 > strlen( $_POST['username'] ) )
						{
							$this->errors->add( 'username_length', 'Username too short. At least 4 characters is required' );
						}

						elseif ( username_exists( $_POST['username'] ) )
						{
							$this->errors->add( 'user_name', 'Sorry, that username already exists!' );
						}

						elseif ( ! validate_username( $_POST['username'] ) )
						{
							$this->errors->add( 'username_invalid', 'Sorry, the username you entered is not valid' );
						}

						elseif ( 5 > strlen( $_POST['password'] ) )
						{
							$this->errors->add( 'password', 'Password length must be greater than 5' );
						}

						elseif ( !is_email( $_POST['email'] ) )
						{
							$this->errors->add( 'email_invalid', 'Email is not valid' );
						}

						elseif ( email_exists( $_POST['email'] ) )
						{
							$this->errors->add( 'email', 'Email Already in use' );
						}

						if (count($this->errors->get_error_messages()) == 0)
						{
							$userdata = array(
								'user_login' => $_POST['username'],
								'user_email' => $_POST['email'],
								'user_pass' => $_POST['password'],
								'first_name' => $_POST['fname'],
								'last_name' => $_POST['lname']
							);
							$user_id = wp_insert_user( $userdata );
							update_user_meta( $user_id, 'neighborhood_id', $_SESSION['wbb_register_neighborhood_id'] );
							update_user_meta( $user_id, 'address', $_POST['address'] );
							update_user_meta( $user_id, 'mailing_list', isset($_POST['mailing_list']) ? '1' : '0' );

							if (isset($_POST['order']))
							{
								$order = $_POST['order'];
								if (count($order) > 0)
								{
									update_user_meta( $user_id, 'order', json_encode($order) );
								}
							}

							update_user_meta( $user_id, 'gift', $_POST['gift'] );
							update_user_meta( $user_id, 'full_name', $_POST['full_name'] );
							update_user_meta( $user_id, 'address', $_POST['address'] );
							update_user_meta( $user_id, 'date1', $_POST['date1'] );
							update_user_meta( $user_id, 'date2', $_POST['date2'] );
							update_user_meta( $user_id, 'date3', $_POST['date3'] );

							wp_mail('info@walkbikebus.org', 'New Walk Bike Bus Registration', $_POST['full_name'] . ' has just signed up. Check the website for complete registration information.');

							header('Location:'.$this->current_page.'?wbb_action=login&wbb_data=registration_complete');
							exit;
						}

						break;
				}
			}
		}
	}

	public function ajax_add_entry()
	{
		$response = array(
			'success' => 0,
			'error' => 'Something went wrong. Please try again.'
		);

		if ( wp_verify_nonce($_POST['entry_nonce'], 'entry-nonce'))
		{
			$miles = (strlen(trim($_POST['miles'])) > 0 && is_numeric($_POST['miles'])) ? abs(trim($_POST['miles'])) : 1;

			$location = new Location;
			if ($_POST['location_id'] > 0)
			{
				$location->id = $_POST['location_id'];
				$location->read();
				if (!$location->user_id == get_current_user_id())
				{
					$location->id = 0;
				}
			}

			if ($location->id == 0)
			{
				$location->user_id = get_current_user_id();
				$location->title = (strlen(trim($_POST['title'])) > 0) ? trim($_POST['title']) : 'New Location';
				$location->miles = $miles;

				$location_id = Location::checkTitle($location->user_id, $location->title);
				if ($location_id > 0)
				{
					$location->id = $location_id;
					$location->update();
				}
				else
				{
					$location->create();
				}
			}

			$entry = new Entry;
			$entry->user_id = get_current_user_id();
			$entry->entry_date = $_POST['year'].'-'.$_POST['month'].'-'.$_POST['day'];
			$entry->mode = $_POST['mode'];
			$entry->miles = $miles;
			$entry->location = $location;
			$entry->create();

			if ($entry->id > 0)
			{
				$response['success'] = 1;
				$response['id'] = $entry->id;
				$response['title'] = $entry->location->title;
				$response['miles'] = number_format($entry->miles, 2);
				$response['day'] = $_POST['day'];
			}
		}

		header( 'Content-Type: application/json' );
		echo json_encode( $response );
		exit;
	}

	public function ajax_subscribe()
	{
		$post = array(
			'post_title' => $_POST['email'],
			'post_type' => 'wbb_subscriber',
			'post_status' => 'publish'
		);

		wp_insert_post( $post );
	}
}