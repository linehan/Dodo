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
 * CSS-OM    CSS Object Model                http://drafts.csswg.org/cssom-view/
 * HTML-LS   HTML Living Standard            https://html.spec.whatwg.org/
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
namespace domo;

require_once('util.php');
require_once('NonDocumentTypeChildNode.php');
require_once('Attr.php');
require_once('NamedNodeMap.php');


/*
 * Qualified Names, Local Names, and Namespace Prefixes
 *
 * An Element or Attribute's qualified name is its local name if its
 * namespace prefix is null, and its namespace prefix, followed by ":",
 * followed by its local name, otherwise.
 */

/*
 * OPTIMIZATION: When we create a DOM tree, we will likely create many
 * elements with the same tag name / qualified name, and thus need to
 * repeatedly convert those strings to ASCII uppercase to form the
 * HTML-uppercased qualified name.
 *
 * This table caches the results of that ASCII uppercase conversion,
 * turning subsequent calls into O(1) table lookups.
 */
$UC_Cache = array();


class Element extends NonDocumentTypeChildNode
{
	/* Required by Node */
        public $_nodeType = ELEMENT_NODE;
        public $_nodeValue = NULL;
	public $_nodeName = NULL; /* HTML-uppercased qualified name */
        public $_ownerDocument = NULL;

	/* Required by Element */
        public $_namespaceURI = NULL;
        public $_localName = NULL;
        public $_prefix = NULL;

	/* Actually attached without an accessor */
        public $attributes = NULL;

        /**
         * Element constructor
         *
         * @param Document $doc
	 * @param string $lname
	 * @param string $ns
	 * @param ?string $ns
	 * @return void
	 */
        public function __construct(Document $doc, string $lname, ?string $ns, ?string $prefix=NULL)
        {
		global $UC_Cache; /* See declaration, above */

                parent::__construct();

		/*
		 * TODO
		 * DOM-LS: "Elements have an associated namespace, namespace
		 * prefix, local name, custom element state, custom element
		 * definition, is value. When an element is created, all of
		 * these values are initialized.
		 */
                $this->_namespaceURI  = $ns;
                $this->_prefix        = $prefix;
                $this->_localName     = $lname;
                $this->_ownerDocument = $doc;

		/*
		 * DOM-LS: "An Element's qualified name is its local name
		 * if its namespace prefix is null, and its namespace prefix,
		 * followed by ":", followed by its local name, otherwise."
		 */
		$qname = ($prefix === NULL) ? $lname : "$prefix:$lname";

		/*
		 * DOM-LS: "An element's tagName is its HTML-uppercased
		 * qualified name".
		 *
		 * DOM-LS: "If an Element is in the HTML namespace and its node
		 * document is an HTML document, then its HTML-uppercased
		 * qualified name is its qualified name in ASCII uppercase.
		 * Otherwise, its HTML-uppercased qualified name is its
		 * qualified name."
		 */
                if ($this->isHTMLElement()) {
                        if (!isset($UC_Cache[$qname])) {
                                $uc_qname = \domo\ascii_to_uppercase($qname);
                                $UC_Cache[$qname] = $uc_qname;
                        } else {
                                $uc_qname = $UC_Cache[$qname];
                        }
                } else {
			/* If not an HTML element, don't uppercase. */
			$uc_qname = $qname;
		}

		/*
		 * DOM-LS: "User agents could optimize qualified name and
		 * HTML-uppercased qualified name by storing them in internal
		 * slots."
		 */
                $this->_nodeName = $uc_qname;

		/*
		 * DOM-LS: "Elements also have an attribute list, which is
		 * a list exposed through a NamedNodeMap. Unless explicitly
		 * given when an element is created, its attribute list is
		 * empty."
		 */
                $this->attributes = new NamedNodeMap($this);
        }

        /**********************************************************************
         * ACCESSORS
         **********************************************************************/

        public function prefix(): ?string
        {
                return $this->_prefix;
        }
        public function localName(): string
        {
                return $this->_localName;
        }
        public function namespaceURI(): ?string
        {
                return $this->_namespaceURI;
        }
        public function tagName(): string
        {
                return $this->_nodeName;
        }
	public function nodeName(): string
	{
		return $this->_nodeName;
	}
	public function nodeValue(): string
	{
		return $this->_nodeValue;
	}

	/**
	 * Get or set the text content of an Element.
	 *
	 * @param string $value
	 * @return ?string
	 * @spec DOM-LS
	 * @implements abstract public Node::textContent
	 */
        public function textContent(?string $value = NULL)
        {
                /* GET */
                if ($value === NULL) {
                        $text = array();
                        \domo\algorithm\descendant_text_content($this, $text);
                        return implode("", $text);
                /* SET */
                } else {
                        $this->__remove_children();
                        if ($value !== "") {
                                /* Equivalent to Node:: appendChild without checks! */
                                \domo\whatwg\insert_before_or_replace($node, $this->_ownerDocument->createTextNode($value), NULL);
                        }
                }
        }

        public function innerHTML(string $value = NULL)
        {
                if ($value === NULL) {
                        return $this->__serialize();
                } else {
                        /* NYI */
                }
        }

        public function outerHTML(string $value = NULL)
        {
                /* NOT IMPLEMENTED ANYMORE */
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

        public function _subclass_cloneNodeShallow(): ?Node
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

        public function _subclass_isEqualNode(Node $node): bool
        {
                if ($this->localName() !== $node->localName()
                || $this->namespaceURI() !== $node->namespaceURI()
                || $this->prefix() !== $node->prefix()
                || count($this->attributes) !== count($node->attributes)) {
                        return false;
                }

                /*
                 * Compare the sets of attributes, ignoring order
                 * and ignoring attribute prefixes.
                 */
                foreach ($this->attributes as $a) {
                        if (!$node->hasAttributeNS($a->namespaceURI(), $a->localName())) {
                                return false;
                        }
                        if ($node->getAttributeNS($a->namespaceURI(), $a->localName()) !== $a->value()) {
                                return false;
                        }
                }
                return true;
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


        /**********************************************************************
         * ATTRIBUTES
         **********************************************************************/

	/* GET ****************************************************************/

        /**
         * Fetch the value of an attribute with the given qualified name
         *
         * @param string $qname The attribute's qualifiedName
         * @return ?string the value of the attribute
         */
        public function getAttribute(string $qname): ?string
        {
                $attr = $this->attributes->getNamedItem($qname);
                return $attr ? $attr->value() : NULL;
        }

        /**
         * Fetch value of attribute with the given namespace and localName
         *
         * @param ?string $ns The attribute's namespace
         * @param string $lname The attribute's local name
         * @return ?string the value of the attribute
         * @spec DOM-LS
         */
        public function getAttributeNS(?string $ns, string $lname): ?string
        {
                $attr = $this->attributes->getNamedItemNS($ns, $lname);
                return $attr ? $attr->value() : NULL;
        }

        /**
         * Fetch the Attr node with the given qualifiedName
         *
         * @param string $lname The attribute's local name
         * @return ?Attr the attribute node, or NULL
         * @spec DOM-LS
         */
        public function getAttributeNode(string $qname): ?Attr
        {
                return $this->attributes->getNamedItem($qname);
        }

        /**
         * Fetch the Attr node with the given namespace and localName
         *
         * @param string $lname The attribute's local name
         * @return ?Attr the attribute node, or NULL
         * @spec DOM-LS
         */
        public function getAttributeNodeNS(?string $ns, string $lname): ?Attr
        {
                return $this->attributes->getNamedItemNS($ns, $lname);
        }

	/* SET ****************************************************************/

        /**
         * Set the value of first attribute with a particular qualifiedName
         *
         * @param string $qname
         * @param $value
         * @return void
         * @spec DOM-LS
         *
         * NOTES
         * Per spec, $value is not a string, but the string value of
         * whatever is passed.
         */
        public function setAttribute(string $qname, $value)
        {
                if (!\domo\whatwg\is_valid_xml_name($qname)) {
                        \domo\error("InvalidCharacterError");
                }

                if (!ctype_lower($qname) && $this->isHTMLElement()) {
                        $qname = \domo\ascii_to_lowercase($qname);
                }

                $attr = $this->attributes->getNamedItem($qname);
                if ($attr === NULL) {
                        $attr = new Attr($this, $qname, NULL, NULL);
                }
                $attr->value($value);
                $this->attributes->setNamedItem($attr);
        }

        /**
         * Set value of attribute with a particular namespace and localName
         *
         * @param string $ns
         * @param string $qname
         * @param $value
         * @return void
         * @spec DOM-LS
         *
         * NOTES
         * Per spec, $value is not a string, but the string value of
         * whatever is passed.
         */
        public function setAttributeNS(?string $ns, string $qname, $value)
        {
                $lname = NULL;
                $prefix = NULL;

                \domo\whatwg\validate_and_extract($ns, $qname, $prefix, $lname);

                $attr = $this->attributes->getNamedItemNS($ns, $qname);
                if ($attr === NULL) {
                        $attr = new Attr($this, $lname, $prefix, $ns);
                }
                $attr->value($value);
                $this->attributes->setNamedItemNS($attr);
        }

        /**
         * Add an Attr node to an Element node
         *
         * @param Attr $attr
         * @return ?Attr
         */
        public function setAttributeNode(Attr $attr): ?Attr
        {
                return $this->attributes->setNamedItem($attr);
        }

        /**
         * Add a namespace-aware Attr node to an Element node
         *
         * @param Attr $attr
         * @return ?Attr
         */
        public function setAttributeNodeNS($attr)
        {
                return $this->attributes->setNamedItemNS($attr);
        }

	/* REMOVE *************************************************************/

        /**
         * Remove the first attribute given a particular qualifiedName
         *
         * @param string $qname
         * @return Attr or NULL the removed attribute node
         * @spec DOM-LS
         */
        public function removeAttribute(string $qname): ?Attr
        {
                return $this->attributes->removeNamedItem($qname);
        }

        /**
         * Remove attribute given a particular namespace and localName
         *
         * @param string $ns namespace
         * @param string $lname localName
         * @return Attr or NULL the removed attribute node
         * @spec DOM-LS
         */
        public function removeAttributeNS(?string $ns, string $lname)
        {
                return $this->attributes->removeNamedItemNS($ns, $lname);
        }

        /**
         * Remove the given attribute node from this Element
         *
         * @param Attr $attr attribute node to remove
         * @return Attr or NULL the removed attribute node
         * @spec DOM-LS
         */
        public function removeAttributeNode(Attr $attr)
        {
                /* TODO: This is not a public function */
                return $this->attributes->_remove($attr);
        }

	/* HAS ****************************************************************/

        /**
         * Test Element for attribute with the given qualified name
         *
         * @param string $qname Qualified name of attribute
         * @return bool 
         * @spec DOM-LS
         */
        public function hasAttribute(string $qname): bool 
        {
                return $this->attributes->hasNamedItem($qname);
        }

        /**
         * Test Element for attribute with the given namespace and localName
         *
         * @param ?string $ns the namespace
         * @param string $lname the localName
         * @return bool 
         * @spec DOM-LS
         */
        public function hasAttributeNS(?string $ns, string $lname): bool 
        {
                return $this->attributes->hasNamedItemNS($ns, $lname);
        }

	/* OTHER **************************************************************/

        /**
         * Toggle the first attribute with the given qualified name
         *
         * @param string $qname qualified name
         * @param bool $force whether to set if no attribute exists
         * @return bool whether we set or removed an attribute
         * @spec DOM-LS
         */
        public function toggleAttribute(string $qname, ?bool $force=NULL): bool 
        {
                if (!\domo\whatwg\is_valid_xml_name($qname)) {
                        \domo\error("InvalidCharacterError");
                }

                $a = $this->attributes->getNamedItem($qname);

                if ($a === NULL) {
                        if ($force === NULL || $force === true) {
                                $this->setAttribute($qname, "");
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

        /**
         * Test whether this Element has any attributes
         *
         * @return bool 
         * @spec DOM-LS
         */
        public function hasAttributes(): bool 
        {
                return !empty($this->attributes->index_to_attr);
        }

        /**
         * Fetch the qualified names of all attributes on this Element
         *
         * @return array of strings, or empty array if no attributes.
         * @spec DOM-LS
         *
         * NOTE
         * The names are *not* guaranteed to be unique.
         */
        public function getAttributeNames(): array
        {
                /*
		 * Note that per spec, these are not guaranteed to be
 		 * unique.
                 */
                $ret = array();

                foreach ($this->attributes as $a) {
                        $ret[] = $a->name();
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
                //return $select->matches($this, $selector);
        }

        public function closest($selector)
        {
                $el = $this;
                while (method_exists($el, "matches") && !$el->matches($selector)) {
                        $el = $el->parentNode();
                }
                return (method_exists($el, "matches")) ? $el : NULL;
        }

        /*********************************************************************
         * DOMO EXTENSIONS
         ********************************************************************/

        /* Calls isHTMLDocument() on ownerDocument */
        public function isHTMLElement()
        {
                if ($this->_namespaceURI === NAMESPACE_HTML
                && $this->_ownerDocument
                && $this->_ownerDocument->isHTMLDocument()) {
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
