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
/******************************************************************************
 * PORT NOTES
 * ----------
 * CHANGED:
 *        - Element now extends NonDocumentTypeChildNode, as in the spec
 *
 *        - isHTML  => isHTMLElement(),
 *          mirroring the change in Document from isHTML to isHTMLDocument()
 *
 *        - Read-only attributes converted to getter methods backed by
 *          private/protected properties starting with an underscore, per
 *          convention in this port:
 *                nodeType
 *                ownerDocument
 *                localName
 *                namespaceURI
 *                prefix
 *
 *        - setAttributeNode and setAttributeNodeNS were not behaving
 *          according to spec; rather than creating a new Attr if one
 *          already existed, and replacing the old one, they are mutating
 *          the ones that exist.
 *
 *          I changed the behavior to conform with spec.
 *
 *        - _lookupNamespacePrefix has a bizarre prototype and used a
 *          context object ($this) and the same thing given as an arg, 
 *          was only ever called in one place (on Node class), and there
 *          with a bizarre call structure that did not match spec.
 *          It was factored out and fixed up here and in Node 
 *
 * REMOVED:
 *        - mutation of prefix in _setAttributeNS() since DOM4 eliminates
 *        - 'set' branch of Element::classList(), which is not in spec.
 *        - AttributesArray object; baked it into NamedNodeMap
 *	    _insertAdjacent was the wrong way to factor insertAdjacentElement
 *	   and insertAdjacentText -- the better way puts that functionality
 *         into insertAdjacentElement, and lets insertAdjacentText call
 *	  insertAdjacentElement after it's created a text node.
 *        - The code in setAttributeNS doing validation was literally a
 *          re-written version of the validate-and-extract algorithm from
 *          the whatwg spec, already implemented as the function 
 *          validateAndExtract, which I relocated to lib/xmlnames.php, the
 *          same file that handles validation of XML names and qnames.
 *
 * TODO:
 *        - Calls to mozHTMLParser should be replaced by calls to either
 *          Tim's new PHP HTML parser, or to a generic harness that you
 *          can strap an arbitrary HTML parser into.
 *
 ******************************************************************************/

require_once("xmlnames");
require_once("utils");
require_once("attributes.php");
require_once("NodeList.php");
require_once("FilteredElementList.php");
require_once("DOMException.php");
require_once("DOMTokenList.php");
require_once("NonDocumentTypeChildNode.php");
require_once("NamedNodeMap.php");
require_once("select.php");


$UC_Tagname_Cache = array();


function recursiveGetText($node, $a)
{
        /*
         * TODO PORT:
         * Wow! This will trigger switches from LL to array on all children
         * Is that okay?
         */
        if ($node->nodeType === TEXT_NODE) {
                $a[]= $node->_data;
        } else {
                for ($i=0, $n=count($node->childNodes()); $i<$n; $i++) {
                        recursiveGetText($node->childNodes()[$i], $a);
                }
        }
}

class Element extends NonDocumentTypeChildNode
{
        public $attributes = NULL;

        public $_nodeType = ELEMENT_NODE;
        public $_ownerDocument = NULL;
        public $_localName = NULL;
        public $_namespaceURI = NULL;
        public $_prefix = NULL;
        public $_tagName = NULL;
        public $_nodeName = NULL;
        public $_nodeValue = NULL;

        public function __construct($document, $localName, $namespaceURI, $prefix)
        {
                /* Calls NonDocumentTypeChildNode and so on */
                parent::__construct();

                $this->_ownerDocument = $document;
                $this->_localName     = $localName;
                $this->_namespaceURI  = $namespaceURI;
                $this->_prefix        = $prefix;

                /* Set tag name */
                $tagname = ($prefix === NULL) ? $localName : "$prefix:$localName";
                if ($this->isHTMLElement()) {
                        if (!isset($UC_Tagname_Cache[$tagname])) {
                                $uc_tagname = \domo\ascii_to_uppercase($tagname);
                                $UC_Tagname_Cache[$tagname] = $uc_tagname;
                        } else {
                                $uc_tagname = $UC_Tagname_Cache[$tagname];
                        }
                }
                $this->_tagName = $uc_tagname;
                $this->_nodeName = $uc_tagname; /* per spec */
                $this->_nodeValue = NULL;    /* TODO spec or stub? */

                $this->attributes = new NamedNodeMap($this);
        }

        /**********************************************************************
         * UNSUPPORTED METHODS
         **********************************************************************/

        /* DOM-LS: Historical method */
        public function insertAdjacentElement(string $where, Element $element): ?Element
        {
        }
        /* DOM-LS: Historical method */
        public function insertAdjacentText(string $where, string $data): void
        {
        }
        /* [DOM-PS-WD] A new extension in draft phase. */
        public function insertAdjacentHTML(string $where, string $text): void
        {
        }

        /**********************************************************************
         * METHODS DELEGATED FROM NODE
         **********************************************************************/

        public function _subclass_cloneNodeShallow(): Element
        {
                /*
                 * XXX:
                 * Modify this to use the constructor directly or avoid
                 * error checking in some other way. In case we try
                 * to clone an invalid node that the parser inserted.
                 */
                if ($this->namespaceURI() !== NAMESPACE_HTML 
                || $this->prefix() 
                || !$this->ownerDocument()->isHTMLDocument()) {
                        if ($this->prefix() === NULL) {
                                $name = $this->localName();
                        } else {
                                $name = $this->prefix().':'.$this->localName();
                        }
                        $clone = $this->ownerDocument()->createElementNS(
                                $this->namespaceURI(), 
                                $name
                        );
                } else {
                        $clone = $this->ownerDocument()->createElement(
                                $this->localName()
                        );
                }

                foreach ($this->attributes as $a) {
                        $clone->setAttributeNodeNS($a->cloneNode());
                }

                return $clone;
        }

        public function _subclass_isEqual(Element $elt): boolean
        {
                if ($this->localName() !== $elt->localName() 
                || $this->namespaceURI() !== $elt->namespaceURI() 
                || $this->prefix() !== $elt->prefix() 
                || $this->attributes->length() !== $elt->attributes->length()) {
                        return false;
                }

                /*
                 * Compare the sets of attributes, ignoring order
                 * and ignoring attribute prefixes.
                 */
                foreach ($this->attributes as $a) {
                        if (!$elt->hasAttributeNS($a->namespaceURI(), $a->localName())) {
                                return false;
                        }
                        if ($elt->getAttributeNS($a->namespaceURI(), $a->localName()) !== $a->value()) {
                                return false;
                        }
                }
                return true;
        }

        /**********************************************************************
         * ACCESSORS 
         **********************************************************************/

        public function prefix(): ?string
        {
                return $this->_prefix;
        }

        public function localName(): ?string
        {
                return $this->_localName;
        }

        public function tagName(): string
        {
                return $this->_tagName;
        }

        /* TODO This is defined on Node in the spec! */
        public function textContent(string $newtext = NULL)
        {
                if ($newtext === NULL) {
                        /* GET */
                        $strings = array();
                        recursiveGetText($this, $strings);
                        return implode("", $strings);
                } else {
                        /* SET */
                        $this->removeChildren();
                        if ($newtext !== "") {
                                $this->_appendChild($this->ownerDocument->createTextNode($newtext));
                        }
                }
        }

        public function innerHTML(string $value = NULL)
        {
                if ($value === NULL) {
                        return $this->serialize();
                } else {
                        /* NYI */
                }
        }

        public function outerHTML(string $value = NULL)
        {
                /* NOT IMPLEMENTED ANYMORE */
        }

        /**********************************************************************
         * ParentNode MIXIN 
         **********************************************************************/

        public function append() {}
        public function prepend() {}

        public function querySelector($selector)
        {
                /* TODO: SELECTOR INTEGRATION */
                return select($selector, $this)[0];
        }

        public function querySelectorAll($selector)
        {
                /* TODO: SELECTOR INTEGRATION */
                $nodes = select($selector, $this);
                return ($nodes instanceof NodeList) ? $nodes : new NodeList($nodes);
        }

        public function children()
        {
                if (!$this->_children) {
                        $this->_children = new HTMLCollection($this);
                }
                return $this->_children;
        }

        public function firstElementChild()
        {
                for ($n=$this->firstChild(); $n!==NULL; $n=$n->nextSibling()) {
                        if ($n->_nodeType === ELEMENT_NODE) {
                                return $n;
                        }
                }
                return NULL;
        }

        public function lastElementChild()
        {
                for ($n=$this->lastChild(); $n!==NULL; $n=$n->previousSibling()) {
                        if ($n->_nodeType === ELEMENT_NODE) {
                                return $n;
                        }
                }
                return NULL;
        }

        public function childElementCount()
        {
                return count($this->children());
        }

        /**********************************************************************
         * QUERIES AND SELECTIONS
         **********************************************************************/

        /* XXX:
         * Tests are currently failing for this function.
         * Awaiting resolution of:
         * http://lists.w3.org/Archives/Public/www-dom/2011JulSep/0016.html
         */
        public function getElementsByTagName($lname)
        {
                $filter;

                if (!$lname) {
                        return new NodeList();
                }
                if ($lname === '*') {
                        /* TODO: defining this function in PHP... */
                        $filter = function() { return true; };
                } else if ($this->isHTMLElement()) {
                        $filter = htmlLocalNameElementFilter($lname);
                } else {
                        $filter = localNameElementFilter($lname);
                }

                return new FilteredElementList($this, $filter);
        }

        public function getElementsByTagNameNS($ns, $lname)
        {
                if ($ns === '*' && $lname === '*') {
                        /* TODO: defining this fn in PHP... */
                        $filter = function() { return true; };
                } else if ($ns === '*') {
                        $filter = localNameElementFilter($lname);
                } else if ($lname === '*') {
                        $filter = namespaceElementFilter($ns);
                } else {
                        $filter = namespaceLocalNameElementFilter($ns, $lname);
                }

                return new FilteredElementList($this, $filter);
        }

        public function getElementsByClassName($names)
        {
                $names = trim(strval($names));

                if ($names === '') {
                        $result = new NodeList(); // Empty node list
                        return $result;
                }
                $names = preg_split('/\s+/', $names); // split on ASCII whitespace

                return new FilteredElementList($this, classNamesElementFilter($names));
        }

        public function getElementsByName($name)
        {
                return new FilteredElementList($this, elementNameFilter(strval($name)));
        }


        /*********************************************************************
         * ATTRIBUTES
         ********************************************************************/
        /*
         * Attributes in the DOM are tricky:
         *
         * - there are the 8 basic get/set/has/removeAttribute{NS} methods
         *
         * - but many HTML attributes are also 'reflected' through IDL
         *   attributes which means that they can be queried and set through
         *   regular properties of the element.  There is just one attribute
         *   value, but two ways to get and set it.
         *
         * - Different HTML element types have different sets of reflected
         *   attributes.
         *
         * - attributes can also be queried and set through the .attributes
         *   property of an element.  This property behaves like an array of
         *   Attr objects.  The value property of each Attr is writeable, so
         *   this is a third way to read and write attributes.
         *
         * - for efficiency, we really want to store attributes in some kind
         *   of name->attr map.  But the attributes[] array is an array, not a
         *   map, which is kind of unnatural.
         *
         * - When using namespaces and prefixes, and mixing the NS methods
         *   with the non-NS methods, it is apparently actually possible for
         *   an attributes[] array to have more than one attribute with the
         *   same qualified name.  And certain methods must operate on only
         *   the first attribute with such a name.  So for these methods, an
         *   inefficient array-like data structure would be easier to
         *   implement.
         *
         * - The attributes[] array is live, not a snapshot, so changes to the
         *   attributes must be immediately visible through existing arrays.
         *
         * - When attributes are queried and set through IDL properties
         *   (instead of the get/setAttributes() method or the attributes[]
         *   array) they may be subject to type conversions, URL
         *   normalization, etc., so some extra processing is required in that
         *   case.
         *
         * - But access through IDL properties is probably the most common
         *   case, so we'd like that to be as fast as possible.
         *
         * - We can't just store attribute values in their parsed idl form,
         *   because setAttribute() has to return whatever string is passed to
         *   getAttribute even if it is not a legal, parseable value. So
         *   attribute values must be stored in unparsed string form.
         *
         * - We need to be able to send change notifications or mutation
         *   events of some sort to the renderer whenever an attribute value
         *   changes, regardless of the way in which it changes.
         *
         * - Some attributes, such as id and class affect other parts of the
         *   DOM API, like getElementById and getElementsByClassName and so
         *   for efficiency, we need to specially track changes to these
         *   special attributes.
         *
         * - Some attributes like class have different names (className) when
         *   reflected.
         *
         * - Attributes whose names begin with the string 'data-' are treated
         *   specially.
         *
         * - Reflected attributes that have a boolean type in IDL have special
         *   behavior: setting them to false (in IDL) is the same as removing
         *   them with removeAttribute()
         *
         * - numeric attributes (like HTMLElement.tabIndex) can have default
         *   values that must be returned by the idl getter even if the
         *   content attribute does not exist. (The default tabIndex value
         *   actually varies based on the type of the element, so that is a
         *   tricky one).
         *
         * See
         * http://www.whatwg.org/specs/web-apps/current-work/multipage/urls.html#reflect
         * for rules on how attributes are reflected.
         */

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

        //}

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

        public function toggleAttribute(string $qname, ?boolean $force=NULL): boolean
        {
                if (!\domo\is_valid_xml_name($qname)) {
                        \domo\error("InvalidCharacterError");
                }

                $key = $this->_helper_qname_key($qname);
                $a = $this->_attrsByQName[$key] ?? NULL;

                if ($a === NULL) {
                        if ($force === NULL || $force === true) {
                                /* TODO: Why are we calling _setAttribute? */
                                $this->_setAttribute($qname, "");
                                return true;
                        }
                        return false;
                } else {
                        if ($force === NULL || $force === false) {
                                $this->removeAttribute($qname);
                                return false;
                        }
                        return true;
                }
        }

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


        /*********************************************************************
         * OTHER 
         ********************************************************************/

/* TODO TODO TODO */
  //// Define getters and setters for an 'id' property that reflects
  //// the content attribute 'id'.
  //id: attributes.property({name: 'id'}),

  //// Define getters and setters for a 'className' property that reflects
  //// the content attribute 'class'.
  //className: attributes.property({name: 'class'}),
/* TODO TODO TODO */

        public function classList()
        {
                $self = $this;
                if ($this->_classList) {
                        return $this->_classList;
                }
                /* TODO: FIx this into PHP
                $dtlist = new DOMTokenList(
                        function() {
                                return self.className || "";
                        },
                        function(v) {
                                self.className = v;
                        }
                );
                */
                $this->_classList = $dtlist;
                return $dtlist;
        }

        public function matches($selector)
        {
                /* TODO: SELECTOR INTEGRATION */
                return select->matches($this, $selector);
        }

        public function closest($selector)
        {
                $el = $this;
                while (method_exists($el, "matches") && !$el->matches($selector)) {
                        $el = $el->parentNode();
                }
                return (method_exists($el, "matches") ? $el : NULL;
        }

        /*********************************************************************
         * DOMO EXTENSIONS 
         ********************************************************************/

        public function isHTMLElement()
        {
                /* DOMO Convenience function to test if HTMLElement */
                /* (See Document->isHTMLDocument()) */
                if ($this->namespaceURI === NAMESPACE_HTML && $this->ownerDocument && $this->ownerDocument->isHTMLDocument()) {
                        return true;
                }
                return false;
        }

        /*
         * Return the next element, in source order, after this one or
         * null if there are no more.  If root element is specified,
         * then don't traverse beyond its subtree.
         *
         * This is not a DOM method, but is convenient for
         * lazy traversals of the tree.
         */
        public function nextElement($root)
        {
                if (!$root) {
                        $root = $this->ownerDocument()->documentElement();
                }
                $next = $this->firstElementChild();
                if (!$next) {
                        /* don't use sibling if we're at root */
                        if ($this === $root) {
                                return NULL;
                        }
                        $next = $this->nextElementSibling();
                }
                if ($next) {
                        return $next;
                }

                /*
                 * If we can't go down or across, then we have to go up
                 * and across to the parent sibling or another ancestor's
                 * sibling. Be careful, though: if we reach the root
                 * element, or if we reach the documentElement, then
                 * the traversal ends.
                 */
                for ($parent = $this->parentElement(); $parent && $parent !== $root; $parent = $parent->parentElement()) {
                        $next = $parent->nextElementSibling();
                        if ($next) {
                                return $next;
                        }
                }
                return NULL;
        }

}

/*
 * TODO: Here is the busted JavaScript style where class
 * extension is treated as a bunch of mixins applied in order
 */
//Object.defineProperties(Element.prototype, ChildNode);
//Object.defineProperties(Element.prototype, NonDocumentTypeChildNode);

// Register special handling for the id attribute
//attributes.registerChangeHandler(Element, 'id',
 //function(element, lname, oldval, newval) {
   //if (element.rooted) {
     //if (oldval) {
       //element.ownerDocument.delId(oldval, element);
     //}
     //if (newval) {
       //element.ownerDocument.addId(newval, element);
     //}
   //}
 //}
//);
//attributes.registerChangeHandler(Element, 'class',
 //function(element, lname, oldval, newval) {
   //if (element._classList) { element._classList._update(); }
 //}
//);









// These functions return predicates for filtering elements.
// They're used by the Document and Element classes for methods like
// getElementsByTagName and getElementsByClassName

//function localNameElementFilter(lname) {
  //return function(e) { return e.localName === lname; };
//}

//function htmlLocalNameElementFilter(lname) {
  //var lclname = utils.toASCIILowerCase(lname);
  //if (lclname === lname)
    //return localNameElementFilter(lname);

  //return function(e) {
    //return e.isHTML ? e.localName === lclname : e.localName === lname;
  //};
//}

//function namespaceElementFilter(ns) {
  //return function(e) { return e.namespaceURI === ns; };
//}

//function namespaceLocalNameElementFilter(ns, lname) {
  //return function(e) {
    //return e.namespaceURI === ns && e.localName === lname;
  //};
//}

//function classNamesElementFilter(names) {
  //return function(e) {
    //return names.every(function(n) { return e.classList.contains(n); });
  //};
//}

//function elementNameFilter(name) {
  //return function(e) {
    //// All the *HTML elements* in the document with the given name attribute
    //if (e.namespaceURI !== NAMESPACE.HTML) { return false; }
    //return e.getAttribute('name') === name;
  //};
//}
