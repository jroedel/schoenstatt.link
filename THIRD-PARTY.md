Third-party code in this repository
===================================

`LICENSE.txt` (BSD-3-Clause) covers everything here except what is listed below.
Nothing in this file grants or withdraws a permission — it records who wrote
what, and under which terms it arrived, so that a reader does not have to
reconstruct it from file headers.

The inventory is taken from the shipped copies themselves. Where a copy carries
no licence banner, this file says so rather than naming a licence from the
upstream project's current README, which is not what was vendored.


Derived from Zend Framework 2
-----------------------------

Seven files were taken from `zendframework/zf2` and adapted. Each retains its
original header — `Copyright (c) 2005-2015 Zend Technologies USA Inc.`, New BSD
— which is what that licence requires, and the reason Zend's copyright also
stands at the head of `LICENSE.txt`:

    module/SionModel/src/Filter/DateSelectNoYear.php
    module/SionModel/src/Filter/MixedCase.php
    module/SionModel/src/Filter/SortArray.php
    module/SionModel/src/Filter/ToAscii.php
    module/SionModel/src/Filter/ToDateTime.php
    module/SionModel/src/Filter/TrimStringArray.php
    module/SionModel/src/Validator/RegularExpression.php

`LICENSE.txt` itself began as the `zendframework/skeleton-application` licence
file and named Zend alone until 2026-09. That was an artefact of how the project
started, not a statement about who wrote the code.

Elsewhere in the tree, comments describing what laminas *did* — "laminas'
behaviour", "laminas' rule" — are references, not copied code. Two places carry
a laminas expression rather than a laminas design: the float arithmetic in
`module/SionModel/src/Validator/Step.php`, and an HTML5 e-mail pattern literal.
Both are noted here rather than given a header of their own.


Modules with their own licence
------------------------------

Absorbed from their own repositories on 2026-09-23. Their `LICENSE` files remain
beside them and continue to govern them:

| module | licence | holder |
| --- | --- | --- |
| `module/JUser` | MIT | Copyright (c) 2016 jroedel |
| `module/JTranslate` | MIT | Copyright (c) 2016 jroedel |

`module/SionModel` has no licence file of its own — it never had one — and is
covered by `LICENSE.txt` like the rest of the application.


Front-end assets under `public/`
--------------------------------

Vendored as files rather than installed by a package manager, which is how the
application has always served them. Each retains the banner its upstream shipped
with, where there was one.

| library | version | licence as the shipped copy states it |
| --- | --- | --- |
| Bootstrap | 3.0.3 and 3.3.7 | the 3.0.3 copies say Apache-2.0, the 3.3.7 copies say MIT — upstream relicensed between them, and both versions are present |
| Glyphicons Halflings | with Bootstrap 3 | `public/fonts/glyphicons-halflings-regular.*` |
| jQuery | 3.2.1, 3.3.1 (and a 1.11.1 source map) | MIT |
| jQuery UI | 1.12.1 | MIT |
| jQuery Validation | 1.17.0 | MIT, Copyright (c) 2017 Jörn Zaefferer |
| Font Awesome | 4.6.3 | font SIL OFL 1.1, CSS MIT |
| selectize.js | 0.12.2 | Apache-2.0, Copyright (c) 2013–2015 Brian Reavis & contributors |
| bootstrap-markdown | 2.10.0 | Apache-2.0, Copyright 2013-2016 Taufan Aditya |
| bootstrap-sortable | 2.0.0 | MIT, Matus Brlit (drvic10k) and contributors |
| Chart.js | 2.6.0 | MIT, Copyright 2017 Nick Downie |
| clipboard.js | 1.5.10 | MIT © Zeno Rocha |
| Fuse.js | 2.0 | Apache-2.0, Copyright (c) 2012-2016 Kirollos Risk |
| Moment.js | 2.3.1 | MIT |
| Vue.js | 1.0.22 | MIT, (c) 2016 Evan You |
| markdown.js | — | MIT, Dominic Baggott, Ash Berlin, Christoph Dorn |
| to-markdown | — | MIT, Copyright 2011+ Dom Christie |
| html5shiv | 3.7.2 | dual MIT/GPL2 |
| respond.js | 1.1.0 | dual MIT/BSD, Scott Jehl, Paul Irish, Nicholas Zakas |

Four vendored copies carry **no banner at all**, so the terms cannot be read off
the file. They are listed for completeness, and re-fetching each from upstream
with its banner intact is the way to close this:

    public/js/commonmark.js
    public/js/timeline.js
    public/css/flag-icon.css, public/css/flag-icon.min.css
    public/js/jquery-eu-cookie-law-popup.js, public/css/jquery-eu-cookie-law-popup.css

The `gen-*.js` and `gen-*.css` files are build output: concatenations of the
sources above with the application's own scripts. Concatenation does not always
preserve a banner, so the table rather than the bundle is the record of what
they contain.
