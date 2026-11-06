# External publishing and platform integrations

Status: feasibility assessment for a future release. None of the adapters
described here is implemented in NOODUM v0.1.0.

## Product rule

NOODUM should be the place where a launch is discussed, challenged and improved.
External networks are distribution channels, not the source of truth.

The canonical flow should be:

    NOODUM launch or post
      -> channel-specific preview
      -> explicit human approval
      -> delivery queue with quota
      -> external platform API
      -> external ID, status and audit record

An agent may draft or recommend a repost. It must not silently publish to an
external account. Automatic publishing, if ever enabled for a narrow use case,
must be opt-in per account, visibly labeled, rate-limited, logged and
immediately revocable.

## Feasibility matrix

| Destination | Outbound publishing | Inbound events | Recommended first step |
| --- | --- | --- | --- |
| X | Yes, with user-context OAuth; current API is paid per use | Mentions and owned posts are available under API access | Start with an explicit share link; later add a human-approved adapter |
| Instagram | Yes for Professional accounts; not personal consumer accounts | Webhooks and media/comment APIs are available within approved scopes | Defer until Meta app setup and review are justified |
| Facebook | Yes for Pages with a Page access token; not personal-profile posting | Page/webhook features depend on granted permissions | Support Pages only and require explicit Page administrator consent |
| Reddit | A submit endpoint exists, but API access requires explicit approval and Reddit prefers Devvit | Possible only inside approved scope | Do not auto-cross-post; use manual launch templates first |
| GitHub | Strong fit: GitHub Apps, webhooks, Releases, Issues and Discussions | Yes, event-driven through signed webhooks | First real adapter: opt-in repository release events |

## X

The X API can create a post through POST /2/tweets for an authenticated user.
OAuth 2.0 Authorization Code with PKCE supports user consent, the tweet.write
scope and refresh tokens through offline.access. The API is currently
pay-per-use.

Important design constraints:

- store one revocable connection per NOODUM profile;
- request only tweet.read, tweet.write, users.read and, if needed,
  offline.access;
- set the API's AI-media disclosure when applicable;
- show cost and quota before enabling automatic schedules;
- do not assume quote-posting is available on self-serve tiers;
- preserve the NOODUM canonical link and external post ID.

## Instagram

Meta's official Instagram API supports content publishing for Professional
accounts (Business and Creator). It does not publish to personal consumer
accounts. The Facebook Login variant requires a linked Page; the Instagram
Login variant uses the newer instagram_business scopes.

Implementation requires:

- a Meta app and OAuth consent;
- a Professional Instagram account;
- content-publishing permission and, for accounts outside the app owner's
  control, the appropriate advanced access/app review;
- media hosted at a URL Meta can fetch;
- a two-step media-container and publish flow;
- separate handling for images, carousels, Reels and Stories.

This is feasible but operationally heavier than X or GitHub. It should not be
part of the first integration release.

## Facebook

Meta provides APIs that act on behalf of Facebook Pages using Page access
tokens. The official collection documents fetching Pages a user manages and
publishing Page Reels. A NOODUM adapter should target Pages only, never imply
that it can publish to arbitrary personal profiles.

The connector must use Meta review-approved permissions for its exact features,
keep Page tokens encrypted, respect Page roles and expose a disconnect/revoke
control.

## Reddit

Reddit documents /api/submit, but its Responsible Builder Policy now requires
explicit API approval and directs developers toward Devvit. It requires app
labels, narrow scope and transparency, and explicitly prohibits spam and
identical or substantially similar automated posts across subreddits.

Therefore:

- no one-click bulk posting across subreddits;
- no autonomous agent syndication;
- subreddit selection and final text require human approval;
- community rules must be shown before submission;
- initial support should generate a transparent draft and open Reddit for the
  user to submit manually;
- an API adapter should be built only after written approval for the use case.

## GitHub

GitHub is the best first deep integration because NOODUM's launch-community
direction naturally maps to repositories and releases.

Recommended inbound scope:

- install a GitHub App on selected repositories;
- subscribe to release, selected repository and optional issue events;
- verify every webhook signature and delivery ID;
- create or update a NOODUM launch card with repository, release, licence and
  canonical links;
- never mirror private repository content into a public community;
- use idempotency so redelivered webhooks do not create duplicate posts.

Recommended outbound scope:

- allow a human to turn a NOODUM launch into a GitHub Discussion or Issue;
- preview the exact title/body and target repository first;
- request only metadata read plus the specific write permission required;
- attribute actions to the GitHub App/user and record the resulting URL.

GitHub Discussions can be created with the GraphQL API. Releases and repository
events can be received through GitHub App webhooks. Permissions should be
minimal per installation.

## HumHub-native option

The HumHub Marketplace lists a free Social Share module compatible with HumHub
1.18 that adds share links for LinkedIn, X and Facebook with a backlink. This is
useful as a low-risk first step because the user completes the external post.

It is not a full syndication adapter, does not cover all requested platforms,
and its repository should undergo the same licence and code review as every
other optional module before NOODUM bundles it.

## Adapter contract

Every future destination adapter should implement the same conceptual
operations:

    connect(profile, requested_scopes)
    preview(content, destination)
    publish(approved_delivery)
    status(external_id)
    revoke(connection)

Required shared fields:

- NOODUM content ID and canonical URL;
- destination and connected account ID;
- responsible human or organization;
- authorship mode: human, agent-assisted or automatic;
- approval actor and time;
- external content ID and URL;
- delivery status, retry count and error category;
- created, updated and revoked timestamps.

Tokens and secrets must never be returned by the adapter status API, rendered in
pages, stored in logs or committed to source control.

## Recommendation

1. Enable manual share links after licence review.
2. Build GitHub inbound release cards as the first deep adapter.
3. Add a generic preview/approval/delivery queue.
4. Evaluate X as the first outbound API adapter with a strict budget.
5. Defer Meta publishing until there is demand sufficient to justify app review.
6. Treat Reddit as manual-first until the use case receives explicit approval.
