<?php

namespace DOM\util

/*
 * PORT: Replaces utils.js NAMESPACE = {
 *                              HTML: "http://www.w3.org/1999/xhtml",
 *                              ...
 *                         }
 */
define("DOM\util\NAMESPACE_HTML", "http://www.w3.org/1999/xhtml");
define("DOM\util\NAMESPACE_XML", "http://www.w3.org/XML/1998/namespace");
define("DOM\util\NAMESPACE_XMLNS", "http://www.w3.org/2000/xmlns/");
define("DOM\util\NAMESPACE_MATHML", "http://www.w3.org/1998/Math/MathML");
define("DOM\util\NAMESPACE_SVG", "http://www.w3.org/2000/svg");
define("DOM\util\NAMESPACE_XLINK", "http://www.w3.org/1999/xlink");


/*
 * TODO: Should we target PHP 7 features, or should we provide
 * backwards-compatibility with say 5.8?
 *
 * Asserts in particular have changed, so.
 *
 * In fact, should we event use asserts, or just throw instead?
 */
/*
 * Original:
 * throw new Error("Assertion failed: " + (msg || "") + "\n" new Error().stack);
 *
 * TODO: Need to add the stack trace info, or advise catchers call
 * Exception::getTraceAsString()
 *
 */
function assertion(boolean condition, string message)
{
        if (!condition) {
                throw new Exception("Assertion failed: " + (msg || "") + "\n" new Error().stack);
        }
}


?>
