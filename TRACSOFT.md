# Tracsoft Kadence MCP

This is Tracsoft's maintained copy of
[mcp-abilities-kadence](https://github.com/joostadams/mcp-abilities-kadence) by
Joost Adams. It is the plugin that lets Claude build and edit pages with Kadence
Blocks and Kadence Blocks Pro on client sites, alongside Enable Abilities for MCP,
which provides the sign-in.

Most of the code and its comments are upstream's and in Dutch. Every Tracsoft
change is marked with a `Tracsoft` comment, so `grep -rn Tracsoft` lists them.

## What Tracsoft changed

The changes in this table came with 1.27.1.1, from a security review of
upstream v1.27.1 on 2026-11-20.

| Area | Upstream | Tracsoft |
| :--- | :--- | :--- |
| Updates | From joostadams/mcp-abilities-kadence: latest release, then any version tag, then the head of `main` | Only a published, non-prerelease release of TracsoftHQ/tracsoft-kadence-mcp that carries `tracsoft-kadence-mcp.zip`. No tag or branch fallback. `Update URI` header set so WordPress.org never offers an update. |
| Write tools | Allowed in on `kadence_mcp_view`; `kadence_mcp_write` checked inside each handler, after the dry-run preview | `kadence_mcp_write` required at the permission layer for all 26 write tools |
| Reading posts by ID | Several tools (describe-post-type, describe-query, update-post previews, build previews, usage lists) loaded any post without a read check | One helper, `Kadence_MCP_Capabilities::can_read_post()`, used everywhere: `read_post`, plus `edit_post` for password-protected posts |
| Site CSS | `edit_theme_options` | Also `edit_css`, as core requires |
| Page status | `publish_posts` checked for publish only | Also for private |
| Query creation | Published without `publish_posts`; source not read-checked | Both checked |
| Kadence object types | Any type starting with `kadence_` (includes forms) for export, import and set-entity-meta | Allowlist of seven: navigation, header, element, query, query card, vector, custom SVG |
| Custom SVG content | Written raw | Requires `unfiltered_html` |
| Terms on create/update-post | No `assign_terms` check | Checked per taxonomy |
| CI | Actions pinned by tag | Pinned by commit SHA |

Added in 1.27.1.3: the **Allow administrators to use the write tools** switch
under Kadence Blocks → MCP. Upstream leaves granting `kadence_mcp_write` to
WP-CLI or a role plugin on purpose. Tracsoft builds client sites as an
administrator, so the settings screen can now grant and remove it for the
Administrator role. It is still off after installation, and only a user with
`manage_options` can change it (the screen itself needs only
`kadence_mcp_manage`). The code is `Kadence_MCP_Capabilities::set_admin_write()`
and `Kadence_MCP_Settings::sanitize_admin_write()`.

Known and accepted, not changed:

- `set-entity-meta` and `import-entity` write `_kad_` meta without running
  WordPress's per-key `auth_callback`. The gate is still `kadence_mcp_write`
  plus `edit_post` on an allowlisted Kadence object.
- The two-step token is a safety check against mistakes, not a human approval:
  an agent can preview and confirm on its own. Human review happens in Claude
  before the agent is told to write.
- Releases are not signed. Anyone who can publish a release on this repo can
  ship code to every site that runs the plugin. Protect the repo (see below).

## Setting it up on a site

1. Install MCP Adapter, Enable Abilities for MCP, and the
   `tracsoft-kadence-mcp.zip` from the latest release. Activate all three.
2. Activation gives administrators `kadence_mcp_view` and `kadence_mcp_manage`.
   Nobody gets `kadence_mcp_write`, and without it Claude sees no write tools
   at all.
3. Under Kadence Blocks → MCP, switch the plugin on and enable the tools you
   want to offer. To let Claude build, tick **Allow administrators to use the
   write tools** on the same screen and save. That gives the Administrator
   role `kadence_mcp_write`. Untick it and save when a build is done, if the
   site should go back to read-only.

   The switch covers administrators only. For another role, or one user, use a
   role plugin such as Members, or WP-CLI:

   ```bash
   wp cap add <role> kadence_mcp_write
   ```

   The switch reads the role itself, so it also shows a grant made with WP-CLI
   or Members.
4. Never install the original mcp-abilities-kadence on the same site. If it is
   there, delete it first: its uninstall removes the shared capabilities and
   settings, so reactivate Tracsoft Kadence MCP afterwards.

## How to release an update

1. Make the change on a branch and open a pull request. CI lints PHP 7.4 and 8.3.
2. Bump the version in three places, all identical:
   - `Version:` in the header of `tracsoft-kadence-mcp.php`
   - `KADENCE_MCP_VERSION` in the same file
   - `Stable tag:` in `readme.txt`
3. Add a changelog entry in `readme.txt`.
4. Merge, then tag the merge commit: `git tag v1.27.1.2 && git push origin v1.27.1.2`.
5. The Release workflow checks the three versions match the tag, lints, builds
   `tracsoft-kadence-mcp.zip` and publishes the release.
6. Sites see the update within about 12 hours (WordPress's normal update check),
   or right away from MainWP after a sync or from Dashboard → Updates →
   Check again on the site.

Version scheme: upstream version plus a fourth Tracsoft number.
1.27.1.1 is upstream 1.27.1 with the first Tracsoft changes. After merging
upstream 1.28.0, the next release is 1.28.0.1.

Don't mark a release as a pre-release unless you want sites to ignore it;
the updater skips pre-releases, which makes them a safe way to share a test
build.

## How to pull in upstream fixes

```bash
git fetch upstream --tags
git merge v1.28.0   # the upstream tag
```

Resolve conflicts keeping every `Tracsoft` change, re-check the table above
against the new code (new tools may load posts or write meta in new places),
then release as above.

## Translations

Upstream writes its source strings in Dutch. Tracsoft keeps the source as is
and ships an English translation in
`languages/mcp-abilities-kadence-en_US.mo`, loaded on every site whose
language is not Dutch (`kadence_mcp_load_english()` in the main file).

- `languages/en_US.json` is the file to edit: Dutch source string → English.
- Some Dutch words are data, not prose: the plugin returns and accepts them as
  values (verdicts `veilig`, `riskant`, `blokkeer`; the `pad:` path prefix;
  attribute group names such as `kleur`, `achtergrond`; keys such as `soort`).
  Translations keep those words in backticks with an English gloss.
- A few texts sit in PHP constants, where a translation function can't run;
  those 33 were replaced with English directly in the source. After an
  upstream merge, check new hard-coded Dutch with `grep` before releasing.
- The skill, README, readme and CONTEXT.md were translated in place; on an
  upstream merge, translate the changed sections of those files by hand.

After merging upstream changes (needs `brew install gettext`):

```bash
python3 bin/build-translations.py extract   # lists new strings in languages/untranslated.json
# add English for each one to languages/en_US.json
python3 bin/build-translations.py build     # checks placeholders, compiles the .mo
```

`build` refuses to compile if any string is missing or a `%s`-style
placeholder differs between Dutch and English.

## Protecting the release channel

Because releases are unsigned, the repo is the security boundary:

- Require two-factor authentication for every member of TracsoftHQ.
- Protect `main` (pull request required) and add a tag protection rule for
  `v*` so only admins can create release tags.
- Keep the number of people with write access small.
