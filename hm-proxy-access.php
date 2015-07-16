<?php

/**
 * Plugin Name: HM Proxy Access
 * Description: Limit access and check if the site is accessed though a HM Proxy
 */

namespace HM\Proxy_Access;

define( 'HM_IS_PROXIED', is_proxied() );

function get_proxy_hostnames() {
	return array(
		// Old-style
		'eu.proxy.hmn.md',
		'au.proxy.hmn.md',
		'us-w.proxy.hmn.md',
		'us-e.proxy.hmn.md',

		// New-style, in ascending timezone order
		'us-west-1.aws.hmn.md',
		'us-east-1.aws.hmn.md',
		'eu-west-1.aws.hmn.md',
		'eu-central-1.aws.hmn.md',
		'ap-southeast-2.aws.hmn.md',
	);
}

function get_proxy_ip_addresses() {
	$hostnames = get_proxy_hostnames();

	if ( function_exists( 'apc_fetch' ) ) {
		$key = 'hm_proxy_ip_addresses_' . md5( serialize( $hostnames ) );

		$ip_addresses = apc_fetch( $key );

		if ( empty( $ip_addresses ) ) {
			$ip_addresses = array_map( 'gethostbyname', $hostnames );

			apc_store( $key, $ip_addresses, 3600 );
		}
	} else {
		$ip_addresses = array_map( 'gethostbyname', $hostnames );
	}

	return $ip_addresses;

}

/**
 * Is the current request proxied?
 *
 * @return boolean
 */
function is_proxied() {
	// Development should always count as proxied
	if ( defined( 'HM_DEV' ) && HM_DEV ) {
		return true;
	}

	// Is this proxied at all?
	if ( empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
		return false;
	}

	// There can be multiple IPs if there are multiple reverse proxies. E.g ELB -> Varnish -> The Server
	$upstream_ips = array_map( 'trim', explode( ',', $_SERVER['HTTP_X_FORWARDED_FOR'] ) );

	return (bool) array_intersect( $upstream_ips, get_proxy_ip_addresses() );
}
