Reporting a security problem
============================

schoenstatt.link is a live production site holding personal data about members of
the Schoenstatt Movement — names, e-mail addresses, telephone numbers, and the
borrower records of its lending libraries. A flaw here has consequences for real
people, so a report is genuinely welcome and will be taken seriously.

Please do not test against the live site
----------------------------------------

<https://schoenstatt.link> is production. There is no staging deployment today
(tracked as #270). Automated scanners, injection attempts and load generation
against it affect real users and fill the exception store with noise that hides
real faults.

Everything you need to look for a flaw is in this repository, and the Docker
capsule described in [CONTRIBUTING.md](CONTRIBUTING.md) brings up a complete copy
of the application against an invented database in about two commands. Test
there. If you believe a finding can only be confirmed against production, say so
in your report and ask first.

How to report
-------------

**Preferred: GitHub private vulnerability reporting.** Use the *Report a
vulnerability* button under this repository's Security tab. It gives you a private
thread with the maintainer, and nothing is published until we agree it should be.

**By e-mail:** <webmaster@schoenstatt.link>. Please put "security" in the subject.

Either way, a useful report says what you did, what happened, and what you expected
instead. A request and a response are worth more than a scanner's classification.

Please do not open a public issue for a vulnerability. Everything else — including
a suspicion you are not sure about — is fine as a normal issue.

What to expect
--------------

This is a volunteer-maintained project, not a staffed product, and it is honest to
say so rather than promise a service level nobody is on call to meet:

- an acknowledgement within **seven days**;
- an assessment of whether it is exploitable, and a fix or a stated decision not to
  fix, as soon as one can be worked out;
- credit in the commit or the release notes if you want it, and none if you prefer.

If seven days pass with no reply, the address may be failing — please open a public
issue saying only that you sent a security report and got no answer, with no detail
about the finding.

Scope
-----

**In scope**: anything reachable at schoenstatt.link that this repository builds —
the application in `src/` and `module/`, the `/api/v3` endpoints, the
authorization model in `src/Acl` and `src/Authorization`, the magic-link
authentication in `module/JUser`, the maintenance endpoints under `/sm/`, the
deploy and migration tooling in `tools/`.

**Out of scope**: the shared hosting platform and its control panel, which belong
to the hosting provider and not to this project; the dependencies under `vendor/`
(report those upstream, though do tell us so the version can be pinned); and
anything requiring physical access or a compromised maintainer account.

Known and already accepted
--------------------------

Please check these before writing a report — they are published deliberately, and
a finding that is already on one of these lists is not news:

- **`test/Fuzz/known-form-gaps.php`** is an explicit catalogue of form validation
  gaps that are known and accepted. The fuzz suite's contract is "no *new* gaps".
- **`docs/acl-baseline.json`** records every route's guard, including the routes
  that carry none. A route being unguarded there is a recorded fact, not an
  oversight — though an argument that a particular one should be guarded is a
  perfectly good issue.
- **The open issues**, particularly #252 through #258, which are this project's own
  audit of its privacy and data-handling gaps. They are open because they are real.

What is genuinely interesting: anything that lets one person see another's personal
data, anything that gets past the magic-link authentication in `module/JUser`,
anything that escalates a role, and anything that turns a maintenance endpoint into
a way in.
