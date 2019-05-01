# DOMO (DOM Operator)

Domo is an effort to port [Domino.js](https://github.com/fgnass/domino) to PHP, in order to provide a more performant and spec-compliant DOM library than the DOMDocument PHP extension, which is built on [libxml2](www.xmlsoft.org).

## Status

This software is a work in progress. Prioritized *TODO*s:

1. Porting of the [W3C DOM Test Suite](https://www.w3.org/DOM/Test/)
2. Porting of the [WHATWG test suite](https://wiki.whatwg.org/wiki/Testsuite)
4. Integration with [RemexHtml](https://gerrit.wikimedia.org/g/mediawiki/libs/RemexHtml/)
5. Integration with [zest.php](https://github.com/cscott/zest.php/tree/master)
6. Performance benchmarks
7. Cutting out things (even if they're in the spec) that are irrelevant to Parsoid

## Background

(taken from [this page](https://www.mediawiki.org/wiki/Parsoid/PHP/Help_wanted))

The PHP DOM extension is a wrapper around libxml2 with a thin layer of
DOM-compatibility on top ("To some extent libxml2 provides support for the
following additional specifications but doesn't claim to implement them
completely [...] Document Object Model (DOM) Level 2 Core [...] but it doesn't
implement the API itself, gdome2 does this on top of libxml2").

This is not really remotely close to a modern standards-compliant HTML5 DOM
implementation and is barely maintained, much less kept in sync with the
WHATWG's pace of change.

