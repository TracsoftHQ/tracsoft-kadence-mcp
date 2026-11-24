# Tracsoft Kadence MCP

Tracsoft's hardened build of [mcp-abilities-kadence](https://github.com/joostadams/mcp-abilities-kadence)
by Joost Adams (GPL-2.0-or-later). The upstream documentation follows below,
translated to English. Tracsoft's changes are listed in `TRACSOFT.md` and in the
changelog in `readme.txt`.

**Install:** download `tracsoft-kadence-mcp.zip` from the
[latest release](https://github.com/TracsoftHQ/tracsoft-kadence-mcp/releases/latest)
and upload it under Plugins → Add New. Sites then get updates from this repo's
releases like any other plugin update, including in MainWP.

---

# MCP Abilities — Kadence

Gives an MCP agent access to Kadence Blocks, Kadence Blocks Pro, Kadence Pro
and the Kadence theme, through the WordPress Abilities API.

**Fifty-two abilities: twenty-four read, twenty-eight write.**

So writing is not the exception here. Every write ability requires three things:
the capability `kadence_mcp_write` (which is granted to no one on
installation), edit rights according to WordPress itself, and a token from a
preceding check step. Afterwards the result is read back and compared with what
was intended.

Pay attention to where they write. Most touch `post_content` and therefore create
a revision. `set-entity-meta` and `set-card-layout` write post meta, which has
**no revisions** — the old value in the response is your only way back.
`set-global-typography`, `set-theme-mods` and `set-site-css` affect the whole
site instead of one post. `upload-media` adds an image to the media library.

### Read

| Ability | What it does |
|---|---|
| `kadence/find-post` | from page name or slug to a `post_id` |
| `kadence/inspect-post` | the block tree of a page, header or element |
| `kadence/get-raw-markup` | the stored block markup, as it is in the database |
| `kadence/diff-blocks` | only the differences between two blocks |
| `kadence/list-blocks` | which Kadence blocks exist and which plugin they come from |
| `kadence/describe-block` | the attributes of one block, grouped and paginated |
| `kadence/list-entities` | the Kadence post types and their posts |
| `kadence/get-post-meta` | the Kadence configuration that is not in block attributes |
| `kadence/find-usages` | which posts refer to a query, navigation or card |
| `kadence/check-bindings` | do the dynamic bindings still point to something that exists |
| `kadence/get-global-styles` | the color palette, the base typography and the insert defaults |
| `kadence/describe-query` | the three layers of a Query Loop side by side, with a trial run |
| `kadence/describe-post-type` | taxonomies and meta fields, as a step toward a Query Card |
| `kadence/list-recipes` | which section templates exist |
| `kadence/generate-section` | builds valid markup from a template — saves nothing |
| `kadence/preview-write` | the markup before and after, without saving anything |
| `kadence/validate-write` | checks an intended change — writes nothing |
| `kadence/verify-markup` | finds blocks whose classes no longer match their attributes |
| `kadence/prepare-import` | checks markup from the editor or from another site, converts uniqueIDs, converts media, term and post IDs according to a given map, reports references and issues a token for `insert-blocks` — writes nothing |
| `kadence/check-access` | what this account may read and edit per post type, and which capabilities (`edit_theme_options`, `unfiltered_html`) are missing |
| `kadence/audit-colors` | for each color value, where it is used: block attributes, Kadence meta and typography, with hex values that equal a palette color reported separately |
| `kadence/site-fingerprint` | hashes of palette, typography, fonts, terms, Kadence objects and plugins, to compare two sites structurally |
| `kadence/export-entity` | a Kadence object as a package: content and `_kad` settings, with the IDs that need a map on another site |

### Write

| Ability | What it touches |
|---|---|
| `kadence/set-attributes` | attributes on one block |
| `kadence/style-blocks` | attributes on multiple blocks, in one revision |
| `kadence/set-text` | the text inside one text block |
| `kadence/insert-blocks` | add markup from `generate-section` or `prepare-import` to a post |
| `kadence/remove-blocks` | remove blocks, including everything below them |
| `kadence/move-blocks` | move blocks within a post, preserving the uniqueID |
| `kadence/replace-block` | rebuild a block while preserving its `uniqueID` |
| `kadence/duplicate-blocks` | copy a block to another post |
| `kadence/create-page` | a new page, as a draft by default |
| `kadence/create-post` | a post of any public post type (post, service, market …) with terms, featured image and ACF fields, all checked against that post type — as a draft by default |
| `kadence/update-post` | update an existing post of such a post type: title, slug, excerpt, order, image, terms and ACF fields — ACF and terms without a revision |
| `kadence/create-entity` | an empty navigation, header or element with Kadence's full set of settings, or a vector from an SVG — as a draft by default |
| `kadence/set-page-status` | draft to published, or back |
| `kadence/connect-translation` | link an existing post in WPML as a translation of its original, singly or as a list — touches only WPML's translation table |
| `kadence/replace-colors` | convert colors across the whole site with a map {old: new}, with deliberate exceptions (opacity, core blocks, Gravity Forms) |
| `kadence/update-entity-content` | replace text in the SVG of a vector or the JSON of a custom SVG, with an expected count and a loss check |
| `kadence/trash-post` | move a post to the trash, after checking where it is still used — never permanently |
| `kadence/import-entity` | place a package from `export-entity`, as new or over an existing object, with ID maps and byte-for-byte read-back |
| `kadence/create-query` | copy a Query Loop, with fresh `uniqueID`s |
| `kadence/set-query` | what a Query Loop fetches — post meta |
| `kadence/sync-query-facets` | make the facets match the filter blocks again — post meta |
| `kadence/create-query-card` | copy a Query Card |
| `kadence/set-card-layout` | columns and gap of a Query Card — post meta, no revision |
| `kadence/set-entity-meta` | a `_kad_` setting on a Kadence post — post meta, no revision |
| `kadence/set-global-typography` | the theme's global typography — site-wide, no revision |
| `kadence/set-site-css` | the Customizer's Additional CSS — site-wide |
| `kadence/set-theme-mods` | the theme's header and footer builders, logo, header buttons and footer columns — site-wide, no revision (Tracsoft) |
| `kadence/upload-media` | a JPG, PNG, GIF or WebP from the call into the media library, up to 2 MB (Tracsoft) |

Files and caches are never touched.

Every response carries a `status` line that says whether an empty result means
that there is nothing, or that it could not be checked.

## Requirements

- WordPress 6.9 or higher, PHP 7.4 or higher. As of 6.9 the
  [**Abilities API**](https://github.com/WordPress/abilities-api) is in core;
  below that you need it as a separate plugin, which is why the minimum is set there
- The [**MCP Adapter**](https://github.com/WordPress/mcp-adapter), as its own
  plugin alongside this one. It is not in the plugin directory, so searching under
  **Plugins → Add New Plugin** returns nothing. Get the ZIP from its
  [Releases page](https://github.com/WordPress/mcp-adapter/releases/latest),
  or with WP-CLI:

      wp plugin install https://github.com/WordPress/mcp-adapter/releases/latest/download/mcp-adapter.zip --activate

  A vendored copy inside another plugin — Gravity Forms ships one —
  does make the class exist but starts nothing. This plugin therefore starts the
  adapter itself as soon as you turn on the main switch, but only then.
  Don't build on that: the adapter itself advises against bundling and will remove that form
  in a later version
- Kadence Blocks. The rest of the Kadence stack is optional; whatever is missing
  is simply not reported

If the Abilities API or the adapter is missing, the plugin registers nothing and
the settings screen explains what is missing.

## Download

One file, and it is not behind the green button.

Get `tracsoft-kadence-mcp.zip` from the
[Releases page](https://github.com/TracsoftHQ/tracsoft-kadence-mcp/releases/latest).
That is the ZIP built for that version tag, with the folder name WordPress
expects and without the development files.

So not **Code → Download ZIP**. That gives you the current state of `main` — which
need not be a released version — in a folder with `-main` appended.

You don't need to fetch the agent skill separately here. It is included in the plugin and is
available as a download under **Kadence Blocks → MCP** after installation.

## Installation

1. Install the ZIP via **Plugins → Add New Plugin → Upload Plugin**, and
   activate it
2. Go to **Kadence Blocks → MCP**
3. Turn on *Enable MCP*
4. Check the tools you want to offer — everything is off by default
5. Copy the endpoint URL and put it in your MCP client

After that, updates come through WP admin. The plugin checks the GitHub releases of
this repo and offers a new version as a regular plugin update; no
token is needed, because the repo is public.

## Endpoint

Two modes.

**Own endpoint** (default) — `/wp-json/mcp/kadence`. Every enabled
ability appears as a separate tool in the assistant's list. The namespace `mcp`
is the same one where the adapter puts its default server and where Gravity Forms
places its server, so all of a site's MCP endpoints sit together.

The assistant sees the tool names without a slash: MCP does not allow one,
so `kadence/list-blocks` arrives as **`kadence-list-blocks`**. In the
shared mode, by contrast, the name with the slash is the value you pass to
`ability_name`.

**Shared site endpoint** — `/wp-json/mcp/mcp-adapter-default-server`. The
endpoint that all MCP plugins on the site share. One connection, but the
Kadence tools are only reachable through WordPress's generic discovery tools:
the assistant first has to ask what is available and then execute it
in a roundabout way.

Choose the shared mode only if you deliberately want one connection for the whole site.

## Permissions

Three capabilities of its own. On activation only the administrator role gets
`kadence_mcp_view` and `kadence_mcp_manage`; nobody gets the master key
automatically.

**Deactivating does not touch your roles.** Cleanup happens only when the
plugin is deleted. Otherwise a single deactivate-activate cycle would empty your own MCP role.

| Capability | Grants the right to |
|---|---|
| `read` | get past the REST gate and see posts (WordPress default) |
| `kadence_mcp_view` | run the read tools, and request the tool catalog |
| `kadence_mcp_manage` | view and save the settings screen |
| `kadence_mcp_full_access` | everything above, including whatever is added later |

**Give the assistant its own user.** That role needs **two**:
`kadence_mcp_view` and `read` — the latter is the ordinary subscriber capability. Without
`read` the account cannot get past the REST gate and the tools that read posts
cannot see a single post. If the assistant should also be able to read drafts and
unpublished headers and elements, it additionally needs
`edit_theme_options`; leave that out if you don't want that. Attach an
account to it and connect with an application password for that account. What that role may not do, the assistant
cannot do — regardless of which tools are on. A permissions plugin like Members can
assign the capabilities per role without this plugin needing to know about it.

Tracsoft addition: for the Administrator role you don't need a permissions
plugin. Under Kadence Blocks → MCP, **Allow administrators to use the write
tools** grants `kadence_mcp_write` to administrators when you save, and removes
it when you untick it.

The toggles and the capabilities do different things:

- the **toggle** determines whether an ability is offered
- the **capability** determines whether this account may run it

The toggle reduces the surface. The capability is the lock.

## Agent skill

Under `skill/` there is a skill with the working methods and pitfalls that don't fit in a
tool schema. The server description already carries the conventions and is loaded
automatically; the skill is the level above that.

You get it with the *Download skill* button under **Kadence Blocks → MCP** —
which delivers the same folder, shipped with the installed version. Unzip the ZIP
into your agent's skills folder, so that `kadence-blocks` sits directly
inside it. `skill/README.md` describes the same for anyone working from the repo.

## Extending

Adding an ability is one raw definition in a class that has
`get_definitions()`, plus that class in `Kadence_MCP_Registry::bronnen()`.
The registry fills in the schema, `permission_callback` and meta, and the
settings screen builds its list from that same registry — you don't need to update a
second list anywhere.

The filter `kadence_mcp_ability_definition` may adjust label, description and schemas.
Name, ability class, `permission_callback` and `execute_callback`
are reset afterwards: a filter may change the description, never the
lock.

## License

GPL-2.0-or-later.
