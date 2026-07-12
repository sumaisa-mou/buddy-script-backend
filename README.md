# Feed App — Backend (Laravel API)

This is the backend for a small social feed app I built as part of an interview assessment. Users can register, log in, create public or private posts (with images), and interact through likes, comments, and replies.

It's a plain Laravel REST API, consumed by a React frontend. I deliberately kept the dependency list to what ships with Laravel — no extra packages — and focused the effort on the two things the task called out: security and performance at feed scale.

## Tech Stack

- **Laravel 10** (PHP) — REST API
- **MySQL** — relational data fits this domain naturally
- **Laravel Sanctum** — session/cookie-based SPA authentication

## Getting Started

```bash
composer install
cp .env.example .env          # then fill in your DB credentials
php artisan key:generate
php artisan migrate
php artisan storage:link      # exposes uploaded post images
php artisan serve
```

The API runs at `http://localhost:8000`. Make sure the frontend's origin is listed in `SANCTUM_STATEFUL_DOMAINS` so the session cookie works.

## How Authentication Works

I went with **Sanctum's cookie-based SPA auth** rather than JWT. For a first-party frontend on the same domain, an `HttpOnly` session cookie is the safer default: the token is never readable by JavaScript, so an XSS bug can't exfiltrate it, and Laravel's CSRF protection covers the rest. Login and register endpoints are rate-limited (6 attempts/minute), and the session ID is regenerated on login to prevent session fixation.

One small but deliberate detail: requesting someone else's **private** post returns a **404, not a 403**. A 403 would confirm the post exists; a 404 reveals nothing.

## The Interesting Part: Loading the Feed Without N+1

The feed is where most of the design thinking went. A naive feed implementation dies in two places:

1. **N+1 HTTP calls** — the frontend fetches the post list, then fires a separate request per post for its comments, then one per comment for its replies. Twenty posts on screen could mean 60+ requests.
2. **N+1 database queries** — even with one HTTP call, lazily loading each post's author, comments, like counts, and "did I like this?" flag multiplies queries per row.

### One HTTP call, a fixed number of queries

`GET /posts` returns everything the feed needs to render in a **single response**: each post comes with its author, image attachments, like count, comment count, whether *the current viewer* liked it, a preview of its **latest 2 top-level comments**, and for each of those comments a preview of its **first 2 replies** — each with their own like counts and liked-by-me flags.

On the database side, Eloquent's eager loading (`with` / `withCount`) batches all of that into a handful of queries **regardless of how many posts are on the page**. Loading 20 posts costs the same number of queries as loading 2.

### How the "2 comments per post" preview works

Eager loading has a well-known limitation: `->limit(2)` inside a `with()` applies one limit across the *whole* result set, not per post. The common fixes are pulling in a package or running a query per post — I wanted neither. Instead, the comments relation filters with a correlated subquery:

```sql
(select count(*) from comments c2
 where c2.post_id = comments.post_id
   and c2.parent_id is null
   and c2.id > comments.id) < 2
```

In plain terms: *keep a comment only if fewer than 2 newer comments exist on the same post* — which is exactly "the latest 2 per post", enforced in SQL, in one batched query. The same pattern caps reply previews at 2 per comment. Full comment threads and older replies load on demand through dedicated paginated endpoints (`GET /posts/{post}/comments`, `GET /comments/{comment}/replies`), so the deep data is only fetched when a user actually asks for it.

### "Liked by me" without loading likes

Whether the viewer liked a post or comment is computed as a **scalar subquery** (`addSelect`) against the likes table, selecting a constant `1` with `limit 1`. The alternative — loading like rows into memory or a `whereExists` per row in PHP — doesn't scale. This way the flag rides along in the same query and hits the unique index on `(user_id, post_id)` / `(user_id, comment_id)`.

### Designed for millions of rows

- **Cursor pagination** (`cursorPaginate`) instead of offset pagination. `OFFSET 100000` forces the database to scan and discard 100k rows; a cursor seeks directly to the next page, so page 5,000 is as cheap as page 1. It also doesn't skip or duplicate posts when new ones arrive while scrolling.
- **Indexes match the queries**: `(visibility, created_at)` on posts for the feed's filter + sort, `(parent_id, created_at)` on comments for reply threads, and unique composite indexes on both like tables — which double as data integrity (a user physically cannot like the same thing twice).
- **Replies are one level deep** (`parent_id` on the comments table, self-referencing). The design only needs comment → reply, so I skipped generic tree structures — a nullable `parent_id` is simpler, and the queries stay flat.

## Data Model

```
users ─┬─< posts ─┬─< comments (parent_id → comments, one level of replies)
       │          ├─< post_likes      (unique per user+post)
       │          └─< attachments     (polymorphic — reusable for other models)
       └─< comment_likes              (unique per user+comment)
```

Visibility is a column on `posts` (`public` / `private`), enforced in one place — a `visibleTo` query scope reused by every endpoint that touches posts, so there's no way to forget the check on a new route.

## API Overview

| Method | Endpoint | Purpose |
|---|---|---|
| POST | `/api/register`, `/api/login`, `/api/logout` | Auth (rate-limited) |
| GET | `/api/me` | Current user |
| GET | `/api/posts` | Feed — everything in one call, cursor-paginated |
| POST | `/api/post` | Create post (text + images, public/private) |
| GET / POST | `/api/posts/{post}/comments` | Paginated comments / add comment or reply |
| GET | `/api/comments/{comment}/replies` | Paginated older replies |
| POST / DELETE / GET | `/api/posts/{post}/likes` | Like, unlike, who liked |
| POST / DELETE / GET | `/api/comments/{comment}/likes` | Same, for comments and replies |

All feed routes sit behind `auth:sanctum`. Input validation lives in dedicated Form Request classes, and responses are shaped by API Resources so the wire format stays consistent and never leaks internal fields.

## Trade-offs I Made Knowingly

- **Image uploads are fault-tolerant, not transactional**: if one of several images fails to store, the post and remaining images still succeed and the response says how many failed. For a feed, a post with a missing image beats a failed post.
- **No caching layer yet.** The query shape is efficient enough for the assessment's scope; at real scale the next step would be caching the hot feed page and moving image storage to S3 + a CDN.
- **Like counts are computed, not denormalized.** `withCount` on indexed foreign keys is fine at this scale; counter columns are the known upgrade path if profiling ever demands it.
