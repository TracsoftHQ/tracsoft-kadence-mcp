# Agent skill

`kadence-blocks/SKILL.md` contains the working methods and pitfalls that don't fit in a
tool schema — how the conventions differ per block, where text and
visibility live, and which call fits which question.

The server description already carries the conventions and is loaded automatically. This
skill is the level above that: it describes *working methods*, and is intended for
anyone who uses the plugin on multiple sites or with multiple people.

## Installation

Copy the `kadence-blocks` folder to your agent's skills folder:

    ~/.claude/skills/kadence-blocks/        (gebruikersbreed)
    <project>/.claude/skills/kadence-blocks/ (per project)

Claude then loads it on its own based on the `description` in the frontmatter.
