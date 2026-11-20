<?php
/**
 * Plugin Name:       Tracsoft Kadence MCP
 * Plugin URI:        https://github.com/TracsoftHQ/tracsoft-kadence-mcp
 * Description:       Tracsoft's hardened build of MCP Abilities — Kadence. Gives an MCP agent access to Kadence Blocks, Kadence Blocks Pro, Kadence Pro and the Kadence theme through the WordPress Abilities API. Everything is off by default, and writing needs its own capability that nobody gets automatically. Based on mcp-abilities-kadence by Joost Adams.
 * Version:           1.27.1.1
 * Requires at least: 6.9
 * Requires PHP:      7.4
 * Author:            Tracsoft (based on work by Joost Adams)
 * Author URI:        https://tracsoft.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       mcp-abilities-kadence
 * Update URI:        https://github.com/TracsoftHQ/tracsoft-kadence-mcp
 *
 * @package Kadence_MCP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * Tracsoft: refuse to load next to the original mcp-abilities-kadence plugin.
 * Both declare the same constants and classes; the original sorts first in
 * the plugin load order, so if its constant already exists we stop here and
 * tell the admin instead of letting PHP fatal on a duplicate class.
 */
if ( defined( 'KADENCE_MCP_VERSION' ) ) {
	add_action(
		'admin_notices',
		static function () {
			if ( ! current_user_can( 'activate_plugins' ) ) {
				return;
			}
			echo '<div class="notice notice-error"><p>'
				. esc_html__( 'Tracsoft Kadence MCP is not running because the original "MCP Abilities — Kadence" plugin is active. Delete the original (this removes its settings and capabilities), then deactivate and reactivate Tracsoft Kadence MCP and set it up again.', 'mcp-abilities-kadence' )
				. '</p></div>';
		}
	);
	return;
}

/*
 * Version scheme: upstream version plus a fourth Tracsoft patch number
 * (1.27.1.1 = upstream 1.27.1, first Tracsoft release). version_compare()
 * orders 1.27.1.1 above 1.27.1, and a later upstream merge to 1.28.0 starts
 * again at 1.28.0.1.
 */
define( 'KADENCE_MCP_VERSION', '1.27.1.1' );
define( 'KADENCE_MCP_FILE', __FILE__ );
define( 'KADENCE_MCP_PATH', plugin_dir_path( __FILE__ ) );
define( 'KADENCE_MCP_BASENAME', plugin_basename( __FILE__ ) );

/**
 * De namespace waaronder alle abilities van deze plugin vallen.
 *
 * Staat als constante vast: de dedicated server filtert hierop, de
 * instellingen groeperen hierop, en de capability-controle leest hem.
 */
define( 'KADENCE_MCP_NAMESPACE', 'kadence' );

require_once KADENCE_MCP_PATH . 'includes/class-kadence-mcp-capabilities.php';
require_once KADENCE_MCP_PATH . 'includes/class-kadence-mcp-settings.php';
require_once KADENCE_MCP_PATH . 'includes/class-kadence-mcp-skill.php';
require_once KADENCE_MCP_PATH . 'includes/class-kadence-mcp-inventory.php';
require_once KADENCE_MCP_PATH . 'includes/class-kadence-mcp-profielen.php';
require_once KADENCE_MCP_PATH . 'includes/class-kadence-mcp-sjablonen.php';
require_once KADENCE_MCP_PATH . 'includes/class-kadence-mcp-query.php';
require_once KADENCE_MCP_PATH . 'includes/class-kadence-mcp-registry.php';
require_once KADENCE_MCP_PATH . 'includes/class-kadence-mcp-server.php';
require_once KADENCE_MCP_PATH . 'includes/class-kadence-mcp.php';

/**
 * Activering.
 *
 * Bewust op topniveau geregistreerd — binnen een hook vuurt hij niet.
 *
 * Er is GEEN deactivation hook. Deactiveren hoort de rollen ongemoeid te
 * laten; het opruimen gebeurt in uninstall.php. Zie de toelichting bij
 * Kadence_MCP_Capabilities::on_uninstall().
 */
register_activation_hook( __FILE__, array( 'Kadence_MCP_Capabilities', 'on_activate' ) );

/**
 * Updates vanaf GitHub, via plugin-update-checker.
 *
 * De repo is publiek, dus er is geen token nodig: geen setAuthentication(), geen
 * constante in wp-config.php. Zou de repo ooit priv\u00e9 worden, dan is dat het
 * moment om die er alsnog bij te zetten — zonder token geeft GitHub dan een 404
 * en verschijnt er stil nooit meer een update.
 *
 * enableReleaseAssets() laat PUC de ZIP pakken die de release-workflow bouwt, in
 * plaats van de automatisch gegenereerde zipball van de tag. Die zipball is de
 * hele repo, inclusief .github/, en dat hoort niet op een klantserver.
 *
 * setBranch('main') schakelt release-detectie NIET uit: PUC behandelt main en
 * master als "eerst de laatste release, dan de hoogste versietag, en pas als die
 * er geen van beide zijn de branch zelf".
 */
function kadence_mcp_register_updater() {
	$puc = KADENCE_MCP_PATH . 'vendor/plugin-update-checker/plugin-update-checker.php';

	if ( ! is_readable( $puc ) ) {
		return;
	}

	require_once $puc;

	$checker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
		'https://github.com/TracsoftHQ/tracsoft-kadence-mcp/',
		KADENCE_MCP_FILE,
		'tracsoft-kadence-mcp'
	);

	$checker->setBranch( 'main' );

	/*
	 * Tracsoft hardening: only a published GitHub release that carries the
	 * zip built by the release workflow can become an update. PUC's default
	 * fallbacks (any version tag's zipball, then the head of main) are
	 * removed, so an unreleased commit or a stray tag never reaches a site,
	 * and REQUIRE_RELEASE_ASSETS refuses a release that lacks the built zip.
	 */
	$checker->getVcsApi()->enableReleaseAssets(
		'/^tracsoft-kadence-mcp\.zip$/',
		\YahnisElsts\PluginUpdateChecker\v5p6\Vcs\Api::REQUIRE_RELEASE_ASSETS
	);

	add_filter(
		$checker->getUniqueName( 'vcs_update_detection_strategies' ),
		static function ( $strategies ) {
			$latest_release = \YahnisElsts\PluginUpdateChecker\v5p6\Vcs\Api::STRATEGY_LATEST_RELEASE;

			return isset( $strategies[ $latest_release ] )
				? array( $latest_release => $strategies[ $latest_release ] )
				: array();
		}
	);
}
add_action( 'plugins_loaded', 'kadence_mcp_register_updater', 0 );

/**
 * Start de plugin.
 *
 * Op 'plugins_loaded' zodat Kadence, de Abilities API en de MCP Adapter
 * geladen zijn voordat er iets wordt bekeken of geregistreerd. Er gebeurt
 * bij het inladen van dit bestand bewust niets anders dan definiëren.
 */
add_action( 'plugins_loaded', array( 'Kadence_MCP', 'boot' ) );
