<?php
/******************************************************************************
 * Document.php
 * ------------
 * Implements a Document. It is a somewhat complicated class,
 * having a number of internal bookkeeping needs.
 ******************************************************************************/
namespace domo;

require_once('DOMImplementation.php');
require_once('Node.php');
require_once('NodeList.php');
require_once('Element.php');
require_once('Comment.php');
require_once('Text.php');
require_once('ProcessingInstruction.php');
require_once('DocumentType.php');
require_once('utilities.php');

/*
 * DOM-LS specifies that in the
 * event that two Elements have
 * the same 'id' attribute value,
 * the first one, in document order,
 * shall be returned from getElementById.
 *
 * This data structure makes that
 * as performant as possible, by:
 *
 * 1. Caching the first element in the list, in document order
 * It is updated on move because a move is treated as a
 * removal followed by an insertion, and those two operations
 * will update this table.
 *
 * 2. Elements are looked up by an integer index set when they
 * are adopted by Document. This index gives a canonical
 * integer representation of an Element, so we can operate
 * on integers instead of Elements.
 */
class MultiId
{
        public $table = array();
        public $length = 0;
        /*
         * The first element,
         * in document order.
         * NULL indicates the
         * cache is not set
         * and the first
         * element must be
         * re-computed.
         */
        public $first = NULL;

        public function __construct(Node $node)
        {
                $this->table[$node->__document_index] = $node;
                $this->length = 1;
                $this->first = NULL;
        }

        /*
         * Add a Node to array
         * in O(1) time by using
         * Node::$__document_index
         * as the array index.
         */
        public function add(Node $node)
        {
                if (!isset($this->table[$node->__document_index])) {
                        $this->table[$node->__document_index] = $node;
                        $this->length++;
                        $this->first = NULL; /* invalidate cache */
                }
        }

        /*
         * Remove a Node from
         * the array in O(1)
         * time by using
         * Node::$__document_index
         * to perform the lookup.
         */
        public function del(Node $node)
        {
                if ($this->table[$node->__document_index]) {
                        unset($this->table[$node->__document_index]);
                        $this->length--;
                        $this->first = NULL; /* invalidate cache */
                }
        }

        /*
         * Retreive that Node
         * from the array which
         * appears first in
         * document order in
         * the associated document.
         *
         * Cache the value for
         * repeated lookups.
         *
         * The cache is invalidated
         * each time the array
         * is modified. The list
         * is modified when a Node
         * is inserted or removed
         * from a Document, or when
         * the 'id' attribute value
         * of a Node is changed.
         */
        public function get_first()
        {
                if ($this->first === NULL) {
                        /*
                         * No item has been cached.
                         * Well, let's find it then.
                         */
                        foreach ($this->table as $document_index => $node) {
                                if ($this->first->compareDocumentPosition($node) & DOCUMENT_POSITION_PRECEDING) {
                                        $this->first = $node;
                                }
                                /* TODO: What about the old NULLity stuff?? */
                                //if ($this->first === NULL || $this->first->compareDocumentPosition($node) & DOCUMENT_POSITION_PRECEDING) {
                                        //$this->first = $node;
                                //}
                        }
                }
                return $this->first;
        }

        /*
         * If there is only one node left, return it. Otherwise return "this".
         */
        public function downgrade()
        {
                if ($this->length === 1) {
                        foreach ($this->table as $document_index => $node) {
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
         * Properties that are for internal use by this library
         **********************************************************************/
        /*
         * DEVELOPERS NOTE
         * Certain APIs are only defined
         * when the Document contains HTML
         * (rather than XML). Rather than
         * implement a separate HTMLDocument
         * class, we simply store the type
         * of Document in a variable.
         *
         * Outside callers will use the
         * isHTMLDocument() method, which
         * makes use of this value.
         */
        protected $__type;

        /*
         * DEVELOPERS NOTE:
         * Used to assign the document
         * index to Nodes on ADOPTION.
         */
        protected $__document_index_next = 2;

        /*
         * DEVELOPERS NOTE:
         * Document's aren't going to
         * adopt themselves, so we set
         * this to a default of 1.
         */
        protected $__document_index = 1;

        /*
         * Element nodes having an 'id'
         * attribute are stored in this
         * table, indexed by their 'id'
         * value.
         *
         * This is how getElementById
         * performs its fast lookup.
         *
         * The table must be mutated on:
         *      - Element insertion
         *      - Element removal
         *      - mutation of 'id' attribute
         *        on an inserted Element.
         */
        private $__id_to_element = array();

        /**********************************************************************
         * Properties that appear in DOM-LS
         **********************************************************************/

        /*
         * Part of Node parent class
         */
        public $_nodeType = DOCUMENT_NODE;
        public $_nodeName = '#document';
        public $_ownerDocument = NULL;
        public $_nodeValue = NULL;

        /*
         * Part of Document class
         */
        public const _characterSet = 'UTF-8';
        public $_encoding = 'UTF-8';
        public $_type = 'xml';
        public $_contentType = 'application/xml';
        public $_URL = 'about:blank';
        public $_origin = NULL;
        public $_compatMode = 'no-quirks';

        /*
         * ANNOYING LIVE REFERENCES
         *
         * The below are slightly annoying
         * because we must keep them updated
         * whenever there is mutation to the
         * children of the Document.
         */

        /*
         * Reference to the first
         * DocumentType child, in
         * document order. NULL if
         * no such child exists.
         */
        public $_doctype = NULL;

        /*
         * Reference to the first
         * Element child, in
         * document order. NULL if
         * no such child exists.
         */
        public $_documentElement = NULL;

        /*
         * Called when a child is
         * inserted or removed from
         * the document. Keeps the
         * above references live.
         */
        private function __rereference_doctype_and_documentElement(): void
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

        /* TODO: These three amigos. */
        public $_implementation;
        public $_readyState;
        public $__mutation_handler = NULL;


        public function __construct(string $type="xml", ?string $url=NULL)
        {
                parent::__construct();

                /******** DOM-LS ********/

                /* Having an HTML Document affects some APIs */
                if ($type === 'html') {
                        $this->_contentType = 'text/html';
                        $this->__type = 'html';
                }

                /* DOM-LS: used by the documentURI and URL method */
                if ($url !== NULL) {
                        $this->_URL = $url;
                }

                /* DOM-LS: DOMImplementation associated with document */
                $this->_implementation = new DOMImplementation($this);

                /******** JUNK ********/

                $this->_readyState = "loading";

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

        /*********************************************************************
         * Accessors for read-only properties defined in Document
         *********************************************************************/
        public function characterSet(): string
        {
                return $this->_characterSet;
        }
        public function charset(): string
        {
                return $this->_characterSet; /* historical alias */
        }
        public function inputEncoding(): string
        {
                return $this->_characterSet; /* historical alias */
        }
        public function implementation(): DOMImplementation
        {
                return $this->_implementation;
        }
        public function documentURI()
        {
                return $this->_URL;
        }
        public function URL() : string
        {
                return $this->_URL; /* Alias for HTMLDocuments */
        }
        public function compatMode()
        {
                return $this->_compatMode === "quirks" ? "BackCompat" : "CSS1Compat";
        }
        public function contentType(): ?string
        {
                return $this->_contentType;
        }
        public function doctype(): ?DocumentType
        {
                return $this->_doctype;
        }
        public function documentElement(): ?Element
        {
                return $this->_documentElement;
        }
        public function textContent(?string $value = NULL)
        {
                /* HTML-LS: no-op */
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
                /* TODO PORT: Wait, is this a bug? Should it be !== -1, or === -1 ? */
                if (!\domo\whatwg\is_valid_xml_name($target) || strpos($data, '?'.'>') !== false) {
                        \domo\error('InvalidCharacterError');
                }
                return new ProcessingInstruction($this, $target, $data);
        }

        public function createAttribute($localName)
        {
                $localName = strval($localName);

                if (!\domo\whatwg\is_valid_xml_name($localName)) {
                        \domo\error('InvalidCharacterError');
                }
                if ($this->isHTMLDocument()) {
                        $localName = \domo\to_ascii_lowercase($localName);
                }
                return new Attr(NULL, $localName, NULL, NULL, '');
        }

        public function createAttributeNS(?string $ns, string $qname)
        {
                if ($ns === '') {
                        $ns = NULL; /* spec */
                }

                $lname = NULL;
                $prefix = NULL;

                \domo\whatwg\validate_and_extract($ns, $qname, $prefix, $lname);

                return new Attr(NULL, $lname, $prefix, $ns, '');
        }

        public function createElement($lname)
        {
                $lname = strval($lname);

                if (!\domo\whatwg\is_valid_xml_name($lname)) {
                        error("InvalidCharacterError");
                }

                /*
                 * Per spec, namespace should be HTML namespace if
                 * "context object is an HTML document or context
                 * object's content type is "application/xhtml+xml",
                 * and null otherwise.
                 */
                return new Element($this, $lname, NULL, NULL);

                //if ($this->_contentType === 'text/html') {
                        //if (!ctype_lower($lname)) {
                                //$lname = \domo\ascii_to_lowercase($lname);
                        //}

                        //[> TODO STUB <]
                        ////return domo\html\createElement($this, $lname, NULL);

                //} else if ($this->_contentType === 'application/xhtml+xml') {
                        //[> TODO STUB <]
                        ////return domo\html\createElement($this, $lname, NULL);
                //} else {
                        //return new Element($this, $lname, NULL, NULL);
                //}
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

                \domo\whatwg\validate_and_extract($ns, $qname, $prefix, $lname);

                return $this->_createElementNS($lname, $ns, $prefix);
        }

        /*
         * This is used directly by HTML parser, which allows it to create
         * elements with localNames containing ':' and non-default namespaces
         */
        public function _createElementNS($lname, $ns, $prefix)
        {
                if ($ns === NAMESPACE_HTML) {
                        /* TODO STUB */
                        //return domo\html\createElement($this, $lname, $prefix);
                } else if ($ns === NAMESPACE_SVG) {
                        /* TODO STUB */
                        //return svg\createElement($this, $lname, $prefix);
                } else {
                        return new Element($this, $lname, $ns, $prefix);
                }
        }

        /*********************************************************************
         * MUTATION
         *********************************************************************/

        /**
         * Adopt the subtree rooted at Node
         * into this Document.
         *
         * This means setting ownerDocument
         * of each node in the subtree to
         * point to $this.
         *
         * No insertion is performed, but
         * if Node is inserted into another
         * Document, it will be removed.
         */
        public function adoptNode(Node $node): Node
        {
                if ($node->_nodeType === DOCUMENT_NODE) {
                        /*
                         * A Document cannot adopt
                         * another Document. Throw
                         * a "NotSupported" exception.
                         */
                        \domo\error("NotSupported");
                }
                if ($node->_nodeType === ATTRIBUTE_NODE) {
                        /*
                         * Attributes do not have
                         * an ownerDocument, so
                         * do nothing.
                         */
                        return $node;
                }
                if ($node->parentNode()) {
                        /*
                         * If the Node is currently
                         * inserted in some Document,
                         * remove it.
                         *
                         * TODO:
                         * Why is this not using $node->__is_rooted()?
                         * Is this diagnostic for rooted-ness? Why
                         * doesn't __is_rooted() just do this?
                         */
                        $node->parentNode()->removeChild($node);
                }
                if ($node->_ownerDocument !== $this) {
                        /*
                         * If the Node is not
                         * currently connected
                         * to this Document,
                         * then recursively set
                         * the ownerDocument.
                         *
                         * (The recursion skips
                         * the above checks because
                         * they don't make sense.)
                         */
                        $node->__set_owner($this);
                }

                /* DOM-LS requires this return $node */
                return $node;
        }

        /**
         * Clone and then adopt either
         * $node or, if $deep === true,
         * the entire subtree rooted at
         * $node, into the Document.
         *
         * By default, only $node will
         * be cloned.
         */
        public function importNode(Node $node, bool $deep=false): Node
        {
                return $this->adoptNode($node->cloneNode($deep));
        }

        /**
         * The following three methods
         * are a simple extension of
         * the Node methods, with an
         * added call to update the
         * doctype and documentElement
         * references that are specific
         * to the Document interface.
         *
         * NOTE
         * appendChild is not extended,
         * because it calls insertBefore.
         */
        public function insertBefore(Node $node, ?Node $refChild): Node
        {
                $ret = parent::insertBefore($node, $refChild);
                $this->__rereference_doctype_and_documentElement();
                return $ret;
        }
        public function replaceChild(Node $node, ?Node $child): Node
        {
                $ret = parent::replaceChild($node, $child);
                $this->__rereference_doctype_and_documentElement();
                return $ret;
        }
        public function removeChild(ChildNode $child): ?Node
        {
                $ret = parent::removeChild($child);
                $this->__rereference_doctype_and_documentElement();
                return $ret;
        }

        /**
         * Clone this Document, import nodes, and call __update_document_state
         *
         * @param bool $deep - if true, clone entire subtree
         * @return Clone of $this.
         * @extends Node::cloneNode()
         * @spec DOM-LS
         *
         * NOTE:
         * 1. What a tangled web we weave
         * 2. With Document nodes, we need to take the additional step of
         *    calling importNode() to bring copies of child nodes into this
         *    document.
         * 3. We also need to call _updateDocTypeElement()
         */
        public function cloneNode(bool $deep = false): ?Node
        {
                /* Make a shallow clone  */
                $clone = parent::cloneNode(false);

                if ($deep === false) {
                        /* Return shallow clone */
                        $clone->__rereference_doctype_and_documentElement();
                        return $clone;
                }

                /* Clone children too */
                for ($n=$this->firstChild(); $n!==NULL; $n=$n->nextSibling()) {
                        $clone->appendChild($clone->importNode($n, true));
                }

                $clone->__rereference_doctype_and_documentElement();
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

        /*********** Utility methods extending normal DOM behavior ***********/

        /* TODO Where does this fit in */
        public function isHTMLDocument(): bool
        {
                if ($this->__type === 'html') {
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
        protected function _subclass_cloneNodeShallow(): Node
        {
                $shallow = new Document($this->isHTMLDocument(), $this->_address);
                $shallow->_mode = $this->_mode;
                $shallow->_contentType = $this->_contentType;
                return $shallow;
        }

        /**
         * Delegated method called by Node::isEqualNode()
         *
         * @param Node $other to compare
         * @return bool True (two Documents are always equal)
         * @spec DOM-LS
         *
         * NOTE:
         * Any two Documents are shallowly equal, since equality
         * is determined by their children; this will be tested by
         * Node::isEqualNode(), so just return true.
         */
        protected function _subclass_isEqualNode(Node $other = NULL): bool
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
         *
         * See, we are adding, and removing, but never using...?
         *********************************************************************/

        public function __add_to_id_table(string $id, Element $elt): void
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

        public function __remove_from_id_table(string $id, Element $elt): void
        {
                if (isset($this->__id_to_element[$id])) {
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
                        ));
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
}

