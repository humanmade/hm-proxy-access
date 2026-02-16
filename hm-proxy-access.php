<?php

/**
 * Plugin Name: HM Proxy Access
 * Description: Limit access and check if the site is accessed though a HM Proxy
 */

namespace HM\Proxy_Access;

define( 'HM_IS_PROXIED', is_proxied() );

function get_proxy_hostnames() {
	return apply_filters( 'hm_proxy_hostnames', array(
		// Old-style
		'eu.proxy.hmn.md',
		'au.proxy.hmn.md',
		'us-w.proxy.hmn.md',
		'us-e.proxy.hmn.md',

		// New-style, in ascending timezone order
		'us-west-1.aws.hmn.md',
		'us-east-1.aws.hmn.md',
		'eu-west-1.aws.hmn.md',
		'eu-west-2.aws.hmn.md',
		'eu-central-1.aws.hmn.md',
		'ap-southeast-1.aws.hmn.md',
		'ap-southeast-2.aws.hmn.md',
	) );
}

function get_proxy_ip_addresses() {
	$hostnames = get_proxy_hostnames();
	$cache_key = 'hm_proxy_ip_addresses_' . md5( serialize( $hostnames ) );

	if ( function_exists( 'apc_fetch' ) ) {
		$ip_addresses = apc_fetch( $cache_key );

		if ( empty( $ip_addresses ) ) {
			$ip_addresses = array_map( 'gethostbyname', $hostnames );
			apc_store( $cache_key, $ip_addresses, 3600 );
		}
	} else {
		$ip_addresses = wp_cache_get( $cache_key );

		if ( empty( $ip_addresses ) ) {
			$ip_addresses = array_map( 'gethostbyname', $hostnames );
			wp_cache_set( $cache_key, $ip_addresses, '', DAY_IN_SECONDS );
		}
	}
	
	$p81_vpn_ip_addresses = array( '212.59.69.208', '131.226.44.47', '159.223.60.92', '108.61.251.94' );
	$nordlayer_vpn_ip_addresses = array( '168.199.129.11', '102.129.136.51' );
	$ip_addresses = array_merge( $ip_addresses, $p81_vpn_ip_addresses, $nordlayer_vpn_ip_addresses );

	return apply_filters( 'hm_proxy_ip_addresses', $ip_addresses );

}

/**
 * Is the current request proxied?
 *
 * @return boolean
 */
function is_proxied() {
	// Development should always count as proxied
	if ( defined( 'HM_DEV' ) && HM_DEV && ! ( defined( 'HM_DEV_NOT_PROXIED' ) && HM_DEV_NOT_PROXIED ) ) {
		return true;
	}

	// Is this proxied at all?
	$ip = $_SERVER['REMOTE_ADDR'];

	// There can be multiple IPs if there are multiple reverse proxies. E.g ELB -> Varnish -> The Server
	$upstream_ips = array_map( 'trim', explode( ',', $ip ) );

	return (bool) array_intersect( $upstream_ips, get_proxy_ip_addresses() );
}
