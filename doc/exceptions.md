# Exceptions

Every exception of the library implements `Phil\RouteAccessCheck\Exception\RouteAccessCheckException`, which extends
`Throwable`: one `catch` catches them all.

| Exception | Extends | Thrown when |
|---|---|---|
| `InvalidBaseline` | `InvalidArgumentException` | A baseline is not the versioned JSON document the library writes, is larger than 5 MB, or tolerates a status that is not a failure. |
| `InvalidSecurityMap` | `InvalidArgumentException` | The security map is not in the shape `SecurityMap::fromArray()` reads, holds a regular expression PCRE refuses, or more than 10,000 rules. |
| `InvalidConfiguration` | `InvalidArgumentException` | A `#[PublicRoute]` reason is empty or longer than 500 characters, the route option of `PublicRouteDetector` has no name, or an ignored prefix is empty. |

Their messages name the faulty part — `access_control[2].request.port is an integer.` — and never the content of a
file beyond it. A `#[PublicRoute]` with an empty reason does not stop a check: the route stays uncovered, its rule
carrying the message.
