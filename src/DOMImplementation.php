<?php
/******************************************************************************
 * DOMImplementation.php
 * `````````````````````
 * The DOMImplementation interface represents an object providing methods
 * which are not dependent on any particular document. Such an object is
 * available in the Document->implementation property.
 *
 * PORT NOTES:
 *
 * Removes:
 *       public function mozSetOutputMutationHandler($doc, $handler)
 *       public function mozGetInputMutationHandler($doc)
 *       public $mozHTMLParser = HTMLParser;
 *
 * Renames:
 * Changes:
 *      - supportedFeatures array was moved to a static variable inside of
 *        DOMImplementation->hasFeature(), and renamed to $supported.
 *
 ******************************************************************************/
//declare( strict_types = 1 );

namespace domo;

/* circular dependency, meh... */
require_once('Document.php');
//require_once("interfaces/Document.php");
//require_once("interfaces/DocumentType.php");
//require_once("parsers/HTMLParser.php");
//require_once("parsers/xmlnames.php");
//require_once("utils.php");



/*
 * Each Document must have its own instance of
 * a DOMImplementation object
 */
class DOMImplementation
{
        public
        function __construct(/* TODO: What is this? A Window? */$contextObject)
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
        function hasFeature(string $feature="", string $version="") : bool 
        {
                /*
                 * Feature/version pairs that DOMImplementation->hasFeature()
                 * returns true for. It returns false for anything else.
                 */
                static $supported = array(
                        "xml"   => array( "" => true, "1.0" => true, "2.0" => true ),
                        "core"  => array( "" => true, "2.0" => true ),
                        "html"  => array( "" => true, "1.0" => true, "2.0" => true ),
                        "xhtml" => array( "" => true, "1.0" => true, "2.0" => true )
                );

                if (!isset($supported[$feature])) {
                        return false;
                } else {
                        if (!isset($supported[$feature][$version])) {
                                return false;
                        }
                }
                return true;
        }

        public
        function createDocumentType($qualifiedName, $publicId, $systemId)
        {
                /* 
                if (!xmlnames\isValidQName($qualifiedName)) {
                        utils\InvalidCharacterError();
                }

                return new DocumentType($this->contextObject, $qualifiedName, $publicId, $systemId);
                */
                /* TEMPORARY STUB */
        }

        public
        function createDocument($namespace, $qualifiedName, $doctype)
        {
                /*
                 * Note that the current DOMCore spec makes it impossible
                 * to create an HTML document with this function, even if
                 * the namespace and doctype are properly set. See thread:
                 * http://lists.w3.org/Archives/Public/www-dom/2011AprJun/0132.html
                 *
                 * TODO PORT: Okay....so...
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

                if ($namespace === NAMESPACE_HTML) {
                        $d->_contentType = "application/xhtml+xml";
                } else if ($namespace === NAMESPACE_SVG) {
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

                /* Start tracking modifications */
                $d->modclock = 1;

                return $d;
        }
}

?>
