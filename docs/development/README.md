# Development records

Design decisions and verification records from the package's initial
development. They explain why the hierarchy, authorization and assignment code
behaves as it does; read them before changing those contracts. User
documentation lives in the [README](../../README.md). This folder is excluded
from package archives.

| Document | Contents |
| --- | --- |
| [FOUNDATION.md](FOUNDATION.md) | Hierarchy, authorization, concurrency and compatibility contracts (milestones F0–F5) |
| [F6_ACCEPTANCE.md](F6_ACCEPTANCE.md) | Performance measurements, operating guidelines and open acceptance checks |
| [F6_REVIEW.md](F6_REVIEW.md) | Review loops and fixes for milestones F0–F5 |
| [f6-performance.json](f6-performance.json) | Raw measurement samples behind F6_ACCEPTANCE.md |
| [P1_ASSIGNMENTS.md](P1_ASSIGNMENTS.md) | Design of explicit term assignments (`HasTaxonomies`) |
| [P2_FIELDS.md](P2_FIELDS.md) | Design of the `TaxonomySelect` form field |
