<?php
/**
 * Capabilities.
 *
 * Gemodelleerd naar Gravity Forms: granulaire capabilities per groep, plus
 * één hoofdsleutel die alles opent. Dat maakt een apart MCP-account mogelijk
 * dat precies deze rechten heeft en verder niets — de opzet die Gravity Forms
 * aanraadt voor zijn eigen MCP-server.
 *
 * @package Kadence_MCP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Beheert de capabilities van de plugin.
 */
class Kadence_MCP_Capabilities {

	/**
	 * Leesabilities uitvoeren.
	 */
	const VIEW = 'kadence_mcp_view';

	/**
	 * Het instellingenscherm zien en opslaan.
	 */
	const MANAGE = 'kadence_mcp_manage';

	/**
	 * Schrijfabilities uitvoeren.
	 *
	 * Bewust los van VIEW. Een account dat alleen leest hoort niet per ongeluk
	 * te kunnen schrijven doordat er een ability bijkomt, en een rol die dit
	 * niet heeft kan geen enkele schrijfactie starten — ongeacht welke tools
	 * er in het instellingenscherm aanstaan.
	 *
	 * Wordt bij activering aan NIEMAND toegekend, ook niet aan de beheerder.
	 * Wie wil schrijven kent hem bewust toe.
	 */
	const WRITE = 'kadence_mcp_write';

	/**
	 * Hoofdsleutel. Opent elke capability van deze plugin.
	 *
	 * Tegenhanger van gform_full_access. Bedoeld voor een rol die je bewust
	 * volledige MCP-toegang geeft zonder dat je bij elke nieuwe ability de
	 * rol opnieuw moet bijwerken.
	 */
	const FULL_ACCESS = 'kadence_mcp_full_access';

	/**
	 * Maak de capabilities zichtbaar in rechtenplugins.
	 *
	 * Zonder dit bestaat kadence_mcp_write voor Members niet, want die leidt
	 * zijn lijst af uit capabilities die ergens in gebruik zijn — en deze is
	 * bewust aan niemand toegekend. Je zou hem dan handmatig moeten intypen op
	 * precies de plek waar je hem hoort te kunnen kiezen.
	 *
	 * Twee haken, want Members biedt ze allebei: de action om ze netjes te
	 * registreren mét label, en het filter als vangnet voor oudere versies.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'members_register_caps', array( __CLASS__, 'registreer_bij_members' ) );
		add_filter( 'members_get_capabilities', array( __CLASS__, 'vul_members_lijst_aan' ) );
	}

	/**
	 * De capabilities met hun leesbare label.
	 *
	 * @return array<string,string>
	 */
	public static function labels() {
		return array(
			self::VIEW        => __( 'Kadence MCP: leestools uitvoeren', 'mcp-abilities-kadence' ),
			self::MANAGE      => __( 'Kadence MCP: instellingen beheren', 'mcp-abilities-kadence' ),
			self::WRITE       => __( 'Kadence MCP: blokken wijzigen', 'mcp-abilities-kadence' ),
			self::FULL_ACCESS => __( 'Kadence MCP: volledige toegang', 'mcp-abilities-kadence' ),
		);
	}

	/**
	 * Registreer bij Members, met label en groep.
	 *
	 * @return void
	 */
	public static function registreer_bij_members() {
		if ( ! function_exists( 'members_register_cap' ) ) {
			return;
		}

		foreach ( self::labels() as $naam => $label ) {
			members_register_cap(
				$naam,
				array(
					'label' => $label,
					'group' => 'custom',
				)
			);
		}
	}

	/**
	 * Vangnet: voeg de namen toe aan de lijst die Members toont.
	 *
	 * @param array $caps De bestaande lijst.
	 *
	 * @return array
	 */
	public static function vul_members_lijst_aan( $caps ) {
		if ( ! is_array( $caps ) ) {
			return $caps;
		}

		return array_values( array_unique( array_merge( $caps, array_keys( self::labels() ) ) ) );
	}

	/**
	 * Alle capabilities die deze plugin definieert.
	 *
	 * @return string[]
	 */
	public static function all() {
		return array( self::VIEW, self::MANAGE );
	}

	/**
	 * Alle capabilities, inclusief die niet automatisch worden toegekend.
	 *
	 * @return string[]
	 */
	public static function all_including_write() {
		return array( self::VIEW, self::MANAGE, self::WRITE, self::FULL_ACCESS );
	}

	/**
	 * Mag de huidige gebruiker dit?
	 *
	 * De hoofdsleutel opent alles. Verder is het een gewone capability-controle,
	 * dus een rechtenplugin als Members kan hem per rol toekennen zonder dat
	 * deze plugin daar iets van hoeft te weten.
	 *
	 * @param string $capability Een van de constanten hierboven.
	 *
	 * @return bool
	 */
	public static function current_user_can( $capability ) {
		if ( current_user_can( self::FULL_ACCESS ) ) {
			return true;
		}

		return current_user_can( $capability );
	}

	/**
	 * Tracsoft hardening: may the current user read this post through MCP?
	 *
	 * One check for every tool that loads a post by ID, so a read or a dry-run
	 * preview never shows a draft, private or trashed post the user could not
	 * open in WordPress. read_post ignores post passwords, so a
	 * password-protected post additionally needs edit_post.
	 *
	 * Keeps upstream's kadence_mcp_can_read_post filter so a site can tighten
	 * the rule further.
	 *
	 * @param WP_Post|object|int $post A post, a raw $wpdb row with an ID, or a post ID.
	 *
	 * @return bool
	 */
	public static function can_read_post( $post ) {
		if ( ! $post instanceof WP_Post ) {
			// Some tools loop over raw $wpdb rows (stdClass with ID); resolve
			// those, and plain IDs, to a real post so the password is known.
			$post = get_post( is_object( $post ) && isset( $post->ID ) ? (int) $post->ID : $post );
		}

		if ( ! $post instanceof WP_Post ) {
			return false;
		}

		/** This filter is documented in includes/abilities/class-kadence-mcp-abilities-content.php */
		$allowed = (bool) apply_filters( 'kadence_mcp_can_read_post', current_user_can( 'read_post', $post->ID ), $post->ID );

		if ( $allowed && '' !== (string) $post->post_password && ! current_user_can( 'edit_post', $post->ID ) ) {
			$allowed = false;
		}

		return $allowed;
	}

	/**
	 * Tracsoft hardening: the Kadence object types MCP may export, import and
	 * write meta on.
	 *
	 * Upstream accepted any post type starting with kadence_, which also
	 * matches Kadence's form and other add-on types, whose meta holds
	 * notification recipients and webhook settings.
	 *
	 * @return string[]
	 */
	public static function entity_types() {
		return array(
			'kadence_navigation',
			'kadence_header',
			'kadence_element',
			'kadence_query',
			'kadence_query_card',
			'kadence_vector',
			'kadence_custom_svg',
		);
	}

	/**
	 * Is this a Kadence object type MCP may work with?
	 *
	 * @param string $post_type The post type.
	 *
	 * @return bool
	 */
	public static function is_entity_type( $post_type ) {
		return in_array( (string) $post_type, self::entity_types(), true );
	}

	/**
	 * The error returned when can_read_post() says no.
	 *
	 * Deliberately the same wording whether the post is a draft, private or
	 * password-protected, so the reply does not reveal more than "no access".
	 *
	 * @param int $post_id The post ID.
	 *
	 * @return WP_Error
	 */
	public static function read_denied( $post_id ) {
		return new WP_Error(
			'kadence_mcp_post_forbidden',
			sprintf(
				/* translators: %d: post ID. */
				__( 'No read access to post %d.', 'mcp-abilities-kadence' ),
				(int) $post_id
			),
			array( 'status' => 403 )
		);
	}

	/**
	 * Ken de capabilities toe aan de beheerdersrol bij activering.
	 *
	 * Alleen aan administrator, en alleen de twee granulaire. De hoofdsleutel
	 * krijgt niemand automatisch: die is er om bewust uit te delen, en een
	 * capability die je nooit hebt toegekend kan ook nooit onbedoeld ergens
	 * vandaan komen.
	 *
	 * @return void
	 */
	public static function on_activate() {
		$rol = get_role( 'administrator' );

		if ( ! $rol ) {
			return;
		}

		foreach ( self::all() as $capability ) {
			$rol->add_cap( $capability );
		}
	}

	/**
	 * Haal de capabilities weg bij VERWIJDEREN van de plugin.
	 *
	 * Bewust niet bij deactivering. WP_Role::remove_cap schrijft persistent
	 * naar de database, terwijl on_activate() alleen VIEW en MANAGE teruggeeft
	 * en alleen aan administrator. Een rol 'mcp-agent' met kadence_mcp_view —
	 * precies de opzet die het instellingenscherm aanraadt — zou na één
	 * deactiveer-activeercyclus leeg zijn, en elke tool zou zonder melding
	 * permission denied geven. Gravity Forms raakt bij deactivering dan ook
	 * geen enkele capability aan (gravityforms.php:862-866).
	 *
	 * Bij verwijderen is het wél juist om ze overal weg te halen.
	 *
	 * @return void
	 */
	public static function on_uninstall() {
		$capabilities = self::all_including_write();

		foreach ( wp_roles()->role_objects as $rol ) {
			foreach ( $capabilities as $capability ) {
				if ( $rol->has_cap( $capability ) ) {
					$rol->remove_cap( $capability );
				}
			}
		}
	}
}
