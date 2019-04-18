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

TODO:
        - Calls to mozHTMLParser should be replaced by calls to either
          Tim's new PHP HTML parser, or to a generic harness that you
          can strap an arbitrary HTML parser into.
*/

require_once("xmlnames");
require_once("utils");
require_once("attributes.php");
require_once("Node.php");
require_once("NodeList.php");
require_once("NodeUtils.php");
require_once("FilteredElementList.php");
require_once("DOMException.php");
require_once("DOMTokenList.php");
require_once("select.php");
require_once("ContainerNode.php");
require_once("ChildNode.php");
require_once("NonDocumentTypeChildNode.php");
require_once("NamedNodeMap.php");


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
        private $_attrsByQName = array(); // The qname->Attr map
        private $_attrsByLName = array(); // The ns|lname->Attr map
        private $_attrKeys = array();     // attr index -> ns|lname

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
                        $this->_attributes = new AttributesArray($this);
                }
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

        public function getAttribute($qname)
        {
                $attr = $this->getAttributeNode($qname);
                return $attr ? $attr->value() : NULL;
        }

        public function getAttributeNS($ns, $lname)
        {
                $attr = $this->getAttributeNodeNS($ns, $lname);
                return $attr ? $attr->value() : NULL;
        }

        public function getAttributeNode($qname)
        {
                $qname = strval($qname);

                if (!ctype_lower($qname) && $this->isHTMLElement()) {
                        $qname = utils\toASCIILowerCase($qname);
                }

                $attr = $this->_attrsByQName[$qname];

                if (!$attr) {
                        return NULL;
                }

                return is_array($attr) ? $attr[0] : $attr;
        }

        public function getAttributeNodeNS($ns, $lname)
        {
                $ns = ($ns === NULL) ? '' : strval($ns);
                $lname = strval($lname);

                $attr = $this->_attrsByLName[$ns . '|' . $lname];
                return $attr ? $attr : NULL;
        }

        public function hasAttribute($qname)
        {
                $qname = strval($qname);

                if (!ctype_lower($qname) && $this->isHTMLElement()) {
                        $qname = utils\toASCIILowerCase($qname);
                }
                return isset($this->_attrsByQName[$qname]);
        }

        public function hasAttributeNS($ns, $lname)
        {
                $ns = ($ns === NULL) ? '' : strval($ns);

                $lname = strval($lname);

                $key = $ns . '|' . $lname;

                return isset($this->_attrsByLName[$key]);
        }

        public function hasAttributes()
        {
                return $this->_numattrs > 0;
        }

        public function toggleAttribute($qname, $force=NULL)
        {
                $qname = strval($qname);

                if (!xml\isValidName($qname)) {
                        utils\InvalidCharacterError();
                }

                if (!ctype_lower($qname) && $this->isHTMLElement())
                        $qname = utils\toASCIILowerCase($qname);
                }
                $a = $this->_attrsByQName[$qname] ?? NULL;

                if ($a === NULL) {
                        if ($force === NULL || $force === true) {
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

        /* Set the attribute without error checking. The parser uses this. */
        public function _setAttribute($qname, $value)
        {
                /*
                 * XXX: the spec says that this next search should be done
                 * on the local name, but I think that is an error.
                 * email pending on www-dom about it.
                 */
                $attr = $this->_attrsByQName[$qname];
                if (!$attr) {
                        $attr = $this->_newattr($qname);
                        $isnew = true;
                } else {
                        if (is_array($attr)) {
                                $attr = $attr[0];
                        }
                }

                /*
                 * Now set the attribute value on the new or existing Attr
                 * object. The Attr.value setter method handles mutation
                 * events, etc.
                 */
                $attr->value($value);

                if ($this->_attributes) {
                        $this->_attributes[$qname] = $attr;
                }
                if ($isnew && $this->_newattrhook) {
                        $this->_newattrhook($qname, $value);
                }
        }

        /* Check for errors, and then set the attribute */
        public function setAttribute($qname, $value)
        {
                $qname = strval($qname);

                if (!xml\isValidName($qname)) {
                        utils\InvalidCharacterError();
                }

                if (!ctype_lower($qname) && $this.isHTMLElement()) {
                        $qname = utils\toASCIILowerCase($qname);
                }
                $this->_setAttribute($qname, strval($value));
        }

        /*
         * Attributes have a qualified name expressed as
         *
         *      <qualified name> := [<prefix>]:<local name>
         *
         * e.g. 'xmlns:foo' or 'svg:bar'
         *
         * TODO: Should we throw an error on e.g. ':foo' ?
         */

        /* The version with no error checking used by the parser */
        /* TODO: This is basically just creating an attribute. Okay. */
        public function _setAttributeNS(?string $ns, string $qname, string $value)
        {
                /*
                 * Split the attribute's qualified name into its prefix
                 * and local name.
                 *
                 * If the qualified name consists only of a local name,
                 * the prefix is set to NULL.
                 */
                $pos = strpos($qname, ":");

                if ($pos === false) {
                        /* No namespace on the attribute */
                        $prefix = NULL;
                        $lname = $qname;
                } else {
                        /* Split into prefix and lname */
                        $prefix = substr($qname, 0, $pos);
                        $lname = substr($qname, $pos+1);
                }

                /* Coerce empty string namespace to NULL for convenience */
                if ($ns === "") {
                        $ns = NULL;
                }

                /* Key to look up attribute record in internal storage */
                if ($ns === NULL) {
                        $key = "|$lname";
                } else {
                        $key = "$ns|$lname";
                }

                $attr = $this->_attrsByLName[$key];

                if (!$attr) {
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
                if ($this->_newattrhook) {
                        $this->_newattrhook($qname, $value);
                }
        }

        /* Do error checking then call _setAttributeNS */
        public function setAttributeNS(?string $ns, string $qname, string $value)
        {
                /* Convert parameter types according to WebIDL */
                $ns = ($ns === NULL || $ns === "") ? NULL : $ns;

                if (!xml\isValidQName($qname)) {
                        utils\InvalidCharacterError();
                }

                $pos = strpos($qname, ":");
                if ($pos === false) {
                        $prefix = NULL;
                } else {
                        $prefix = substr($qname, 0, $pos);
                }

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

                $this->_setAttributeNS($ns, $qname, $value);
        }

        public function setAttributeNode($attr)
        {
                if ($attr->ownerElement() !== NULL && $attr->ownerElement() !== $this) {
                        /* TODO: Fix this exception */
                        throw new DOMException(DOMException::INUSE_ATTRIBUTE_ERR);
                }

                $result = NULL;

                $oldAttrs = $this->_attrsByQName[$attr->name()];

                if ($oldAttrs) {
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
                if ($attr->ownerElement() !== NULL) {
                        /* TODO: Fix this exception */
                        throw new DOMException(DOMException::INUSE_ATTRIBUTE_ERR);
                }

                $ns = $attr->namespaceURI();

                if ($ns === NULL) {
                        $key = $attr->localName();
                } else {
                        $key = "$ns|" . $attr->localName();
                }

                $oldAttr = $this->_attrsByLName[$key];

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

  removeAttribute: { value: function removeAttribute(qname) {
    qname = String(qname);
    if (/[A-Z]/.test(qname) && this.isHTML)
      qname = utils.toASCIILowerCase(qname);

    var attr = this._attrsByQName[qname];
    if (!attr) return;

    // If there is more than one match for this qname
    // so don't delete the qname mapping, just remove the first
    // element from it.
    if (Array.isArray(attr)) {
      if (attr.length > 2) {
        attr = attr.shift();  // remove it from the array
      }
      else {
        this._attrsByQName[qname] = attr[1];
        attr = attr[0];
      }
    }
    else {
      // only a single match, so remove the qname mapping
      this._attrsByQName[qname] = undefined;
    }

    var ns = attr.namespaceURI;
    // Now attr is the removed attribute.  Figure out its
    // ns+lname key and remove it from the other mapping as well.
    var key = (ns === null ? '' : ns) + '|' + attr.localName;
    this._attrsByLName[key] = undefined;

    var i = this._attrKeys.indexOf(key);
    if (this._attributes) {
      Array.prototype.splice.call(this._attributes, i, 1);
      this._attributes[qname] = undefined;
    }
    this._attrKeys.splice(i, 1);

    // Onchange handler for the attribute
    var onchange = attr.onchange;
    attr._setOwnerElement(null);
    if (onchange) {
      onchange.call(attr, this, attr.localName, attr.value, null);
    }
    // Mutation event
    if (this.rooted) this.ownerDocument.mutateRemoveAttr(attr);
  }},

  removeAttributeNS: { value: function removeAttributeNS(ns, lname) {
    ns = (ns === undefined || ns === null) ? '' : String(ns);
    lname = String(lname);
    var key = ns + '|' + lname;
    var attr = this._attrsByLName[key];
    if (!attr) return;

    this._attrsByLName[key] = undefined;

    var i = this._attrKeys.indexOf(key);
    if (this._attributes) {
      Array.prototype.splice.call(this._attributes, i, 1);
    }
    this._attrKeys.splice(i, 1);

    // Now find the same Attr object in the qname mapping and remove it
    // But be careful because there may be more than one match.
    this._removeQName(attr);

    // Onchange handler for the attribute
    var onchange = attr.onchange;
    attr._setOwnerElement(null);
    if (onchange) {
      onchange.call(attr, this, attr.localName, attr.value, null);
    }
    // Mutation event
    if (this.rooted) this.ownerDocument.mutateRemoveAttr(attr);
  }},

  removeAttributeNode: { value: function removeAttributeNode(attr) {
    var ns = attr.namespaceURI;
    var key = (ns === null ? '' : ns) + '|' + attr.localName;
    if (this._attrsByLName[key] !== attr) {
      utils.NotFoundError();
    }
    this.removeAttributeNS(ns, attr.localName);
    return attr;
  }},

  getAttributeNames: { value: function getAttributeNames() {
    var elt = this;
    return this._attrKeys.map(function(key) {
      return elt._attrsByLName[key].name;
    });
  }},

  // This 'raw' version of getAttribute is used by the getter functions
  // of reflected attributes. It skips some error checking and
  // namespace steps
  _getattr: { value: function _getattr(qname) {
    // Assume that qname is already lowercased, so don't do it here.
    // Also don't check whether attr is an array: a qname with no
    // prefix will never have two matching Attr objects (because
    // setAttributeNS doesn't allow a non-null namespace with a
    // null prefix.
    var attr = this._attrsByQName[qname];
    return attr ? attr.value : null;
  }},

  // The raw version of setAttribute for reflected idl attributes.
  _setattr: { value: function _setattr(qname, value) {
    var attr = this._attrsByQName[qname];
    var isnew;
    if (!attr) {
      attr = this._newattr(qname);
      isnew = true;
    }
    attr.value = String(value);
    if (this._attributes) this._attributes[qname] = attr;
    if (isnew && this._newattrhook) this._newattrhook(qname, value);
  }},

  // Create a new Attr object, insert it, and return it.
  // Used by setAttribute() and by set()
  _newattr: { value: function _newattr(qname) {
    var attr = new Attr(this, qname, null, null);
    var key = '|' + qname;
    this._attrsByQName[qname] = attr;
    this._attrsByLName[key] = attr;
    if (this._attributes) {
      this._attributes[this._attrKeys.length] = attr;
    }
    this._attrKeys.push(key);
    return attr;
  }},

  // Add a qname->Attr mapping to the _attrsByQName object, taking into
  // account that there may be more than one attr object with the
  // same qname
  _addQName: { value: function(attr) {
    var qname = attr.name;
    var existing = this._attrsByQName[qname];
    if (!existing) {
      this._attrsByQName[qname] = attr;
    }
    else if (Array.isArray(existing)) {
      existing.push(attr);
    }
    else {
      this._attrsByQName[qname] = [existing, attr];
    }
    if (this._attributes) this._attributes[qname] = attr;
  }},

  // Remove a qname->Attr mapping to the _attrsByQName object, taking into
  // account that there may be more than one attr object with the
  // same qname
  _removeQName: { value: function(attr) {
    var qname = attr.name;
    var target = this._attrsByQName[qname];

    if (Array.isArray(target)) {
      var idx = target.indexOf(attr);
      utils.assert(idx !== -1); // It must be here somewhere
      if (target.length === 2) {
        this._attrsByQName[qname] = target[1-idx];
        if (this._attributes) {
          this._attributes[qname] = this._attrsByQName[qname];
        }
      } else {
        target.splice(idx, 1);
        if (this._attributes && this._attributes[qname] === attr) {
          this._attributes[qname] = target[0];
        }
      }
    }
    else {
      utils.assert(target === attr);  // If only one, it must match
      this._attrsByQName[qname] = undefined;
      if (this._attributes) {
        this._attributes[qname] = undefined;
      }
    }
  }},

  // Return the number of attributes
  _numattrs: { get: function() { return this._attrKeys.length; }},
  // Return the nth Attr object
  _attr: { value: function(n) {
    return this._attrsByLName[this._attrKeys[n]];
  }},

  // Define getters and setters for an 'id' property that reflects
  // the content attribute 'id'.
  id: attributes.property({name: 'id'}),

  // Define getters and setters for a 'className' property that reflects
  // the content attribute 'class'.
  className: attributes.property({name: 'class'}),

  classList: { get: function() {
    var self = this;
    if (this._classList) {
      return this._classList;
    }
    var dtlist = new DOMTokenList(
      function() {
        return self.className || "";
      },
      function(v) {
        self.className = v;
      }
    );
    this._classList = dtlist;
    return dtlist;
  }, set: function(v) { this.className = v; }},

  matches: { value: function(selector) {
    return select.matches(this, selector);
  }},

  closest: { value: function(selector) {
    var el = this;
    while (el.matches && !el.matches(selector)) el = el.parentNode;
    return el.matches ? el : null;
  }},

  querySelector: { value: function(selector) {
    return select(selector, this)[0];
  }},

  querySelectorAll: { value: function(selector) {
    var nodes = select(selector, this);
    return nodes.item ? nodes : new NodeList(nodes);
  }}

});

/*
 * TODO: Here is the busted JavaScript style where class
 * extension is treated as a bunch of mixins applied in order
 */
Object.defineProperties(Element.prototype, ChildNode);
Object.defineProperties(Element.prototype, NonDocumentTypeChildNode);

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


// The attributes property of an Element will be an instance of this class.
// This class is really just a dummy, though. It only defines a length
// property and an item() method. The AttrArrayProxy that
// defines the public API just uses the Element object itself.
function AttributesArray(elt) {
  NamedNodeMap.call(this, elt);
  for (var name in elt._attrsByQName) {
    this[name] = elt._attrsByQName[name];
  }
  for (var i = 0; i < elt._attrKeys.length; i++) {
    this[i] = elt._attrsByLName[elt._attrKeys[i]];
  }
}
AttributesArray.prototype = Object.create(NamedNodeMap.prototype, {
  length: { get: function() {
    return this.element._attrKeys.length;
  }, set: function() { /* ignore */ } },
  item: { value: function(n) {
    /* jshint bitwise: false */
    n = n >>> 0;
    if (n >= this.length) { return null; }
    return this.element._attrsByLName[this.element._attrKeys[n]];
    /* jshint bitwise: true */
  } },
});

// We can't make direct array access work (without Proxies, node >=6)
// but we can make `Array.from(node.attributes)` and for-of loops work.
if (global.Symbol && global.Symbol.iterator) {
    AttributesArray.prototype[global.Symbol.iterator] = function() {
        var i=0, n=this.length, self=this;
        return {
            next: function() {
                if (i<n) return { value: self.item(i++) };
                return { done: true };
            }
        };
    };
}


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
