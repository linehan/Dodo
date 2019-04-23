<?php
/******************************************************************************
 * Element.php
 * ````````````
 * Defines an "Element"
 ******************************************************************************/
/******************************************************************************
 *
 * Where a specification is implemented, the following annotations appear.
 *
 * DOM-1     W3C DOM Level 1 		     http://w3.org/TR/DOM-Level-1/
 * DOM-2     W3C DOM Level 2 Core	     http://w3.org/TR/DOM-Level-2-Core/
 * DOM-3     W3C DOM Level 3 Core	     http://w3.org/TR/DOM-Level-3-Core/
 * DOM-4     W3C DOM Level 4 		     http://w3.org/TR/dom/
 * DOM-LS    WHATWG DOM Living Standard      http://dom.spec.whatwg.org/
 * DOM-PS-WD W3C DOM Parsing & Serialization http://w3.org/TR/DOM-Parsing/
 * WEBIDL-1  W3C WebIDL Level 1	             http://w3.org/TR/WebIDL-1/
 * XML-NS    W3C XML Namespaces		     http://w3.org/TR/xml-names/
 *
 ******************************************************************************/

require_once("../lib/xmlnames.php");
require_once("../lib/utils.php");
require_once("DOMException.php");
require_once("NonDocumentTypeChildNode.php");
require_once("NamedNodeMap.php");

$UC_Tagname_Cache = array();

class Element extends NonDocumentTypeChildNode
{
        public $attributes = NULL;

        public $nodeType = ELEMENT_NODE;
        public $ownerDocument = NULL;
        public $localName = NULL;
        public $namespaceURI = NULL;
        public $prefix = NULL;
        public $tagName = NULL;
        public $nodeName = NULL;
        public $nodeValue = NULL;

        public function __construct($document, $localName, $namespaceURI, $prefix)
        {
                /* Calls NonDocumentTypeChildNode and so on */
                parent::__construct();

                $this->ownerDocument = $document;
                $this->localName     = $localName;
                $this->namespaceURI  = $namespaceURI;
                $this->prefix        = $prefix;

                /* Set tag name */
                $tagname = ($prefix === NULL) ? $localName : "$prefix:$localName";
                if ($this->isHTMLElement()) {
                        if (!isset($UC_Tagname_Cache[$tagname])) {
                                $tagname = $UC_Tagname_Cache[$tagname] = \domo\ascii_to_uppercase($tagname);
                        } else {
                                $tagname = $UC_Tagname_Cache[$tagname];
                        }
                }
                $this->tagName = $tagname;
                $this->nodeName = $tagname; /* per spec */
                $this->nodeValue = NULL;    /* TODO spec or stub? */

                $this->attributes = new NamedNodeMap($this);
        }

        public function isHTMLElement()
        {
                if ($this->namespaceURI === NAMESPACE_HTML && $this->ownerDocument && $this->ownerDocument->isHTMLDocument()) {
                        return true;
                }
                return false;
        }

        /**********************************************************************
         * STUBBED 
         **********************************************************************/
        public function textContent(string $newtext = NULL){}
        public function innerHTML(string $value = NULL){}
        public function outerHTML(string $value = NULL) {}
        public function insertAdjacentElement(string $where, Element $element): ?Element {}
        public function insertAdjacentText(string $where, string $data): void {}
        public function insertAdjacentHTML(string $where, string $text): void {}
        public function nextElement($root){}
        public function getElementsByTagName($lname){}
        public function getElementsByTagNameNS($ns, $lname){}
        public function getElementsByClassName($names){}
        public function getElementsByName($name){}
        public function _lookupNamespacePrefix($ns, $originalElement){}
        public function lookupNamespaceURI(string $prefix = NULL){}
        public function toggleAttribute(string $qname, ?boolean $force=NULL): boolean {}
        public function classList(){}
        public function matches($selector){}
        public function closest($selector){}
        public function id() {}

        /**********************************************************************
         * ParentNode mixin 
         **********************************************************************/
        public function children(){}
        public function childElementCount(){}
        public function firstElementChild(){}
        public function lastElementChild(){}
        public function querySelector($selector) {}
        public function querySelectorAll($selector) {}
        public function append() {}
        public function prepend() {}

        /**********************************************************************
         * DELEGATED FROM Node 
         **********************************************************************/
        public function _subclass_cloneNodeShallow(){}
        public function _subclass_isEqual($other){}


        /*********************************************************************
         * ATTRIBUTES
         ********************************************************************/

	/****** GET ******/

        public function getAttribute(string $qname): ?string
        {
                $attr = $this->attributes->getNamedItem($qname);
                return $attr ? $attr->value : NULL;
        }

        public function getAttributeNS(?string $ns, string $lname): ?string
        {
                $attr = $this->attributes->getNamedItemNS($ns, $lname);
                return $attr ? $attr->value : NULL;
        }

        public function getAttributeNode(string $qname): ?Attr
        {
                return $this->attributes->getNamedItem($qname);
        }

        public function getAttributeNodeNS(?string $ns, string $lname): ?Attr
        {
                return $this->attributes->getNamedItemNS($ns, $lname);
        }

	/****** SET ******/

        public function setAttribute(string $qname, $value)
        {
                if (!\domo\is_valid_xml_name($qname)) {
                        \domo\error("InvalidCharacterError");
                }

                if (!ctype_lower($qname) && $this->isHTMLElement()) {
                        $qname = \domo\ascii_to_lowercase($qname);
                }

                $attr = $this->attributes->getNamedItem($qname);
                if ($attr === NULL) {
                        $attr = new Attr($this, $name, NULL, NULL);
                }
                $attr->value = $value;
                $this->attributes->setNamedItem($attr);
        }

        public function setAttributeNS(?string $ns, string $qname, $value)
        {
                $lname = NULL;
                $prefix = NULL;

                \domo\extract_and_validate($ns, $qname, &$prefix, &$lname);

                $attr = $this->attributes->getNamedItemNS($ns, $qname);
                if ($attr === NULL) {
                        $attr = new Attr($this, $lname, $prefix, $ns);
                }
                $attr->value = $value;
                $this->attributes->setNamedItemNS($attr);
        }

        public function setAttributeNode(Attr $attr): ?Attr
        {
                return $this->attributes->setNamedItem($attr);
        }

        public function setAttributeNodeNS($attr)
        {
                return $this->attributes->setNamedItemNS($attr);
        }

	/****** REMOVE ******/

        public function removeAttribute(string $qname)
        {
                return $this->attributes->removeNamedItem($qname);
        }

        public function removeAttributeNS(?string $ns, string $lname)
        {
                return $this->attributes->removeNamedItemNS($ns, $lname);
        }

        public function removeAttributeNode(Attr $attr)
        {
                /* TODO: This is not a public function */
                return $this->attributes->_remove($attr);
        }

	/****** HAS ******/

        public function hasAttribute(string $qname): boolean
        {
                return $this->attributes->hasNamedItem($qname);
        }

        public function hasAttributeNS(?string $ns, string $lname): boolean
        {
                return $this->attributes->hasNamedItemNS($ns, $lname);
        }

	/****** OTHER ******/


        public function hasAttributes(): boolean
        {
                return !empty($this->attributes->index_to_attr);
        }

        public function getAttributeNames()
        {
                /*
		 * Note that per spec, these are not guaranteed to be
 		 * unique.
                 */
                $ret = array();

                foreach ($this->attributes->index_to_attr as $a) {
                        $ret[] = $a->name;
                }

                return $ret;
        }
}


