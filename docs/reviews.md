# Reviews and comments — started, unfinished, tabled

What a visitor says *about* a record, as opposed to what the record says. A pilgrim's
impression of a shrine, a reader's note on a publication — a human interaction with the
digital model, in the shape Google reviews have.

**Tabled 2026-08-23.** Attention is on the shrine dataset itself
([shrine-data.md](shrine-data.md)); this document exists so the next person does not have to
re-derive what is here. One item in it is a live exposure and is called out below.

## What this is not

**Not an edit-proposal system.** A review is somebody's opinion; a suggestion is a proposed
change to the corpus. They are different features with different tables, and the codebase has
always said so — it carries two separate vocabularies:

| | `SionModel\Db\Model\PredicatesTable` (reviews) | `SionModel\Db\Model\SionTable` (edit proposals) |
| --- | --- | --- |
| statuses | `in-review`, `published`, `denied` | `SUGGESTION_ERROR`, `_INREVIEW`, `_ACCEPTED`, `_DENIED` |
| kinds | `comment`, `rating`, `review` | `ENTITY_ACTION_SUGGEST` |
| storage | `comments` + `predicates` + `relationships` | nothing — never built |

Worth recording that these were **conflated once**, in this repo, on 2026-08-23: the review
workflow columns (`Status`, `ReviewedBy`, `ReviewedOn`) were read as a moderation buffer for
proposed *edits*, and a removal of the whole "suggest-moderate" surface was proposed on that
basis. The mistake is easy to make because the two systems share vocabulary — "in review",
"denied" — and because the edit-proposal half is invisible, having never been implemented. If
you are looking at `SUGGESTION_*` and wondering where the UI is, there isn't one, and it has
nothing to do with `comments`.

Edit proposals belong with provenance instead — see
[shrine-data.md](shrine-data.md) § Workstream 2.

## What exists

- **`comments`** — `Rating`, `CommentKind`, `Comment varchar(500)`, `Status`, `ReviewedBy`,
  `ReviewedOn`, plus created/updated columns. **No entity reference of its own.**
- **Attachment is through the predicate graph.** A comment is linked to its subject by a
  `relationships` row whose `PredicateKind` has `SubjectEntityKind = 'comment'`. Five exist:
  `comment-comments-composition`, `-event`, `-file`, `-text`, and
  `comment-reviews-publication`.
- **`PredicatesTable`** — read side only: `getComments()`, `getComment()`,
  `getCommentPredicates()`, `getPredicates()`. Nothing writes a status.
- **A working create path on both front controllers** —
  `App\Controller\CommentCreateController` and `SionModel\Controller\CommentController`, with
  `SionModel\Form\CommentForm` and a ported Twig template
  (`templates/sion-model/_comment-create.html.twig`, `_comments-list.html.twig`).
- **Eight real comments**, 2019-07-01 through 2025-05-28, from seven distinct accounts.

## What is missing

- **The rating half was never built.** `CommentForm` has three elements — `comment`,
  `redirect`, `submit`. There is no rating input, so the `rating` and `review` kinds are
  unreachable through the UI and `Rating` is `NULL` in all eight rows. What exists is a
  comments feature; the Google-reviews shape needs the stars.
- **No moderation UI.** `in-review` and `denied` have never been written. Both controllers
  hardcode `COMMENT_STATUS_PUBLISHED`, and the laminas one carries the `@todo` that says what
  should happen instead: *get some info from entity spec*.
- **Shrines cannot be reviewed.** There is no `comment-*-association` predicate, so the one
  subject where a pilgrim's impression is most valuable — and which the feature was intended
  for — cannot receive one.
- **`comment` is `required => false` with a `ToNull` filter**, which is why two of the eight
  rows hold an empty string. An empty submission creates a row.
- **No duplicate guard.** Comments 2 and 3 are the same author, the same length, one second
  apart — a double submit, stored twice, both published.

One thing that *is* right: comment 6 is exactly 500 characters (509 bytes), so the
`varchar(500)` bound is holding correctly under multi-byte text.

## The live exposure

**Any address that can receive mail can publish text on this site, with no review.** The
chain, verified 2026-08-23:

```
passwordless registration — an unknown address becomes an account
  (JUser\Model\UserTable::createUserFromEmail)
    → sch_user is is_default = 1, so every new account holds it
      → sch_user (id 23) has parent_id 28, which is the `user` role
        → config/autoload/acl.global.php guards route/comments/create with ['user']
          → CommentCreateController:130 sets status = COMMENT_STATUS_PUBLISHED unconditionally
            → the comment renders on the entity page immediately
```

Confirmed rather than reasoned: the 2025-05-28 comment is served today at
`https://schoenstatt.link/en/literature/8197`.

Two things bound the risk, and neither closes it. The blast radius is publications,
compositions, events, files and texts — **not** shrines, for want of a predicate. And the
observed volume is eight comments in six years, so this has not been discovered and abused.

That volume also settles the design question the buffer would otherwise raise. A concern
recorded in [shrine-data.md](shrine-data.md) § Decision 1 — that a moderation queue becomes
the place updates go to wait — was an argument about field edits at scale. **At 1.3 items a
year a queue is an inbox, not a backlog**, so for this surface the buffer is plainly correct
and that objection does not apply.

## Next steps, when it is picked up

In dependency order. The first two must ship together, because defaulting to `in-review`
without a moderation screen means reviews silently stop appearing.

1. **Take the status from the entity spec** rather than a constant, defaulting to
   `in-review`. This is the `@todo` already in the code.
2. **A moderation screen** — list pending, publish or deny, writing `Status`, `ReviewedBy`
   and `ReviewedOn`. `PredicatesTable` has no write path for these; it needs one.
3. **Decide the empty-comment and duplicate-submit behaviour.** Both are validation, not
   moderation, and both are cheap: a `NotEmpty` on the element, and a guard against an
   identical body from the same author within a short window.
4. **If shrine reviews are wanted** — and the original intent says they are — add the
   `comment-*-association` predicate and a rating element. Then a shrine page could carry
   schema.org `aggregateRating`, which feeds the structured data the shrine pages already
   emit and is worth real traffic on exactly the pages that get it.

Ordering note: 1 and 2 are exposure, 3 is hygiene, 4 is a product decision that deserves not
to be rushed alongside them.

## Findings log

- **2026-08-23 — reviews and edit proposals were conflated here**, and a removal of the
  suggest-moderate surface was proposed on the strength of it. Corrected by the person who
  designed the feature: `comments` was always about sharing an impression, never about editing
  the corpus. The two vocabularies in the table above are the evidence that the codebase knew
  the difference all along.
- **2026-08-23 — the review status vocabulary exists and has never been used.**
  `COMMENT_STATUS_IN_REVIEW` and `_DENIED` are defined; all eight rows are `published` with
  `ReviewedBy` and `ReviewedOn` `NULL`. The buffer was designed and left unwired, which is
  what makes the exposure above possible.
