# Design decisions

Why this plugin is the way it is. For whoever modifies it later.

## Writing is possible, and it is fenced in

> **Updated 2026-09-11.** This chapter described a plugin that could not
> write. That has no longer been true since 1.0.0: there are six write abilities and
> as many `wp_update_post` calls. The text below has been kept as a
> description of how writing is fenced in — because that is what remains of the
> original decision.
>
> **Updated again.** There were six; there are now eighteen, and
> they no longer touch only `post_content`. The claim that options are never
> touched has been incorrect since `set-global-typography` and `set-site-css`.

There are three kinds of writers, and the difference lies in what can be
rolled back.

**Post content.** Most of them. They touch the `post_content` of the post you name, and
WordPress creates a revision of it, so rolling back is possible via the
revisions screen.

**New posts.** `create-page`, `create-post`, `create-query`,
`create-query-card` and `create-entity`. These create something instead of changing something; rolling back
means throwing it away. For a navigation, header or element, `create-entity` writes the
whole set of registered settings along with it, because `set-entity-meta` only accepts
keys that already exist. `create-post` is one ability for all
regular post types: what is allowed per type (taxonomies, thumbnail, excerpt,
ACF field groups) it reads from WordPress on each call, instead of having a variant
per post type.

**Post meta.** `set-entity-meta`, `set-card-layout`, `set-query` and
`sync-query-facets`. WordPress does not keep post meta in revisions, so the old
value in the response's `before` field is the only way back. That is also stated
in every status line.

**Site-wide.** `set-global-typography` and `set-theme-mods` write theme mods,
`set-site-css` writes the Customizer's Additional CSS. These affect every page, and theme mods
have no revisions either. They therefore sit behind an extra gate:
`edit_theme_options` on top of `kadence_mcp_write`, and both theme-mod writers accept
only a fixed list of keys — theme mods also contain the header, the colors
and the layout, and a typo must not be able to land there.

All eighteen require the capability `kadence_mcp_write` (granted to
no one on installation), the corresponding WordPress capability, and a token from a
preceding check step. Files and caches are never touched.

The original text, which still applies to the eighteen read tools:

There is no write ability, and the plugin has no place at all where it touches a
post, option, file or cache. "Read-only" is therefore not an
agreement here but a verifiable fact: `grep` for `update_option`,
`wp_update_post` and `wp_insert_post` returns nothing.

That is deliberate. A generic block editor plugin that brings writing along is
not something you install on a site that goes live next week, not even with the
agreement that you won't call those tools — they exist at that point.

## The lock is at execution time, not at registration time

`Kadence_MCP_Ability::check_permissions()` asks the question "is this tool
on?" again on every call. A flag set at registration stays
fixed for as long as the request lasts.

This is taken from Gravity Forms, where the reason is stated explicitly in the code:
the gate must apply on every surface — the Abilities API's REST run route,
the shared MCP server and the plugin's own server — and not only at the place where the
ability was registered.

The class fails closed in doing so: if `Kadence_MCP_Settings` is missing, the
tool counts as off. A half-finished bootstrap must never open the gate.

## Two layers, and they are not interchangeable

The toggle determines whether an ability is offered. The capability determines whether this
account may run it.

A toggle that was turned off and is turned back on grants access immediately; a missing
capability does not. Anyone who wants to know what an MCP account can really do therefore looks at the
role and not at this screen.

## No loosening of the input schema

There used to be a loosening of `type` to `["object","array","null"]` here
for schemas without required fields, taken from another add-on. It
has been removed and must not come back.

The MCP Adapter compares strictly against the string `'object'`
(`SchemaTransformer.php:62`). An array falls through to `wrap_in_object()` and the
published schema becomes `{type:object, properties:{input:…},
required:["input"]}`. The client then sends `{"search":"row"}` flat, the adapter
strips `$arguments['input']` off it, finds nothing, and the ability runs with empty
input — without an error message, with a complete unfiltered list as the response.
The added `"null"` made that invisible: without the loosening, the
validation would have cleanly returned `ability_invalid_input`.

The right solution for "the client sends nothing" is a `default` on the
root schema, as Gravity Forms does it.

## The capabilities stay in place on deactivation

`WP_Role::remove_cap()` writes persistently, while activation only gives back
`kadence_mcp_view` and `kadence_mcp_manage`, and only to
administrator. A custom role `mcp-agent` — exactly what the settings screen
recommends — would be empty after a single deactivate-activate cycle, and every tool would
return permission denied without any message.

Cleanup therefore happens in `uninstall.php`, not in a deactivation hook.
Gravity Forms does not touch any capability on deactivation either. Note that
on uninstall WordPress loads only that file, so the capability class is
loaded separately there.

## A server of its own, not the shared one

On the MCP Adapter's shared default server, abilities sit behind
generic discovery tools. An assistant first has to ask what is available and then
execute it in a roundabout way. On a server of its own, every enabled ability appears
as a separate tool in the list.

Gravity Forms arrives at the same choice and makes dedicated the default, with
the same reasoning. The shared mode remains available for anyone who deliberately wants one
connection for the whole site.

`meta.mcp.public` follows that choice: on the plugin's own server it becomes `false`, because
an ability that already appears there as a separate tool doesn't also need to be published
on the shared server — then the same tool appears twice.

## An ability without an input schema is unusable

`WP_Ability::execute()` rejects any input when there is no schema, even the
empty object that MCP clients always send along. `Kadence_MCP_Registry` therefore gives a
schemaless ability an explicit empty object schema.

That is not theory. The ACF abilities on this site are getting stuck on it live:

```
Ability "mcp-adapter/discover-abilities" definieert geen invoerschema
vereist om de invoer te valideren.
```

Note that the type must remain the STRING `'object'`. There used to be a
loosening to `["object","array","null"]` here; it was removed for the reason
given above under "No loosening of the input schema", and this chapter
then still described it as current behavior. That was not correct.

## Error texts are sanitized

The Abilities API catches an exception from a callback and returns the message
verbatim, including absolute server paths, and that message goes through to
the MCP client. `Kadence_MCP_Ability::do_execute()` logs the real message and
returns a generic message. A `WP_Error` that a handler returns itself
is a deliberate message and passes through untouched.

## Output size is a design requirement, not a convenience

`kadence/rowlayout` has 170 attributes and its `block.json` is 17.6 KB. That
does not fit in one MCP response.

That is why `describe-block` paginates at 40 attributes by default, and
default values that are themselves a large structure are summarized instead of
written out. Measured: the same block comes out as 3.0 KB instead of
17.6 KB.

An explicit list via `attributes` is not paginated — that is by
definition the whole question, and returning less than was asked for is confusing.

## Origin comes from disk, existence from the registry

A registered `WP_Block_Type` does not carry with it which plugin it
comes from. The origin is therefore derived from the `block.json` files under
`dist/blocks/` of both plugins.

The block registry remains the source of truth for what exists. If the
file scan yields no match, the block gets `onbekend` instead of a
guess.

`attribute_count` does not count the two attributes that WordPress itself adds to every block
— `lock` and `metadata` — because otherwise
`kadence/rowlayout` would report 172 while its `block.json` has 170. The comparison
is on identical definition and not on key: `kadence/repeatertemplate`
defines `lock` itself, with its own default, and therefore keeps it.

`kadence/videopopup` is in both plugins. The first match wins, and that is
the free plugin — the same one that WordPress also registers first.

## Post types are discovered, not enumerated

`Kadence_MCP_Inventory::get_post_types()` filters on the prefix `kadence_` instead
of maintaining a fixed list. A new Kadence version or another
Kadence product thereby appears automatically, without a code change.

At the time of writing these are `kadence_header`, `kadence_navigation`,
`kadence_element`, `kadence_form`, `kadence_lottie`, `kadence_vector`,
`kadence_adv_page`, `kadence_query` and `kadence_query_card` — but the code does not
assume that.

With one exception: Kadence Blocks Pro registers its icon library
as **`kb_icon`**, without the prefix. That one is in `EXTRA_POST_TYPES`. A
prefix filter alone would miss it, and then it would not exist for the agent.

## Four blocks that don't seem to be there

Kadence registers `kadence/pane`, `kadence/tab`, `kadence/countdown-inner` and
`kadence/countdown-timer` exclusively in JavaScript. So they are not in
`WP_Block_Type_Registry`, while `inspect-post` does show them in the block tree
— an accordion is commonplace. `describe-block` answered "does
not exist on this site", and that was incorrect.

The fallback now reads the `block.json` on disk and marks the result with
`registration: "editor-only"`, so the difference stays visible. Only if a
block is not there either does a `WP_Error` follow.

One of those four, `accordion/pane/block.json`, also lacks the `name` field —
the only one in the whole stack. The name is derived there from the folder name and
marked with `name_source: "directory"`. That is not a general rule: every other
file carries its own name, and blindly deriving it would turn
`header/children/row` into `kadence/row` instead of
`kadence/header-row`.

## Read access to a post is something other than access to the tool

`kadence_mcp_view` says that this account may use the tool. Whether it may read this particular post
is a separate question, and `inspect-post` and `list-entities` both ask it
with `current_user_can( 'read_post', $id )`.

Without that check, an MCP account with only `kadence_mcp_view` could read the content
of drafts and private posts.

## What is deliberately not here

**bjornfix's plugin has not been forked.** `mcp-abilities-block-editor` offers
generic `parse_blocks`/`serialize_blocks` and block CRUD that also works on
Kadence blocks, because it is format-independent. Forking would mean
maintaining 67 abilities and missing upstream fixes. The author
himself uses the pattern of a base stack plus separate add-ons, with 22 add-ons side by
side; this plugin follows that pattern.

**There is no cache flush.** Kadence Blocks generates its CSS per request via
`wp_add_inline_style` on `wp_enqueue_scripts` priority 180. There is no
file cache to empty. If writing is ever added, this is the
first thing that needs to be rechecked.


## Knowing and building are two things (1.21.0)

Until 1.21.0, "there is a profile" also meant "the generator may build it".
As a result you could not partially know a block: anyone who wanted to record the value lists of tabs
thereby made tabs buildable — with a wrapper that had not been
observed. `bouwbaar: false` separates the two. `bekend()` says whether building
is allowed, `heeft_profiel()` whether anything is known.

Derived markup is not always on the outermost element. The slide puts its
alignment on the second div and has an overlay element that only exists if
there is an overlay color. For that, two kinds of rules were added: `reeks`
(classes from an array attribute, per breakpoint) and `element` (a piece of HTML
that is or is not there). Rules with `overal` are checked against the block's whole own
innerHTML, not only against the first class attribute.

## Import without a second write route (1.21.0)

`prepare-import` is deliberately a READ ability. It issues the same token as
`generate-section`, over its own cleaned-up markup, and the writing
stays with `insert-blocks` with all its checks. A separate import writer
would be a second place where the same locks have to be right.

References are reported and never guessed. A media ID on another site is
a different file; a term ID too. Converting only happens with a map that the
user provides (`replace`, `media_map`, `term_map`, and since 1.22.0
`post_map`). Which blocks refer to a post with their `id` is listed in one
table (`post_verwijzing()`), read from Kadence's render, which checks the post type for each of
those blocks. Other blocks use `id` for something
else — an attachment, a sequence number — and are left out. An unresolved
reference yields no token without `accept_warnings`, because "the icon does not exist
here" is a decision and not a detail.

What the import cannot do: prove validity in the editor. That check lives in each
block's JavaScript. The skill therefore prescribes opening the post in the editor
after inserting.
