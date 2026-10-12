# Security review — 2026-10-13

Prepared by i729. This review records tested source changes; it is not a certification of the deployed service.

## Change

Replace the published visitor statistics key with private configuration/header authentication; strip query strings from stored visitor metadata, require HTTPS for upstream API requests/redirects, default outbound visitor notifications off, and pin workflow actions.

## Validation

41 PHP files linted; seven secret/ownership helper checks passed; browser JavaScript and workflow shell syntax passed.

## Required before rollout

Configure VISIT_ADMIN_KEY privately, deploy the endpoint and browser script together, and retire the old public key. Visitor tracking is still a public endpoint and hosting-level rate limits are needed for abuse resistance. No live bot messages or site deployments were performed.

## Scope

Source and locally fetched Git branches/tags/available pull-request heads were inspected. Private production settings, actual database policies, deployed binaries, backup stores, and third-party service permissions were not changed. Current-source cleanup does not remove data from Git history, existing forks, cached copies, or previously downloaded assets. Sensitive values are intentionally omitted.
