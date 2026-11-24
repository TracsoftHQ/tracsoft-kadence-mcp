=== Tracsoft Kadence MCP ===
Contributors: tracsoft, joostadams
Tags: mcp, kadence, abilities, ai
Requires at least: 6.9
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.27.1.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Read and make targeted changes to Kadence Blocks, Kadence Blocks Pro, Kadence Pro and the Kadence theme via MCP.

== Description ==

Fifty-two abilities for the WordPress Abilities API, with which an MCP assistant
can read a site's Kadence structure and make targeted changes to it: which
blocks exist, which attributes they have, how a page is built,
which headers and elements exist, and which color palette applies.

Twenty-four of them are read-only. Twenty-eight write: set-attributes,
style-blocks, set-text, insert-blocks, remove-blocks, move-blocks, duplicate-blocks,
replace-block, create-page, create-entity, create-post, update-post, set-page-status, connect-translation, create-query, create-query-card,
set-query, sync-query-facets, set-card-layout, set-entity-meta,
set-global-typography, set-theme-mods, set-site-css, upload-media, replace-colors, update-entity-content,
trash-post and import-entity. All twenty-eight require a capability of their own (kadence_mcp_write, which after installation is granted to
no one), edit rights on the post according to WordPress, and a token
from a preceding check step. After every write action the post is
read back and compared with what was intended.

Everything is off after installation; you turn on, per tool, what you want to offer, under
Kadence Blocks > MCP.

== Installation ==

1. Upload the folder to `/wp-content/plugins/`
2. Activate the plugin
3. Go to Kadence Blocks > MCP and turn on what you want to offer

Requires the WordPress Abilities API and the MCP Adapter.

== Changelog ==

= 1.27.1.4 (Tracsoft) =
* New: get-theme-mods and set-theme-mods. Claude can now build the Kadence theme's header and footer over MCP: the header builder (desktop and mobile), the footer builder and its column counts, the logo and how it shows, the header and mobile buttons, and the header and footer HTML. A fixed list of keys, each with a shape check: a builder changes per slot, a slot must exist, an item must be one the builder offers, and an item may sit in only one place. Two steps with a token, read back afterwards. Theme mods have no revisions, so the before field is the way back. Needs kadence_mcp_write and edit_theme_options.
* New: upload-media. Puts a JPG, PNG, GIF or WebP from the call (base64, at most 2 MB) into the media library, checked on content rather than extension; no SVG. For logos and small images that aren't online anywhere, on hosts whose firewall refuses a PUT upload link. Needs kadence_mcp_write and upload_files.

= 1.27.1.3 (Tracsoft) =
* New: a switch under Kadence Blocks → MCP, "Allow administrators to use the write tools". Saving it gives the Administrator role kadence_mcp_write, or takes it away, so a site no longer needs WP-CLI or a role plugin for that. It is off after installation, shows what the role holds right now, changes no other role, and can only be changed by a user who can manage site options.
* The Status table on the same screen shows whether administrators can write.

= 1.27.1.2 (Tracsoft) =
* English: every message, setting, tool description and error is now in English, through an English translation (languages/mcp-abilities-kadence-en_US.mo) of all 948 upstream strings, loaded on every site whose language is not Dutch. Values the plugin returns or accepts as data (such as the verdicts veilig, riskant and blokkeer, and the pad: path prefix) stay as they are, with an English gloss in the descriptions.
* English: the Claude skill (skill/kadence-blocks/SKILL.md), README, readme and developer notes are translated; 33 Dutch texts written directly in the code (block notes, field labels, log messages) are now English.
* New: bin/build-translations.py to update the translation after an upstream merge.

= 1.27.1.1 (Tracsoft) =
* Fork: renamed to Tracsoft Kadence MCP (slug tracsoft-kadence-mcp); updates now come only from published releases of TracsoftHQ/tracsoft-kadence-mcp, and only as the built release zip. Refuses to run next to the original plugin.
* Security: write tools now require kadence_mcp_write at the permission layer, so view-only users no longer get dry-run previews or tokens.
* Security: every tool that loads a post by ID now checks read access, including post passwords (describe-post-type, describe-query, update-post, build previews, query usage lists, site scans).
* Security: set-site-css requires edit_css, as core does for Additional CSS.
* Security: set-page-status requires publish rights for private; create-query and create-query-card require publish rights and a readable source.
* Security: export, import and set-entity-meta work only on the seven Kadence object types MCP manages, not every kadence_ type (e.g. forms).
* Security: writing custom SVG content requires unfiltered_html.
* Security: create-post and update-post check assign_terms per taxonomy.
* CI: GitHub Actions pinned to commit SHAs.

= 1.27.1 =
* Fix: create-post and update-post now see ACF field groups attached to a term (a location rule on a tag, category or custom taxonomy). The fields were looked up with only the post type, and ACF always returns false for a term rule without a post_id; such a field was rejected with "belongs to no field group". The screen for ACF now carries the post ID and the terms as they will be after the call (what the input specifies per taxonomy replaces the current terms). Observed on Ondernemersfonds Utrecht: "Hero afbeelding Nieuws" (post_tag:nieuws) could not be written.

= 1.27.0 =
* New: kadence/connect-translation. Links an existing post in WPML as a translation of its original, via the official hook wpml_set_element_language_details; singly or as a list (items, one token). Needed because a translation created outside the translation editor (WordPress importer, create-post) stays unlinked: WPML Export and Import only links if the original carries the import fields in the database, and those exist only in the export file. Refuses an original that already has a different translation in that language, a post that itself already has translations, a non-translatable post type and an inactive language. Reads back via WPML (trid, language and wpml_object_id). Touches no content, meta or status.

= 1.26.1 =
Fixes from the 2026-09-28 review. Only turn on the four new write abilities with this version.
* trash-post: riskant, without a token and also not overridable with ignore_usages, for an active element on a hook (such as the footer on replace_footer), the front page, the posts page, the privacy page and a scan that failed or was limited (objections). Until now the active footer got "veilig". find-usages reports truncated.
* Post meta is written slashed everywhere (Kadence_MCP_Inventory::schrijf_meta). In thirteen places that happened without wp_slash, causing backslashes to silently disappear, including when copying a query or card.
* import-entity: only meta keys with the prefix _kad_; status and slug are in the token; publishing checks publish_posts; an existing object with the same title gives riskant (use target_id). meta_ids_to_check lists the IDs per key, including from "ids":[…] (an element's page conditions).
* Creation tokens (create-page, create-post, create-entity, create-query, create-query-card) work once: the same call made twice produced two objects. A token that already produced an object returns kadence_mcp_token_used, unless that object is in the trash.
* get-raw-markup cuts on a character boundary (mb_strcut); offset and next_offset count bytes.
* site-fingerprint now really compares local and staging: the whole site URL (scheme and port) and the uniqueIDs in class names are normalized, meta is unpacked for the hash. The plugin list only with activate_plugins.
* Skill and server instructions: only style-blocks and set-text accept expect_modified.
* tests/smoke.php (wp eval-file) calls every read ability; CI lints on PHP 7.4 and 8.3; the release checks KADENCE_MCP_VERSION and Stable tag in addition to the header.

= 1.26.0 =
* New: kadence/check-access. What this account may read, edit, create and publish per post type, and whether edit_theme_options and unfiltered_html are missing — up front, instead of finding out afterwards from a silent failure. list-entities now also counts posts that exist but are not readable (unreadable).
* New: kadence/audit-colors and kadence/replace-colors. For each color value, where it is used (block attributes, Kadence meta, typography), with hex values that equal a palette color reported separately; and converting with a map {old: new}, one revision per post. Deliberately skipped, with a reason: a var() that has an opacity attached (including shadows as an object and as a list), a palette name in a non-Kadence block, a non-hex value in a Gravity Forms block.
* New: kadence/site-fingerprint. Hashes of palette, typography (saved or default), fonts, taxonomies, terms with description, Kadence objects (by title, without IDs and domain), plugins and reading settings, to compare two sites structurally; with detail, the values themselves.
* New: kadence/export-entity and kadence/import-entity. A Kadence object with content and _kad meta as a package, with the references that need a map; placed as a new object or over an existing one, with replace, post_map, media_map and term_map, and read back byte for byte. Kadence's own export/import strips backslashes from the content.
* New: kadence/update-entity-content (replace text in the SVG of a vector or the JSON of a custom SVG, with an expected count and a loss check) and kadence/trash-post (trash after a usage check; never permanent).
* validate-write, set-attributes, style-blocks, get-raw-markup and inspect-post accept a path ("pad:0.1.0.2") instead of a uniqueID, for core blocks and the Gravity Forms form block. inspect-post gives blocks without a uniqueID a path field.
* validate-write blocks a Kadence palette name (palette5) in a color attribute of a non-Kadence block; verify-markup reports existing cases (palette_names) and Kadence blocks without a uniqueID (missing_unique_id, with a suggestion in Kadence form).
* get-global-styles: typography_sources (saved or Kadence default), fonts (the font faces of Kadence Custom Fonts, with a warning for a family or weight without a file), and in environment the child theme's version and an active page cache.
* create-entity for a vector predicts in advance what will remain of the SVG after Kadence's sanitizer and kses, and refuses on loss (accept_loss); after creation, elements are read back.
* set-entity-meta: items for the same change on multiple entities, with one token.
* find-post: unique_id, to find a block's counterpart on another site.
* create-post: date and an explicitly empty list of terms ({"category": []}); the proposal reports what WordPress adds on its own, the response what it actually added.
* create-query: preset no-filters or simple, with card_id, following Kadence's templates.
* check-bindings warns about a dynamic background in a loop without inQueryBlock (every card the same photo).
* replace-block writes the link of a Section (kb-section-has-link and the link overlay, including dynamic); kadence/search is marked as not buildable, with an explanation.
* For maxWidth on a Section, validate-write says per breakpoint what it becomes (the height in a vertical parent, including with vertical-reverse).
* describe-block flags block-support attributes whose name resembles a setting of the block's own (style versus listStyle), knows allOption on the dropdown and the checkboxes, and the values of widthType (button) and slideFrom/widthType (off-canvas).
* get-raw-markup: reading in chunks with offset, and truncated, next_offset and total_chars as separate fields.
* Corrected: find-usages also found "id":2 in "id":24 and counted the sequence number of tabs and slides as a reference.
* The error message for an expired token names the usual cause (another write action on the same post) and how to avoid it.

= 1.25.0 =
* New: kadence/update-post. The counterpart of create-post for an existing post: title, slug, excerpt, order, featured image, terms (replaced per named taxonomy) and ACF fields (by field key), with the same checks against the post type. The proposal gives before and after per part; ACF fields, terms and the image have no revisions. Needed to move the content of services and markets to another environment: create-post could only create.
* New: kadence/move-blocks. Moves blocks within a post (before, after, or as the first or last child) while preserving their uniqueID, so that per-block CSS and references keep working. Refuses if the target is inside a moved block, if a Section moves out of, into or between Row Layouts (column count; swapping places within its own row is allowed), if a slide or tab moves to another parent (slideCount, tab titles), and if the block's parent rule does not allow it (also for kadence/slide, pane and repeatertemplate, which don't state this in their registration). Checks before writing that no block disappears.
* Corrected: set-text on a kadence/listitem replaced the entire content of the <li>, making the link and icon disappear. Now only the text in .kt-svg-icon-list-text is replaced; if that span is missing (already flattened markup), it refuses with a pointer to replace-block.
* Corrected: set-entity-meta, set-card-layout and set-query reported a mismatch on read-back when a number came back as text (240 versus "240", true versus "1"): that is how WordPress stores scalar post meta. The comparison now treats those as equal.
* replace-block no longer refuses on a Section with a background color: background sets no class in the markup (read across 88 Sections). Still refused: a kadence/single-icon with a link, because the profile does not know the <a> around the span and rebuilding would silently remove the link.
* Dynamic HTML profile: h1Typography through h6Typography only render with enableH1 through enableH6; textTypography and textColor also apply to every <p> in the block.

= 1.24.0 =
* New: kadence/create-post. Creates a post of any public post type that is in the REST API — a post or a custom post type such as a service or market — with title, slug, excerpt, order, featured image, terms, ACF fields and optionally block markup. One ability for all post types: whatever differs per type is read from WordPress and checked on each call. An excerpt or image on a type that does not support it, a taxonomy not attached to the type, a term that does not exist, an ACF field outside the type's field groups, an unknown subfield in a repeater or a relationship to a post of a disallowed type is rejected, with the names that are allowed. ACF fields are saved by field key, so that ACF recognizes them on a new post. Pages, attachments and Kadence's post types stay with their own ability. A draft by default; two steps with a token; read back afterwards.

= 1.23.1 =
* Corrected: describe-post-type returned an error ("output[sample] is not of type object") for a post type without published posts. sample is null in that case, and the output schema did not allow that. Precisely in a new environment, where the post types exist but are still empty, the tool was therefore unusable.

= 1.23.0 =
* New: kadence/create-entity. Creates an empty kadence_navigation, kadence_header or kadence_element with ALL the settings Kadence registers for that post type, at their defaults — otherwise set-entity-meta rejects them afterwards, because a missing key may be a typo. Non-default settings go straight into meta, registered keys only. It creates a kadence_vector from an SVG, via Kadence's own route and therefore through Kadence's sanitizer. A draft by default: a published element on replace_footer immediately replaces the footer of the whole site. Together with post_map in prepare-import, a header with navigations, or a footer element, can now be moved from one environment to another without wizards.
* validate-write blocks gutter and rowGap on a Section if the corresponding gutterVariable or rowGapVariable is not "custom" there: Kadence then uses a preset (1rem) and ignores the number. Measured: twenty Sections with gutter all got 1rem. For gutter in a vertical Section it adds that rowGap is the space between the children.
* validate-write warns about flexBasis (applies to all children, on the parent), about background "transparent" on a button (the editor then shows the theme button; rgba(0,0,0,0) works everywhere), and about the old borderWidth on a Section (the border then comes without a color).
* set-entity-meta warns about spacing on a navigation as long as spacingUnit is set to em, Kadence's default: 20 became 340 px.
* generate-section fixes the spacing in a Section itself: gutterVariable or rowGapVariable to "custom" where a number is set, and gutter in a vertical Section becomes rowGap. Each correction is listed in the new notes field.
* generate-section can build kadence/vector, kadence/navigation and kadence/navigation-link, read from markup that Kadence wrote. The list for the custom recipe now comes from the profiles and names all buildable blocks; it named five, while there were also postgrid, dynamichtml, slider and the query blocks.

= 1.22.0 =
* New in prepare-import: post_map. Blocks that refer to another post with their id — kadence/navigation, kadence/header, kadence/query, kadence/query-card, kadence/vector, kadence/advanced-form, and a kadence/navigation-link with kind post-type — get the new ID from the map; for the menu item, the url becomes the permalink of the new post. Without this map, a header with navigations, or a mega menu with smaller menus inside it, could not be moved to another environment: post IDs differ per site and replace cannot convert numbers.
* prepare-import now also reports references to posts: if the ID is missing on this site, or it is a different post type (a navigation block with the ID of a page), that is an unresolved warning.
* media_map now also knows Kadence's background pairs: bgImg with bgImgID (Row Layout, Section) and overlayBgImg with overlayBgImgID. These were not recognized, so a background photo came along with the source site's ID.
* Corrected: media detection mistook a menu item's id and url for an attachment, and media_map would convert such a menu item as a photo.

= 1.21.0 =
* New: kadence/prepare-import. insert-blocks only accepted markup from generate-section, so markup the editor wrote itself — tabs, a slider, anything the generator doesn't know — or markup from another environment could not go in, while that is precisely the markup most worth trusting. prepare-import writes nothing: it checks (only blocks, existing block types, round trip, classes against attributes, schema and value lists, counters such as slideCount against the number of child blocks), gives every uniqueID the target post's prefix, and reports what differs per site: links to another domain, media, custom SVG icons, terms, palette colors with their value on the target site, and custom CSS classes. Converting only explicitly, with replace, media_map and term_map; nothing is guessed. veilig yields a token for insert-blocks; a reference that does not exist yields no token without accept_warnings.
* Knowing and building are separated. A block profile can now have bouwbaar false: the plugin then knows the values and the derived markup, but the generator does not build it. kadence/tabs is listed that way — the wrapper and the title list follow from attributes in a way that has not been observed enough to generate.
* New profiles: kadence/slider and kadence/slide (Advanced Slider) can be built and checked, read from the save() of Blocks Pro 2.8.19 and re-measured in the editor: all generated slides are valid there. kadence/postgrid is self-closing and knows its layout values.
* Derived markup that is not on the outermost element is now also checked: a slide's alignment is on the second div, and the overlay is its own element that only exists if there is an overlay color. Anyone who sets only backgroundOverlay via an attribute gets the color but not the div.
* Value lists extended: verticalAlignment on a Section also knows space-between, space-around and space-evenly (these were wrongly blocked), hAlign and vAlign on a button row, inheritStyles on a button, and the slider options sliderType, loopType, arrowPosition, arrowStyle, dotStyle and heightType. describe-block now shows such lists as known_values instead of "don't invent a value here".
* Attributes that the render ignores are reported, in validate-write and describe-block: slidesScroll on the slider (the render always writes 1) and className on Advanced Text.
* The generator fills in counters such as slideCount itself, and takes kbVersion from the profile where the editor writes something other than 2 (the slider gets 3).

= 1.20.2 =
* The settings screen now says where the MCP Adapter comes from. "Missing" was a dead end: the adapter is not in the plugin directory, so searching under Plugins > Add New Plugin returns nothing, and of the two downloads on GitHub only the file under Assets works — the source code lacks vendor/ and with it the autoloader. If the Abilities API is missing, the screen also names this site's WordPress version, because as of 6.9 it is in core.

= 1.20.1 =
* The minimum goes from WordPress 6.8 to 6.9. As of 6.9 the Abilities API is in core, which drops a requirement you would otherwise have to add as a separate plugin. On 6.8 the plugin only worked with that extra plugin; explaining that in documentation costs more than it's worth.
* The documentation did not say where to get the plugin. Anyone who opened the repo ended up at the green Code button, which gives the state of main in a folder with -main appended — not a released version, and a folder name WordPress does not expect. The README now points to the ZIP under Releases, which the workflow builds per version tag.
* That same workflow now leaves CONTEXT.md out of the ZIP. Those are design decisions for whoever modifies the plugin, and they belong in the repo, not on a production site. The skill folder does go along: the plugin itself serves it as a download under Kadence Blocks > MCP, and without those files that screen reports that the skill is missing.
* Too little was said about the MCP Adapter. It is not in the plugin directory, so searching under Plugins > Add New Plugin returns nothing; it has to be the ZIP from its own Releases page. A vendored copy inside another plugin counts for the class check, but the adapter itself advises against that form and will remove it in a later version — so that is not something to build on.

= 1.20.0 =
* A "Select all N" per group on the settings screen. Checking thirty-five boxes by hand is work nobody should have to do, and it invites leaving things half done. Deliberately per group and not one button for the whole screen: reading and writing are different decisions, and one checkbox that turns both on is exactly the click you don't want. The group checkbox goes to indeterminate as soon as a group is partly on — an empty checkbox above a partly checked list reads as "nothing is on", and then you click it to see what happens.
* This only reduces the surface; the lock remains the capability. Turning on all write tools still does nothing for an account without kadence_mcp_write.

= 1.19.0 =
* The plugin updates itself from GitHub, via plugin-update-checker. The repo is public, so no token is needed; the release workflow builds a ZIP so that the tag's automatically generated zipball — which also contains .github/ — never ends up on a site.
* The documentation no longer matched the code. Four places still described six writers while there are seventeen, including the server description that every MCP client loads in every session: an agent therefore started out convinced that writing was an exception. That is exactly the error that was already corrected once in 1.8.0 for the plugin header and README, and it had crept back in at the place where it costs the most.
* CONTEXT.md claimed that writers only touch post_content and that options are never touched. Since set-global-typography and set-site-css that is incorrect. There is now a distinction by what can be rolled back: post content gets a revision, post meta does not, and site-wide settings affect every page.
* References to a specific client site have been removed from the code comments. The origin notes remain — having been read from real markup is the reason the block profiles are correct — but without saying whose it was.

= 1.18.0 =
* style-blocks and set-text now accept expect_modified as an alternative to the token. The token did two things: let a human see the change, and prevent writing to a version that no longer exists. For these two writers the first is weak — there is a schema, every value has already been checked — while the cost is high: on 2026-09-14, aligning 31 hero headings took 62 calls instead of 31. expect_modified keeps the protection against stale writes fully intact and does it in one call. Deliberately NOT for remove-blocks, replace-block, insert-blocks and create-page: those change the structure, and no schema can tell you whether that was the intent.
* New: set-global-typography. The only place where you can do something about headings without a size of their own. This site's global typography had only desktop values, and without a mobile value Kadence does not scale along — a phone then gets the desktop size. Fixed list of keys and no prefix check, because theme mods also contain the header, the colors and the layout. What you pass in is merged, two levels deep: specifying only size.mobile leaves family, weight and size.desktop in place.
* New: set-site-css, for Appearance > Customize > Additional CSS. The Custom CSS field on a Kadence header does exist but is not served on the front end; that was measured and produced CSS that ended up nowhere. This is the only place in WordPress itself that loads on every page.
* generate-section knows kadence/icon and kadence/single-icon. Reason: a removed icon turned out to be impossible to get back from anywhere — there was not a second one on the whole site to copy, and without a profile the generator could not build it. A block you can delete but not restore is a gap in the toolset.
* The profiles know {ATTR:naam}, so that a block that puts an attribute value literally in its markup becomes expressible. kadence/single-icon carries the icon name as data-name on an empty span; without that span Kadence renders nothing. The value goes through esc_attr and falls back to the default value from block.json.
* validate-write warns about maxWidth on a Section. Kadence adds margin-left/right: auto there, and auto margins on a grid item set that item to fit-content — the block then shrinks to the width of its text. Measured: grid track 88.78px, maxWidth 88px, result 24px. Deliberately a note and not a block: outside a row, maxWidth does exactly what you expect.

= 1.17.0 =
* New: set-entity-meta. The styling of a navigation and the placement of an element are in post meta with the prefix _kad_, not in the block markup, and no ability could reach it. A menu that ended up on a black background therefore stayed black on black until someone fixed it in the editor. Two gates: only Kadence post types, and only keys that already exist on that post — Kadence writes its whole set on every save, so a missing key is a typo that would otherwise be silently saved and ignored. Post meta has no revisions, so the old value is in the response and the status says so.
* set-text can now also address blocks without a uniqueID, via match_text with the current text. Kadence hands out uniqueIDs, WordPress does not: a core/list-item in an accordion had none and was therefore unreachable by any write ability. Matching is on the full text, not on a part: with zero or more than one match nothing is written and you are told how many there were.
* The generate-section token no longer covers the literal string but the serialized block tree. An agent that copies the markup from a JSON response returns `\u002d\u002d` where there was `--`; the blocks are then identical, the string is not, and insert-blocks rejected it with "your proposal is different" without being able to say where. That stopped nothing dangerous, only something harmless. If an attribute or a text changes, the tree does change and the token still expires.

= 1.16.1 =
* Repair: set-page-status called mag_schrijven(), and that helper does not exist in the build class but in the query class. Every call produced a fatal error. The two checks are now written out in place.

= 1.16.0 =
* New: set-page-status. create-page could only set a status at CREATION, so a page that had been built as a draft could no longer be published afterwards without the editor — and therefore also could not be checked on the front end.

= 1.15.1 =
* Repair: create-page failed for every top-level page. The output schema required an object for parent, and that is null without a parent.

= 1.15.0 =
* New: create-page, replace-block and set-card-layout.
* insert-blocks with before and after now works at any depth instead of only at the top level, including the placeholders in innerContent.
* replace-block built up the text with the summarizing tekst_uit_blok(), thereby throwing away inline HTML and cutting off at 300 characters. There is now binnenhtml_uit_blok(), which returns the content inside the outermost tag verbatim.
* style-blocks reports class drift: for an attribute that classes are derived from (such as direction), it reports which class is missing, with the pointer to replace-block.

= 1.9.1 =
* Repair of a regression from 1.8.0: style-blocks reported an attribute as a mismatch when it dropped out because it equaled the default value. Setting maxWidth to ["","",""] produced "it was written, but something differs on read-back", while omitting it was exactly the intent. Such attributes now go into defaults, not into mismatch. That distinction matters: a notice that raises false alarms teaches you to ignore it.
* The skill explains which attribute controls a Section's alignment. That is counterintuitive: with direction vertical, verticalAlignment is about the height (justify-content) and justifyContent controls the width (align-items). Allowed values for verticalAlignment are top, middle, bottom and stretch; neither has an enum, so validate-write lets any text through.
* Correction in the skill and the error log: the claim that the class kb-section-dir-horizontal controls the display is incorrect. Measured in both directions, the attribute wins, and style-blocks can simply change direction on an existing block. That claim came from the review and was adopted without testing.

= 1.9.0 =
* generate-section knows the Query blocks: kadence/query, query-card, query-filter, query-filter-search, query-filter-reset, query-result-count, query-sort and query-pagination. Read from an existing query; they are all self-closing. Placing an existing Query Loop somewhere else is thereby one block with the id of the query post.
* Repair of the same pattern as with the color classes: the direction attribute on a Section derives three classes (kb-section-dir-, kb-section-md-dir-, kb-section-sm-dir-) that the generator did not write along. A Section with direction horizontal got the attribute neatly saved and stayed vertical, because the CSS hangs on the class. This was finding 3 from the review, now confirmed on real markup.
* A block without content and without a wrapper of its own is written self-closing, as WordPress itself does. That is necessary because kadence/query has two forms: in a page it only refers to a query post and is empty, in that query post it carries the whole layout.

= 1.8.0 =
Following a full review. The common thread: a check that comes back green because it tests the wrong thing.

* CRITICAL: insert-blocks and duplicate-blocks with position inside put a block OUTSIDE an empty container. The placeholder in innerContent ended up at index 0 instead of between the opening and closing tags, causing the block to be written before the opening tag. With a container that already had children it went fine, and precisely because of that it went unnoticed.
* CRITICAL: both read-back checks reported "written and read back" in that case, because they only checked whether the uniqueID appeared anywhere in the post — not under which parent. There is now a parent check: if the block is not under the specified container, an error follows with the actual parent included.
* CRITICAL: the recipes tekst, cta and the heading row of kolommen wrote a one-column row without the columns attribute. Kadence then assumes two columns and puts the content at half width, with an empty slot next to it. columns is now derived on every generated row from the number of children.
* Four of the six writers used serialize_blocks() instead of the plugin's own serialiseer(), causing the blank lines between all sections of the page to disappear on every write action. All six now do the same.
* The style-blocks read-back check skipped precisely the case that matters: an attribute that is entirely gone after saving. That is the "metadata was thrown away" bug in its purest form, and the notice read "written and read back".
* COL_LAYOUTS blocked five valid values, including "row" at every column count and "first-row" — the latter is used by this site's homepage.
* A rejected token now says WHAT is wrong. The message covered two very different situations ("the post has changed or your proposal is different"), and that ambiguity left duplicate-blocks broken for six versions: every call produced that message and it sounded like normal behavior. The token now consists of two halves — six characters for the post and modification date, the rest for the proposal — so the server can see which half differs.
* remove-blocks, set-attributes, style-blocks and set-text publish destructive: true. MCP clients use that annotation to decide whether to ask for confirmation; all six were set to false.
* The plugin header, README.md and CONTEXT.md said this plugin writes nothing. That has not been true since 1.0.0, and it is exactly the text on which someone decides whether kadence_mcp_write may be handed out.
* set-attributes called itself "THE ONLY WRITE ACTION" and generate-section claimed that insert-blocks only inserts at the top level — outdated since 1.5.0 and 1.7.0 respectively.

= 1.7.2 =
* Repair: the kolommen template set colLayout to "thirds" and "fourths". Those names do not exist. On the front end that went unnoticed because the kt-has-N-columns class determines the widths there, but the editor chooses its display based on colLayout and stacked all columns. Now "equal", as Kadence itself does it.
* validate-write blocks a colLayout that does not match the number of columns. In Kadence the valid names are only in JavaScript, not in block.json, so the ordinary type check let any text through.
* describe-block sets values_not_validated: true on every string attribute without an enum, with a note. Silence about the allowed values read as approval; now it says that there was nothing to check and that the value should be read off rather than invented.

= 1.7.1 =
* Skill only: the method for rebuilding a Figma design has been recorded, with the translation table that proved correct in practice and the two pitfalls when checking the numbers — measure on the right layer (Kadence puts padding on .kt-row-column-wrap and the column background on .kt-inside-inner-col), and a fullPage screenshot lies about a floating header.

= 1.7.0 =
* insert-blocks and duplicate-blocks know position "inside": blocks are then placed as a CHILD of an existing container instead of at the top level. With that, a copied accordion can finally land in a column, inside the background and the content width of that row; at the top level it sits outside them.
* The sting was in innerContent: that array carries a null in the place of every child block, and adding a child without also adding a null makes serialize_block() skip the last child. The null is now inserted just before the container's closing HTML. A self-closing block has no inside and is rejected.

= 1.6.1 =
* Repair: generated text blocks stayed black, even with color palette9 and colorClass theme-palette9. The color of a Kadence text block does not come from the attribute but from a class in the markup (has-theme-palette-9-color has-text-color), and the generator did not write that along. The block data was fine and it still displayed wrong — visible only by querying getComputedStyle in the browser. Now the classes are derived from colorClass and backgroundColorClass.

= 1.6.0 =
* generate-section now knows recipe "custom": the structure is then passed in via tree, as a nested list of nodes. Needed because four fixed templates don't cover a real design, and because assembling afterwards is not possible — insert-blocks only inserts at the top level, so getting a block into an existing column doesn't work. Whatever belongs in one section is built in one go.
* The way of generating stays fixed: the same wrapper markup read from real blocks, the same issuing of uniqueIDs (passing in your own ID is rejected), the same attribute check as validate-write, and the same parse and serialize round trip as the final check. Only the combination is free.
* tag and level are aligned with each other. advancedheading determines its tag from htmlTag (default "heading", falls back to level): an h3 in the markup with level 2 would render as h2 while it says h3.

= 1.5.0 =
* New: kadence/style-blocks writes attributes to multiple blocks in one save. set-attributes does one block per call, and styling a section quickly touches six blocks — that's fourteen calls, with each token expiring as soon as the previous step has changed post_modified. Now it's two, and one revision for the whole set.
* The checking is not duplicated: style-blocks calls validate_write() per block, so there is only one place with checking logic. All or nothing — if one block is blokkeer or riskant, no token is issued and nothing is written. A block that appears twice in the list is rejected.

= 1.4.1 =
* Repair: the remove-blocks proposal left the text field empty as soon as you specified a container. tekst_uit_blok() only reads the block's own innerHTML, and a Row Layout has none — the text is in the grandchildren. The proposal therefore reported that nothing would disappear while a whole section of headings hung from it, and that is exactly the information the proposal is about. The whole subtree is now traversed.

= 1.4.0 =
* REPAIR, important: kadence/duplicate-blocks had been unable to write at all since 1.1.0. The map from old to new uniqueID was filled with random values again on every call, while the token was precisely about that map — so the write call always produced a different token than the dry run had given, and no call could succeed. The id_map is now input with dry_run false, just as the markup is for insert-blocks, and is checked before writing for coverage, duplicate values and collisions with the target post.
* Repair: metadata and lock got the verdict riskant from validate-write, and riskant yields no token — which made a block name that had been dropped by an earlier version impossible to restore. They now get ok with a note: there is nothing to check, but no render depends on them either.
* Repair: the status line for the verdict riskant always named the same reason ("at least one value is fixed in the saved HTML"), even next to a response that itself reported self_closing: true. It now names the attributes involved and points to their note.
* Repair: script and style tags were removed by wp_kses while their content remained as readable text. Those two are now removed together with their content, even when the tag is never closed.
* Repair: set-text rejected a Kadence column with the message that there were multiple elements "at the same level", while those divs are nested. The two cases are now distinguished and each gets its own explanation.
* New: kadence/remove-blocks removes blocks from a post, at any depth, including a single button from a button row. Two steps: first see what would disappear, including everything below it and what text it contains, and only then do it. If one of the specified uniqueIDs is not in the post, nothing is removed.

= 1.3.0 =
* New: kadence/list-recipes, kadence/generate-section and kadence/insert-blocks build a complete section — a Row Layout with Sections and text blocks inside — from a template, instead of having markup invented. That is necessary because Kadence bakes every uniqueID into the block's class names (twice for advancedheading, in the class and in data-kb-block) and encodes the JSON in the block comment with its own flags. Hand-written markup is guaranteed to deviate from that, and you can't tell from the result: the block appears, but without its CSS.
* The built markup is parsed and re-serialized before it is returned. If that does not produce exactly the same thing, an error follows instead of markup — WordPress would otherwise have rewritten it on save.
* insert-blocks refuses to write when the insertion would make an existing uniqueID disappear or create a duplicate. A block's CSS hangs on its uniqueID, so an ID that is gone means a block that is gone.
* The templates deliberately provide only structure, no styling. Colors, margins and font sizes come from the global styles; an invented value is noise that someone has to track down later.

= 1.2.0 =
* Repair of silent data loss: metadata and lock are WordPress's own attributes and are in no block.json, so normalization filtered them out as "unknown" on every write action. On 2026-09-11 that cost a row its block name: the write action succeeded, the read-back check reported it, and the name was gone. They are now passed through untouched and placed at the end of the comment, where the editor puts them too.
* New: kadence/set-text writes the text of a block. Needed because the text of a heading or paragraph is not in the attributes but in the innerHTML — set-attributes rightly refuses that, because the classes and data attributes are also built from it. set-text replaces only the part inside the outermost tag and leaves that tag verbatim, so that Gutenberg's block validation keeps passing. Refuses for a block with child blocks, for an innerHTML that is not exactly one wrapping element, and for an empty text.
* validate-write no longer blocks metadata and lock as "does not exist"; they get the verdict riskant, because there is no schema to check their content against.

= 1.1.0 =
* New: kadence/duplicate-blocks copies an existing block with everything below it to another post, and gives each copy a fresh uniqueID prefixed with the target post. The ID is replaced in both places at once — in the attribute and in the classes in the markup — so that no mismatch can arise and no class pattern needs to be known. A dry run with a token by default; writing requires dry_run false and that token.

= 1.0.1 =
* Repair: kadence_mcp_write could not be found in Members because that plugin derives its list from capabilities that are in use somewhere — and this one is deliberately granted to no one. The capabilities are now registered with a readable label via members_register_caps, with members_get_capabilities as a fallback.

= 1.0.0 =
* FIRST WRITE ACTION: kadence/set-attributes changes attributes on one existing block. Three locks before it — a capability of its own, kadence_mcp_write, that nobody gets automatically, edit rights on the post according to WordPress, and a valid token from validate-write. One after it: the post is read back and compared with what was intended, because Kadence rewrites post_content on save.
* Attributes are normalized the way the editor does it: whatever equals the default value drops out, the rest goes in the order of the block schema. Measured on a real page: of 170 attributes, 23 were stored, none equal to its default, in exact block.json order.
* The Write group on the settings screen is no longer empty.

= 0.9.3 =
* Correction in the server description, the skill and the documentation: blockVisibility was attributed to Kadence, but it is WordPress's own native hide option and applies to every block.

= 0.9.2 =
* Repair: in 0.9.1 the truncation of preview-write was in the input schema but not in the code, so full_markup was advertised but ignored.

= 0.9.1 =
* check-bindings now also checks a taxonomy against the post type that the query requests. Existing alone is not enough: a facet on a taxonomy that is not attached to the requested post type produces a filter that never shows anything. Found on a real site.
* preview-write by default returns only the opening comment of the changed block instead of the full tree. The full markup can be requested with full_markup.

= 0.9.0 =
* New: kadence/preview-write — shows the block markup before and after an intended change, without saving anything. Intended as a dry run between validate-write and the final write action, so that someone looks once instead of repairing afterwards. Warns explicitly if the innerHTML would change, which should not happen with an attribute change.

= 0.8.0 =
* validate-write returns a token with the verdict veilig, bound to the post, the block, the exact attributes and the post's modification date. A future write action can require that token, which makes checking impossible to skip. With riskant, deliberately no token is issued.
* validate-write now uses rest_validate_value_from_schema(), the same function WordPress runs when rendering. A value that does not get through it is silently discarded and replaced by the default value; the check thereby matches reality.

= 0.7.0 =
* New: kadence/get-raw-markup — the stored block markup of a post or a single block. The other tools show only parsed attributes, while it is precisely the markup that determines whether something is safe to change.
* New: kadence/validate-write — checks an intended attribute change without writing anything. At its core is an observation: if the current value appears literally in that block's innerHTML, changing it is a silent break. Plus type and enum checks, source attributes as a no-op, and the three specific failure paths uniqueID, kbVersion and columns, including a collision check.
* New: kadence/check-bindings — checks all references in a post across five storage locations, including the shortcode copy and inline spans.
* describe-block and list-blocks now state explicitly with is_dynamic that it is not a safety signal: all Kadence blocks have a render_callback.

= 0.6.0 =
* The agent skill can now be downloaded from the settings screen, as a zip, behind the same capability gate as the settings themselves.

= 0.5.0 =
* describe-block now splits uses_context into uses_context_own (from the block's own block.json) and uses_context_added (added by another plugin). GP Entry Blocks attaches its entry context to every block, which otherwise wrongly reads as a Kadence property.
* Skill extended with the two mechanisms for dynamic content, why a query-card is reusable as long as the bindings are generic, and where dynamic blocks get their context from.

= 0.4.1 =
* Repair: due to a botched edit, ancestor and the context fields did not make it into the output of describe-block, and top_level_only still filtered only on parent.
* Correction: block_defaults was described as something that changes the meaning of a missing attribute. That is not correct — kadence_blocks_config_blocks only goes to the editor and is an insert default that is written into the markup on save. Existing blocks simply follow block.json.

= 0.4.0 =
* list-blocks and describe-block now read ancestor. That is something other than parent and explains why the query blocks only become available inside a Query Loop; 29 of the 92 Kadence blocks use it.
* Repair: top_level_only filtered only on parent and therefore let kadence/navigation-link and kadence/off-canvas-trigger through as freely placeable.
* describe-block now also returns uses_context, provides_context, allowed_blocks and keywords.
* get-global-styles now returns block_defaults: the site-wide default settings per block from kadence_blocks_config_blocks. Important, because where they exist, a missing attribute does not mean the default value from block.json.

= 0.3.1 =
* describe-block: new group "indeling" for colLayout, columns and inheritMaxWidth — these previously fell under "overig", while colLayout in particular explains column ratios.
* describe-block: loggedIn and loggedOut now fall under "conditioneel" instead of "overig".
* Skill extended with Query Loops (post meta, shared object, separate filter blocks, one card per query) and with the difference in visibility options between Row Layout and Section.

= 0.3.0 =
* New: kadence/get-post-meta — the Kadence configuration that is not in block attributes, such as the query definition in _kad_query_query. Allowlist on _kad_, _kt_ and kadence_; meta from other plugins is not returned.
* New: kadence/find-usages — which posts refer to a query, navigation, card or header via an id attribute.
* inspect-post: from_unique_id to start the tree at a particular block.
* inspect-post: new filter kadence_mcp_can_read_post, and the status line now reports when a post is readable but does not come out of a normal query (for example because of Members).
* Repair: HTML entities in include_text were not decoded.
* Repair: the description of find-post said that search only searches the title; WordPress also searches the content.

= 0.2.0 =
* New: kadence/diff-blocks — returns only the differences between two blocks.
* New: kadence/find-post — from page name or slug to a post_id.
* inspect-post: metadata included by default (contains blockVisibility and the name from the list view), plus include_text and full_attributes.
* describe-block: attributes grouped instead of alphabetical, with a group filter and a count per group.
* The server description now carries a primer on how Kadence stores values.
* Every response has a status line, so that an empty result explains itself.
* Repair: typography always came back empty because of an incorrect method_exists check on a class with __call.
* Agent skill added under skill/.

= 0.1.0 =
* First version. Five read abilities, a dedicated MCP server, settings screen and capabilities.
