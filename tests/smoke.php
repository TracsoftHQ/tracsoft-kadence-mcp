<?php
/**
 * Smoke-test: roept elke lees-ability één keer aan en controleert dat elke
 * schrijf-ability een aanroepbare callback heeft. Schrijft niets.
 *
 * Draaien op een site met de plugin actief:
 *
 *   wp eval-file tests/smoke.php [gebruikersnaam]
 *
 * Zonder gebruikersnaam draait hij als de eerste beheerder. Een WP_Error telt
 * als geslaagd (de ability antwoordt netjes), een exception of fatal niet.
 * Bedoeld na elke wijziging aan de plugin, vóór een tag: in de gesymlinkte
 * map legt één fout de hele site plat.
 *
 * @package MCP_Abilities_Kadence
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

$login     = isset( $args[0] ) ? (string) $args[0] : '';
$gebruiker = '' !== $login ? get_user_by( 'login', $login ) : null;

if ( ! $gebruiker ) {
	$beheerders = get_users( array( 'role' => 'administrator', 'number' => 1 ) );
	$gebruiker  = $beheerders ? $beheerders[0] : null;
}

wp_set_current_user( $gebruiker ? $gebruiker->ID : 0 );

// Invoer die op elke Kadence-site bestaat.
$pagina = (int) get_option( 'page_on_front' );

if ( ! $pagina ) {
	$paginas = get_posts( array( 'post_type' => 'page', 'numberposts' => 1, 'fields' => 'ids' ) );
	$pagina  = $paginas ? (int) $paginas[0] : 0;
}

$uids   = $pagina ? array_keys( Kadence_MCP_Inventory::verzamel_unique_ids( parse_blocks( (string) get_post_field( 'post_content', $pagina ) ) ) ) : array();
$uid    = $uids ? (string) $uids[0] : '';
$query  = get_posts( array( 'post_type' => 'kadence_query', 'numberposts' => 1, 'fields' => 'ids' ) );
$object = get_posts( array( 'post_type' => array( 'kadence_element', 'kadence_navigation', 'kadence_header' ), 'numberposts' => 1, 'fields' => 'ids' ) );
$recept = '';

foreach ( Kadence_MCP_Registry::get_definitions() as $d ) {
	if ( 'kadence/list-recipes' === $d['name'] ) {
		$lijst = call_user_func( $d['execute_callback'], array() );

		if ( is_array( $lijst ) && ! empty( $lijst['recipes'] ) ) {
			$eerste = reset( $lijst['recipes'] );
			$recept = is_array( $eerste ) && isset( $eerste['slug'] ) ? (string) $eerste['slug'] : '';
		}
	}
}

$waarden = array(
	'post_id'     => $pagina,
	'post_id_a'   => $pagina,
	'object_id'   => $pagina,
	'name'        => 'kadence/rowlayout',
	'post_type'   => 'page',
	'recipe'      => $recept,
	'markup'      => '<!-- wp:paragraph --><p>smoke</p><!-- /wp:paragraph -->',
	'unique_id'   => $uid,
	'unique_id_a' => $uid,
	'unique_id_b' => $uid,
	'attributes'  => (object) array(),
);

$fouten = 0;

foreach ( Kadence_MCP_Registry::get_definitions() as $d ) {
	$alleen_lezen = ! empty( $d['meta']['annotations']['readonly'] );

	if ( ! is_callable( $d['execute_callback'] ) ) {
		WP_CLI::warning( $d['name'] . ': execute_callback is niet aanroepbaar.' );
		++$fouten;
		continue;
	}

	if ( ! $alleen_lezen ) {
		WP_CLI::log( sprintf( '%-32s callback ok (schrijft, niet aangeroepen)', $d['name'] ) );
		continue;
	}

	$invoer = array();

	foreach ( isset( $d['input_schema']['required'] ) ? $d['input_schema']['required'] : array() as $veld ) {
		$invoer[ $veld ] = isset( $waarden[ $veld ] ) ? $waarden[ $veld ] : '';
	}

	if ( 'kadence/describe-query' === $d['name'] ) {
		$invoer['post_id'] = $query ? (int) $query[0] : $pagina;
	}

	if ( 'kadence/export-entity' === $d['name'] ) {
		$invoer['post_id'] = $object ? (int) $object[0] : $pagina;
	}

	// Een toets op een waarde die er al staat: verandert niets, raakt wel de
	// hele validatieketen.
	if ( in_array( $d['name'], array( 'kadence/validate-write', 'kadence/preview-write' ), true ) ) {
		$invoer['attributes'] = array( 'uniqueID' => $uid );
	}

	$start = microtime( true );

	try {
		$uit = call_user_func( $d['execute_callback'], $invoer );
	} catch ( Throwable $e ) {
		WP_CLI::warning( sprintf( '%s: %s in %s:%d', $d['name'], $e->getMessage(), $e->getFile(), $e->getLine() ) );
		++$fouten;
		continue;
	}

	$ms = (int) round( ( microtime( true ) - $start ) * 1000 );

	if ( is_wp_error( $uit ) ) {
		WP_CLI::log( sprintf( '%-32s WP_Error %s (%d ms)', $d['name'], $uit->get_error_code(), $ms ) );
	} elseif ( is_array( $uit ) ) {
		WP_CLI::log( sprintf( '%-32s ok, %d kB (%d ms)', $d['name'], (int) ceil( strlen( (string) wp_json_encode( $uit ) ) / 1024 ), $ms ) );
	} else {
		WP_CLI::warning( $d['name'] . ': gaf geen array en geen WP_Error terug.' );
		++$fouten;
	}
}

// Tracsoft: set-theme-mods and upload-media, proposal step only. Without a
// token neither saves anything, so this keeps the promise in the header.
$verwacht = array(
	'theme-mods: proposal'      => array( array( 'Kadence_MCP_Abilities_Site', 'set_theme_mods' ), array( 'mods' => array( 'header_button_label' => 'Smoke test' ) ), 'array' ),
	'theme-mods: unknown key'   => array( array( 'Kadence_MCP_Abilities_Site', 'set_theme_mods' ), array( 'mods' => array( 'blogname' => 'x' ) ), 'kadence_mcp_unknown_theme_mod' ),
	'theme-mods: unknown item'  => array( array( 'Kadence_MCP_Abilities_Site', 'set_theme_mods' ), array( 'mods' => array( 'header_desktop_items' => array( 'main' => array( 'main_right' => array( 'not-an-item' ) ) ) ) ), 'kadence_mcp_bad_theme_mod' ),
	'theme-mods: unknown slot'  => array( array( 'Kadence_MCP_Abilities_Site', 'set_theme_mods' ), array( 'mods' => array( 'header_desktop_items' => array( 'main' => array( 'main_nowhere' => array() ) ) ) ), 'kadence_mcp_bad_theme_mod' ),
	'theme-mods: item twice'    => array( array( 'Kadence_MCP_Abilities_Site', 'set_theme_mods' ), array( 'mods' => array( 'header_desktop_items' => array( 'main' => array( 'main_left' => array( 'logo' ), 'main_right' => array( 'logo' ) ) ) ) ), 'kadence_mcp_bad_theme_mod' ),
	'theme-mods: bad choice'    => array( array( 'Kadence_MCP_Abilities_Site', 'set_theme_mods' ), array( 'mods' => array( 'header_button_size' => 'huge' ) ), 'kadence_mcp_bad_theme_mod' ),
	'upload-media: png'         => array( array( 'Kadence_MCP_Abilities_Site', 'upload_media' ), array( 'filename' => 'smoke.png', 'data' => 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=' ), 'array' ),
	'upload-media: svg refused' => array( array( 'Kadence_MCP_Abilities_Site', 'upload_media' ), array( 'filename' => 'smoke.svg', 'data' => base64_encode( '<svg xmlns="http://www.w3.org/2000/svg"/>' ) ), 'kadence_mcp_upload_type' ),
);

$mag_schrijven = Kadence_MCP_Capabilities::current_user_can( Kadence_MCP_Capabilities::WRITE );

foreach ( $verwacht as $naam => $geval ) {
	$recht = 0 === strpos( $naam, 'upload-media' ) ? 'upload_files' : 'edit_theme_options';

	// Without the write grant (the default after install) these answer with a
	// clean WP_Error, which proves nothing about the checks; skip them.
	if ( ! $mag_schrijven || ! current_user_can( $recht ) ) {
		WP_CLI::log( sprintf( '%-32s skipped (needs kadence_mcp_write and %s)', $naam, $recht ) );
		continue;
	}

	try {
		$uit = call_user_func( $geval[0], $geval[1] );
	} catch ( Throwable $e ) {
		WP_CLI::warning( sprintf( '%s: %s in %s:%d', $naam, $e->getMessage(), $e->getFile(), $e->getLine() ) );
		++$fouten;
		continue;
	}

	$kreeg = is_wp_error( $uit ) ? $uit->get_error_code() : ( is_array( $uit ) ? 'array' : gettype( $uit ) );

	if ( $kreeg === $geval[2] ) {
		WP_CLI::log( sprintf( '%-32s ok (%s)', $naam, $kreeg ) );
	} else {
		WP_CLI::warning( sprintf( '%s: expected %s, got %s', $naam, $geval[2], $kreeg ) );
		++$fouten;
	}
}

if ( $fouten ) {
	WP_CLI::error( sprintf( '%d abilities faalden.', $fouten ) );
}

WP_CLI::success( 'Alle abilities antwoorden.' );
