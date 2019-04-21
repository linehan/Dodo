<?php

namespace domo

/*
 * PORT: Replaces utils.js NAMESPACE = {
 *                              HTML: "http://www.w3.org/1999/xhtml",
 *                              ...
 *                         }
 */
const NAMESPACE_HTML = 'http://www.w3.org/1999/xhtml';
const NAMESPACE_XML = 'http://www.w3.org/XML/1998/namespace';
const NAMESPACE_XMLNS = 'http://www.w3.org/2000/xmlns/';
const NAMESPACE_MATHML = 'http://www.w3.org/1998/Math/MathML';
const NAMESPACE_SVG = 'http://www.w3.org/2000/svg';
const NAMESPACE_XLINK = 'http://www.w3.org/1999/xlink';


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

/* #1 ASCII LOWER CASE */
/* #2 XML NAME VALIDATION */
/* #3 HTML ATTRIBUTE NAME LC COERCION */
/* #4 NAMESPACE VALIDATION - big 'if' in Element::setAttributeNS */
/* Exceptions */
/* utils\Syntax error */
/* utils\not implemented */
/* Search for the rest */
/* type error */


/*
 * Why do this, you ask? Well, I'm not sure. We know that strtolower() and
 * strtoupper() don't do the right thing for non-ASCII characters sometimes,
 * and that mb_strtolower()/mb_strtoupper() are up to 30 times slower. But
 * this is only called on things that should accept only ASCII values to
 * begin with, which brings us back to -- why are we doing this?
 *
 * Can we just replace these with strtolower() and strtoupper() ?
 */
function toASCIILowerCase(string $s)
{
	return preg_replace_callback('/[A-Z]+/', function ($char) {
      		return strtolower($char);
      	}, $s);
}

function toASCIIUpperCase(string $s)
{
	return preg_replace_callback('/[a-z]+/', function ($char) {
      		return strtoupper($char);
      	}, $s);
}

/*****************************************************************************
 * EXCEPTIONS
 *****************************************************************************/

function error(string $name, ?string $message)
{
	throw new DOMException($message, $name);
}

//exports.IndexSizeError = function() { throw new DOMException(ERR.INDEX_SIZE_ERR); };
//exports.HierarchyRequestError = function() { throw new DOMException(ERR.HIERARCHY_REQUEST_ERR); };
//exports.WrongDocumentError = function() { throw new DOMException(ERR.WRONG_DOCUMENT_ERR); };
//exports.InvalidCharacterError = function() { throw new DOMException(ERR.INVALID_CHARACTER_ERR); };
//exports.NoModificationAllowedError = function() { throw new DOMException(ERR.NO_MODIFICATION_ALLOWED_ERR); };
//exports.NotFoundError = function() { throw new DOMException(ERR.NOT_FOUND_ERR); };
//exports.NotSupportedError = function() { throw new DOMException(ERR.NOT_SUPPORTED_ERR); };
//exports.InvalidStateError = function() { throw new DOMException(ERR.INVALID_STATE_ERR); };
//exports.SyntaxError = function() { throw new DOMException(ERR.SYNTAX_ERR); };
//exports.InvalidModificationError = function() { throw new DOMException(ERR.INVALID_MODIFICATION_ERR); };
//exports.NamespaceError = function() { throw new DOMException(ERR.NAMESPACE_ERR); };
//exports.InvalidAccessError = function() { throw new DOMException(ERR.INVALID_ACCESS_ERR); };
//exports.TypeMismatchError = function() { throw new DOMException(ERR.TYPE_MISMATCH_ERR); };
//exports.SecurityError = function() { throw new DOMException(ERR.SECURITY_ERR); };
//exports.NetworkError = function() { throw new DOMException(ERR.NETWORK_ERR); };
//exports.AbortError = function() { throw new DOMException(ERR.ABORT_ERR); };
//exports.UrlMismatchError = function() { throw new DOMException(ERR.URL_MISMATCH_ERR); };
//exports.QuotaExceededError = function() { throw new DOMException(ERR.QUOTA_EXCEEDED_ERR); };
//exports.TimeoutError = function() { throw new DOMException(ERR.TIMEOUT_ERR); };
//exports.InvalidNodeTypeError = function() { throw new DOMException(ERR.INVALID_NODE_TYPE_ERR); };
//exports.DataCloneError = function() { throw new DOMException(ERR.DATA_CLONE_ERR); };


?>
