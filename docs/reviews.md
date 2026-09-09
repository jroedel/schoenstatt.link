# Reviews and comments — started, unfinished, tabled

What a visitor says *about* a record (a pilgrim's impression of a shrine, a reader's note on a
publication), in the shape Google reviews have. **Tabled**; attention is on the shrine dataset
([shrine-data.md](shrine-data.md)). One live exposure below.

## What this is not

**Not an edit-proposal system.** A review is an opinion; a suggestion is a proposed change to
the corpus. The codebase carries two separate vocabularies and they were conflated once here
(the `Status`/`ReviewedBy`/`ReviewedOn` columns were misread as a moderation buffer for edits,
and removing the whole surface was proposed on that basis):

| | `SionModel\Db\Model\PredicatesTable` (reviews) | `SionModel\Db\Model\SionTable` (edit proposals) |
| --- | --- | --- |
| statuses | `in-review`, `published`, `denied` | `SUGGESTION_ERROR`, `_INREVIEW`, `_ACCEPTED`, `_DENIED` |
| kinds | `comment`, `rating`, `review` | `ENTITY_ACTION_SUGGEST` |
| storage | `comments` + `predicates` + `relationships` | nothing — never built, no UI |

Edit proposals belong with provenance — [shrine-data.md](shrine-data.md) § Workstream 2.

## What exists

- **`comments`**: `Rating`, `CommentKind`, `Comment varchar(500)`, `Status`, `ReviewedBy`,
  `ReviewedOn`, created/updated columns. **No entity reference of its own.**
- **Attachment through the predicate graph**: a `relationships` row whose `PredicateKind` has
  `SubjectEntityKind = 'comment'`. Five exist: `comment-comments-composition`, `-event`,
  `-file`, `-text`, `comment-reviews-publication`.
- **`PredicatesTable` is read-only** (`getComments()`, `getComment()`,
  `getCommentPredicates()`, `getPredicates()`); nothing writes a status.
- **Create path**: `App\Controller\CommentCreateController` (Symfony, `comments/create`) and
  `SionModel\Controller\CommentController`, `SionModel\Form\CommentForm`, templates
  `templates/sion-model/_comment-create.html.twig`, `_comments-list.html.twig`.
- **Eight real comments**, 2019-07-01 to 2025-05-28, seven accounts. All `published`,
  `ReviewedBy`/`ReviewedOn` NULL. Comment 6 is exactly 500 characters (509 bytes), so the
  `varchar(500)` bound holds under multi-byte text.

## What is missing

- **The rating half.** `CommentForm` has `comment`, `redirect`, `submit` — no rating input, so
  the `rating`/`review` kinds are unreachable and `Rating` is NULL in all eight rows.
- **Moderation UI.** `in-review` and `denied` have never been written; **both controllers
  hardcode `COMMENT_STATUS_PUBLISHED`** (`CommentCreateController:129`,
  `CommentController:18` with its `@todo get some info from entity spec`).
- **Shrines cannot be reviewed** — no `comment-*-association` predicate, on the one subject
  the feature was intended for.
- **Empty submissions create rows**: `comment` is `required => false` with `ToNull`; two of
  eight rows are empty strings.
- **No duplicate guard**: comments 2 and 3 are one author, one second apart, both published.

## The live exposure

**Any address that can receive mail can publish text on this site with no review.**

```
passwordless registration — an unknown address becomes an account (UserTable::createUserFromEmail)
  → sch_user is is_default = 1, so every new account holds it
    → sch_user (id 23) has parent_id 28 = the `user` role
      → config/autoload/acl.global.php guards route/comments/create with ['user']
        → CommentCreateController:129 sets status = published unconditionally
          → the comment renders on the entity page immediately
```

Confirmed live: the 2025-05-28 comment is served at `https://schoenstatt.link/en/literature/8197`.
Bounds, neither of which closes it: blast radius is publications, compositions, events, files
and texts (not shrines, for want of a predicate); observed volume is 8 comments in 6 years.

That volume settles the design: the "a queue becomes where updates wait" objection in
[shrine-data.md](shrine-data.md) is about field edits at scale. **At 1.3 items a year a queue is
an inbox**, so a moderation buffer is correct here.

## Next steps, in dependency order

1. **Status from the entity spec, defaulting to `in-review`** (the existing `@todo`).
2. **A moderation screen** — list pending, publish/deny, writing `Status`, `ReviewedBy`,
   `ReviewedOn`; `PredicatesTable` needs a write path. **1 and 2 ship together**, or reviews
   silently stop appearing.
3. **Empty-comment and duplicate-submit validation**: `NotEmpty` on the element; refuse an
   identical body from the same author within a short window.
4. **Shrine reviews, if wanted** (the original intent): add the `comment-*-association`
   predicate and a rating element; then the shrine page can carry schema.org `aggregateRating`.

1–2 are exposure, 3 is hygiene, 4 is a product decision not to be rushed alongside them.
