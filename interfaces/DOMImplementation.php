<?php
declare( strict_types = 1 );

namespace \domo\interfaces

use \domo\interfaces\Document
use \domo\interfaces\DocumentType
use \domo\parsers\HTMLParser
use \domo\parsers\xmlnames
use \domo\utilities

require_once("interfaces/Document.php");
require_once("interfaces/DocumentType.php");
require_once("parsers/HTMLParser.php");
require_once("parsers/xmlnames.php");
require_once("utils.php");

/*
 * Feature/version pairs that DOMImplementation->hasFeature()
 * returns true for. It returns false for anything else.
 */
$supportedFeatures = array(
        /* DOM Core [sic?] */
        "xml" => array(
                "" => true,
                "1.0" => true,
                "2.0" => true
        ),
        /* DOM Core */
        "core" => array(
                "" => true,
                "2.0" => true
        ),
        /* HTML */
        "html" => array(
                "" => true,
                "1.0" => true,
                "2.0" => true
        ),
        /* HTML [sic?] */
        "xhtml" => array(
                "" => true,
                "1.0" => true,
                "2.0" => true
        )
        /* TODO: is SVG not on here? Don't we support it? */
);


/*
 * Each Document must have its own instance of
 * a DOMImplementation object
 */
class DOMImplementation
{
        public
        function __construct(/* TODO: What is this? */$contextObject)
        {
                $this->contextObject = $contextObject;
        }

        /**
         * hasFeature()
         * @feature: a string corresponding to a key in $supportedFeatures
         * @version: [optional] a string corresponding to a version in $supportedFeatures
         * Return  : False if arg (pair) not in $supportedFeatures, else True
         * NOTE[TODO]
         *      It returns false due to the data structure having no
         *      "" member in the primary array. This is not very
         *      defensive programming.
         */
        public
        function hasFeature(string $feature="", string $version="") : boolean
        {
                if (!isset($supportedFeatures[$feature])) {
                        return false;
                } else {
                        if (!isset($supportedFeatures[$feature][$version])) {
                                return false;
                        }
                }
                return true;
        }

        public
        function createDocumentType($qualifiedName, $publicId, $systemId)
        {
                if (!xmlnames\isValidQName($qualifiedName)) {
                        utils\InvalidCharacterError();
                }

                return new DocumentType($this->contextObject, $qualifiedName, $publicId, $systemId);
        }

        public
        function createDocument($namespace, $qualifiedName, $doctype)
        {
                /*
                 * Note that the current DOMCore spec makes it impossible
                 * to create an HTML document with this function, even if
                 * the namespace and doctype are properly set. See thread:
                 * http://lists.w3.org/Archives/Public/www-dom/2011AprJun/0132.html
                 */
                $d = new Document(false, null);
                $e;

                if ($qualifiedName) {
                        $e = $d->createElementNS($namespace, $qualifiedName);
                } else {
                        $e = null;
                }

                if ($doctype) {
                        $d->appendChild($doctype);
                }

                if ($e) {
                        $d->appendChild($e);
                }

                if ($namespace === utils\NAMESPACE_HTML) {
                        $d->_contentType = "application/xhtml+xml";
                } else if ($namespace === utils\NAMESPACE_SVG) {
                        $d->_contentType = "image/svg+xml";
                } else {
                        $d->_contentType = "application/xml";
                }

                return $d;
        }

        public
        function createHTMLDocument(string $titleText=NULL)
        {
                $d = new Document(true, null);

                $d->appendChild(new DocumentType($d, "html"));

                $html = $d->createElement("html");

                $d->appendChild($html);

                $head = $d->createElement("head");

                $html->appendChild($head);

                if ($titleText !== NULL) {
                        $title = $d->createElement("title");
                        $head->appendChild($title);
                        $title->appendChild($d->createTextNode($titleText));
                }

                $html->appendChild($d->createElement("body"));

                $d->modclock = 1; // Start tracking modifications

                return $d;
        }

        /* PORT TODO: What is all this mozilla stuff? Do we still need it? */

        public
        function mozSetOutputMutationHandler($doc, $handler)
        {
                $doc->mutationHandler = $handler;
        }

        public
        function mozGetInputMutationHandler($doc)
        {
                \util\nyi();
        }

        public $mozHTMLParser = HTMLParser,
}
