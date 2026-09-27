# Re-read Tracker Comments Before Posting

Before publishing a comment to the issue tracker, **fetch the issue's comments again** — even when you already read them earlier in the same session.

- Your earlier read is stale by definition: a teammate may have posted meanwhile, and your own previous attempt may have succeeded while looking like it failed.
- A tool call that errors or times out can still have created the comment. Never repost after a failure without re-reading first.
- The MCP tools available here can create and edit a comment, but **cannot delete one**. A duplicate is removed by hand, by a person, so the cost of skipping the check is somebody else's time.
- The check is a single call and it precedes every `issue_comment_create`, without exception.
- The same applies to any other outward-facing post that cannot be taken back: merge-request notes, chat messages, review comments.

Origin: CI-284 — the same comment was published twice, six minutes apart (16.09.2026), and again went unnoticed until the lead cleared it by hand (22.09.2026).
