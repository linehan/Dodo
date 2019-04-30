<?php
namespace domo;

require_once(__DIR__.'/../interfaces/DOMException.php');

/******************************************************************************
 * CONSTANTS
 * ---------
 * The various W3C and WHATWG recommendations define a number of
 * constants. Although these are usually associated with a particular
 * interface, we collect all of them here for convenience.
 ******************************************************************************/

/**
 * NAMESPACE_*
 * Strings defining the various document namespaces
 * [DOMO] These are used by this library and aren't part of a spec.
 */
const NAMESPACE_HTML = "http://www.w3.org/1999/xhtml";
const NAMESPACE_XML = "http://www.w3.org/XML/1998/namespace";
const NAMESPACE_XMLNS = "http://www.w3.org/2000/xmlns/";
const NAMESPACE_MATHML = "http://www.w3.org/1998/Math/MathML";
const NAMESPACE_SVG = "http://www.w3.org/2000/svg";
const NAMESPACE_XLINK = "http://www.w3.org/1999/xlink";

/**
 * Node types
 * Integers enumerating the various Node types
 * [DOM-LS] These are found on the Node interface.
 */
const ELEMENT_NODE = 1;
const ATTRIBUTE_NODE = 2;
const TEXT_NODE = 3;
const CDATA_SECTION_NODE = 4;
const ENTITY_REFERENCE_NODE = 5;
const ENTITY_NODE = 6;
const PROCESSING_INSTRUCTION_NODE = 7;
const COMMENT_NODE = 8;
const DOCUMENT_NODE = 9;
const DOCUMENT_TYPE_NODE = 10;
const DOCUMENT_FRAGMENT_NODE = 11;
const NOTATION_NODE = 12;

/**
 * DOCUMENT_POSITION_*
 * Bitmasks indicating position of a node x relative to a node y.
 * [DOM-LS] These are found on the Node interface
 * [DOM-LS] Returned from x->compareDocumentPosition(y)
 */
/* x and y are not part of the same tree */
const DOCUMENT_POSITION_DISCONNECTED = 1;
/* y precedes x */
const DOCUMENT_POSITION_PRECEDING = 2;
/* y follows x */
const DOCUMENT_POSITION_FOLLOWING = 4;
/* y is an ancestor of x */
const DOCUMENT_POSITION_CONTAINS = 8;
/* y is a descendant of x */
const DOCUMENT_POSITION_CONTAINED_BY = 16;
/* whatever you need it to be */
const DOCUMENT_POSITION_IMPLEMENTATION_SPECIFIC = 32;




/*****************************************************************************
 * EXCEPTIONS
 *****************************************************************************/

/*
 * Original:
 * throw new Error("Assertion failed: " + (msg || "") + "\n" new Error().stack);
 *
 * TODO: Need to add the stack trace info, or advise catchers call
 * Exception::getTraceAsString()
 *
 * TODO: Make this a true assert()?
 */
function assert(bool $condition, ?string $message="")
{
        if (!$condition) {
                throw new Exception("Assert failed: $message");
        }
}


/**
 * Throw a DOMException
 *
 * @param $name one of the values below
 * @param $message an optional message to include in the Exception
 * @return void
 * @throws DOMException
 *
 * NOTE
 * Allowed values for $string are: IndexSizeError, HierarchyRequestError
 * WrongDocumentError, InvalidCharacterError, NoModificationAllowedError,
 * NotFoundError, NotSupportedError, InvalidStateError, SyntaxError,
 * InvalidModificationError, NamespaceError, InvalidAccessError,
 * TypeMismatchError, SecurityError, NetworkError, AbortError,
 * UrlMismatchError, QuotaExceededError, TimeoutError,
 * InvalidNodeTypeError, and DataCloneError
 *
 * For more information, see interfaces/DOMException.php
 */
function error(string $name, ?string $message=NULL)
{
	throw new DOMException($message, $name);
}


/******************************************************************************
 * TEXT FORMATTING AND VALIDATORS
 *****************************************************************************/

/*
 * Why? I don't know. strtolower()/strtoupper() don't do the right thing
 * for non-ASCII characters, and mb_strtolower()/mb_strtoupper() are up
 * to 30x slower. But these are only called on things that should accept
 * only ASCII values to begin with (e.g. attribute names in HTML). So -- why?
 */
function ascii_to_lowercase(string $s): string
{
	return preg_replace_callback('/[A-Z]+/', function ($char) {
      		return strtolower($char);
      	}, $s);
}

function ascii_to_uppercase(string $s): string
{
	return preg_replace_callback('/[a-z]+/', function ($char) {
      		return strtoupper($char);
      	}, $s);
}


?>
