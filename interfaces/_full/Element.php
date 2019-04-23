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

/*
PORT NOTES
----------
CHANGED:
        - Element now extends NonDocumentTypeChildNode, as in the spec

        - isHTML  => isHTMLElement(),
          mirroring the change in Document from isHTML to isHTMLDocument()

        - Read-only attributes converted to getter methods backed by
          private/protected properties starting with an underscore, per
          convention in this port:
                nodeType
                ownerDocument
                localName
                namespaceURI
                prefix

        - setAttributeNode and setAttributeNodeNS were not behaving
          according to spec; rather than creating a new Attr if one
          already existed, and replacing the old one, they are mutating
          the ones that exist.

          I changed the behavior to conform with spec.

        - _lookupNamespacePrefix has a bizarre prototype and used a
          context object ($this) and the same thing given as an arg, 
          was only ever called in one place (on Node class), and there
          with a bizarre call structure that did not match spec.
          It was factored out and fixed up here and in Node 

REMOVED:
        - mutation of prefix in _setAttributeNS() since DOM4 eliminates
        - 'set' branch of Element::classList(), which is not in spec.
        - AttributesArray object; baked it into NamedNodeMap
	_ _insertAdjacent was the wrong way to factor insertAdjacentElement
	  and insertAdjacentText -- the better way puts that functionality
	  into insertAdjacentElement, and lets insertAdjacentText call
	  insertAdjacentElement after it's created a text node.
        - The code in setAttributeNS doing validation was literally a
          re-written version of the validate-and-extract algorithm from
          the whatwg spec, already implemented as the function 
          validateAndExtract, which I relocated to lib/xmlnames.php, the
          same file that handles validation of XML names and qnames.

TODO:
        - Calls to mozHTMLParser should be replaced by calls to either
          Tim's new PHP HTML parser, or to a generic harness that you
          can strap an arbitrary HTML parser into.
*/

/* Used to verify valid XML attribute names */
require_once("xmlnames");
/* Used for NYI, some exceptions, and some NAMESPACE_* constants */
require_once("utils");
/*
 * Used for defining IDL reflected attributes, and registering handlers
 * to fire on mutation. Only used here for 'class' and 'id', although
 * a full implementation would need to specify all of the unique behavior,
 * defaults, enumerated values, etc. for <select> and other elements with
 * semantic attributes. But we're not implementing HTML, we're implementing
 * the DOM.
 */
require_once("attributes.php");
/*
 * TODO: Get rid of this?
 * I think this is here for... the node-type constants? Should be inherited
 * through NonDocumentTypeChildNode.
 */
//require_once("Node.php");
/*
   Why is this here? It's part of Node.php
*/
//require_once("NodeUtils.php");
/*
   REMOVED, since ContainerNode is now part of Node
*/
//require_once("ContainerNode.php");
/*
   REMOVED Since NonDocumentChildNode extends ChildNode, we don't need this
*/
//require_once("ChildNode.php");

/*
   getElementsByTagName and getElementsByClassName return NodeList
   querySelectorAll does too, but that belongs on the ParentNode mixin
   (not implemented here... yet)
*/
require_once("NodeList.php");
/*
   THIS IS NOT A DOM INTERFACE.
   A similar thing is exposed in Dart (is that where this came from?)
   This is sometimes the return value of getElementsBy*Name
*/
require_once("FilteredElementList.php");
/*
   Used sometimes, and other times util\exception or util\assert is
   used. No clue why.
   TODO: Unify
*/
require_once("DOMException.php");
/*
   Set of space-separated tokens, returned by Element.classList
   (also HTMLLinkElement.relList, HTMLAnchorElement.relList,
    and HTMLAreaElement.relList, but they are not here, are they?)
*/
require_once("DOMTokenList.php");
/*
   We extend this class
*/
require_once("NonDocumentTypeChildNode.php");

/*
   The DOM collection of Attr elements, returned by Element.attributes
*/
require_once("NamedNodeMap.php");

/*
   The [external] selector library used in querySelector[All] et al.
*/
require_once("select.php");


$uppercaseCache = array();


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
                        if (!isset($uppercaseCache[$tagname])) {
                                $tagname = $uppercaseCache[$tagname] = \domo\ascii_to_uppercase($tagname);
                        } else {
                                $tagname = $uppercaseCache[$tagname];
                        }
                }
                $this->tagName = $tagname;
                $this->nodeName = $tagname; /* per spec */
                $this->nodeValue = NULL;    /* TODO spec or stub? */

                $this->attributes = new NamedNodeMap($this);
        }

        public function isHTMLElement()
        {
                /* DOMO Convenience function to test if HTMLElement */
                /* (See Document->isHTMLDocument()) */
                if ($this->namespaceURI === NAMESPACE_HTML && $this->ownerDocument && $this->ownerDocument->isHTMLDocument()) {
                        return true;
                }
                return false;
        }

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
                /* TODO: STUB
                if ($value === NULL) {
                */
                        /*
                         * "the attribute must return the result of running
                         * the HTML fragment serialization algorithm on a
                         * fictional node whose only child is the context
                         * object"
                         *
                         * The serialization logic is intentionally implemented
                         * in a separate `NodeUtils` helper instead of the more
                         * obvious choice of a private `_serializeOne()` method
                         * on the `Node.prototype` in order to avoid the
                         * megamorphic `this._serializeOne` property access,
                         * which reduces performance unnecessarily. If you need
                         * specialized behavior for a certain subclass, you'll
                         * need to implement that in `NodeUtils`. See
                         * https://github.com/fgnass/domino/pull/142 for more
                         * information.
                         */
                /*
                        return NodeUtils::serializeOne($this, array("nodeType" => 0 ));
                } else {
                        $document = $this->ownerDocument();
                        $parent = $this->parentNode();
                        if ($parent === NULL) {
                                return;
                        }
                        if ($parent->nodeType() === DOCUMENT_NODE) {
                                \domo\error("NoModificationAllowedError");
                        }
                        if ($parent->nodeType === DOCUMENT_FRAGMENT_NODE) {
                                $parent = $parent->ownerDocument->createElement("body");
                        }
                */
                        /* TODO: Deal with this parser */
                /* 
                        $parser = $document->implementation->mozHTMLParser(
                                $document->_address,
                                $parent
                        );
                        $parser->parse($v===null?'':strval($value), true);
                        $this->replaceWith($parser->_asDocumentFragment());
                }
                */
        }

        /* DOM-LS: Historical method */
        public function insertAdjacentElement(string $where, Element $element): ?Element
        {
                /*
                 * PORT: Don't bother with toASCIILowerCase since the values
                 * are all enumerated as; just do strtolower and if it
                 * corrupts then it ends up in the default case.
                 */
                switch (strtolower($where)) {
                case "beforebegin":
                        if (NULL === ($parent = $this->parentNode()) {
                                return NULL;
                        } else {
                        	return $parent->insertBefore($element, $this);
			}
                case "afterend":
                        if (NULL === ($parent = $this->parentNode())) {
                                return NULL;
                        } else {
                        	return $parent->insertBefore($element, $this->nextSibling());
			}
                case "afterbegin":
                        return $this->insertBefore($element, $this->firstChild);
                case 'beforeend':
                        return $this->insertBefore($element, NULL);
                default:
                        error("SyntaxError");
			return NULL;
                }
        }

        /* DOM-LS: Historical method */
        public function insertAdjacentText(string $where, string $data): void
        {
		/* TODO: Don't we have to check for an ownerDocument? */
		$textNode = $this->ownerDocument->createTextNode($data);
		$this->insertAdjacentElement($where, $textNode);

                /*
                 * "This method returns nothing because it existed before we
                 * had a chance to design it."
                 */
        }

        /* [DOM-PS-WD] A new extension in draft phase. */
        public function insertAdjacentHTML(string $where, string $text): void
        {
                /* STUB 
                $text = strval($text);

                switch (strtolower($where)) {
                case "beforebegin":
                case "afterend":
                        $ctx = $this->parentNode();
                        if ($ctx === NULL || $ctx->nodeType === DOCUMENT_NODE) {
                                error("NoModificationAllowedError");
				return;
                        }
                        break;
                case "afterbegin":
                case "beforeend":
                        $ctx = $this;
                        break;
                default:
                        error("SyntaxError");
			return;
                }

                if (
			(!($ctx instanceof Element))
			||
			($ctx->ownerDocument->isHTMLDocument() && $ctx->localName === 'html' && $ctx->namespaceURI === NAMESPACE_HTML)
		) {
                        $ctx = $ctx->ownerDocument->createElementNS(NAMESPACE_HTML, 'body');
                }

                $parser = 
			//$this->ownerDocument()->implementation()->mozHTMLParser(
                        //$this->ownerDocument()->_address,
                        //$context
                );

                $parser->parse($text, true);
                $this->insertAdjacentElement($where, $parser->_asDocumentFragment());
                */
        }

        public function children()
        {
                if (!$this->_children) {
                        $this->_children = new ChildrenCollection($this);
                }
                return $this->_children;
        }

        public function firstElementChild()
        {
                for ($n=$this->firstChild(); $n!==NULL; $n=$n->nextSibling()) {
                        if ($n->nodeType === ELEMENT_NODE) {
                                return $n;
                        }
                }
                return NULL;
        }

        public function lastElementChild()
        {
                for ($n=$this->lastChild(); $n!==NULL; $n=$n->previousSibling()) {
                        if ($n->nodeType === ELEMENT_NODE) {
                                return $n;
                        }
                }
                return NULL;
        }

        public function childElementCount()
        {
                return count($this->children());
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

        public function _subclass_cloneNodeShallow()
        {
                /*
                 * XXX:
                 * Modify this to use the constructor directly or
                 * avoid error checking in some other way. In case we try
                 * to clone an invalid node that the parser inserted.
                 */
                if ($this->namespaceURI() !== NAMESPACE_HTML || $this->prefix() || !$this->ownerDocument()->isHTMLDocument()) {
                        $e = $this->ownerDocument()->createElementNS(
                                $this->namespaceURI(), ($this->prefix() !== NULL) ? ($this->prefix() . ':' . $this->localName()) : $this->localName()
                        );
                } else {
                        $e = $this->ownerDocument()->createElement($this->localName());
                }

                for ($i=0, $n=count($this->_attrKeys); $i<$n; $i++) {
                        $lname = $this->_attrKeys[$i];
                        $a = $this->_attrsByLName[$lname];
                        $b = $a->cloneNode();
                        $b->_setOwnerElement($e);
                        $e->_attrsByLName[$lname] = $b;
                        $e->_addQName($b);
                }
                /*
                 * TODO: PHP Arrays are assigned by copy, while objects are
                 * assigned by reference.
                 * We want a copy, and since this is an array, we get one.
                 */
                $e->_attrKeys = $this->_attrKeys;

                return $e;
        }

        public function _subclass_isEqual($other)
        {
                if ($this->localName() !== $other->localName() ||
                $this->namespaceURI() !== $other->namespaceURI() ||
                $this->prefix() !== $that->prefix() ||
                $this->_numattrs !== $that->_numattrs) {
                        return false;
                }

                /*
                 * Compare the sets of attributes, ignoring order
                 * and ignoring attribute prefixes.
                 */
                for ($i=0, $n=$this->_numattrs; $i<$n; $i++) {
                        $a = $this->_attr($i);
                        if (!$that->hasAttributeNS($a->namespaceURI(), $a->localName())) {
                                return false;
                        }
                        if ($that->getAttributeNS($a->namespaceURI(), $a->localName()) !== $a->value()) {
                                return false;
                        }
                }
                return true;
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

	/* TODO: THESE ARE PART OF THE ParentNode MIXIN ! Along with .children etc. */

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








// The children property of an Element will be an instance of this class.
// It defines length, item() and namedItem() and will be wrapped by an
// HTMLCollection when exposed through the DOM.
//function ChildrenCollection(e) {
  //this.element = e;
  //this.updateCache();
//}

//ChildrenCollection.prototype = Object.create(Object.prototype, {
  //length: { get: function() {
    //this.updateCache();
    //return this.childrenByNumber.length;
  //} },
  //item: { value: function item(n) {
    //this.updateCache();
    //return this.childrenByNumber[n] || null;
  //} },

  //namedItem: { value: function namedItem(name) {
    //this.updateCache();
    //return this.childrenByName[name] || null;
  //} },

  //// This attribute returns the entire name->element map.
  //// It is not part of the HTMLCollection API, but we need it in
  //// src/HTMLCollectionProxy
  //namedItems: { get: function() {
    //this.updateCache();
    //return this.childrenByName;
  //} },

  //updateCache: { value: function updateCache() {
    //var namedElts = /^(a|applet|area|embed|form|frame|frameset|iframe|img|object)$/;
    //if (this.lastModTime !== this.element.lastModTime) {
      //this.lastModTime = this.element.lastModTime;

      //var n = this.childrenByNumber && this.childrenByNumber.length || 0;
      //for(var i = 0; i < n; i++) {
        //this[i] = undefined;
      //}

      //this.childrenByNumber = [];
      //this.childrenByName = Object.create(null);

      //for (var c = this.element.firstChild; c !== null; c = c.nextSibling) {
        //if (c.nodeType === Node.ELEMENT_NODE) {

          //this[this.childrenByNumber.length] = c;
          //this.childrenByNumber.push(c);

          //// XXX Are there any requirements about the namespace
          //// of the id property?
          //var id = c.getAttribute('id');

          //// If there is an id that is not already in use...
          //if (id && !this.childrenByName[id])
            //this.childrenByName[id] = c;

          //// For certain HTML elements we check the name attribute
          //var name = c.getAttribute('name');
          //if (name &&
            //this.element.namespaceURI === NAMESPACE.HTML &&
            //namedElts.test(this.element.localName) &&
            //!this.childrenByName[name])
            //this.childrenByName[id] = c;
        //}
      //}
    //}
  //} },
//});

//// These functions return predicates for filtering elements.
//// They're used by the Document and Element classes for methods like
//// getElementsByTagName and getElementsByClassName

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
