<?php
/**
 * WebinoCRM Visitor Statistics
 * Track visits and provide overall, daily, top pages, browser/OS/device, referrers, recent/online.
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WebinoCRM_Visitor_Statistics {

	private static function get_client_ip() {
		$keys = array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP', 'REMOTE_ADDR' );
		foreach ( $keys as $key ) {
			if ( ! empty( $_SERVER[ $key ] ) ) {
				$ips = array_map( 'trim', explode( ',', $_SERVER[ $key ] ) );
				foreach ( $ips as $ip ) {
					if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
						return $ip;
					}
				}
			}
		}
		return isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '0.0.0.0';
	}

	private static function is_bot( $ua ) {
		if ( empty( $ua ) ) {
			return true;
		}
		$bots = array( 'googlebot', 'bingbot', 'slurp', 'duckduckbot', 'yandexbot', 'facebookexternalhit', 'twitterbot', 'rogerbot', 'linkedinbot', 'embedly', 'quora', 'showbot', 'outbrain', 'pinterest', 'slackbot', 'vkshare', 'w3c_validator', 'redditbot', 'applebot', 'whatsapp', 'flipboard', 'tumblr', 'bitlybot', 'skypeuripreview', 'nuzzel', 'semrushbot', 'ahrefsbot', 'dotbot' );
		$ual = strtolower( $ua );
		foreach ( $bots as $b ) {
			if ( strpos( $ual, $b ) !== false ) {
				return true;
			}
		}
		return false;
	}

	private static function parse_user_agent( $ua ) {
		$r = array( 'browser' => 'Unknown', 'browser_version' => '', 'os' => 'Unknown', 'os_version' => '', 'device_type' => 'desktop', 'device_model' => '' );
		if ( empty( $ua ) ) {
			return $r;
		}
		$ual = strtolower( $ua );
		if ( preg_match( '/android/i', $ua ) ) {
			$r['os'] = 'Android';
			$r['device_type'] = 'mobile';
		} elseif ( preg_match( '/iphone|ipod/i', $ua ) ) {
			$r['os'] = 'iOS';
			$r['device_type'] = 'mobile';
			$r['device_model'] = 'iPhone';
		} elseif ( preg_match( '/ipad/i', $ua ) ) {
			$r['os'] = 'iOS';
			$r['device_type'] = 'tablet';
			$r['device_model'] = 'iPad';
		} elseif ( preg_match( '/windows nt/i', $ua ) ) {
			$r['os'] = 'Windows';
		} elseif ( preg_match( '/macintosh|mac os x/i', $ua ) && ! preg_match( '/mobile|iphone|ipad/i', $ua ) ) {
			$r['os'] = 'macOS';
		} elseif ( preg_match( '/linux/i', $ua ) && ! preg_match( '/android/i', $ua ) ) {
			$r['os'] = 'Linux';
		}
		if ( preg_match( '/edg\/([\d.]+)/i', $ua, $m ) ) {
			$r['browser'] = 'Edge';
			$r['browser_version'] = $m[1];
		} elseif ( preg_match( '/chrome\/([\d.]+)/i', $ua, $m ) ) {
			$r['browser'] = preg_match( '/mobile/i', $ua ) ? 'Chrome Mobile' : 'Chrome';
			$r['browser_version'] = $m[1];
		} elseif ( preg_match( '/safari\/([\d.]+)/i', $ua, $m ) && ! preg_match( '/chrome/i', $ua ) ) {
			$r['browser'] = preg_match( '/iphone|ipad|ipod/i', $ua ) ? 'Mobile Safari' : 'Safari';
			$r['browser_version'] = $m[1];
		} elseif ( preg_match( '/firefox\/([\d.]+)/i', $ua, $m ) ) {
			$r['browser'] = 'Firefox';
			$r['browser_version'] = $m[1];
		} elseif ( preg_match( '/opr\/([\d.]+)/i', $ua, $m ) ) {
			$r['browser'] = 'Opera';
			$r['browser_version'] = $m[1];
		}
		return $r;
	}

	/**
	 * Normalize page URL for deduplication (scheme + host + path, no query).
	 */
	private static function normalize_page_url( $url ) {
		$parsed = wp_parse_url( $url );
		if ( empty( $parsed['host'] ) ) {
			return $url;
		}
		$scheme = isset( $parsed['scheme'] ) ? $parsed['scheme'] : 'http';
		$path   = isset( $parsed['path'] ) ? $parsed['path'] : '/';
		return $scheme . '://' . $parsed['host'] . $path;
	}

	private static function get_session_id() {
		if ( ! session_id() && ! headers_sent() ) {
			@session_start();
		}
		if ( ! empty( $_SESSION['webino_visitor_session_id'] ) ) {
			return $_SESSION['webino_visitor_session_id'];
		}
		$sid = wp_generate_password( 32, false );
		if ( isset( $_SESSION ) && is_array( $_SESSION ) ) {
			$_SESSION['webino_visitor_session_id'] = $sid;
		}
		return $sid;
	}

	/**
	 * Track a visit. Skip dashboard/admin, bots, rate limit.
	 */
	public static function track_visit( $page_url = null, $page_title = null, $referrer = null, $entity_id = null ) {
		global $wpdb;
		if ( $page_url === null ) {
			$page_url = ( is_ssl() ? 'https' : 'http' ) . '://' . ( isset( $_SERVER['HTTP_HOST'] ) ? $_SERVER['HTTP_HOST'] : '' ) . ( isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '' );
		}
		$page_url = self::normalize_page_url( $page_url );
		if ( strpos( $page_url, '/dashboard' ) !== false || strpos( $page_url, '/wp-admin' ) !== false || strpos( $page_url, 'wp-login' ) !== false ) {
			return false;
		}
		$ip = self::get_client_ip();
		$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
		if ( self::is_bot( $user_agent ) ) {
			return false;
		}
		$transient_key = 'webino_track_' . md5( $ip . $page_url );
		if ( get_transient( $transient_key ) ) {
			return false;
		}
		set_transient( $transient_key, 1, 30 );

		$ua_info = self::parse_user_agent( $user_agent );
		$v_table = $wpdb->prefix . 'webinocrm_visitors';
		$visitor = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$v_table} WHERE ip_address = %s LIMIT 1", $ip ) );
		if ( $visitor ) {
			$wpdb->update( $v_table, array(
				'last_visit' => current_time( 'mysql' ),
				'visit_count' => $visitor->visit_count + 1,
				'user_agent' => $user_agent,
				'browser' => $ua_info['browser'],
				'browser_version' => $ua_info['browser_version'],
				'os' => $ua_info['os'],
				'os_version' => $ua_info['os_version'],
				'device_type' => $ua_info['device_type'],
				'device_model' => $ua_info['device_model'],
			), array( 'id' => $visitor->id ), array( '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ), array( '%d' ) );
			$visitor_id = $visitor->id;
		} else {
			$wpdb->insert( $v_table, array(
				'ip_address' => $ip,
				'user_agent' => $user_agent,
				'browser' => $ua_info['browser'],
				'browser_version' => $ua_info['browser_version'],
				'os' => $ua_info['os'],
				'os_version' => $ua_info['os_version'],
				'device_type' => $ua_info['device_type'],
				'device_model' => $ua_info['device_model'],
				'first_visit' => current_time( 'mysql' ),
				'last_visit' => current_time( 'mysql' ),
				'visit_count' => 1,
				'is_bot' => 0,
			), array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d' ) );
			$visitor_id = $wpdb->insert_id;
		}
		if ( ! $visitor_id ) {
			return false;
		}

		$referrer_domain = null;
		if ( $referrer ) {
			$parsed = wp_parse_url( $referrer );
			$referrer_domain = isset( $parsed['host'] ) ? $parsed['host'] : null;
		}
		$session_id = self::get_session_id();
		$visits_table = $wpdb->prefix . 'webinocrm_visits';
		$wpdb->insert( $visits_table, array(
			'visitor_id' => $visitor_id,
			'page_url' => substr( $page_url, 0, 500 ),
			'page_title' => $page_title ? substr( $page_title, 0, 255 ) : null,
			'referrer' => $referrer ? substr( $referrer, 0, 500 ) : null,
			'referrer_domain' => $referrer_domain ? substr( $referrer_domain, 0, 255 ) : null,
			'visit_date' => current_time( 'mysql' ),
			'session_id' => $session_id,
			'entity_id' => $entity_id ? (int) $entity_id : null,
		), array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d' ) );

		$pages_table = $wpdb->prefix . 'webinocrm_visitor_pages';
		$page_url_short = substr( $page_url, 0, 500 );
		$existing = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$pages_table} WHERE page_url = %s LIMIT 1", $page_url_short ) );
		if ( $existing ) {
			$wpdb->update( $pages_table, array(
				'visit_count' => $existing->visit_count + 1,
				'last_visit' => current_time( 'mysql' ),
				'page_title' => $page_title ? substr( $page_title, 0, 255 ) : $existing->page_title,
			), array( 'id' => $existing->id ), array( '%d', '%s', '%s' ), array( '%d' ) );
		} else {
			$wpdb->insert( $pages_table, array(
				'page_url' => $page_url_short,
				'page_title' => $page_title ? substr( $page_title, 0, 255 ) : '',
				'visit_count' => 1,
				'unique_visitors' => 1,
				'last_visit' => current_time( 'mysql' ),
			), array( '%s', '%s', '%d', '%d', '%s' ) );
		}
		return true;
	}

	public static function get_overall_stats( $start_date = null, $end_date = null ) {
		global $wpdb;
		$vi = $wpdb->prefix . 'webinocrm_visits';
		if ( $start_date && $end_date ) {
			$total_visits = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$vi} WHERE visit_date >= %s AND visit_date <= %s", $start_date . ' 00:00:00', $end_date . ' 23:59:59' ) );
			$unique = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(DISTINCT visitor_id) FROM {$vi} WHERE visit_date >= %s AND visit_date <= %s", $start_date . ' 00:00:00', $end_date . ' 23:59:59' ) );
		} else {
			$total_visits = $wpdb->get_var( "SELECT COUNT(*) FROM {$vi}" );
			$unique = $wpdb->get_var( "SELECT COUNT(DISTINCT visitor_id) FROM {$vi}" );
		}
		$today = wp_date( 'Y-m-d' );
		$today_count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$vi} WHERE visit_date >= %s AND visit_date <= %s", $today . ' 00:00:00', $today . ' 23:59:59' ) );
		$online_cut = wp_date( 'Y-m-d H:i:s', current_time( 'timestamp' ) - 300 );
		$online = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(DISTINCT visitor_id) FROM {$vi} WHERE visit_date >= %s", $online_cut ) );
		return array(
			'total_visits' => (int) $total_visits,
			'unique_visitors' => (int) $unique,
			'today_visits' => (int) $today_count,
			'online_now' => (int) $online,
		);
	}

	public static function get_daily_visits( $start_date = null, $end_date = null ) {
		global $wpdb;
		$vi = $wpdb->prefix . 'webinocrm_visits';
		if ( ! $start_date ) {
			$start_date = wp_date( 'Y-m-d', strtotime( '-30 days' ) );
		}
		if ( ! $end_date ) {
			$end_date = wp_date( 'Y-m-d' );
		}
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT DATE(visit_date) AS d, COUNT(*) AS cnt FROM {$vi} WHERE visit_date >= %s AND visit_date <= %s GROUP BY d ORDER BY d ASC",
			$start_date . ' 00:00:00',
			$end_date . ' 23:59:59'
		), OBJECT );
		$out = array();
		$t = strtotime( $start_date );
		$end = strtotime( $end_date );
		while ( $t <= $end ) {
			$d = date( 'Y-m-d', $t );
			$out[ $d ] = array( 'date' => $d, 'visits' => 0 );
			$t = strtotime( '+1 day', $t );
		}
		foreach ( $rows as $r ) {
			$out[ $r->d ]['visits'] = (int) $r->cnt;
		}
		return array_values( $out );
	}

	public static function get_top_pages( $limit = 10, $start_date = null, $end_date = null ) {
		global $wpdb;
		$p = $wpdb->prefix . 'webinocrm_visitor_pages';
		$order = " ORDER BY visit_count DESC LIMIT " . (int) $limit;
		if ( $start_date && $end_date ) {
			$vi = $wpdb->prefix . 'webinocrm_visits';
			return $wpdb->get_results( $wpdb->prepare(
				"SELECT page_url, page_title, COUNT(*) AS visit_count FROM {$vi} WHERE visit_date >= %s AND visit_date <= %s GROUP BY page_url ORDER BY visit_count DESC LIMIT %d",
				$start_date . ' 00:00:00',
				$end_date . ' 23:59:59',
				$limit
			), OBJECT );
		}
		return $wpdb->get_results( "SELECT page_url, page_title, visit_count FROM {$p} ORDER BY visit_count DESC LIMIT " . (int) $limit, OBJECT );
	}

	public static function get_browser_stats( $start_date = null, $end_date = null ) {
		global $wpdb;
		$v = $wpdb->prefix . 'webinocrm_visitors';
		$vi = $wpdb->prefix . 'webinocrm_visits';
		$where = '';
		$params = array();
		if ( $start_date && $end_date ) {
			$where = " INNER JOIN {$vi} vi ON vi.visitor_id = v.id AND vi.visit_date >= %s AND vi.visit_date <= %s ";
			$params = array( $start_date . ' 00:00:00', $end_date . ' 23:59:59' );
		}
		$q = "SELECT v.browser AS name, COUNT(*) AS count FROM {$v} v {$where} GROUP BY v.browser ORDER BY count DESC";
		return $wpdb->get_results( $params ? $wpdb->prepare( $q, $params ) : $q, OBJECT );
	}

	public static function get_os_stats( $start_date = null, $end_date = null ) {
		global $wpdb;
		$v = $wpdb->prefix . 'webinocrm_visitors';
		$vi = $wpdb->prefix . 'webinocrm_visits';
		$where = '';
		$params = array();
		if ( $start_date && $end_date ) {
			$where = " INNER JOIN {$vi} vi ON vi.visitor_id = v.id AND vi.visit_date >= %s AND vi.visit_date <= %s ";
			$params = array( $start_date . ' 00:00:00', $end_date . ' 23:59:59' );
		}
		$q = "SELECT v.os AS name, COUNT(*) AS count FROM {$v} v {$where} GROUP BY v.os ORDER BY count DESC";
		return $wpdb->get_results( $params ? $wpdb->prepare( $q, $params ) : $q, OBJECT );
	}

	public static function get_device_stats( $start_date = null, $end_date = null ) {
		global $wpdb;
		$v = $wpdb->prefix . 'webinocrm_visitors';
		$vi = $wpdb->prefix . 'webinocrm_visits';
		$where = '';
		$params = array();
		if ( $start_date && $end_date ) {
			$where = " INNER JOIN {$vi} vi ON vi.visitor_id = v.id AND vi.visit_date >= %s AND vi.visit_date <= %s ";
			$params = array( $start_date . ' 00:00:00', $end_date . ' 23:59:59' );
		}
		$q = "SELECT v.device_type AS name, COUNT(*) AS count FROM {$v} v {$where} GROUP BY v.device_type ORDER BY count DESC";
		return $wpdb->get_results( $params ? $wpdb->prepare( $q, $params ) : $q, OBJECT );
	}

	public static function get_referrer_stats( $limit = 10, $start_date = null, $end_date = null ) {
		global $wpdb;
		$vi = $wpdb->prefix . 'webinocrm_visits';
		$where = " WHERE referrer_domain IS NOT NULL AND referrer_domain != '' ";
		$params = array();
		if ( $start_date && $end_date ) {
			$where .= " AND visit_date >= %s AND visit_date <= %s ";
			$params[] = $start_date . ' 00:00:00';
			$params[] = $end_date . ' 23:59:59';
		}
		$params[] = (int) $limit;
		return $wpdb->get_results( $wpdb->prepare( "SELECT referrer_domain AS name, COUNT(*) AS count FROM {$vi} {$where} GROUP BY referrer_domain ORDER BY count DESC LIMIT %d", $params ), OBJECT );
	}

	public static function get_recent_visitors( $limit = 50, $start_date = null, $end_date = null ) {
		global $wpdb;
		$vi = $wpdb->prefix . 'webinocrm_visits';
		$v = $wpdb->prefix . 'webinocrm_visitors';
		$where = ' 1=1 ';
		$params = array();
		if ( $start_date && $end_date ) {
			$where .= " AND vi.visit_date >= %s AND vi.visit_date <= %s ";
			$params[] = $start_date . ' 00:00:00';
			$params[] = $end_date . ' 23:59:59';
		}
		$params[] = (int) $limit;
		return $wpdb->get_results( $wpdb->prepare( "SELECT vi.id, vi.visitor_id, vi.page_url, vi.page_title, vi.visit_date, v.ip_address, v.browser, v.os, v.device_type FROM {$vi} vi INNER JOIN {$v} v ON v.id = vi.visitor_id WHERE {$where} ORDER BY vi.visit_date DESC LIMIT %d", $params ), OBJECT );
	}

	public static function get_online_visitors() {
		global $wpdb;
		$vi = $wpdb->prefix . 'webinocrm_visits';
		$cut = wp_date( 'Y-m-d H:i:s', current_time( 'timestamp' ) - 300 );
		return $wpdb->get_results( $wpdb->prepare( "SELECT DISTINCT vi.visitor_id, vi.visit_date, vi.page_url FROM {$vi} vi WHERE vi.visit_date >= %s ORDER BY vi.visit_date DESC", $cut ), OBJECT );
	}
}
