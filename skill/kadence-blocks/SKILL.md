---
name: kadence-blocks
description: "How to read and modify a Kadence site through the Kadence MCP server, and the pitfalls involved (kadence/list-blocks, describe-block, inspect-post, diff-blocks, list-entities, find-post, get-global-styles, validate-write, preview-write, set-attributes, set-text, move-blocks, update-post, duplicate-blocks, prepare-import, check-access, audit-colors, replace-colors, site-fingerprint, export-entity, import-entity, update-entity-content, trash-post). Load this before you answer a question about how a Kadence page is built, why two blocks look different, what needs to change for mobile, or before you write anything."
---

# Reading Kadence through MCP

This skill describes how to use the Kadence MCP server without making the
mistakes that the tool schemas cannot prevent.

The server has forty-nine abilities: twenty-three read, twenty-six write.
**So writing is not the exception** — check per tool whether it writes, instead
of assuming that reading is the norm.

Every write ability requires the capability `kadence_mcp_write`, edit rights on
the post according to WordPress, and a token from a preceding check step. Read
below how that check works before you use one.

Note that they do not all touch the same thing. Most write `post_content`
and therefore create a revision. `set-entity-meta` and `set-card-layout` write post
meta, and post meta has **no** revisions — the old value in the response is your only way
back. `set-global-typography` and `set-site-css` affect the whole site instead of
a single post.

## Before you propose a change

As soon as you RECOMMEND or WRITE a change, it must have been checked —
otherwise you propose something that silently does nothing or silently breaks something.

**Always call `kadence/validate-write`** with the block and the intended
attributes. It writes nothing; it returns `veilig` (safe), `riskant` (risky) or `blokkeer` (block).

On `veilig` a **token** comes back. It is bound to this post, this
block, these exact attributes, and the post's modification date. A subsequent
write action requires that token: you cannot skip it, and you cannot write something other than
what was checked. If someone else has changed the post in the meantime, the token
expires — an approval of a version that no longer exists is not an
approval.

Then use **`kadence/preview-write`** to show what the markup would look like. It
saves nothing and is the last step before you recommend something: the user
looks at it once instead of having to repair it afterwards. In the
result, pay particular attention to the `innerHTML` — if it still refers to an old value,
the change is not complete.

On `riskant` **no** token comes back. That is deliberate: a human should
decide there, not a hash.

Why the type check matters: `WP_Block_Type::prepare_attributes_for_render()`
runs `rest_validate_value_from_schema()` over your attributes on **every** render and
**silently discards a value that fails**, after which the default value takes
its place (`wp-includes/class-wp-block-type.php:513-520`). Your
write action then appears to succeed and does nothing. `validate-write` uses exactly
that same function, so whatever it approves survives rendering.

The core of that check is an observation, not a list. A block that is saved
**self-closing** (`/-->`, so empty `innerHTML`) has no markup of its own that can
drift — there, every attribute is free. If the block **does** have markup, the question is
whether the current value appears in it **literally**.

Observed example:

```html
<!-- wp:kadence/rowlayout {"uniqueID":"228_2b2d4e-25",...} -->
<!-- wp:kadence/column {"uniqueID":"228_4ec348-d0",...} -->
<div class="wp-block-kadence-column kadence-column228_4ec348-d0">
```

The rowlayout saves **nothing**. The column bakes its `uniqueID` into the class.
Changing that same value is therefore risk-free on one block and a
silent break on the other. `get-raw-markup` shows this; `validate-write` checks it.

**`is_dynamic` is not a safety signal.** Every Kadence block gets a
render_callback, so that field is `true` on **all** of them. It says nothing about
validation.

In addition, three attributes have their own failure path, none of the three visible in
the markup: `uniqueID` (empty drops both the wrapper and the CSS; a collision makes two
blocks share their CSS), `kbVersion` (at 1 the entire render branch switches), and
`columns` on a rowlayout (the layout class no longer matches the number of
children).

## Checking references

`kadence/check-bindings` goes through a post for everything that points outward. Do this
before you recommend anything about dynamic content or a Query Loop, because **Kadence
logs nothing here and shows no error message**:

- a broken dynamic reference makes the block **silently disappear**
- a `fallback` actively masks that
- an invalid post type in a Query Loop falls back to `post` and shows
  **blog posts**
- a taxonomy that is not attached to any post type produces an empty filter

Note that the same setting lives in multiple places. A dynamic button
stores its binding both in `kadenceDynamic.link` and **once more** as a
`[kb-dynamic field='…']` shortcode in the `link` attribute itself. On this site
the two have already diverged. Whoever changes only one makes it worse.

## The rule that matters most

**Never draw a conclusion about what a block can do from a filtered list.**

`describe-block` has `search`, and it matches on the attribute **name**. Kadence
often stores responsive values as an array `[desktop, tablet, mobiel]` under one
name. The attribute that determines the mobile direction of a Section is called
`direction`. If you search for `mobile`, you will not find it — and you will wrongly
conclude that a Section has no mobile settings. It has thirty.

Use `group` instead (`responsive`, `spacing`, `achtergrond`,
`rand`, `typografie`, `kleur`, `link`, `afmeting`, `conditioneel`,
`identiteit`), or fetch everything with `per_page: 100`. The `groups` field in the
response counts per group across the **whole** block, including what is not on this page.

## The conventions are not uniform

This is the second pitfall, and it looks like the first.

| Block | How responsive values are stored |
|---|---|
| `kadence/column` | `direction`, `justifyContent`, `gutter` as an array of 3 |
| `kadence/advancedheading` | `fontSize` as an array of 3, with `sizeType` for the unit |
| `kadence/tabs` | `tabWidth` as an array of 3, but `mobileLayout` and `tabletLayout` as separate strings |
| `kadence/rowlayout` | `mobilePadding`, `tabletPadding`, `mobileLayout` as separate attributes |

So there is no rule that applies to all blocks. Check per block.

## Insert defaults: what a new block gets here

`get-global-styles` returns the site-wide defaults per block under `block_defaults`.
Read carefully what that is and what it is not.

This option goes **exclusively to the editor** — there is no render filter. It is
an insert default: for a new block the editor fills in those values, and they
are written into the markup on save. A missing attribute on an
**existing** block therefore simply means the default value from `describe-block`.

What it **is** useful for: knowing what form something should take on this site.
If, for example, it says `kadence/advancedheading: {htmlTag: "p"}`, then a new
"Advanced Text" block on that site is a paragraph by default, not a heading. That is
house style, and it is useful when you propose something to create.

## Placement and data flow are two different constraints

Four fields, and each of them does something different.

| Field | Means |
|---|---|
| `parent` | which block this block may sit in DIRECTLY |
| `ancestor` | which block it must hang under somewhere, however deep |
| `uses_context` | which data it receives from an ancestor |
| `provides_context` | which data it supplies to its descendants |

That difference explains something that is otherwise puzzling. The query blocks —
`query-filter`, `query-pagination`, `query-sort`, `query-result-count` — have
`parent: [kadence/query, kadence/column, kadence/modal]` **and**
`ancestor: [kadence/query]`. So they may sit directly in a Section, but
only if that Section itself hangs under a query. **That is why they only appear in the
editor once you are inside the Query Loop.** 29 of the 92 Kadence blocks
use `ancestor`.

Two blocks **do** have `ancestor` but **no** `parent`:
`kadence/navigation-link` and `kadence/off-canvas-trigger`. Filtering on `parent` alone
wrongly makes them pass as freely placeable.

`uses_context` and `provides_context` are not about placement but about
data: a query-card knows which post it renders because `kadence/query`
supplies that context. If dynamic content works in one block but not in another,
look there.

## Where each value lives

**Text lives in the markup, not in the attributes.** `kadence/listitem`,
`kadence/advancedheading` and `kadence/singlebtn` store their content in the
innerHTML. If you want to know **what** it says, use `include_text: true` on
`inspect-post`. Containers then rightly return nothing.

**Whether a block is hidden lives in `metadata`.** That is WordPress's own native
hide option — `blockVisibility` lives in
`wp-includes/js/dist/block-editor.js` and appears nowhere in the Kadence stack.
So it applies to **every** block, not just Kadence blocks. You cannot see it in the attributes.
That is why `inspect-post` returns `metadata` by default. The same field contains
`name` — that is the name the list view in the editor shows.

**Colors are references.** `palette1` through `palette15` point to the
global palette; ask `get-global-styles` for the hex values. Note that the
palette has three sets and that `active` says which one applies. And background is not
stored in the same place everywhere: `bgColor` on `kadence/rowlayout`, `background` on
`kadence/column`, and sometimes on the parent block — in a mega menu it can sit
on the `kadence/navigation-link` itself.

**A missing attribute means "not set," not "empty."** `parse_blocks`
returns only what is explicitly saved. If there is no `mobileLayout`, then
the default value from `describe-block` applies — not the value zero.

**`uniqueID` is `{postID}_{hash}`.** A block prefixed with `6_` comes from post 6,
even if you come across it in another post: that happens with reusable
parts such as headers and mega menus.

## Not everything lives in the blocks

This is the pitfall that is most expensive to discover on your own.

A **Query Loop** is a `kadence_query` post that is included in a page through an `id`
attribute. The `kadence/query` block has exactly six attributes —
`id`, `uniqueID`, `className`, `style` and two GPEB fields — and **none of them
is about the query**. Post type, sorting, `meta_key`, items per page and the
filters live in post meta under `_kad_query_query`. Ask `get-post-meta` as soon as
the question is how something is configured rather than whether it is there.

The same applies to `kadence_query_card` (`_kad_query_card_*`) and to the
Kadence header. These objects can be edited separately in the admin — Queries,
Navigation, Cards — and are reused in multiple places. So never
propose anything for such an object without running `find-usages` first: then you know
where else the change lands.

## Query Loops, and why they are different than they look

A Query Loop is **not** a block with settings. It is a **`kadence_query` post**
that is included in a page through an `id` attribute:

```html
<!-- wp:kadence/query {"uniqueID":"420_9c962e-39","id":1456} /-->
```

Four things follow from that, which you need to know before you propose anything.

**The settings live in post meta, not in the block.** The
`kadence/query` block has six attributes and none of them is about the query. Everything —
post type, sorting, `meta_key`, items per page — lives in
`_kad_query_query`. Ask `get-post-meta`.

The allowed values for `orderBy` are `date`, `title`, `author`,
`menu_order` and `meta_value`. With `meta_value`, `orderMetaKey` and
`orderMetaKeyType` fill the `meta_key` and `meta_type` of the underlying WP_Query.

**The same query on two pages is one object.** If you copy a Query Loop
to another page and change the filters there, it changes in both
places. A different display requires a new `kadence_query`. That is a
limitation of the model, not a setting you are overlooking. So run
`find-usages` before you propose anything for an existing query.

**Filters and pagination are separate blocks**, not settings of the
query block. `kadence/query-filter`, `-filter-checkbox`, `-filter-search`,
`-filter-reset`, `kadence/query-pagination`, `kadence/query-sort` and
`kadence/query-result-count` sit as blocks of their own in the markup of the
query post. Their configuration is **also** stored in `_kad_query_facets`, per
facet as JSON containing the `uniqueID` of the corresponding block.

**There is one card per query.** `kadence_query_card` determines the layout of a
result, and it can hold only one form. Variation goes through dynamic
content and showing or hiding individual elements — not through a second card.

**A facet on a taxonomy that is not attached to the post type shows nothing.**
Check that before you conclude that a filter is broken. The taxonomy must
actually be registered to the post type.

**Kadence shows only published items.** A taxonomy that nothing is
assigned to yet therefore does not appear in the taxonomy list, even though it exists and
has terms. If all terms are at `count: 0`, that is the signal — and
it usually means the taxonomy is attached to the wrong post type or that the
items do not carry their term yet. In that case, do not say "the taxonomy does not exist."

**Which post types Kadence offers** is determined by `public => true` and
`show_in_rest => true` (`kadence-blocks-pro/includes/init.php:195-201`), **not**
by `exclude_from_search`. That last flag only determines which post types the
frontend filters include.

## Dynamic content: two mechanisms that look alike

**Blocks that are normally static** — buttons, images, headings — get
a `kadenceDynamic` object that states, per property, whether it becomes dynamic:

```json
"kadenceDynamic": {
  "link": { "enable": true, "field": "post|post_url", "before": "• " },
  "text": { "enable": false }
}
```

That attribute is in **no** `block.json` at all, and not in the live registry either:
Kadence Blocks Pro reads it from the saved attributes at render time. So you
do see it with `inspect-post`, but never with `describe-block`.

**Blocks that exist specifically for this** — `kadence/dynamichtml`,
`kadence/dynamiclist` — have **no** `kadenceDynamic`. Their entire content **is** the
binding, so it lives in ordinary attributes:

```
source · field · metaField · customMeta · relate · relcustom
wrapTag · stripHTML · limitWords · maxWords · showEllipsis · showAllFields
```

The format of `field` is `bron|veld` (source|field), for example `post|post_title`. ACF and
other meta fields go through `metaField` or `customMeta`; `relate` and `relcustom`
point to a related post — that is the route if you want to show something from
an item's parent on that item.

Searching for "dynamic" in `describe-block` returns **nothing** for these blocks. The
binding lives in `source` and `field`, and those words do not contain it.

### Why a card **is** reusable

A `kadence_query_card` is a shared object: the same card in two queries is
one post. That is usually a feature and not a risk, because a binding such as
`post|post_title` remains meaningful on every post type.

It only becomes a problem when a binding is type-bound — a `metaField`
or `customMeta` pointing to a field that exists on only one post type, or a
`relate`/`relcustom` pointing to a relationship that does not exist elsewhere. So look at the
**bindings** before you warn about reuse, not at the fact **that** it is
shared.

### Where the data comes from

`kadence/dynamichtml` has `uses_context: postId, queryId,
kadence/dynamicSource, kadence/repeaterRowData, kadence/repeaterRow`. `postId` is
the post the query is rendering at that moment. That is why such a block works inside a
query and not outside it: without that context it does not know which post it is.
If dynamic content does not work somewhere, first check whether the context is there.

## Context does not always come from the block's author

`uses_context` from the live registry also contains keys that other plugins add
at runtime. Gravity Perks Entry Blocks attaches its
entry context through the `get_block_type_uses_context` filter to **every** non-GPEB
block. On `kadence/dynamichtml` you therefore see twelve keys, of which five
are Kadence's and seven are GravityWiz's.

`describe-block` splits that: **`uses_context_own`** comes from the block's own
`block.json`, **`uses_context_added`** was added by something else.
Do not conclude that Kadence does anything with entry blocks because those keys are
there — that is GPEB making itself broadly available.

## Visibility lives on two levels

This differs per block and is a common mistake.

| Block | Visibility options |
|---|---|
| `kadence/rowlayout` | `loggedIn`, `loggedOut` **and** `vsdesk`, `vstablet`, `vsmobile` |
| `kadence/column` (Section) | only `vsdesk`, `vstablet`, `vsmobile` |

Hiding based on login status is therefore **only possible on a Row Layout**. If
a Section needs to disappear for logged-out visitors, it needs a Row Layout
around it. In `describe-block`, both fall under the `conditioneel` group.

## Workflows

**"How is this page built?"**
`find-post` → `inspect-post` with `kadence_only: true`. Add `include_text: true`
if the question is about content rather than layout.

**"Why does A look different from B?"**
Use `diff-blocks`. Selecting and comparing attributes yourself is
unreliable: you miss what you did not request. A `null` in the response
means "not set on this one," so the default value applies there.

**"What do I need to change for mobile?"**
First `inspect-post` for the structure, then `describe-block` with
`group: "responsive"` on the block in question, then a targeted `inspect-post` with those
attribute names to see the current values. Compare the mobile value with the
desktop value — a mobile padding that is larger than the desktop variant is
almost always unintended.

**"Build a Query Loop / change one"**
`list-entities` with `post_types: ["kadence_query"]` for the existing queries,
then `find-usages` on each candidate to see which one is actually in use
— taking an abandoned query as a model copies a design language that nobody
sees anymore. Then `get-post-meta` on the query that **is** in use, and
`inspect-post` on the query post for the block structure around it.

When you create one, Kadence shows a selection screen with presets (no filters, simple
filters, advanced filters, sidebar left, sidebar right, sidebar left single).
That choice determines which filter blocks are included right away; it can be skipped.
The title that is asked for afterwards is internal and becomes the title of the
`kadence_query` post.

**"What color is this?"**
`get-global-styles`, and look at `active` to know which of the three
palette sets applies.

## Row Layout versus Section

Both are containers, and a page often mixes them.

`kadence/rowlayout` handles column counts, `collapseOrder` and how columns break
on tablet and mobile. `kadence/column` ("Section" in the editor) is a
flex container with `direction`, `justifyContent`, `gutter`, `flexBasis` and
`flexGrow`, each per breakpoint.

If something is off on mobile, **first** check which of the two is the enclosing
block. A nested Section you fix with `direction`, a Row Layout with
`mobileLayout`.

## Blocks that are not in the registry

Kadence registers `kadence/pane`, `kadence/tab`, `kadence/countdown-inner` and
`kadence/countdown-timer` only in JavaScript. For those four, `describe-block`
falls back to the `block.json` on disk and sets
`registration: "editor-only"` in the response. So they definitely do exist; you
come across them in every accordion.

## Styling more than one block: `style-blocks`

`set-attributes` handles one block per call. As soon as you style a whole section, it is
the wrong tool, and not out of laziness: styling a hero touches the row, the
column, two text blocks, the button row and the button. With `validate-write`
plus `set-attributes` that is fourteen calls — and every intermediate step changes
`post_modified`, so **every subsequent token expires** and you have to check again.

`style-blocks` takes a list of `{unique_id, attributes}` and works in two
steps: without a token, each block is checked individually (literally with
`validate_write()`, so exactly the same checks); with a token, everything goes into a single
save and therefore a single revision.

**All or nothing.** If one block is `blokkeer` or `riskant`, no token comes back
and nothing is written. Applying it halfway leaves the page in a state
nobody intended and that cannot be rolled back with a single revision. Take
that one block out of the list and do it separately.

A block that appears twice in the list is rejected — otherwise the
second entry silently overwrites the first.

Rule of thumb: **one block → `set-attributes`** (with `preview-write` in between if you
want to see the markup). **More than one → `style-blocks`.**

## Writing text is a different ability from writing attributes

The text of a heading or paragraph is **not in the attributes**. It is in the
innerHTML:

```html
<!-- wp:kadence/advancedheading {"level":1,"uniqueID":"1471_12622c-65",...} -->
<h1 class="kt-adv-heading1471_12622c-65 wp-block-kadence-advancedheading ..."
    data-kb-block="kb-adv-heading1471_12622c-65">Samen sterk voor de studentensport</h1>
<!-- /wp:kadence/advancedheading -->
```

`kadence/advancedheading` does have a `content` attribute, but it has
`source: html` — it is read from the markup, not from the block comment.
If you write it to the comment, nothing happens. `validate-write` returns
`blokkeer` for that, with exactly that reason.

Use **`kadence/set-text`** for this. It works in **two** steps, not
three:

1. Call it **without a token**. You get back `before`, `after` and a token,
   and nothing is saved.
2. Call it again **with that token** to write.

A separate `validate` step would add nothing: there is no schema to check
a text against, so the only meaningful check is reading the markup.

`set-text` replaces only the part inside the outermost tag and leaves that tag
exactly as it is. That is not tidiness but necessity: those classes and that
`data-kb-block` are rebuilt by `save()` from the attributes, and
if the saved markup deviates from that, Gutenberg reports "this
block contains unexpected or invalid content" on open. That is why it refuses a block with
child blocks, an innerHTML that is not exactly one enclosing element, and
empty text.

A `kadence/listitem` is the exception to "inside the outermost tag": there
the text sits in `<span class="kt-svg-icon-list-text">`, with the icon and possibly an
`<a class="kt-svg-icon-link">` around it. There, `set-text` replaces only the
content of that span. If the span is missing — markup that has been flattened at some point —
it refuses; in that case rebuild the item with `replace-block`.

HTML in the text survives only as a formatting tag: `strong`, `em`, `b`, `i`, `u`,
`br`, `sub`, `sup`, `del`, `code`, `mark`, `span` and `a`. `mark` is included deliberately
— that is what Kadence Advanced Highlight works with, and it would otherwise break on every
text change. Whatever was removed is listed in `notes`.

## metadata and lock are real, even though they are in no schema

`metadata` carries the **block name** (what you see in the list view) and the
visibility toggle; `lock` carries the padlock against moving or removing.
Both come from WordPress itself, and both are absent from Kadence's `block.json`.

On 11-09-2026 that cost a block name: a write action on page 1471
succeeded, and afterwards the row's name "Hero" was gone because normalization filtered the
attribute out as "unknown." Since 1.2.0 they are passed through untouched
and placed at the end of the comment, where the editor also
puts them.

You can also set them yourself: `validate-write` returns `ok` for them with a note
attached, so that a name lost to an earlier version can be
restored. There is nothing to check — they are not Kadence attributes, so no render
depends on them — but that also means a wrong key goes unnoticed.
So check the shape yourself: `metadata` expects `{"name":"Hero"}`.

Two things follow from that. Read the `notes` of every write action — they state
literally what was left out and why. And for a block with a name, include that
name explicitly if you are unsure whether it is still there; `inspect-post` returns
`metadata` by default, so you can see it beforehand.

## Building a new section: do not write markup yourself

Do not invent block markup. Ask `kadence/list-recipes` which templates exist and
let `kadence/generate-section` build it.

The reason is not convenience but correctness. Kadence bakes a
block's `uniqueID` into its own class names — for `advancedheading` even twice, in the
class **and** in `data-kb-block` — and the JSON in the block comment is encoded by
WordPress with its own flags, where a double hyphen becomes `\u002d\u002d`
because it would otherwise close the HTML comment. Hand-written
markup is guaranteed to deviate from that, and you cannot see it in the result: the
block appears, but without its CSS.

The order is:

1. `list-recipes` — which templates, which slots
2. `generate-section` with `post_id` — builds and checks, saves nothing,
   returns markup plus a token
3. `insert-blocks` with that token — places it, at `append`, `prepend`, or with
   `position: after`/`before` plus `relative_to`

`post_id` is required in step 2 because the `uniqueID`s must be unique within
the post where the section lands. That post's occupied IDs are fetched and
avoided.

Two checks you cannot skip. The built markup is parsed and
re-serialized, and if it does not come out identical, you get an error instead
of markup — otherwise WordPress would have rewritten it on save.
And `insert-blocks` refuses to write if the insertion would make an existing
`uniqueID` disappear or create a duplicate.

### If the design fits no template: `recipe: "custom"`

The four templates cover four shapes. A real design has more — a hero
with a label above the heading, a section with five colored bars, three columns
of which one has a different background. In that case you specify the structure yourself
through `tree`.

Build what belongs together in one go. Since 1.7.0 you can also add something afterwards into
an existing container: `insert-blocks` and `duplicate-blocks` support
`position: "inside"` with the `uniqueID` of that container. That is the only way
to get, for example, a copied accordion into a column, within the
background and content width of that row — a block at the top level
sits outside of them.

A self-closing block has no inside and is rejected: a Row Layout
without markup of its own, or a button, cannot hold child blocks.

Each node is `{block, attrs, tag, text}` or `{block, attrs, children}` — text
and children together is rejected, because the text would overwrite the children.

```json
[{"block": "kadence/rowlayout", "attrs": {"bgColor": "palette1"},
  "children": [
    {"block": "kadence/column", "children": [
      {"block": "kadence/advancedheading", "attrs": {"background": "palette3"},
       "tag": "div", "text": "Over ons"},
      {"block": "kadence/advancedheading", "tag": "h1", "text": "Samen sterk voor…"}
    ]}
  ]}]
```

What is fixed does not change: the wrapper markup comes from the same observed
table, the `uniqueID`s are assigned here (so do not supply them yourself), every
attribute is checked the way `validate-write` does it, and the parse-and-
serialize round trip remains the final check. Only blocks whose shape is
known; for the rest, `duplicate-blocks` is the way.

`tag` and `level` are automatically aligned with each other. That is necessary because
`advancedheading` determines its tag from two attributes: `htmlTag` is set to
`"heading"` and then falls back to `level`. If you write an `h3` in the markup but
`level` stays at 2, Kadence renders an `h2` while the markup says `h3` —
exactly the silent mismatch that Gutenberg notices later.

**The templates supply structure, not styling.** No colors, no margins, no
font sizes. That is deliberate: those come from the global styles, and an invented
value is noise that someone has to track down later. If you want to style the section, do it
afterwards with `set-attributes` on the `uniqueID`s that `generate-section` returned.

If the page already has something that resembles what you need to build, use
`duplicate-blocks` instead of a template — then you inherit the styling that is already
there, instead of having to set it again.

## Copying: the id_map has to come along

In the dry run, `duplicate-blocks` returns three things: the markup, an
**`id_map`** from old to new `uniqueID`, and a token. When writing
(`dry_run: false`) you must **return that `id_map` literally**, just as you
return the markup with `insert-blocks`.

That is not a formality. Without that map the ability would generate fresh IDs and
therefore write something other than what you approved — and until 1.3.0 it did exactly
that, so the token by definition never matched and writing was impossible.
Before writing, it is checked that the map covers exactly the blocks to be copied,
contains no duplicate values, and does not collide with IDs already in the
target post.

Prefer `duplicate-blocks` over `generate-section` as soon as the
page already has something that resembles what you need to build: you then inherit the styling that is already
there, instead of having to set it again.

## What you should not generate, and why

`generate-section` knows four templates, and they use five block types. For the
rest the rule is: **copy with `duplicate-blocks`, do not build.**

The accordion shows why. Its wrapper carries classes that are each
derived from attributes:

```
kt-accordion-id{uniqueID}  kt-accordion-has-{paneCount}-panes  kt-active-pane-0
kt-pane-header-alignment-left  kt-accodion-icon-style-basic  kt-accodion-icon-side-right
```

plus `data-allow-multiple-open` and `data-start-open` on the inner layer, and
per pane a `kt-accordion-pane-{volgnummer}` that has to stay in step with the `id`
attribute. If you change one attribute, the class has to change with it — and if the
generator guesses wrong, the block simply appears, without its CSS.

And then the two that nobody would ever make up: in the pane there is
`kt-acccordion-button-label-show` with **three c's**, and in the wrapper
`kt-accodion-icon-style-basic` with **"accodion" without an r**. Those are typos
in Kadence itself, and they must be copied literally.

That is the rule behind the entire `VORMEN` table in the plugin: it was read off
markup that Kadence itself wrote, not taken from documentation. Where there is no
observation, nothing is generated.

In practice: if the site already has an accordion, tabs, a countdown or a video popup,
copy it with `duplicate-blocks` and then adjust it with
`set-attributes`. If there is nothing, build it in the editor (see *Building through
the editor*) or bring in editor markup with `prepare-import`.

**Knowing is not building.** A block profile can be `bouwbaar: false`: the
plugin then knows the value lists, the derived markup and the checks, but the
generator does not build it. Tabs are listed that way. So `describe-block` and
`validate-write` do know what a valid tabs value is, and
`prepare-import` checks the number of tabs, but `generate-section` refuses.

The Advanced Slider (`kadence/slider` with `kadence/slide`) **can** be built: its
`save()` has been fully read off, and generated slides have been measured as valid in the
editor.

## Markup from elsewhere: `prepare-import`

`insert-blocks` only accepts markup with a token. That token comes from
`generate-section` — or from `prepare-import`, for markup the plugin did not
build itself: serialized in the editor, read out with `get-raw-markup` on another
environment, or from a pattern. `prepare-import` writes nothing.

What it does, in order:

1. blocks only — loose HTML becomes a Classic block in the editor
2. every block type must exist on **this** site
3. the markup must survive a parse-serialize round trip
4. every uniqueID gets the target post's prefix, in the attribute **and** the classes
5. classes against attributes, for every block with a profile — like `verify-markup`
6. schema and value lists, like `validate-write`
7. counters such as `slideCount` and `tabCount` against the number of child blocks
8. references: links to another domain, media, custom SVG icons
   (`kb-custom-N`), terms, other posts (navigation, header, query, query card,
   vector, form, menu item pointing to a post), palette colors with their value on
   the target site, and custom CSS classes

Verdicts: **veilig** (safe) gives a token; **blokkeer** (block) does not; **riskant** (risky) — a
reference that does not exist on the target site — only with `accept_warnings`, and
a human decides that.

Conversion happens only explicitly; nothing is guessed:

| what | how |
|---|---|
| domain, icon, class | `replace`: `[{"from":"https://oud","to":"https://nieuw"}]` |
| media ID | `media_map`: `{"111": 87}` — the url becomes that of the new file; applies to `{id, url/img}` **and** to background pairs such as `bgImg` + `bgImgID` |
| term ID | `term_map`: `{"3": 12}` — the label becomes the name of the new term |
| post ID | `post_map`: `{"183": 412}` — the `id` of `kadence/navigation`, `header`, `query`, `query-card`, `vector`, `advanced-form`, and of a `navigation-link` with `kind: post-type` (there the url becomes the permalink of the new post; the label stays) |

A post ID that does exist on the target site but is a different type is worse than
a missing one: a navigation block with the ID of a page silently shows nothing.
The report flags both (`missing`, `wrong_type`).

Media, term and post IDs are numbers; `replace` works only on text and therefore
cannot convert them. And never replace a bare number with `replace` — "112" also
appears in dimensions.

Whatever falls outside the markup does not come along: the theme's CSS, filters in
`functions.php`, the palette, the content width. That is exactly why the report names the custom
CSS classes and the palette values.

**After inserting: open the post once in the editor.** Whether Kadence considers the blocks
valid can only be told by the block's JavaScript; the server cannot
check that.

Workflow from one environment to another: `get-raw-markup` on the source →
`prepare-import` on the target, with `replace`/`media_map`/`post_map` →
`insert-blocks` with the token → open the editor and go through `isValid`.

If things are linked to each other through post IDs — a header with navigations, a mega
menu with smaller menus inside it, a footer with a vector — first create the
target posts on the target site with **`create-entity`** (empty, with Kadence's full set of
settings), note their IDs, and transfer from the bottom up:
first whatever points to nothing. Then the settings with `set-entity-meta`,
read off with `get-post-meta` on the source. Publish an element only once its content
is in it: an element on `replace_footer` replaces the footer immediately.

Ordinary posts — services, markets, posts — do not go over as markup but with
**`create-post`**: title, order, featured image (a media ID from the
target site), terms by slug, and ACF fields by field name. It checks everything against the
post type and refuses whatever does not belong to it. Relationship fields point to post IDs
on the target site: so first create the posts that others refer to. If
posts refer to each other in both directions (services among themselves), one side can only be done later:
create them first and fill in the relationship afterwards with **`update-post`**. The new IDs
then go into the `post_map` of `prepare-import`.

`update-post` works like `set-text`: without a token you get `before`
and `after` per part plus a token; with that token it writes. Only what you name
changes; `terms` replaces the terms of the named taxonomies, and ACF fields
go by field name or field key. It refuses pages and Kadence objects — those
go through the blocks and `set-entity-meta`. Note: only title, slug and
excerpt get a revision. Terms, featured image and ACF fields
do not; the `before` in the response is your way back.

## Space in a Section, and other silent no-ops

Kadence uses `gutter` and `rowGap` on a Section only if
`gutterVariable` and `rowGapVariable` at the same position are `"custom"`; otherwise
a preset (1rem) applies. Moreover, `gutter` is the space SIDE BY SIDE: in a
vertical Section the space between the children is `rowGap`. `validate-write`
blocks a number without its *Variable, and `generate-section` sets it correctly
itself (see `notes` in the response).

Four things that work, but differently than the name promises, flagged by
`validate-write` or `set-entity-meta`:

| | what happens | instead |
|---|---|---|
| `flexBasis` on a Section | applies to all children of that Section | `maxWidth` on the child (becomes `flex: 0 1 …`) |
| `background: "transparent"` on a button | transparent on the frontend, the editor shows the theme button | `rgba(0,0,0,0)` |
| `borderWidth` on a Section | the old border path: width without the color from `borderStyle` | only `borderStyle` with width, `borderWidth` empty |
| `_kad_navigation_spacing` | in em by default (`spacingUnit`), so 20 becomes 340 px | also set `spacingUnit` to `px` |

In a page or header, a navigation takes the form of a reference
(`kadence/navigation` with `id`, self-closing); the navigation post itself contains the
`kadence/navigation-link` blocks. `generate-section` can build both,
as well as `kadence/vector`.

## Moving blocks: `move-blocks`

Moving a block to another place by deleting it and inserting it again
costs its `uniqueID`, and with it the per-block CSS and every reference
to it. **`kadence/move-blocks`** moves within the same post while keeping the
ID: `position` is `before` or `after` the target block, or `inside_start` /
`inside_end` inside it. Multiple blocks move along in the order of the post. The same
two steps: without a token, a proposal with the new parent; with a token, it writes.

It refuses whatever Kadence would silently break: a Section out of or into a Row Layout (that
changes the number of columns, and `columns` is fixed in the row), a slide or tab
to a different parent (slideCount and the tab titles live in the parent), a block
whose parent rule does not allow the target, and a target that lies inside one of the
blocks being moved. Rearranging a row's columns within that row is allowed.

## Building through the editor (`wp.data`)

For what the generator is not allowed to build, or for a change that alters the saved
markup, you can let the editor itself do the work: Playwright in the
block editor, and there `wp.data` — the editor's data layer. `createBlock`,
`replaceBlock` and `updateBlockAttributes` on `core/block-editor`; `editPost` and
`savePost` on `core/editor`. Each block's `save()` then runs as well, so the
markup is by definition Kadence's.

The recipe that works:

1. create or change blocks
2. `selectBlock()` each new block once and wait until it has a
   `uniqueID` — Kadence only sets it once the block has rendered, and as a
   non-persistent change
3. `editPost({ content: wp.blocks.serialize( getBlocks() ) })` — without this step
   `savePost()` writes away the old content, without the uniqueIDs (`…-idnotset`)
4. `savePost()`
5. read back with `inspect-post` from the database, not in the editor

Why this is not the default: there is **no safety net**. No check on values,
no token, no read-back comparison; and `savePost()` writes the **entire** post,
including whatever the editor adjusted on its own when opening and whatever someone else changed
in the meantime. It also requires a logged-in browser. So use it only where the
MCP cannot do it, and read back afterwards with the MCP.

| | MCP | `wp.data` |
|---|---|---|
| reading, measuring, checking | ✓ | |
| attributes that exist only in the block comment | ✓ | |
| blocks the generator is not allowed to build (tabs) | | ✓ — or `prepare-import` |
| attributes that markup follows from | `replace-block` if the profile knows it | ✓ |

Two things you will run into:

- **A "leave page?" prompt** when navigating blocks a script that is still
  running. Afterwards, check in the database whether the save succeeded.
- **A post that has just been opened is already "modified"** if it contains blocks
  the editor never saved itself. Kadence then migrates attributes on
  load — measured on Advanced Text: `markBorder` → `markBorderStyles`.
  Harmless; save once in the editor and it is gone.

## Carousel: Advanced Slider or Post Grid

| | Advanced Slider | Post Grid (layout carousel) |
|---|---|---|
| content | fixed, per slide | dynamic from a post type |
| no looping | `loopType: none`, native | **always** loops; only with a `render_block` filter that sets `data-slider-loop-type` |
| scroll per page | the `slidesScroll` attribute is ignored by the render — a filter that sets `data-slider-scroll` to a number ≠ 1 | `slidesScroll: all`, native |
| custom card structure | yes, every slide is a container | only through the `kadence_blocks_post_loop_*` hooks |
| photo behind the card | `backgroundImg` + overlay on the slide | not native |

Slider details that cost time:

- The slider's padding sits on `.kb-advanced-slide-inner-wrap` (default
  20/48). A padding of 0 applies on the frontend; the editor ignores it and shows
  the default.
- `arrowPosition: outside-top-right` puts the arrows above the slides;
  `arrowMargin` is an object per breakpoint (`[{desk:[…],tablet:[…],mobile:[…]}]`).
- The overlay is absolute with `inset: 0`, but the wrap is not positioned —
  it calculates from the `li` and spills over a border on the wrap. One line of CSS:
  `position: relative` on the wrap.
- The overlay div exists only if there is an overlay color, and `align` sets a
  class on the wrap. Both are in the saved markup: change them through
  `replace-block` or the editor, not just as an attribute.
- A slider in a tab only starts when the tab becomes visible, and logs
  `[splide] Already mounted!` once — harmless.
- Kadence does not fill in the slides' `aria-label`: it literally says
  `%1$s of %2$s`. This also applies to the Post Grid carousel.

## A button that needs to do more than Kadence can

Put in the block what the block can do — text, icon, typography, colors, padding,
radius, margin — and only the rest in CSS, with a custom class on the button
(`singlebtn` **does** put `className` on the element). What you will run into:

- The icon has **no background of its own**. An icon in its own panel next to
  the label is CSS.
- Kadence gives the button `overflow: hidden`. Drawing something outside the button requires
  `overflow: visible`.
- The button has a `::before` (absolute, z-index -1, opacity 0) that only gets a color with
  `backgroundHoverType: gradient`. **In the editor** it gets
  the theme button's hover color through `.kt-button.kb-btn-global-fill::before` and
  fades in on hover. If you do not use it, turn it off
  (`content: none`), otherwise you will see a strange color in the editor.
- **Two layers of the same rounded shape** — a background with an overlay layer,
  or a background color with a gradient over it in one element — leave a thin edge of the bottom color at the
  corners. One gradient per panel solves it.
- Kadence's button CSS is (0,3,0) and loads after the theme's stylesheet. A
  custom rule must be (0,4,0).
- Kadence switches the icon color on `:hover` **and** `:focus`; make a custom
  hover effect respond to the same two, otherwise it gets out of step after a click.
- `inheritStyles: inherit` takes over the theme's button style. For a custom
  button: leave it at `fill`.

## The editor and the frontend are two different DOMs

- **Different classes.** In the editor the button is `.kt-button` in
  `.kb-btns-outer-wrap`, with `.kt-btn-svg-icon` and `.kt-button-text`; on the
  frontend it is `.kb-button` in `.kb-buttons-wrap`, with `.kb-svg-icon-wrap` and
  `.kt-btn-inner-text`. Write CSS for both with `:is()`; `:is()` takes the
  highest specificity in its list.
- **Icons are aligned differently.** The editor places a non-square SVG
  on the left in a 1em square (`xMinYMin`); the frontend centers it. A
  proportional width (`width: calc(1em * 20 / 24)`) makes them match;
  `width: auto` does not work on an inline SVG.
- **A server render in the editor does not run frontend JavaScript.** A block
  that is shown in the editor through `ServerSideRender` does not get its
  `viewScript`. Everything that script calculates — a clip-path, a scale —
  must therefore already be right in the markup, otherwise the editor shows something different (and so does a
  visitor without JavaScript).
- **A CSS rule in a Post Grid footer** (`.kt-blocks-post-footer svg { top:
  .125em }`) pushes every SVG in it down. Measure a button in the place where it
  actually sits.

## Color lives in a class, not only in an attribute

A Kadence text block does not take its color from `color`, but from a class in
the markup. `color: "palette9"` alone produces black text. You also need
`colorClass: "theme-palette9"`, and it has to be present as a class in the HTML:

```html
<h1 class="kt-adv-heading… wp-block-kadence-advancedheading has-theme-palette-9-color has-text-color">
```

Note the hyphen that gets added before the number: `theme-palette9` becomes
`has-theme-palette-9-color`. The same applies to `backgroundColorClass` →
`has-…-background-color has-background`.

Since 1.6.1, `generate-section` does this itself: pass both `color` **and** `colorClass` and
the classes end up in the markup automatically. If you write with `set-attributes` on an
existing block, always set both — just one of the two is half a change
that looks correct in the block data.

This is exactly the kind of error that is only visible in the browser. Measure it:
Playwright can run `getComputedStyle`, and then you see `rgb(10,10,10)` where
you expected white.

## A custom class (`className`): on a Section, the markup too

Setting a custom class looks the same everywhere, but it is not. Observed
30-10-2026 (Sportraad Utrecht, page 18, one `style-blocks` call on two Row
Layouts and one Section):

| Block | Where `className` lands | Effect of `style-blocks` / `set-attributes` |
|---|---|---|
| `kadence/rowlayout` | only in the block comment; the row saves no markup and sets the class at render time | done, no follow-up work |
| `kadence/column` (Section) | **also** in the saved `<div class="wp-block-kadence-column …">` | only the attribute changes; the response reports `class_drift` with `missing: [<klasse>]` |

With that drift the frontend looks fine (the render reads the attribute), but
the next time the block is opened, the editor calls it **invalid content**. Fix
it right away with **`replace-block`** without `block` and without attributes: it rebuilds
the markup from the attributes, with the same uniqueID, the children and the
block name (`metadata.name`). First without a token for the proposal (check in
`markup` that the class is in the `<div>` and the children are still there), then with
the token.

So after every write action, read the `class_drift` field, not only `verdict` and
`written`. Code that reacts to the class through `render_block` reads
`$block['attrs']['className']` and also works without the markup; that is no reason
to leave the drift in place.

## A highlight requires `mark.kt-highlight`

`markBG`, `markColor` and `markPadding` create a small colored box around a piece of
text — handy for a label above a heading, because a text block itself takes up the
full width, while a highlight fits around the text.

The CSS that Kadence generates hangs on `mark.kt-highlight`
(`class-kadence-blocks-advanced-heading-block.php:310`). So a bare `<mark>` gets
nothing. Write `<mark class="kt-highlight">Over ons</mark>`.

## Images live in two places, and that is a real choice

An image can be a block of its own (`kadence/image`) or the background of
a Row Layout (`bgImg`, usually with `overlayGradient` over it so that text can
go on top). Kadence offers both.

Which of the two a site uses is not a detail: if you build an image block where
the site uses backgrounds, it looks fine and is still wrong.
So first use `inspect-post` on an existing page to see how it was done there,
before you translate a design.

## Removing blocks

`remove-blocks` removes blocks from a post, at any depth — even a single button from
a button row. Two steps, like `set-text`: without a token you are shown
what would disappear; with a token it happens.

Really read that proposal. **A container takes everything below it with it**:
removing a row deletes its columns and their content. That is why the response returns
`removing` (every `uniqueID` that disappears, not only the block you named),
`blocks` (how many per type) and `text` (the text that would go). If the page
would end up empty, that is stated explicitly.

If one of the given `uniqueID`s is not in the post, nothing is
removed — a request that is half right is not half executed.

## Recreating a design from Figma

This was carried out on 11-09-2026: a frame of four sections, recreated and then
measured at seventeen points. That produced this workflow.

**First read how the site builds, not only what the design looks like.** On this stack, an
image in a design can be a `bgImg` on the row instead of
an image block; a row of colored bars can be an accordion. Use
`inspect-post` to look at an existing page before you translate.

**Hold the colors up against the palette.** `get-global-styles` returns the hex values.
If they coincide, use `palette1` and not the hex — then the page keeps
following along when the palette changes.

**Translation table that proved correct:**

| Figma | Kadence |
|---|---|
| `px-[80px]` on a 1440 frame | `inheritMaxWidth: true` |
| `pt-[176px] pb-[72px]` | `padding: [176,"",72,""]` |
| `text-[72px]` | `fontSize: [72,"",mobiel]` |
| `leading-[0.95]` | `fontHeight: [0.95,"",""]` |
| `w-[880px]` | `maxWidth: [880,"",""]` |
| `tracking-[1px]` | `letterSpacing: 1` |
| a tight box around text | `markBG` + `<mark class="kt-highlight">` |

**Set `columns` explicitly.** If you leave it out for a single column, the row assumes
two and your column gets half the width. That costs you a round.

**Set `margin` explicitly to 0 where the design shows no margin.** The theme gives
an `h1` well over a hundred pixels of top margin, and that wins if you say nothing.

### Measure the result, don't eyeball it

Playwright can run JavaScript in the page. `getComputedStyle` and
`getBoundingClientRect` return exactly what DevTools shows, but in a form
comparable to the values from `get_design_context`.

That is not a luxury. Two errors from this round could not be explained
by eye: text that stayed black while the attribute was correct (the color class
was missing from the markup), and a column that got half the width. And twice
the visual check raised a false alarm — a heading seemed to fall behind the menu while the
measurement said there were nineteen pixels of space.

**Two pitfalls in the measuring itself:**

1. **Measure on the right layer.** Kadence puts the row padding on
   `.kt-row-column-wrap` and the column background on `.kt-inside-inner-col`, not
   on the outermost div. If you measure that one, you read zero while everything is correct.
   If necessary, walk outward with `parentElement` and show each layer.
2. **A `fullPage` screenshot lies about a floating header.** Playwright
   scrolls through the page and a `position: fixed` element moves along. For
   anything around the header: a viewport screenshot at scroll position 0, or measure.

Four more, from measuring motion and hover:

3. **A forced state is not a hover.** If you set an end state directly (a
   custom property, a class), Kadence's `:hover` rules do not run along
   — and that is exactly where the error was. Also measure with a real mouse hover.
4. **Park the mouse.** After a scroll the mouse is suddenly over a different
   element, which is then in its hover state. Hover over something neutral first.
5. **Measure motion per frame.** A `requestAnimationFrame` loop that stores the
   value and the state per frame shows what a screenshot misses: a
   fade that looks like a bug, a color change that comes one frame too late.
6. **A thin edge is only visible in pixels.** Compare corner pixels of two
   screenshots; a difference in the text of less than a pixel is noise from
   a fractional position, not an error. Note that Playwright can put its own
   inspection overlay into a screenshot.

So the loop is: build, measure against the Figma values, correct with
`style-blocks`, measure again. Not: build, screenshot, squint.

## A green check is no proof that the value exists

`validate-write` checks three things: does the attribute exist, does the value match
the declared type, and is it baked into the markup. What it **cannot**
check is whether a text is a value that Kadence knows — because for many
attributes that is not in `block.json`.

That is how it went wrong with `colLayout` on a Row Layout. The schema only says
`type: string`, so the made-up value `"thirds"` got through without objection.
On the frontend it went unnoticed, because there the `kt-has-3-columns` class determines
the widths. In the **editor** it did not: the editor picks its display based on `colLayout` and, for
an unknown name, stacks all columns on top of each other.

The valid names, read from `dist/blocks-rowlayout.js`:

| columns | `colLayout` |
|---|---|
| 1 | `equal` |
| 2 | `equal`, `left-golden`, `right-golden` |
| 3 | `equal`, `left-half`, `right-half`, `center-half`, `center-wide`, `center-exwide` |
| 4 | `equal`, `left-forty`, `right-forty` |
| 5 and 6 | `equal` |

Since 1.7.2, `validate-write` blocks an unknown combination, and
`describe-block` sets `values_not_validated: true` with a note on every
string attribute without an enum.

**The rule underneath:** for such an attribute you do not invent a value. Read
it off an existing block with `get-raw-markup`, or from Kadence's
source code. Silence from the check is not approval — it means there was nothing to
check.

## Query Loops come in three layers

A Query Loop is not a single block but a chain of references:

```
de pagina         kadence/query {id:1456}        zelfsluitend, verwijst alleen
  query-post 1456   de hele loop-layout          filters, sortering, paginering
      kadence/query-card {id:232}                verwijst weer door
        card-post 232   de kaartopmaak
  + post meta op 1456   _kad_query_query         WAT er opgehaald wordt
```

(Top to bottom: the page holds a self-closing `kadence/query` that only points onward; query post 1456 holds the whole loop layout with filters, sorting and pagination; its `kadence/query-card` points on again to card post 232, which holds the card layout; and post meta on 1456, `_kad_query_query`, holds WHAT is fetched.)

Three things follow from that.

**Reusing an existing query is one block.** `kadence/query` with the `id`
of the query post, self-closing. That is why the same loop can appear on multiple
pages.

**All query parts are self-closing** — `query-card`, `query-filter`,
`query-filter-search`, `query-filter-reset`, `query-result-count`, `query-sort`
and `query-pagination`. They carry no markup of their own, so `generate-section` can
build them.

**What the query fetches is NOT in block attributes.** `postType`, `perPage`,
`orderBy` and the facets live in post meta on the query post, under
`_kad_query_query` and `_kad_query_facets`. Read that with `get-post-meta` and write it with `set-query`; you update the facets
with `sync-query-facets`. Note: post meta has no `block.json`, so there
is nothing to check a value against. A typo in `postType` produces an
empty loop without any message — that is why `set-query` refuses unknown keys
and does a trial run of the query before it writes.

Columns within a query carry `inQueryBlock: true` and usually
`noCustomDefaults: true`.

## A block without content is self-closing

`kadence/query` shows that this is not a property of a block type but of
the instance: in a page it only points onward and is empty; in its own
query post it carries the whole layout. One form, two guises.

`generate-section` follows WordPress itself here: if there is nothing between the tags and
the block has no wrapper of its own, it becomes `/-->`.

## Aligning a Section: not where you would look

A Section is a flex container, and which attribute drives which CSS depends
on `direction`. That is counterintuitive and cost three rounds on 12-09-2026.

**With `direction: vertical`:**

| you want | attribute | becomes |
|---|---|---|
| to align the content vertically | `verticalAlignment` | `justify-content` |
| the content at full width | `justifyContent: ["stretch","",""]` | `align-items` |

Yes: **`justifyContent` drives `align-items`.** That is how it is in the render
(`class-kadence-blocks-column-block.php:58, 320`). In a vertical column the
main axis is vertical, so `verticalAlignment` is about the height, and the
width stays at `flex-start` — which is why children get their natural width
instead of the full width.

The allowed values for `verticalAlignment` are `top`, `middle`, `bottom`,
`stretch`, `space-between`, `space-around` and `space-evenly`. The toolbar offers
the first four; the *Vertical Alignment* panel on a vertical Section also offers the
last three. `space-between` is the clean way to get a button at the bottom of a
card: text at the top, button at the bottom, without `flexGrow` (that does not work
here, because in a vertical Section without
alignment the inner layer is not a flex container). `describe-block` shows the list as
`known_values`, and `validate-write` checks against it.

**And note what is NOT true here.** A Section carries classes such as
`kb-section-dir-horizontal` in its markup, and it is tempting to think
that you have to change those along with it. You do not: measured in both directions, the
**attribute** drives the display, and a leftover class changes nothing
about that. So `set-attributes` and `style-blocks` can simply change `direction`
on an existing block.

## Blocks without a uniqueID: the path

Core blocks (`core/post-time-to-read`, `core/paragraph`) and the Gravity Forms
form block have no uniqueID. `inspect-post` gives them a
`path` field, for example `"pad:0.1.0.2"`: the child indexes in `parse_blocks()`,
with whitespace blocks at the top level included in the count. You simply use that path in
`validate-write`, `set-attributes`, `style-blocks`, `get-raw-markup` and
`inspect-post` (`from_unique_id`) instead of a uniqueID.

A path shifts as soon as a block is added or removed before it. The token catches
that, because it is tied to the post's modification date; so after a
structural change, read the path out again.

## A palette name only works in Kadence blocks

`"palette5"` is a Kadence reference. Core does not know it: in
`style.color.text` it literally becomes `color: palette5` (invalid), in
`textColor` the class `has-palette5-color` that nobody defines. In both
cases the block silently inherits its parent's color. `validate-write` now blocks
that, and `verify-markup` reports existing cases in `palette_names`.

In a core block, use a CSS value in `style` (`var(--global-palette5)`
or a custom token) or the preset `var:preset|color|theme-palette5`, and in
`textColor`/`backgroundColor` the slug `theme-palette5`. Gravity Forms accepts only
hex in its form block.

## Typography and fonts: what is actually set

The theme fills in a typography key that is not in the theme mods with
its default value. So a value in `get-global-styles` does not prove that it
has been set: look at `typography_sources` (`opgeslagen` (saved) or `standaard` (default)).
That is how local and staging looked identical while one had GeneralSans at 500 and the
other the system font at 700.

`styles.fonts` lists the font faces the site itself supplies (Kadence Custom
Fonts) and warns if a family from the typography has no `@font-face`
(the browser silently falls back to the fallback) or if a weight has no file of its own
(the browser synthesizes it). `environment` names the child
theme's version and whether a page cache is running: clear it first after a release, before you
treat a difference as an error.

## Transferring to another site

1. **`check-access` first.** Without `edit_theme_options`, headers,
   elements, navigations and vectors cannot be edited and drafts cannot be
   read; without `unfiltered_html`, WordPress strips SVG and `&` in a
   title becomes `&amp;`. `list-entities` reports posts that exist but are not readable
   (`unreadable`) — do not create those again.
2. **Kadence objects with `export-entity` → `import-entity`**, not with
   Kadence's own export/import: that strips backslashes from the content (`\u002d`
   becomes `u002d`) and so breaks `var(--…)` and classes, without an error message.
   `export-entity` carries the content **and** the `_kad` meta (shadows, colors, placement)
   and names the posts and media that are referenced; pass their IDs on the
   target site as `post_map` and `media_map`, and the domain as `replace`.
   IDs **inside** the meta are not converted: `meta_ids_to_check` names them per key
   (`{key, ids}`), including the pages in an element's display conditions.
   Only `_kad_` keys come along. If an object with that title already exists on the target site,
   the proposal is `riskant`: then pass `target_id` to overwrite that object.
3. **Finding the counterpart:** the uniqueIDs stay the same in a transfer,
   the post IDs do not. `find-post` with `unique_id` finds the post on the other site
   without an ID map.
4. **Posts:** `create-post` with `date` (otherwise the current date) and
   `terms: {"category": []}` (otherwise WordPress puts a post in
   *Uncategorized*); the proposal reports in `plan.wordpress_adds` what WordPress would
   add on its own.
5. **Comparing:** `site-fingerprint` on both sites and the hashes side by side;
   if one differs, request that part with `detail: true`. Terms with their
   description, the typography, fonts, Kadence objects (by title, without IDs and
   domain) and plugin versions are included — exactly what a pixel comparison only
   reveals late.
6. **Cleaning up:** objects created twice, with `trash-post`. It first looks for
   where the object is still used (by id, and for a custom SVG by
   `kb-custom-{ID}`) and then gives no token; a reference to a post in the
   trash makes the block silently disappear. Always `riskant`, even with
   `ignore_usages`: an active element on a hook (the footer!), the front page,
   posts page and privacy page, and a scan that failed or was capped — see
   `objections`. That belongs in the admin, with a human involved.

A creation token works once. The same call again returns
`kadence_mcp_token_used` with the ID that already exists, instead of a second
object — unless that first one is in the trash.

## Colors across the whole site

`audit-colors` groups, per color value, where it is used: block attributes, the
`_kad` meta of navigations, headers and queries, and the typography. A hex that
equals a palette color gets `same_as`: it looks right but does not move
along when the palette changes. You do not see colors in CSS files here.

`replace-colors` converts them with a map `{"#04201a": "palette3"}`. It deliberately
skips, with the reason in `skipped`:
- a `var(--…)` that has an opacity attached — a shadow `{color, opacity}`,
  a shadow as a list `[aan, kleur, opacity, …]` (on, color, opacity, …), or a sibling `…Opacity`:
  Kadence then converts the color to rgba, and that breaks a variable;
- a palette name in a non-Kadence block;
- a non-hex value in a Gravity Forms block.

## Vectors and custom SVGs

For a vector, `create-entity` calculates in advance what will remain of the SVG after Kadence's
sanitizer and (without `unfiltered_html`) WordPress's kses,
and refuses if elements would be lost (`accept_loss` to go ahead anyway). After
creation, it reads back element by element.

You change an existing vector or custom SVG with `update-entity-content`: a
list of `{from, to, count}`, where `count` is the number of times `from` currently
appears — if that does not match, nothing happens. For a custom SVG, the JSON must
remain valid.

## Shapes that are in no schema

Read off working blocks; `describe-block` does not know them.

- **Conditional display based on a field:** `kadenceConditional.postData` with
  `conditions: [{field: "post|post_custom_field", para: "kb_custom_input",
  custom: "<veld>", compare: "not_empty"}]`. For an ACF repeater, the
  meta stores the number of rows, so empty means hidden. On a term:
  `field: "post|has_taxonomy"`, `para: "<taxonomie>|<term>"`,
  `compare: "is_true"`. On post type: `post|post_type` equals `service`.
- **Repeater over ACF:** `source` `current|acf_repeater|<veld>` on
  `kadence/repeater`; in the template an inline dynamic span with
  `data-field="repeater|repeater_custom_field"`, `data-custom="<subveld>"`,
  `data-source` equal to the source, and `data-userepeatercontext="true"`. A
  `[kb-dynamic]` shortcode does not work there: it only runs after the rows, without
  row context. A dynamic link in such a row: `kadenceDynamic.link` with
  `useRepeaterContext: true`, plus the same shortcode in `link` with
  `userepeatercontext='true'`.
- **Inline dynamic text in any block** (including the button text of `singlebtn`):
  `<span data-field="post|post_custom_field" data-para="kb_custom_input"
  data-custom="<veld>" class="kb-inline-dynamic">standaard</span>`; the text in
  the span is the default if the field is empty.
- **Dynamic HTML with a custom field:** `metaField: "kb_custom_input"` **and**
  `customMeta`; `customMeta` alone returns nothing.
- **Dynamic background:** on a row `kadenceDynamic.bgImg`, on a Section
  `backgroundImg:0:bgImg`. In a loop (query card, element as a card)
  `inQueryBlock: true` is needed as well, otherwise all cards share one photo — and the editor
  removes that flag when saved on its own. `check-bindings` warns about this.
- **Filters:** `allOption` also works on the dropdown and the checkboxes, even though
  it is only in the `block.json` of the filter buttons.

## Recipe: mega menu

A `kadence/navigation-link` with `isMegaMenu: true` and a width
(`megaMenuWidth: "custom"`, `megaMenuCustomWidth: 802`), with as its child one
`kadence/rowlayout` (for example `columns: 3`, `columnGutter: "none"`,
`padding: [0,0,0,0]`), and inside it Sections with a heading and their own
`kadence/navigation` (`id` of a separate navigation). Two pitfalls:
- blocks in a closed mega menu are not mounted in the editor and get
  no uniqueID. `verify-markup` reports them in `missing_unique_id`, with a
  proposal in Kadence form; set that with `set-attributes` on the path;
- the navigation's `paddingDropdown` also applies to the panel, and adds up with the
  row's padding. The panel's shadow is
  `_kad_navigation_dropdownShadow` on the navigation (post meta).

## Tokens: one after another per post

Every token is tied to the post's modification date. Every save, including one of
your own, expires all other tokens on that post. So check and write per post
one after another, not in parallel, or bundle the blocks into one `style-blocks` (one
token, one save). Multiple Kadence objects with the same meta change you do
in one go with `set-entity-meta` and `items`.

## More, briefly

- `create-query` can now also start from a Kadence preset (`preset: "no-filters"`
  or `"simple"`, with `card_id`), with the same templates as the selection screen.
- `kadence/search` deliberately cannot be generated (a locked template with the
  submit button); copy one with `duplicate-blocks` or `prepare-import`.
- `replace-block` now also writes a Section's link (`kb-section-has-link`
  and the `<a class="kb-section-link-overlay">`, including with a dynamic link).
- For `maxWidth` on a Section, `validate-write` says per breakpoint what it becomes:
  in a vertical parent, the height, and also with `vertical-reverse`.
- `describe-block` flags block-support attributes whose name looks like
  a setting of its own (`style` versus `listStyle`), and knows the values of
  `widthType` (button) and `slideFrom` (off-canvas).
- `find-usages` no longer counts `id` on `kadence/tab`, `kadence/slide` and `kadence/pane`:
  there it is a sequence number, not a reference.
- `get-raw-markup` reads long posts in chunks: `truncated`, `offset` and
  `next_offset`. These count bytes, not characters, and always fall on a
  character boundary.

## Limits

- Twenty-five abilities write, all with a token. Only `style-blocks` and
  `set-text` accept `expect_modified` instead (the `post_modified_gmt`
  you already know): one call instead of two, so use it as soon as you have
  just read the post. The
  other twenty-three are read-only. Do not go by the name: `generate-section`,
  `prepare-import` and `preview-write` sound like writers but save
  nothing, while
  `sync-query-facets` and `set-card-layout` **do**.
- `inspect-post` truncates at `max_blocks` (default 200, maximum 400) and reports
  that in `truncated` and `status`.
- Large attribute values are summarized as "array with N elements." If you want
  the content, name the attribute in `full_attributes`.
- Every response has a `status` line. If a result is empty, read that line
  before you conclude that there is nothing — it tells you whether there **is** nothing or whether you
  could not look.
- Drafts and unpublished Kadence objects require extra capabilities on the
  role. If you get `kadence_mcp_post_forbidden`, report that to the user instead of
  looking for a workaround.
- `find-post` goes through WP_Query and therefore respects plugins that restrict
  content; `inspect-post` goes through `get_post` and does not. A post that
  `find-post` does not find can sometimes **be** read by `inspect-post` — the status line of
  `inspect-post` reports that. Do not hide that difference from the user.
- `get-post-meta` only returns keys starting with `_kad_`, `_kt_` and `kadence_`.
  The number of omitted keys is in `withheld`; do not try to get around that.
- `find-usages` scans a capped number of posts. If the status line says
  the scan was capped, report that too instead of saying "not used
  anywhere."

## Linking translations (WPML): `connect-translation`

The MCP knows no languages: to `inspect-post` and the write tools, a Dutch page is
an ordinary post. A translation that was created outside the translation editor
(WordPress importer, `create-post`, by hand) gets at most a language,
but is linked to nothing. The language switcher then points nowhere, and in the
page list the original shows a plus instead of a pencil.

WPML Export and Import does not solve this for content that is already on the site: it
links through three import fields (`_wpml_import_translation_group`, `…_language_code`,
`…_source_language_code`), and the export only puts those in the **file**, not on
the original in the database. The translation does get `nl`, but finds no
partner (observed at Ouwerkerk Logistics, 12-10-2026).

`connect-translation` creates the link with the official hook
`wpml_set_element_language_details` and reads back through WPML itself (trid, language, and
`wpml_object_id` from original to translation). Multiple links in one go with
`items`. Pass `language` if the translation is still in the original's language
(right after an import). It refuses an original that already has another
translation in that language, and a post that itself already has translations: those would end up
unlinked or move along with it.

Write the original first, then the translation: a write action on the original sets
the translation in WPML to *needs update*.

**The proven route for translations that are already prepared** (Ouwerkerk Logistics, 12-10-2026):

1. A WXR with the translated post per item: the same blocks with the translated text,
   `_wpml_import_translation_group` (ID of the original), `_wpml_import_language_code`,
   `_wpml_import_source_language_code`, and `_wpml_import_after_process_post_status` `publish`.
2. **Post IDs starting from the current counter.** The WordPress importer takes over a free ID from the
   file; a high number makes `AUTO_INCREMENT` jump. Resetting it is only possible once everything
   above it is gone, and `ALTER TABLE wp_posts AUTO_INCREMENT` fails on WordPress tables with
   *1067 Invalid default value for 'post_date'* until you run `SET SESSION sql_mode = ''`.
3. Import (leave the language at the default language), then *Run WPML Import*: the posts get their language
   and the status from the field, but are unlinked.
4. `connect-translation` with `items` for everything at once, then check the frontend.
5. **Converting references.** WPML converts post IDs in blocks only in its translation editor. After an
   import, `core/block` (`ref`), `kadence/query`, `kadence/query-card`, `kadence/navigation` and
   `kadence/navigation-link` in the translations still point to the originals, as do ACF fields with a
   post ID (page_link, relationship). Convert them with the original → translation map, and read back. Terms
   do not come along in a WXR: first translate the terms in WPML, then put the NL term on the NL post.
