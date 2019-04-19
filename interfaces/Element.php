<?php
/******************************************************************************
 * Element.php
 * ````````````
 * Defines an "Element"
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

REMOVED:
        - mutation of prefix in _setAttributeNS() since DOM4 eliminates
        - 'set' branch of Element::classList(), which is not in spec.
        - AttributesArray object; baked it into NamedNodeMap

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
        if ($node->nodeType() === Node\TEXT_NODE) {
                $a[]= $node->_data;
        } else {
                for ($i=0, $n=count($node->childNodes()); $i<$n; $i++) {
                        recursiveGetText($node->childNodes()[$i], $a);
                }
        }
}

class Element extends NonDocumentTypeChildNode
{
        // These properties maintain the set of attributes
        private $_attrsByQName = array(); // The qname->Attr map (can collide)
        private $_attrsByLName = array(); // The ns|lname->Attr map (unique)
        private $_attrKeys = array();     // attr index -> ns|lname

        public $attributes = NULL;

        public function __construct($document, $localName, $namespaceURI, $prefix)
        {
                /* Calls NonDocumentTypeChildNode and so on */
                parent::__construct();

                $this->_nodeType = Node\ELEMENT_NODE;
                $this->_ownerDocument = $document;
                $this->_localName = $localName;
                $this->_namespaceURI = $namespaceURI;
                $this->_prefix = $prefix;
                $this->_tagName = NULL;

        }

        /* DOMO Convenience function to test if HTMLElement */
        /* See Document->isHTMLDocument() */
        public function isHTMLElement()
        {
                if ($this->namespaceURI() === NAMESPACE_HTML && $this->ownerDocument()->isHTMLDocument()) {
                        return true;
                }
                return false;
        }

        public function tagName()
        {
                if ($this->_tagName === NULL) {
                        if ($this->_prefix === NULL) {
                                $tn = $this->localName();
                        } else {
                                $tn = $this->_prefix . ':' . $this->localName();
                        }
                        if ($this->isHTMLElement()) {
                                $up = $uppercaseCache[$tn];
                                if (!$up) {
                                        /*
                                         * Converting to uppercase can be slow,
                                         * so cache the conversion.
                                         */
                                        $uppercaseCache[$tn] = $up = utils\toASCIIUpperCase($tn);
                                }
                                $tn = $up;
                        }
                        $this->_tagName = $tn;
                }
                return $this->_tagName;
        }

        public function nodeName()
        {
                /* Per spec */
                return $this->tagName();
        }

        /* TODO: What is this ? */
        public function nodeValue($value = NULL)
        {
                if ($value === NULL) {
                        /* GET */
                        return NULL;
                } else {
                        /* SET */
                        return NULL;
                }
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
                                $this->_appendChild($this->ownerDocument()->createTextNode($newtext));
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
                if ($value === NULL) {
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
                        return NodeUtils::serializeOne($this, array("nodeType" => 0 ));
                } else {
                        $document = $this->ownerDocument();
                        $parent = $this->parentNode();
                        if ($parent === NULL) {
                                return;
                        }
                        if ($parent->nodeType() === Node\DOCUMENT_NODE) {
                                utils\NoModificationAllowedError();
                        }
                        if ($parent->nodeType() === Node\DOCUMENT_FRAGMENT_NODE) {
                                $parent = $parent->ownerDocument()->createElement("body");
                        }
                        /* TODO: Deal with this parser */
                        $parser = $document->implementation->mozHTMLParser(
                                $document->_address,
                                $parent
                        );
                        $parser->parse($v===null?'':strval($value), true);
                        $this->replaceWith($parser->_asDocumentFragment());
                }
        }

        protected function _insertAdjacent($position, $node)
        {
                $first = false;

                switch ($position) {
                case "beforebegin":
                        $first = true;
                        /* falls through */
                case "afterend":
                        $parent = $this->parentNode();
                        if ($parent === NULL) {
                                return NULL;
                        }
                        return $parent->insertBefore($node, $first ? $this : $this->nextSibling());
                case "afterbegin":
                        $first = true;
                        /* falls through */
                case 'beforeend':
                        return $this->insertBefore($node, $first ? $this->firstChild : NULL);
                default:
                        return utils\SyntaxError();
                }
        }

        public function insertAdjacentElement($position, $element)
        {
                if ($element->nodeType() !== Node\ELEMENT_NODE) {
                        throw new TypeError('not an element');
                }
                $position = utils\toASCIILowerCase(strval($position));
                return $this->_insertAdjacent($position, $element);
        }

        public function insertAdjacentText($position, $data)
        {
                $textNode = $this->ownerDocument()->createTextNode($data);
                $position = utils\toASCIILowerCase(strval($position));
                $this->_insertAdjacent($position, $textNode);

                /*
                 * "This method returns nothing because it existed before we
                 * had a chance to design it."
                 */
        }

        public function insertAdjacentHTML($position, $text)
        {
                $position = utils\toASCIILowerCase(strval($position));
                $text = strval($text);

                switch ($position) {
                case "beforebegin":
                case "afterend":
                        $context = $this->parentNode();
                        if ($context === NULL || $context->nodeType() === Node\DOCUMENT_NODE) {
                                utils\NoModificationAllowedError();
                        }
                        break;
                case "afterbegin":
                case "beforeend":
                        $context = $this;
                        break;
                default:
                        utils\SyntaxError();
                }

                if ((!($context instanceof Element)) || ($context->ownerDocument()->isHTMLDocument() && $context->localName() === "html" && $context->namespaceURI() === NAMESPACE_HTML) ) {
                        $context = $context->ownerDocument()->createElementNS(NAMESPACE_HTML, "body");
                }

                $parser = $this->ownerDocument()->implementation()->mozHTMLParser(
                        $this->ownerDocument()->_address,
                        $context
                );
                $parser->parse($text, true);
                $this->_insertAdjacent($position, $parser->_asDocumentFragment());
        }

        public function children()
        {
                if (!$this->_children) {
                        $this->_children = new ChildrenCollection($this);
                }
                return $this->_children;
        }

        public function attributes()
        {
                if (!$this->_attributes) {
                        $this->_attributes = new NamedNodeMap($this);
                }
                /* TODO: Once we return, is this still live? */
                return $this->_attributes;
        }

        public function firstElementChild()
        {
                for ($n=$this->firstChild(); $n!==NULL; $n=$n->nextSibling()) {
                        if ($n->nodeType() === Node\ELEMENT_NODE) {
                                return $n;
                        }
                }
                return NULL;
        }

        public function lastElementChild()
        {
                for ($n=$this->lastChild(); $n!==NULL; $n=$n->previousSibling()) {
                        if ($n->nodeType() === Node\ELEMENT_NODE) {
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

        /*
         * This is the 'locate a namespace prefix' algorithm from the
         * DOM specification.  It is used by Node.lookupPrefix()
         * (Be sure to compare DOM3 and DOM4 versions of spec.)
         */
        public function _lookupNamespacePrefix($ns, $originalElement)
        {
                if (
                        $this->namespaceURI() &&
                        $this->namespaceURI() === $ns &&
                        $this->prefix() !== NULL &&
                        $originalElement->lookupNamespaceURI($this->prefix()) === $ns
                ) {
                        return $this->prefix();
                }

                for ($i=0, $n=$this->_numattrs; $i<$n; $i++) {
                        $a = $this->_attr($i);
                        if (
                                $a->prefix() === "xmlns" &&
                                $a->value() === $ns &&
                                $originalElement->lookupNamespaceURI($a->localName()) === $ns
                        ) {
                                return $a->localName();
                        }
                }

                $parent = $this->parentElement();
                return $parent ? $parent->_lookupNamespacePrefix($ns, $originalElement) : NULL;
        }

        /*
         * This is the 'locate a namespace' algorithm for Element nodes
         * from the DOM Core spec.  It is used by Node#lookupNamespaceURI()
         */
        public function lookupNamespaceURI(string $prefix = NULL)
        {
                if ($prefix === '') {
                        $prefix = NULL;
                }

                if ($this->namespaceURI() !== NULL && $this->prefix() === $prefix) {
                        return $this->namespaceURI();
                }

                for ($i=0, $n=$this->_numattrs; $i<$n; $i++) {
                        $a = $this->_attr($i);

                        if ($a->namespaceURI() === NAMESPACE_XMLNS) {
                                if (
                                        ($a->prefix() === 'xmlns' && $a->localName() === $prefix) ||
                                        ($prefix === NULL && $a->prefix === NULL && $a->localName() === 'xmlns')
                                ) {
                                        return $a->value() ?? NULL;
                                }
                        }
                }

                $parent = $this->parentElement();
                return $parent ? $parent->lookupNamespaceURI($prefix) : NULL;
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

         /* TODO: Is breaking these functions out still performant?
          */
        private function _helper_lname_key(?string $ns, string $lname) : string
        {
                if ($ns === NULL) {
                        $ns = "";
                }
                return "$ns|$lname";
        }

        private function _helper_qname_key(string $qname) : string
        {
                if (!ctype_lower($qname) && $this->isHTMLElement()) {
                        return utils\toASCIILowerCase($qname);
                } else {
                        return $qname;
                }
        }

	/****** GET ******/

        public function getAttribute(string $qname): ?string
        {
                $attr = $this->getAttributeNode($qname);
                return $attr ? $attr->value() : NULL;
        }

        public function getAttributeNS(?string $ns, string $lname): ?string
        {
                $attr = $this->getAttributeNodeNS($ns, $lname);
                return $attr ? $attr->value() : NULL;
        }

        public function getAttributeNode(string $qname): ?Attr
        {
                $key = $this->_helper_qname_key($qname);
                $attr = $this->_attrsByQName[$key] ?? NULL;

                return is_array($attr) ? $attr[0] : $attr;
        }

        public function getAttributeNodeNS(?string $ns, string $lname): ?Attr
        {
                $key = $this->_helper_lname_key($lname, $ns);
                return $this->_attrsByLName[$key] ?? NULL;
        }

	/****** SET ******/

        /* Set the attribute without error checking. The parser uses this
         * directly.
         */
        /* TODO: Interestingly, the spec says the value has whatever type,
           but gets converted to string.
        */
        public function _unsafe_setAttribute(string $qname, $value)
        {
                /* TODO: Should this be done in Attr::value() ? */
                $value = strval($value);

                /*
                 * XXX: the spec says that this next search should be done
                 * on the local name, but I think that is an error.
                 * email pending on www-dom about it.
                 */
                $attr = $this->_attrsByQName[$qname] ?? NULL;
                if ($attr === NULL) {
                        $new = true;
                        $attr = $this->_newattr($qname);
                } else {
                        $new = false;
                        $attr = (is_array($attr)) ? $attr[0] : $attr;
                }

                /*
                 * Now set the attribute value on the new or existing Attr
                 * object. The Attr.value setter method handles mutation
                 * events, etc.
                 */
                $attr->value($value);

                if ($this->_attributes) {
                        // TODO: is this copying to keep live? or should we
                        // only do this on the 'new' branch?
                        $this->_attributes[$qname] = $attr;
                }

                if ($new === true && $this->_newattrhook) {
                        $this->_newattrhook($qname, $value);
                }
        }

        public function setAttribute(string $qname, string $value)
        {
                /*
                 * #1: Ensure qname is valid XML
                 */
                if (!xml\isValidName($qname)) {
                        utils\InvalidCharacterError();
                }

                /*
                 * #2: Ensure if we're in HTML, that the qname
                 * is all lowercase.
                 *
                 * NOTE: Same as _helper_qname_key(), but this is
                 * just a coincidence; if we change how we key things,
                 * this would be affected and we don't want that.
                 */
                if (!ctype_lower($qname) && $this->isHTMLElement()) {
                        $qname = utils\toASCIILowerCase($qname);
                }

                /* Call the unsafe one */
                $this->_unsafe_setAttribute($qname, strval($value));
        }

        /*
         * TODO: setAttribute and setAttributeNS should be
         * factored into a single method with an option for $ns.
         */

        //public function _my_unsafe_setAttribute(string $qname, $value)
        //{
                //$attr = $this->attributes->getNamedItem($qname);
                //if ($attr === NULL) {
                        //$attr = new Attr($this, $name, NULL, NULL);
                //}
                //$this->attributes->setNamedItem($attr);
        //}

        //public function _my_unsafe_setAttributeNS(?string $ns, string $qname, $value)
        //{
                //$attr = $this->attributes->getNamedItemNS($ns, $qname);
                //if ($attr === NULL) {
                        //$attr = new Attr($this, $lname, $prefix, $ns);
                //}
                //$this->attributes->setNamedItemNS($attr);
        //}


        /* The version with no error checking used by the parser */
        /* TODO: This is basically just creating an attribute. Okay. */
        public function _unsafe_setAttributeNS(?string $ns, string $qname, $value)
        {
                /* Split qualified name into prefix and local name. */
                $pos = strpos($qname, ":");
                $prefix = ($pos === false) ? NULL : substr($qname, 0, $pos);
                $lname = ($pos === false) ? $qname : substr($qname, $pos+1);

                /* Coerce empty string namespace to NULL for convenience */
                $ns = ($ns === "") ? NULL : $ns;

                /* Key to look up attribute record in internal storage */
                $key = $this->_helper_lname_key($ns, $lname);
                $attr = $this->_attrsByLName[$key] ?? NULL;

                if ($attr === NULL) {
			/*
			 * TODO: We're making a new Attr here, vs how it
			 * works in setAttribute, like _newattr etc.
			 */
                        $attr = new Attr($this, $lname, $prefix, $ns);
                        $this->_attrsByLName[$key] = $attr;

                        if ($this->_attributes) {
                                $this->_attributes[] = $attr;
                        }

                        $this->_attrKeys[] = $key;

                        /*
                         * We also have to make the attr searchable by qname.
                         * But we have to be careful because there may already
                         * be an attr with this qname.
                         */
                        $this->_addQName($attr);
                }

                /* Automatically sends mutation event */
                $attr->value($value);

                /* TODO: Fix this handler to work in PHP */
                if ($new && $this->_newattrhook) {
                        $this->_newattrhook($qname, $value);
                }
        }

        /* Do error checking then call _setAttributeNS */
        public function setAttributeNS(?string $ns, string $qname, $value)
        {
                /*
                 * #1: Convert parameter types according to WebIDL
                 */
                $ns = ($ns === "") ? NULL : $ns;

                /*
                 * #2: Ensure qname is valid XML
                 */
                if (!xml\isValidQName($qname)) {
                        utils\InvalidCharacterError();
                }

                /*
                 * #3: Check for namespace conflicts
                 */
                $pos = strpos($qname, ":");
                $prefix = ($pos === false) ? NULL : substr($qname, 0, $pos);

                if (
                        ($prefix !== NULL && $ns === NULL)
                        ||
                        ($prefix === 'xml' && $ns !== NAMESPACE_XML)
                        ||
                        (
                                ($qname === "xmlns" || $prefix === "xmlns")
                                &&
                                ($ns !== NAMESPACE_XMLNS)
                        )
                        ||
                        (
                                ($ns === NAMESPACE_XMLNS)
                                &&
                                !($qname === "xmlns" || $prefix === "xmlns")
                        )
                ) {
                        utils\NamespaceError();
                }

                /* Call the unsafe version */
                $this->_unsafe_setAttributeNS($ns, $qname, $value);
        }

        /*
         * TODO: setAttributeNode and setAttributeNodeNS should be
         * factored into a single method with an option for $ns.
         */

        public function setAttributeNode($attr)
        {
                if ($attr->ownerElement() !== NULL && $attr->ownerElement() !== $this) {
                        /* TODO: Fix this exception */
                        throw new DOMException(DOMException::INUSE_ATTRIBUTE_ERR);
                }

                $result = NULL;

                $oldAttrs = $this->_attrsByQName[$attr->name()] ?? NULL;

                if ($oldAttrs !== NULL) {
                        if (!is_array($oldAttrs)) {
                                $oldAttrs = array($oldAttrs)
                        }

                        if (in_array($attr, $oldAttrs)) {
                                return $attr;
                        } else if ($attr->ownerElement() !== NULL) {
                                /* TODO: Fix this exception */
                                throw new DOMException(DOMException::INUSE_ATTRIBUTE_ERR);
                        }
                        foreach ($oldAttrs as $a) {
                                $this->removeAttributeNode($a);
                        }
                        $result = $oldAttrs[0];
                }

                $this->setAttributeNodeNS($attr);

                return $result;
        }

        public function setAttributeNodeNS($attr)
        {
                /* Can't associate if the attribute is not yet dissociated */
                /* TODO: Why aren't we testing for $this as in setAttributeNode() ? */
                if ($attr->ownerElement() !== NULL) {
                        /* TODO: Fix this exception */
                        throw new DOMException(DOMException::INUSE_ATTRIBUTE_ERR);
                }

                $key = $this->_helper_lname_key($attr->namespaceURI(), $attr->localName());
                $oldAttr = $this->_attrsByLName[$key] ?? NULL;

                /*
                   TODO: Is there no chance of this being an array here??
                   As in the non-NS version?
                 */

                if ($oldAttr) {
                        $this->removeAttributeNode($oldAttr);
                }

                /* TODO: THIS IS NOT A METHOD ANYMORE! */
                $attr->_setOwnerElement($this);
                $this->_attrsByLName[$key] = $attr;

                if ($this->_attributes) {
                        /*
                         * TODO: can we not just append? no, I suppose this
                         * is a bit of memory management going on.
                         */
                        $this->_attributes[count($this->_attrKeys)] = $attr;
                }

                $this->_attrKeys[]= $key;
                $this->_addQName($attr);

                if ($this->_newattrhook) {
                        $this->_newattrhook($attr->name(), $attr->value());
                }

                return $oldAttr ?? NULL;
        }

	/****** REMOVE ******/

        /*
         * TODO: removeAttribute and removeAttributeNS should be
         * factored into a single method with an option for $ns.
         */

        public function removeAttribute(string $qname)
        {
                $key = $this->_helper_qname_key($qname);
                $attr = $this->_attrsByQName[$key] ?? NULL;

                if ($attr === NULL)) {
                        return;
                }

                /*
                 * If there is more than one match for this qname
                 * so don't delete the qname mapping, just remove the first
                 * element from it.
                 */
                if (is_array($attr)) {
                        if (count($attr) > 2) {
                                /* remove it from the array */
                                $attr = array_shift($attr);
                        } else {
                                /* TODO: Ok.. */
                                $this->_attrsByQName[$key] = $attr[1];
                                $attr = $attr[0];
                        }
                } else {
                        /* only a single match, so remove the qname mapping */
                        unset($this->_attrsByQName[$key]);
                }

                /*
                 * Now attr is the removed attribute.
                 * Figure out its ns+lname key and remove it from
                 * the other mapping as well.
                 */
                /*
                 * TODO: make a helper function for this key stuff,
                 * and one for the splitting as well.
                 */
                $key = $this->_helper_lname_key($attr->namespaceURI(), $attr->localName());
                unset($this->_attrsByLName[$key]);

                $i = array_search($key, $this->_attrKeys);

                if ($this->_attributes) {
                        array_splice($this->_attributes, $i, 1);
                        /* TODO: What is this here for? */
                        unset($this->_attributes[$qname]);
                }
                array_splice($this->_attrKeys, $i, 1);

                /* TODO: one kind of notice; Onchange handler for the attribute */
                $onchange = attr.onchange; // TODO: THIS IS NOT A THING NOW
                $attr->_setOwnerElement(NULL);  // TODO: THIS IS NOT A THING NOW
                if ($onchange) {
                        $onchange->call($attr, $this, $attr->localName(), $attr->value(), NULL);
                }

                // TODO: the other kind of notice; Mutation event
                if ($this->rooted) {
                        $this->ownerDocument()->mutateRemoveAttr($attr);
                }
        }

        public function removeAttributeNS(?string $ns, string $lname)
        {
                $key = $this->_helper_lname_key($ns, $lname);
                $attr = $this->_attrsByLName[$key] ?? NULL;

                if ($attr === NULL) {
                        return;
                }

                unset($this->_attrsByLName[$key]);

                $i = array_search($key, $this->_attrKeys);

                if ($this->_attributes) {
			/* TODO: Wait, what?? Oh, because we can treat
			   this as both a numeric array and a map in JS?
			   (see NamedNodeMap spec) really need to fix this up.
			*/
                        array_splice($this->_attributes, $i, 1);
                }
                array_splice($this->_attrKeys, $i, 1);

                /*
                 * Now find the same Attr object in the qname mapping
                 * and remove it. But be careful because there may be
                 * more than one match.
                 */
                $this->_removeQName($attr);

                /* Onchange handler for the attribute */
                /* TODO: It doesn't work this way anymore */
                $onchange = $attr->onchange;
                $attr->_setOwnerElement(NULL);
                if ($onchange) {
                        $onchange($attr, $this, $attr->localName(), $attr->value(), NULL);
                }
                // Mutation event
                if ($this->rooted()) {
                        /* TODO: FIx */
                        $this->ownerDocument()->mutateRemoveAttr($attr);
                }
        }

        /*
         * TODO: there is no such thing as removeAttributeNodeNS()
         */

        public function removeAttributeNode(Attr $attr)
        {
                $key = $this->_helper_lname_key($attr->namespaceURI(), $attr->localName());
                $old = $this->_attrsByLName[$key] ?? NULL;

                if ($old === NULL || $old !== $attr) {
                        utils\NotFoundError();
                }

                $this->removeAttributeNS($attr->namespaceURI(), $attr->localName());

                return $attr;
        }

	/****** HAS ******/

        public function hasAttribute(string $qname): boolean
        {
                $key = $this->_helper_qname_key($qname);
                return isset($this->_attrsByQName[$key]);
        }

        public function hasAttributeNS(?string $ns, string $lname): boolean
        {
                $key = $this->_helper_lname_key($ns, $lname);
                return isset($this->_attrsByLName[$key]);
        }

	/****** OTHER ******/

        public function toggleAttribute(string $qname, ?boolean $force=NULL): boolean
        {
                if (!xml\isValidName($qname)) {
                        utils\InvalidCharacterError();
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
                return $this->_numattrs > 0;
        }

        public function getAttributeNames()
        {
                /*
                 * TODO: Orig used array_map, but I didn't want to deal
                 * with a callback...
		 * Note that per spec, these are not guaranteed to be
 		 * unique.
                 */
                $ret = array();

                foreach ($this->_attrKeys as $key) {
                        $ret[] = $this->_attrsByLName[$key]->name();
                }

                return $ret;
        }

        /*********************************************************************
         * 'raw' versions of methods for reflected attributes
         * TODO: WHY ARE THEY HERE? THESE ARE ONLY CALLED BY THE
         * JUNK TRUNK IN 'attributes.js' AND JUST...WHY!?
         *********************************************************************/
        /*
         * This 'raw' version of getAttribute is used by the getter functions
         * of reflected attributes. It skips some error checking and
         * namespace steps
         */
        public function _getattr($qname)
        {
                /*
                 * Assume that qname is already lowercased, so don't do
                 * it here.
                 * Also don't check whether attr is an array: a qname with
                 * no prefix will never have two matching Attr objects
                 * (because setAttributeNS doesn't allow a non-null namespace
                 * with a null prefix.
                 */
                $attr = $this->_attrsByQName[$qname] ?? NULL;
                return $attr ? $attr->value() : NULL;
        }

        /* The raw version of setAttribute for reflected IDL attributes. */
        public function _setattr($qname, $value)
        {
                $attr = $this->_attrsByQName[$qname] ?? NULL;
                $new = false;
                if ($attr === NULL) {
                        $new = true;
                        $attr = $this->_newattr($qname);
                }
                $attr->value(strval($value));
                if ($this->_attributes) {
                        $this->_attributes[$qname] = $attr;
                }
                if ($new === true && $this->_newattrhook) {
                        $this->_newattrhook($qname, $value);
                }
        }

        /*
         * Create a new Attr object, insert it, and return it.
         * Used by _unsafe_setAttribute() and by _setattr()
	 *
	 * TODO: So, we basically don't have any namespace here,
	 * making this an unnecessarily different branch vs *NS
         */
        public function _newattr($qname)
        {
                $attr = new Attr($this, $qname, NULL, NULL);
                $key = "|$qname";
                $this->_attrsByQName[$qname] = $attr;
                $this->_attrsByLName[$key] = $attr;

                if ($this->_attributes) {
                        $this->_attributes[count($this->_attrKeys)] = $attr;
                }
                $this->_attrKeys[] = $key;

                return $attr;
        }

        /*********************************************************************
         * Bizarre book-keeping code
         *********************************************************************/
        /*
         * Add a qname->Attr mapping to the _attrsByQName object, taking into
         * account that there may be more than one attr object with the
         * same qname
         */
        public function _addQName($attr)
        {
                $qname = $attr->name();
                $existing = $this->_attrsByQName[$qname] ?? NULL;

                if (!$existing === NULL) {
                        $this->_attrsByQName[$qname] = $attr;
                } else if (is_array($existing)) {
                        $existing[] = $attr;
                } else {
                        $this->_attrsByQName[$qname] = array($existing, $attr);
                }

                if ($this->_attributes) {
                        $this->_attributes[$qname] = $attr;
                }
        }

        /*
         * Remove a qname->Attr mapping to the _attrsByQName object,
         * taking into account that there may be more than one attr
         * object with the same qname
         */
        public function _removeQName($attr)
        {
                $qname = $attr->name();
                $target = $this->_attrsByQName[$qname] ?? NULL;

                if (is_array($target)) {
                        $idx = array_search($attr, $target);

                        utils\assert($idx !== -1); // It must be here somewhere

                        if (count($target) === 2) {
                                $this->_attrsByQName[$qname] = $target[1-$idx];
                                if ($this->_attributes) {
                                        $this->_attributes[$qname] = $this->_attrsByQName[$qname];
                                }
                        } else {
                                array_splice($target, $idx, 1);
                                if ($this->_attributes && $this->_attributes[$qname] === $attr) {
                                        $this->_attributes[$qname] = $target[0];
                                }
                        }
                } else {
                        utils\assert($target === $attr);  // If only one, it must match
                        unset($this->_attrsByQName[$qname]);
                        if ($this->_attributes) {
                                unset($this->_attributes[$qname]);
                        }
                }
        }

        /* Return the number of attributes */
        public function _numattrs()
        {
                return count($this->_attrKeys);
        }

        /* Return the nth Attr object */
        public function _attr($n)
        {
                return $this->_attrsByLName[$this->_attrKeys[$n]];
        }

/* TODO TODO TODO */
  // Define getters and setters for an 'id' property that reflects
  // the content attribute 'id'.
  id: attributes.property({name: 'id'}),

  // Define getters and setters for a 'className' property that reflects
  // the content attribute 'class'.
  className: attributes.property({name: 'class'}),
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
attributes.registerChangeHandler(Element, 'id',
 function(element, lname, oldval, newval) {
   if (element.rooted) {
     if (oldval) {
       element.ownerDocument.delId(oldval, element);
     }
     if (newval) {
       element.ownerDocument.addId(newval, element);
     }
   }
 }
);
attributes.registerChangeHandler(Element, 'class',
 function(element, lname, oldval, newval) {
   if (element._classList) { element._classList._update(); }
 }
);








// The children property of an Element will be an instance of this class.
// It defines length, item() and namedItem() and will be wrapped by an
// HTMLCollection when exposed through the DOM.
function ChildrenCollection(e) {
  this.element = e;
  this.updateCache();
}

ChildrenCollection.prototype = Object.create(Object.prototype, {
  length: { get: function() {
    this.updateCache();
    return this.childrenByNumber.length;
  } },
  item: { value: function item(n) {
    this.updateCache();
    return this.childrenByNumber[n] || null;
  } },

  namedItem: { value: function namedItem(name) {
    this.updateCache();
    return this.childrenByName[name] || null;
  } },

  // This attribute returns the entire name->element map.
  // It is not part of the HTMLCollection API, but we need it in
  // src/HTMLCollectionProxy
  namedItems: { get: function() {
    this.updateCache();
    return this.childrenByName;
  } },

  updateCache: { value: function updateCache() {
    var namedElts = /^(a|applet|area|embed|form|frame|frameset|iframe|img|object)$/;
    if (this.lastModTime !== this.element.lastModTime) {
      this.lastModTime = this.element.lastModTime;

      var n = this.childrenByNumber && this.childrenByNumber.length || 0;
      for(var i = 0; i < n; i++) {
        this[i] = undefined;
      }

      this.childrenByNumber = [];
      this.childrenByName = Object.create(null);

      for (var c = this.element.firstChild; c !== null; c = c.nextSibling) {
        if (c.nodeType === Node.ELEMENT_NODE) {

          this[this.childrenByNumber.length] = c;
          this.childrenByNumber.push(c);

          // XXX Are there any requirements about the namespace
          // of the id property?
          var id = c.getAttribute('id');

          // If there is an id that is not already in use...
          if (id && !this.childrenByName[id])
            this.childrenByName[id] = c;

          // For certain HTML elements we check the name attribute
          var name = c.getAttribute('name');
          if (name &&
            this.element.namespaceURI === NAMESPACE.HTML &&
            namedElts.test(this.element.localName) &&
            !this.childrenByName[name])
            this.childrenByName[id] = c;
        }
      }
    }
  } },
});

// These functions return predicates for filtering elements.
// They're used by the Document and Element classes for methods like
// getElementsByTagName and getElementsByClassName

function localNameElementFilter(lname) {
  return function(e) { return e.localName === lname; };
}

function htmlLocalNameElementFilter(lname) {
  var lclname = utils.toASCIILowerCase(lname);
  if (lclname === lname)
    return localNameElementFilter(lname);

  return function(e) {
    return e.isHTML ? e.localName === lclname : e.localName === lname;
  };
}

function namespaceElementFilter(ns) {
  return function(e) { return e.namespaceURI === ns; };
}

function namespaceLocalNameElementFilter(ns, lname) {
  return function(e) {
    return e.namespaceURI === ns && e.localName === lname;
  };
}

function classNamesElementFilter(names) {
  return function(e) {
    return names.every(function(n) { return e.classList.contains(n); });
  };
}

function elementNameFilter(name) {
  return function(e) {
    // All the *HTML elements* in the document with the given name attribute
    if (e.namespaceURI !== NAMESPACE.HTML) { return false; }
    return e.getAttribute('name') === name;
  };
}
