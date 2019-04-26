<?php
/******************************************************************************
 * PORT
 * REMOVED:
 * All event, nodeiterator, and treewalker stuff
 *
 * createEvent
 * createTreeWalker
 * createNodeIterator
 * _attachNodeIterator
 * _detachNodeIterator
 * _preremoveNodeIterators
 * _nodeIterators
 * Silly MirrorAttr code, along with code to set
 *      linkColor, vLinkColor, aLinkColor, fgColor, bgColor
 * applets (deprecated)
 * innerHTML/outerHTML -- not part of Document object in spec
 * dir() -- part of HTMLElement spec
 * activeElement() returned NULL no matter what
 *
 *       public function domain() {}
 *       public function referrer() {}
 *       public function readyState() {}
 *       public function cookie() {}
 *       public function lastModified() {}
 *       public function designMode() {}
 *       public function write()
 *       public function writeln()
 *       public function open()
 *       public function close()
 *
 * _hasMultipleElementsWithId
 *
 * CHANGED:
 * Document second argument '$address' and $_address to $url and $_url,
 * in line with the spec.
 *
 * TODO
 * What on earth is _updateDocTypeElement doing? I think that a Document
 * can only have one child element whose parent is that Document, but
 * here we are looping over several things. AH, I see, there may be other
 * nodes, just not *Element* nodes, e.g. a DocumentType node.
 *
 *****************************************************************************/
namespace domo;

require_once("Node.php");
require_once("NodeList.php");
require_once("Element.php");
require_once("../lib/utils.php");


class MultiId
{
        public $table = array();

        public function __construct(Node $node)
        {
                $this->table[$node->__nid] = $node;
                $this->length = 1;
                $this->first = NULL;
        }

        // Add a node to the list, with O(1) time
        public function add(Node $node)
        {
                if (!isset($this->table[$node->__nid])) {
                        $this->table[$node->__nid] = $node;
                        $this->length++;
                        $this->first = NULL;
                }
        }

        // Remove a node from the list, with O(1) time
        public function del(Node $node)
        {
                if ($this->table[$node->__nid]) {
                        unset($this->table[$node->__nid]);
                        $this->length--;
                        $this->first = NULL;
                }
        }

        // Get the first node from the list, in the document order
        // Takes O(N) time in the size of the list, with a cache that is invalidated
        // when the list is modified.
        // (the invalidation is by setting $this->first to NULL in add and del)
        public function get_first()
        {
                /* jshint bitwise: false */
                if (!$this->first) {
                        foreach ($this->table as $nid => $node) {
                                if ($this->first === NULL || $this->first->compareDocumentPosition($node) & DOCUMENT_POSITION_PRECEDING) {
                                        $this->first = $node;
                                }
                        }
                }
                return $this->first;
        }

        // If there is only one node left, return it. Otherwise return "this".
        public function downgrade()
        {
                if ($this->length === 1) {
                        foreach ($this->table as $nid => $node) {
                                return $node;
                        }
                }
                return $this;
        }
}


/**
 * The Document class. Note that there is another class called
 * HTMLDocument with an extended interface, only for Documents
 * that contain HTML. Rather than use a separate class, we load
 * it onto the Document class and switch behavior using a flag
 * in the constructor. Not sure about that, but it probably
 * things easier.
 */

/*
 * Each document has an associated
 *      encoding (an encoding),
 *      content type (a string),
 *      URL (a URL),
 *      origin (an origin),
 *      type ("xml" or "html"), and
 *      mode ("no-quirks", "quirks", or "limited-quirks").
 */
/*
 * Each document has an associated encoding (an encoding), content type
 * (a string), URL (a URL), origin (an origin), type ("xml" or "html"),
 * and mode ("no-quirks", "quirks", or "limited-quirks").
 *
 * Unless stated otherwise, a document’s encoding is the utf-8 encoding,
 * content type is "application/xml", URL is "about:blank", origin is an
 * opaque origin, type is "xml", and its mode is "no-quirks".
 *
 * A document is said to be an XML document if its type is "xml", and an
 * HTML document otherwise. Whether a document is an HTML document or an
 * XML document affects the behavior of certain APIs.
 *
 * A document is said to be in no-quirks mode if its mode is "no-quirks",
 * quirks mode if its mode is "quirks", and limited-quirks mode if its mode
 * is "limited-quirks".
 */
class Document extends Node
{
        /**********************************************************************
         * DOMO internal book-keeping layer
         **********************************************************************/
        /*
         * The Node class includes an '_nid' property, which is
         * assigned when a Node gets associated with a Document
         * (see Document::_helper_root())
         *
         * '_nid' is an internal-use index used to index Nodes
         * in this table.
         *
         * You can think of it as an unofficial 'id' given to
         * every Node once it becomes a part of a Document.
         */
        private $__nid_to_node = array();

        /*
         * For Element nodes having an actual 'id' attribute, we
         * also store a reference to the node under its 'id' in
         * this table, and use it to implement Document->getElementById();
         *
         * We must check to see if we must update this table every time:
         *      - a node is rooted / uprooted
         *      - a rooted node has an attribute added / removed / changed
         */
        private $__id_to_element = array();

        /* Used to assign Node::__lastmod */
        protected $__modclock = 0;

        /* Documents are rooted by definition and get $__nid = 1 */
        protected $__nid = 1;
        protected $__nid_next = 2;

        /* Required by Node */
        protected const _nodeType = DOCUMENT_NODE; /* see Node::nodeType */
        protected const _nodeName = '#document';   /* see Node::nodeName */
        protected const _ownerDocument = NULL;     /* see Node::ownerDocument */
        protected const _nodeValue = NULL;         /* see Node::nodeValue */

        /* Required by Document */
        protected const _characterSet = 'UTF-8';
        protected $_encoding = 'UTF-8';
        protected $_type = 'xml';
        protected $_contentType = 'application/xml';
        protected $_URL = 'about:blank';
        protected $_origin = NULL;
        protected $_compatMode = 'no-quirks';

        /* Assigned on mutation to the first DocumentType child */
        protected $_doctype = NULL;
        /* Assigned on mutation to the first Element child */
        protected $_documentElement = NULL;

        /* Used to mutate the above */
        private function __update_document_state(void): void
        {
                $this->_doctype = NULL;
                $this->_documentElement = NULL;

                for ($n=$this->firstChild(); $n!==NULL; $n=$n->nextSibling()) {
                        if ($n->_nodeType === DOCUMENT_TYPE_NODE) {
                                $this->_doctype = $n;
                        } else if ($n->_nodeType === ELEMENT_NODE) {
                                $this->_documentElement = $n;
                        }
                }
        }

        public function __construct(string $type="xml", ?string $url=NULL)
        {
                parent::__construct();

                /******** DOM-LS ********/

                /* Having an HTML Document affects some APIs */
                if ($type === 'html') {
                        $this->_contentType = 'text/html';
                        $this->_type = 'html';
                }

                /* DOM-LS: used by the documentURI and URL method */
                if ($url !== NULL) {
                        $this->_URL = $url;
                }

                /* DOM-LS: DOMImplementation associated with document */
                $this->_implementation = new DOMImplementation($this);

                /******** Internal ********/

                $this->__nid = 1;
                $this->__nid_next = 2;
                $this->__nid_to_node[0] = NULL;
                $this->__nid_to_node[1] = $this;

                /*
                 * This property holds a monotonically increasing value
                 * akin to a timestamp used to record the last modification
                 * time of nodes and their subtrees. See the lastModTime
                 * attribute and modify() method of the Node class. And see
                 * FilteredElementList for an example of the use of
                 * lastModTime
                 */
                $this->__modclock = 0;


                /******** JUNK ********/

                $this->readyState = "loading";

                /* USED EXCLUSIVELY IN htmlelts.js to make <TEMPLATE> */
                $this->_templateDocCache = NULL;
        }


        /* USED EXCLUSIVELY IN htmlelts.js to make <TEMPLATE> */
        public function _templateDoc()
        {
                if (!$this->_templateDocCache) {
                        /* "associated inert template document" */
                        $newDoc = new Document($this->isHTML, $this->_address);
                        $this->_templateDocCache = $newDoc->_templateDocCache = $newDoc;
                }
                return $this->_templateDocCache;
        }

        //// This method allows dom.js to communicate with a renderer
        //// that displays the document in some way
        //// XXX: I should probably move this to the window object
        //[> PORT TODO: should stub? <]
        //protected function _setMutationHandler($handler)
        //{
                //$this->mutationHandler = $handler;
        //}

        //// This method allows dom.js to receive event notifications
        //// from the renderer.
        //// XXX: I should probably move this to the window object
        //[> PORT TODO: should stub? <]
        //protected function _dispatchRendererEvent($targetNid, $type, $details)
        //{
                //$target = $this->_nodes[$targetNid];
                //if (!$target) {
                        //return;
                //}
                //$target->_dispatchEvent(new Event($type, $details), true);
        //}

        /*********************************************************************
         * Accessors for read-only properties defined in Document
         *********************************************************************/
        public function characterSet(void): string
        {
                return $this->_characterSet;
        }
        public function charset(void): string
        {
                return $this->characterSet(); /* historical alias */
        }
        public function inputEncoding(void): string
        {
                return $this->characterSet(); /* historical alias */
        }
        public function implementation(void): DOMImplementation
        {
                return $this->_implementation;
        }
        public function documentURI()
        {
                return $this->_URL;
        }
        public function URL(void) : string
        {
                return $this->documentURI(); /* Alias for HTMLDocuments */
        }
        public function compatMode(void)
        {
                return $this->_mode === "quirks" ? "BackCompat" : "CSS1Compat";
        }
        public function contentType(void): ?string
        {
                return $this->_contentType;
        }
        public function doctype(void): ?DocumentType
        {
                return $this->_doctype;
        }
        public function documentElement(void): ?Element
        {
                return $this->_documentElement;
        }

        /*********************************************************************
         * NODE CREATION
         *********************************************************************/
        public function createTextNode($data)
        {
                return new Text($this, strval($data));
        }

        public function createComment($data)
        {
                return new Comment($this, $data);
        }

        public function createDocumentFragment()
        {
                return new DocumentFragment($this);
        }

        public function createProcessingInstruction($target, $data)
        {
                /* TODO: STUB */
                /* TODO PORT: Wait, is this a bug? Should it be !== -1, or === -1 ? */
                /*
                if (!domo\xml\isValidName($target) || strpos($data, "?".">") !== false) {
                        domo\utils\InvalidCharacterError();
                }
                return new ProcessingInstruction($this, $target, $data);
                */
        }

        public function createAttribute($localName)
        {
                /* TODO: STUB
                $localName = strval($localName);

                if (!domo\xml\isValidName($localName)) {
                        domo\utils\InvalidCharacterError();
                }
                if ($this->isHTMLDocument) {
                        $localName = \domo\toASCIILowerCase($localName);
                }
                return new Element->_Attr(null, $localName, null, null, "");
                */
        }

        public function createAttributeNS($namespace, $qualifiedName)
        {
                /* Convert parameter types according to WebIDL */
                /* TODO: STUB
                if ($namespace === NULL || $namespace === "") {
                        $namespace = NULL;
                } else {
                        $namespace = strval($namespace);
                }

                $qualifiedName = strval($qualifiedName);

                $ve = validateAndExtract($namespace, $qualifiedName);

                return new Element->_Attr(NULL, $ve["localName"], $ve["prefix"], $ve["namespace"], "");
                */
        }

        public function createElement($lname)
        {
                $lname = strval($lname);

                if (!\domo\is_valid_xml_name($lname)) {
                        error("InvalidCharacterError");
                }

                /*
                 * Per spec, namespace should be HTML namespace if
                 * "context object is an HTML document or context
                 * object's content type is "application/xhtml+xml",
                 * and null otherwise.
                 */
                if ($this->_contentType === 'text/html') {
                        if (!ctype_lower($lname)) {
                                $lname = \domo\ascii_to_lowercase($lname);
                        }

                        return domo\html\createElement($this, $lname, NULL);

                } else if ($this->contentType === 'application/xhtml+xml') {
                        return domo\html\createElement($this, $lname, NULL);
                } else {
                        return new Element($this, $lname, NULL, NULL);
                }
        }

        public function createElementNS($ns, $qname): ?Element
        {
                /* Convert parameter types according to WebIDL */
                if ($ns === NULL || $ns === "") {
                        $ns = NULL;
                } else {
                        $ns = strval($ns);
                }

                $qname = strval($qname);

                $lname = NULL;
                $prefix = NULL;

                \domo\validate_and_extract($ns, $qname, $prefix, $lname);

                return $this->_createElementNS($lname, $ns, $prefix);
        }

        /*
         * This is used directly by HTML parser, which allows it to create
         * elements with localNames containing ':' and non-default namespaces
         */
        public function _createElementNS($lname, $ns, $prefix)
        {
                if ($ns === NAMESPACE_HTML) {
                        return domo\html\createElement($this, $lname, $prefix);
                } else if ($ns === NAMESPACE_SVG) {
                        return svg\createElement($this, $lname, $prefix);
                } else {
                        return new Element($this, $lname, $ns, $prefix);
                }
        }

        /*********************************************************************
         * MUTATION
         *********************************************************************/

        /**
         * Set the ownerDocument of a Node and its subtree to $this.
         *
         * @param Node $node
         * @return Node
         * @throws DOMException "NotSupported"
         * @spec DOM-LS
         */
        public function adoptNode(Node $node): Node
        {
                if ($node->_nodeType === DOCUMENT_NODE) {
                        \domo\error("NotSupported");
                }
                if ($node->_nodeType === ATTRIBUTE_NODE) {
                        return $node;
                }
                if ($node->parentNode()) {
                        $node->parentNode()->removeChild($node);
                }
                if ($node->_ownerDocument !== $this) {
                        $node->__set_owner($this);
                }

                return $node;
        }

        /**
         * Adopt a clone of a tree rooted at $node.
         *
         * @param Node $node
         * @param boolean $deep
         * @return Node $node
         * @spec DOM-LS
         */
        public function importNode(Node $node, boolean $deep=false): Node
        {
                return $this->adoptNode($node->cloneNode($deep));
        }

        /**
         * Extends Node::insertBefore to update documentElement and doctype
         *
         * NOTE
         * Node::appendChild is not extended, because it calls insertBefore.
         */
        public function insertBefore(Node $node, ?Node $refChild): Node
        {
                $ret = parent::insertBefore($node, $refChild);
                $this->__update_document_state();
                return $ret;
        }
        /**
         * Extends Node::replaceChild to update documentElement and doctype
         */
        public function replaceChild(Node $node, ?Node $child): Node
        {
                $ret = parent::replaceChild($node, $child);
                $this->__update_document_state();
                return $ret;
        }
        /**
         * Extends Node::removeChild to update documentElement and doctype
         */
        public function removeChild(Node $child): ?Node
        {
                $ret = parent::removeChild($child);
                $this->__update_document_state();
                return $ret;
        }

        /**
         * Clone this Document, import nodes, and call __update_document_state
         *
         * @deep  : if true, clone entire subtree
         * Returns: Clone of $this.
         * Extends: Node::cloneNode()
         * Part_Of: DOMO1
         *
         * NOTE:
         * 1. What a tangled web we weave
         * 2. With Document nodes, we need to take the additional step of
         *    calling importNode() to bring copies of child nodes into this
         *    document.
         * 3. We also need to call _updateDocTypeElement()
         */
        public function cloneNode(boolean $deep = false): ?Node
        {
                /* Make a shallow clone  */
                $clone = parent::cloneNode(false);

                if ($deep === false) {
                        /* Return shallow clone */
                        $clone->__update_document_state();
                        return $clone;
                }

                /* Clone children too */
                for ($n=$this->firstChild(); $n!==NULL; $n=$n->nextSibling()) {
                        $clone->appendChild($clone->importNode($n, true));
                }

                $clone->__update_document_state();
                return $clone;
        }

        /*********************************************************************
         * Query methods
         *********************************************************************/

        /**
         * Fetch an Element in this Document with a given ID value
         *
         * @param string $id
         * @return Element if one exists, else NULL
         * @spec DOM-LS
         *
         * NOTE
         * In the spec, this is actually the sole method of the
         * NonElementParentNode mixin.
         */
        public function getElementById($id)
        {
                if (NULL === ($n = $this->__id_to_element[$id])) {
                        return NULL;
                }
                if ($n instanceof MultiId) {
                        /* there was more than one element with this id */
                        return $n->get_first();
                }
                return $n;
        }

        /* Just copy this method from the Element prototype */
        /* TODO PHP: Not that easy to do mixins in PHP! */
        /*
        getElementsByName: { value: Element.prototype.getElementsByName },
        getElementsByTagName: { value: Element.prototype.getElementsByTagName },
        getElementsByTagNameNS: { value: Element.prototype.getElementsByTagNameNS },
        getElementsByClassName: { value: Element.prototype.getElementsByClassName },
        */


        /*********************************************************************
         * HTMLDocument extensions
         *********************************************************************/

        public function location($value = NULL)
        {
                if ($value === NULL) {
                        /* GET */
                        if ($this->_defaultView) {
                                return $this->_defaultView->location();
                        } else {
                                return NULL; // gh #75
                        }
                } else {
                        /* SET */
                        /* NOT YET IMPLEMENTED */
                }
	}

        /**
         * Fetch the <BODY> Element if we are an HTMLDocument
         *
         * @return Element or NULL
         * @spec HTML-LS
         *
         * NOTE
         * In the standard, this is actually a read-write property,
         * but we don't implement that here because it's madness.
         */
        public function body(void): ?Element
        {
                $elt = $this->_documentElement;

                if ($elt === NULL || $elt->_type !== 'html') {
                        return NULL;
                }
                for ($n=$elt->firstChild(); $n!==NULL; $n=$n->nextSibling()) {
                        if ($n->_nodeType === ELEMENT_NODE
                        &&  $n->localName() === 'body'
                        &&  $n->namespaceURI() === NAMESPACE_HTML) {
                                return $n;
                        }
                }
        }

        /**
         * Fetch the <HEAD> Element if we are an HTMLDocument
         *
         * @return Element or NULL
         * @spec HTML-LS
         */
        public function head(void): ?Element
        {
                $elt = $this->_documentElement;

                if ($elt === NULL || $elt->_type !== 'html') {
                        return NULL;
                }
                for ($n=$elt->firstChild(); $n!==NULL; $n=$n->nextSibling()) {
                        if ($n->_nodeType === ELEMENT_NODE
                        &&  $n->localName() === 'head'
                        &&  $n->namespaceURI() === NAMESPACE_HTML) {
                                return $n;
                        }
                }
        }

        /**
         * Get or set the title of the Document.
         *
         * @param string $value
         * @return string that is the Document's title
         * @spec HTML-LS
         *
         * If the <TITLE> was overridden by calling Document::title,
         * it contains that value. Otherwise, it contains the title
         * specified in the markup
         *
         * Follows spec quite closely, see:
         * https://html.spec.whatwg.org/multipage/dom.html#document.title
         */
        public function title(string $value = NULL): ?string
        {
                $title = $this->getElementsByTagName("title")->item(0);

                /* GET */
                if ($value === NULL) {
                        if ($title === NULL) {
                                /* HTML-LS: "" if <TITLE> does not exist. */
                                return "";
                        } else {
                                /* HTML-LS: Trim+collapse ASCII whitespace */
                                return trim(preg_replace('/\s+/',' ', $title->textContent()));
                        }
                /* SET */
                } else {
                        /* HTML-LS: If documentElement is in HTML namespace */
                        if ($this->documentElement()->isHTMLElement()) {
                                $head = $this->head();
                                if ($title === NULL && $head === NULL) {
                                        /* HTML-LS : If title+head NULL, return */
                                        return "";
                                }
                                if ($title !== NULL) {
                                        /* HTML-LS: If <TITLE> !NULL use it */
                                        $elt = $title;
                                } else {
                                        /* HTML-LS: Else create a new one */
                                        $elt = $this->createElement("title");
                                        /* HTML-LS: and append to head */
                                        $head->appendChild($elt);
                                }
                                if ($elt !== NULL) {
                                        $elt->textContent($value);
                                }
                                /*
                                 * TODO: Spec does not mention what if head
                                 * is NULL but Title is not?
                                 */
                        }
                }
        }

        /*********** Utility methods extending normal DOM behavior ***********/

        /* TODO Where does this fit in */
        public function isHTMLDocument(): boolean
        {
                if ($this->_type === 'html') {
                        $elt = $this->documentElement();
                        if ($elt !== NULL && $elt->isHTMLElement()) {
                                return true;
                        }
                }
                return false;
        }

        /**
         * Delegated method called by Node::cloneNode()
         * Performs the shallow clone branch.
         *
         * @return Document with same invocation as $this
         * @spec DOMO
         */
        protected function _subclass_cloneNodeShallow(): Document
        {
                $shallow = new Document($this->isHTMLDocument(), $this->_address);
                $shallow->_mode = $this->_mode;
                $shallow->_contentType = $this->_contentType;
                return $shallow;
        }

        /**
         * Delegated method called by Node::isEqualNode()
         *
         * @param Document $other to compare
         * @return boolean True (two Documents are always equal)
         * @spec DOM-LS
         *
         * NOTE:
         * Any two Documents are shallowly equal, since equality
         * is determined by their children; this will be tested by
         * Node::isEqualNode(), so just return true.
         */
        protected function _subclass_isEqualNode(Document $other = NULL): boolean
        {
                return true;
        }


        /*********************************************************************
         * Internal book-keeping tables:
         *
         * Documents manage 2: the node table, and the id table.
         * <full explanation goes here>
         *
         * Called by Node::__root() and Node::__uproot()
         *********************************************************************/

        protected function __add_to_node_table(Node $node): integer
        {
                $node->__nid = $this->__nid_next++;
                $this->__nid_to_node[$node->__nid] = $node;
        }

        protected function __remove_from_node_table(Node $node): void
        {
                unset($this->__nid_to_node[$node->__nid]);
                $node->__nid = 0;
        }

        protected function __add_to_id_table(string $id, Element $elt): void
        {
                if (!isset($this->__id_to_element[$id])) {
                        $this->__id_to_element[$id] = $elt;
                } else {
                        if (!($this->__id_to_element[$id] instanceof MultiId)) {
                                $this->__id_to_element[$id] = new MultiId(
                                        $this->__id_to_element[$id]
                                );
                        }
                        $this->__id_to_element[$id]->add($elt);
                }
        }

        protected function __remove_from_id_table(string $id, Element $elt): void
        {
                if (!isset($this->__id_to_element[$id])) {
                        /* nothing */
                } else {
                        if ($this->__id_to_element[$id] instanceof MultiId) {
                                $item = $this->__id_to_element[$id];
                                $item->del($elt);

                                // convert back to a single node
                                if ($item->length === 1) {
                                        $this->__id_to_element[$id] = $item->downgrade();
                                }
                        } else {
                                unset($this->__id_to_element[$id]);
                        }
                }
        }


        /*********************************************************************
         * MUTATION STUFF
         * TODO: The mutationHandler checking
         *
         * NOTES:
         * Whenever a document is updated, these mutation functions
         * are called, e.g. Node::_insertOrReplace.
         *
         * To attach a handler to watch how a document is mutated,
         * you set the handler in DOMImplementation. It will be
         * provided with a single argument, an array.
         *
         * See usage below.
         *
         * These mutations have nothing to do with MutationEvents or
         * MutationObserver, which is confusing.
         *********************************************************************/
        /*
         * Implementation-specific function.  Called when a text, comment,
         * or pi value changes.
         */
        public function __mutate_value($node)
        {
                if ($this->__mutation_handler) {
                        $this->__mutation_handler(array(
                                "type" => MUTATE_VALUE,
                                "target" => $node,
                                "data" => $node->data()
                        ));
                }
        }

        /*
         * Invoked when an attribute's value changes. Attr holds the new
         * value.  oldval is the old value.  Attribute mutations can also
         * involve changes to the prefix (and therefore the qualified name)
         */
        public function __mutate_attr($attr, $oldval)
        {
                if ($this->__mutation_handler) {
                        $this->__mutation_handler(array(
                                "type" => MUTATE_ATTR,
                                "target" => $attr->ownerElement(),
                                "attr" => $attr
                        ));
                }
        }

        /* Used by removeAttribute and removeAttributeNS for attributes. */
        public function __mutate_remove_attr($attr)
        {
                if ($this->__mutation_handler) {
                        $this->__mutation_handler(array(
                                "type" => MUTATE_REMOVE_ATTR,
                                "target" => $attr->ownerElement(),
                                "attr" => $attr
                        ));
                }
        }

        /*
         * Called by Node.removeChild, etc. to remove a rooted element from
         * the tree. Only needs to generate a single mutation event when a
         * node is removed, but must recursively mark all descendants as not
         * rooted.
         */
        public function __mutate_remove($node)
        {
                /* Send a single mutation event */
                if ($this->__mutation_handler) {
                        $this->__mutation_handler(array(
                                "type" => MUTATE_REMOVE,
                                "target" => $node->parentNode(),
                                "node" => $node
                        )
                }
        }
        /*
         * Called when a new element becomes rooted.  It must recursively
         * generate mutation events for each of the children, and mark
         * them all as rooted.
         *
         * Called in Node::_insertOrReplace.
         */
        public function __mutate_insert($node)
        {
                /* Send a single mutation event */
                if ($this->__mutation_handler) {
                        $this->__mutation_handler(array(
                                "type" => MUTATE_INSERT,
                                "target" => $node->parentNode(),
                                "node" => $node
                        ));
                }
        }

        /*
         * Called when a rooted element is moved within the document
         */
        public function __mutate_move($node)
        {
                if ($this->__mutation_handler) {
                        $this->__mutation_handler(array(
                                "type" => MUTATE_MOVE,
                                "target" => $node
                        ));
                }
        }

        public function _resolve($href)
        {
                //XXX: Cache the URL
                return new URL($this->_documentBaseURL)->resolve($href);
        }

        public function _documentBaseURL()
        {
                // XXX: This is not implemented correctly yet
                $url = $this->_address;
                if ($url === "about:blank") {
                        $url = "/";
                }

                $base = $this->querySelector("base[href]");

                if ($base) {
                        return new URL($url)->resolve($base->getAttribute("href"));
                }
                return $url;

                /*
                 * The document base URL of a Document object is the
                 * absolute URL obtained by running these substeps:
                 *
                 * Let fallback base url be the document's address.
                 *
                 * If fallback base url is about:blank, and the
                 * Document's browsing context has a creator browsing
                 * context, then let fallback base url be the document
                 * base URL of the creator Document instead.
                 *
                 * If the Document is an iframe srcdoc document, then
                 * let fallback base url be the document base URL of
                 * the Document's browsing context's browsing context
                 * container's Document instead.
                 *
                 * If there is no base element that has an href
                 * attribute, then the document base URL is fallback
                 * base url; abort these steps. Otherwise, let url be
                 * the value of the href attribute of the first such
                 * element.
                 *
                 * Resolve url relative to fallback base url (thus,
                 * the base href attribute isn't affected by xml:base
                 * attributes).
                 *
                 * The document base URL is the result of the previous
                 * step if it was successful; otherwise it is fallback
                 * base url.
                 */
        }

        public function querySelector($selector)
        {
                /* TODO: select() provided by some mechanism */
                return select($selector, $this)[0];
        }

        public function querySelectorAll($selector)
        {
                $nodes = select($selector, $this);
                if ($nodes->item) {
                        return $nodes;
                } else {
                        return new NodeList($nodes);
                }
        }
}

