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
 *
 * CHANGED:
 * Document second argument '$address' and $_address to $url and $_url,
 * in line with the spec.
 *****************************************************************************/

require_once("Node.php");
require_once("NodeList.php");
require_once("Element.php");
require_once("Text.php");
require_once("Comment.php");
require_once("Event.php");
require_once("DocumentFragment.php");
require_once("ProcessingInstruction.php");
require_once("DOMImplementation.php");
require_once("TreeWalker.php");
require_once("NodeIterator.php");
require_once("NodeFilter.php");
require_once("URL.php");
require_once("utils.php");

//var Node = require('./Node');
//var NodeList = require('./NodeList');
//var ContainerNode = require('./ContainerNode');
//var Element = require('./Element');
//var Text = require('./Text');
//var Comment = require('./Comment');
//var Event = require('./Event');
//var DocumentFragment = require('./DocumentFragment');
//var ProcessingInstruction = require('./ProcessingInstruction');
//var DOMImplementation = require('./DOMImplementation');
//var TreeWalker = require('./TreeWalker');
//var NodeIterator = require('./NodeIterator');
//var NodeFilter = require('./NodeFilter');
//var URL = require('./URL');
//var select = require('./select');
//var events = require('./events');
//var xml = require('./xmlnames');
//var html = require('./htmlelts');
//var svg = require('./svg');
//var utils = require('./utils');
//var MUTATE = require('./MutationConstants');
//var NAMESPACE = utils.NAMESPACE;
//var isApiWritable = require("./config").isApiWritable;


/*
 * Map from lowercase event category names (used as arguments to
 * createEvent()) to the property name in the impl object of the
 * event constructor.
 */
$supportedEvents = array(
        "event" => "Event",
        "customevent" => "CustomEvent",
        "uievent" => "UIEvent",
        "mouseevent" => "MouseEvent"
);

// Certain arguments to document.createEvent() must be treated specially
$replacementEvent = array(
        "events" => "event",
        "htmlevents" => "event",
        "mouseevents" => "mouseevent",
        "mutationevents" => "mutationevent",
        "uievents" => "uievent"
);


/** @spec https://dom.spec.whatwg.org/#validate-and-extract */
function validateAndExtract($namespace, $qualifiedName)
{
        $prefix;
        $localName;
        $pos;

        if ($namespace === "") {
                $namespace = NULL;
        }

        /*
         * See https://github.com/whatwg/dom/issues/671
         * and https://github.com/whatwg/dom/issues/319
         */
        /* TODO: These namespaces, particularly xml */
        if (!domo\xml\isValidQName($qualifiedName)) {
                domo\utils\InvalidCharacterError();
        }

        $prefix = NULL;
        $localName = $qualifiedName;

        $pos = strpos($qualifiedName, ":");

        if ($pos >= 0) {
                $prefix = substr($qualifiedName, 0, $pos);
                $localName = substr($qualifiedName, $pos+1);
        }

        if ($prefix !== NULL && $namespace === NULL) {
                domo\utils\NamespaceError();
        }

        if ($prefix === "xml" && $namespace !== domo\utils\NAMESPACE_XML) {
                domo\utils\NamespaceError();
        }

        if (($prefix === "xmlns" || $qualifiedName === "xmlns") && $namespace !== domo\utils\NAMESPACE_XMLNS) {
                domo\utils\NamespaceError();
        }

        if ($namespace === domo\utils\NAMESPACE_XMLNS && !($prefix === "xmlns" || $qualifiedName === "xmlns")) {
                \domo\utils\NamespaceError();
        }

        return array(
                "namespace" => $namespace,
                "prefix" => $prefix,
                "localName" => $localName
        );
}



/**
 * The Document class. Note that there is another class called
 * HTMLDocument with an extended interface, only for Documents
 * that contain HTML. Rather than use a separate class, we load
 * it onto the Document class and switch behavior using a flag
 * in the constructor. Not sure about that, but it probably
 * things easier.
 */
class Document extends Node
{
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
        private $_nid_table = array();

        /*
         * For Element nodes having an actual 'id' attribute, we
         * also store a reference to the node under its 'id' in
         * this table, and use it to implement Document->getElementById();
         *
         * We must check to see if we must update this table every time:
         *      - a node is rooted / uprooted
         *      - a rooted node has an attribute added / removed / changed
         */
        private $_id_table = array();


        public function __construct(boolean $isHTMLDocument=false, string $url="about:blank")
        {
                /***** Spec-compliant defaults *****/

                /* DOM4-LS (access with Node::nodeType()) */
                $this->_nodeType = Node\DOCUMENT_NODE;

                /* DOM4-LS (access with Node::nodeName()) */
                $this->_nodeName = "#document";

                /* DOM4-LS: For Document nodes, this is always NULL. */
                $this->_ownerDocument = NULL;

                /* DOM4-LS: DocumentType for DTD of Document, or NULL if none exists */
                $this->_doctype = NULL;

                /* DOM4-LS: Element whose parent is $this, or NULL if none exists */
                $this->_documentElement = NULL;

                /* Whether this should implement HTMLDocument or not */
                $this->_isHTMLDocument = $isHTMLDocument;

                /* (HTML only) window associated */
                $this->_defaultView = NULL;

                /* DOM4-LS: MIME content type string */
                if ($this->_isHTMLDocument) {
                        $this->_contentType = "text/html";
                } else {
                        $this->_contentType = "application/xml";
                }

                /* DOM4-LS: used by the documentURI and URL method */
                $this->_URL = $url;

                /* DOM4-LS: DOMImplementation associated with document */
                $this->_implementation = new domo\w3c\DOMImplementation($this);

                /***** Internal *****/

                /* Documents are rooted by definition and get _nid = 1 */
                $this->_nid = 1;
                $this->_nid_next = 2;
                $this->_nid_table[0] = NULL;
                $this->_nid_table[1] = $this;


                /***** JUNK *****/

                $this->readyState = "loading";

                /*
                 * This property holds a monotonically increasing value
                 * akin to a timestamp used to record the last modification
                 * time of nodes and their subtrees. See the lastModTime
                 * attribute and modify() method of the Node class. And see
                 * FilteredElementList for an example of the use of
                 * lastModTime
                 */
                $this->modclock = 0;

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
        public function nodeName(): string
        {
                return "#document";
        }

        public function characterSet(): string
        {
                return "UTF-8";
        }
        public function contentType(): ?string
        {
                return $this->_contentType;
        }
        public function implementation(): DOMImplementation
        {
                return $this->_implementation;
        }

        public function URL() : string
        {
                return $this->_url;
        }

        public function doctype(): ?DocumentType
        {
                return $this->_doctype;
        }

        public function documentElement(): ?Element
        {
                return $this->_documentElement;
        }

        public function ownerDocument(): ?Document
        {
                return $this->_ownerDocument;
        }

        public function nodeValue($value=NULL)
        {
                /* Not implemented ? */
                return NULL;
        }

        public function compatMode()
        {
                /* The _quirks property is set by the HTML parser */
                return $this->_quirks ? "BackCompat" : "CSS1Compat";
        }

        public function origin()
        {
                return NULL;
        }

        /* DOM4-LS: Read-only. Same as URL() but that's for HTMLDocuments */
        public function documentURI()
        {
                return $this->_URL;
        }

        /*********************************************************************
         * Access to particular nodes
         *********************************************************************/
        public function scrollingElement() ?Element
        {
                if ($this->_quirks) {
                        return $this->body();
                } else {
                        return $this->documentElement();
                }
        }

        /* Return the first <BODY> child of the Document. */
        public function body(Element $value = NULL) ?Element
        {
                if ($value === NULL) {
                        /* GET */
                        /* TODO: Deal with this */
                        return namedHTMLChild($this->documentElement(), "body");
                } else {
                        /* SET */
                        domo\utils\nyi();
                }
        }

        /* DOM4-LS: RO: Return the first <HEAD> child of the Document. */
        public function head(): ?Element
        {
                return namedHTMLChild($this->documentElement, "head");
        }

        /* DOM4-LS: RO: List of <img> tags */
        public function images(): ?HTMLCollection
        {
                /* NYI */
        }
        /* DOM4-LS: RO: List of <embed> tags */
        public function embeds(): ?HTMLCollection
        {
                /* NYI */
        }
        /* DOM4-LS: RO: List of plugins */
        public function plugins(): ?HTMLCollection
        {
                /* NYI */
        }
        /* DOM4-LS: RO: List of <area> and <a> tags with href attribs */
        public function links(): ?HTMLCollection
        {
                /* NYI */
        }
        /* DOM4-LS: RO: List of <form> elements */
        public function forms(): ?HTMLCollection
        {
                /* NYI */
        }
        /* DOM4-LS: RO: List of <script> elements */
        public function scripts(): ?HTMLCollection
        {
                /* NYI */
        }

        /*********************************************************************
         * CRUD stuff
         *********************************************************************/

        public function createTextNode($data /*DOMString*/)
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
                if (!domo\xml\isValidName($target) || strpos($data, "?".">") !== false) {
                        domo\utils\InvalidCharacterError();
                }
                return new ProcessingInstruction($this, $target, $data);
        }

        public function createAttribute($localName)
        {
                $localName = strval($localName);

                if (!domo\xml\isValidName($localName)) {
                        domo\utils\InvalidCharacterError();
                }
                if ($this->isHTMLDocument) {
                        $localName = domo\utils\toASCIILowerCase($localName);
                }
                return new Element->_Attr(null, $localName, null, null, "");
        }

        public function createAttributeNS($namespace, $qualifiedName)
        {
                /* Convert parameter types according to WebIDL */
                if ($namespace === NULL || $namespace === "") {
                        $namespace = NULL;
                } else {
                        $namespace = strval($namespace);
                }

                $qualifiedName = strval($qualifiedName);

                $ve = validateAndExtract($namespace, $qualifiedName);

                return new Element->_Attr(NULL, $ve["localName"], $ve["prefix"], $ve["namespace"], "");
        }

        public function createElement($localName)
        {
                $localName = strval($localName);

                if (!domo\xml\isValidName($localName)) {
                        domo\utils\InvalidCharacterError();
                }

                /*
                 * Per spec, namespace should be HTML namespace if
                 * "context object is an HTML document or context
                 * object's content type is "application/xhtml+xml",
                 * and null otherwise.
                 */
                if ($this->isHTMLDocument) {
                        if (!ctype_lower($localName)) {
                                $localName = domo\utils\toASCIILowerCase($localName);
                        }

                        return domo\html\createElement($this, $localName, NULL);

                } else if ($this->contentType === "application/xhtml+xml") {
                        return domo\html\createElement($this, $localName, NULL);
                } else {
                        return new Element($this, $localName, NULL, NULL);
                }
        }
        // }, writable: isApiWritable }, PORT TODO what is this junk

        public function createElementNS($namespace, $qualifiedName)
        {
                /* Convert parameter types according to WebIDL */
                if ($namespace === NULL || $namespace === "") {
                        $namespace = NULL;
                } else {
                        $namespace = strval($namespace);
                }

                $qualifiedName = strval($qualifiedName);

                $ve = validateAndExtract($namespace, $qualifiedName);

                return $this->_createElementNS($ve["localName"], $ve["namespace"], $ve["prefix"]);
        }
        // }, writable: isApiWritable }, PORT TODO what is this junk

        /*
         * This is used directly by HTML parser, which allows it to create
         * elements with localNames containing ':' and non-default namespaces
         */
        public function _createElementNS($localName, $namespace, $prefix)
        {
                if ($namespace === domo\util\NAMESPACE_HTML) {
                        return domo\html\createElement($this, $localName, $prefix);
                } else if ($namespace === domo\util\NAMESPACE_SVG) {
                        return svg\createElement($this, $localName, $prefix);
                } else {
                        return new Element($this, $localName, $namespace, $prefix);
                }
        }

        public function adoptNode($node)
        {
                if ($node->_nodeType === domo\Node\DOCUMENT_NODE) {
                        domo\utils\NotSupportedError();
                }
                if ($node->_nodeType === domo\Node\ATTRIBUTE_NODE) {
                        return $node;
                }
                if ($node->parentNode()) {
                        $node->parentNode()->removeChild($node);
                }
                if ($node->_ownerDocument !== $this) {
                        Document::_helper_recursivelySetOwner($node, $this);
                }

                return $node;
        }

        public function importNode($node, $deep)
        {
                return $this->adoptNode($node->cloneNode($deep));
        }


        /*********************************************************************
         * Extended Node methods
         *********************************************************************/
        /*
         * In PHP inheritance, you can call the method you're
         * overriding using parent::
         *
         * Here, parent is the Node class.
         */

        /*
         * Maintain the documentElement and
         * doctype properties of the document.  Each of the following
         * methods chains to the Node implementation of the method
         * to do the actual inserting, removal or replacement.
         *
         * TODO PORT: Is there some reason they aren't just inherited?
         * Well, we're inheriting them now.
         *
         * Also, what in the *world* is this doing?
         */
        protected function _updateDocTypeElement()
        {
                $this->_doctype = NULL;
                $this->_documentElement = NULL;

                for ($n=$this->firstChild(); $n!==NULL; $n=$n->nextSibling()) {
                        if ($n->_nodeType === domo\util\DOCUMENT_TYPE_NODE) {
                                $this->_doctype = $n;
                        } else if ($n->_nodeType === domo\util\ELEMENT_NODE) {
                                $this->_documentElement = $n;
                        }
                }
        }

        public function insertBefore($child, $refChild)
        {
                parent::insertBefore($child, $refChild);
                $this->_updateDocTypeElement();
                return $child;
        }

        public function replaceChild($node, $child)
        {
                parent::replaceChild($node, $child);
                $this->_updateDocTypeElement();
                return $child;
        }

        public function removeChild($child)
        {
                parent::removeChild($child);
                $this->_updateDocTypeElement();
                return $child;
        }

        /**
         * cloneNode()
         * ```````````
         * Clone this Document, import nodes, and call _updateDocTypeElement
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
        public function cloneNode(boolean $deep = false)
        {
                /*
                 * TODO PORT: Is this part of the standard?
                 */

                /* Make a shallow clone  */
                $clone = parent::cloneNode(false);

                /* TODO TODO TODO: _updateDocTypeElement and _appendChild */

                if ($deep === false) {
                        /* Return shallow clone */
                        $clone->_updateDocTypeElement();
                        return $clone;
                }

                /* Clone children too */
                for ($n=$this->firstChild(); $n!==NULL; $n=$n->nextSibling()) {
                        $clone->_appendChild($clone->importNode($n, true));
                }

                $clone->_updateDocTypeElement();
                return $clone;
        }

        /*********************************************************************
         * Query methods
         *********************************************************************/

        public function getElementById($id)
        {
                $n = $this->byId[$id];

                if (!$n) {
                        return NULL;
                }

                if ($n instanceof MultiId) {
                        // there was more than one element with this id
                        return $n->getFirst();
                }
                return $n;
        }

        protected function _hasMultipleElementsWithId($id)
        {
                // Used internally by querySelectorAll optimization
                return ($this->byId[$id] instanceof MultiId);
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

        /* Reference to Window object, if any associated, else NULL */
        public function defaultView() ?Window
        {
                return $this->_defaultView;
        }

        public function URL() ?string
        {
                return $this->_URL;
        }

        public function domain()
        {
                /* NYI */
        }

        public function referrer()
        {
                /* NYI */
        }

        public function readyState()
        {
                /* NYI */
        }

        public function cookie()
        {
                /* NYI */
        }

        public function lastModified()
        {
                /* NYI */
        }

        public function designMode()
        {
                /* NYI */
        }

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
                        domo\utils\nyi();
                }
	}

        /* Get or set the title of a document */
        /* Returns a string containing the document's title.
         * If the title was overridden by setting document.title,
         * it contains that value. Otherwise, it contains the title
         * specified in the markup (see the Notes below).
         *
         * newTitle is the new title of the document. The assignment
         * affects the return value of document.title, the title displayed
         * for the document (e.g. in the titlebar of the window or tab),
         * and it also affects the DOM of the document (e.g. the content
         * of the <title> element in an HTML document).
         *
         * Follows spec quite closely, see:
         * https://html.spec.whatwg.org/multipage/dom.html#document.title
         */
        public function title(string $value = NULL)
        {
                /*
                 * Fetch the first <TITLE> element of the Document
                 * in tree order. NULL if none exists.
                 */
                $title = $this->getElementsByTagName("title")->item(0);

                /* GET */
                if ($value === NULL) {
                        if ($title === NULL) {
                                /* HTML5: empty string if title element null. */
                                return "";
                        } else {
                                /* HTML5: Strip and collapse ASCII whitespace */
                                $value = $title->textContent();
                                return trim(preg_replace('/\s+/',' ', $value));
                        }
                /* SET */
                } else {
                        /* HTML5: If documentElement is in HTML namespace */
                        if ($this->documentElement()->isHTMLElement()) {
                                $head = $this->head();
                                if ($title === NULL && $head === NULL) {
                                        /* HTML5: If title+head NULL, return */
                                        return;
                                }
                                if ($title !== NULL) {
                                        /* HTML5: If title !NULL use it */
                                        $elt = $title;
                                } else {
                                        /* HTML5: Else create a new one */
                                        $elt = $this->createElement("title");
                                        /* HTML5: and append to head */
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

        public function dir(string $value=NULL)
        {
                $elt = $this->documentElement();
                if ($elt && $elt->tagName() === "HTML") {
                        if ($value === NULL) {
                                return $elt["dir"];
                        } else {
                                $elt["dir"] = $value;
                        }
                }
        }

        public function activeElement()
        {
                return NULL;
        }

        public function innerHTML($value = NULL)
        {
                if ($value === NULL) {
                        /* GET */
                        return $this->serialize();
                } else {
                        /* SET */
                        domo\utils\nyi();
                }
        }

        /* TODO PORT: Note this is the same as innerHTML b/c we're Document */
        /* DOMO EXTENSION */
        public function outerHTML($value = NULL)
        {
                if ($value === NULL) {
                        /* GET */
                        return $this->serialize();
                } else {
                        /* SET */
                        domo\utils\nyi();
                }
        }

        public function write(/* DOMStrings */)
        {
                if (!$this->isHTMLDocument) {
                        domo\utils\InvalidStateError();
                }

                // XXX: still have to implement the ignore part
                if (!$this->_parser /* && this._ignore_destructive_writes > 0 */ ) {
                        return;
                }

                if (!$this->_parser) {
                        // XXX call document.open, etc.
                }

                /* TODO: Double-check that this is */
                $arguments = func_get_args();
                $arguments = implode("", $arguments);

                /*
                 * If the Document object's reload override flag is set,
                 * then append the string consisting of the concatenation
                 * of all the arguments to the method to the Document's
                 * reload override buffer.
                 * XXX: don't know what this is about.  Still have to do it
                 *
                 * If there is no pending parsing-blocking script, have the
                 * tokenizer process the characters that were inserted, one
                 * at a time, processing resulting tokens as they are emitted,
                 * and stopping when the tokenizer reaches the insertion point
                 * or when the processing of the tokenizer is aborted by the
                 * tree construction stage (this can happen if a script end
                 * tag token is emitted by the tokenizer).
                 *
                 * XXX: still have to do the above. Sounds as if we don't
                 * always call parse() here.  If we're blocked, then we just
                 * insert the text into the stream but don't parse it
                 * reentrantly...
                 */

                /* Invoke the parser reentrantly */
                $this->_parser->parse($arguments);
        }

        public function writeln(/* DOMStrings */)
        {
                $arguments = func_get_args();
                $arguments = implode("", $arguments);

                $this->write($arguments . "\n");
        }

        public function open()
        {
                /* TODO PORT: Eh? */
                $this->documentElement = NULL;
        }

        public function close()
        {
                $this->readyState = "interactive";
                /* These are inherited through Node from EventTarget */
                $this->_dispatchEvent(new Event("readystatechange"), true);
                $this->_dispatchEvent(new Event("DOMContentLoaded"), true);
                $this->readyState = "complete";
                $this->_dispatchEvent(new Event("readystatechange"), true);

                if ($this->defaultView) {
                        $this->defaultView->_dispatchEvent(new Event("load"), true);
                }
        }

        /*********** Utility methods extending normal DOM behavior ***********/

        public function isHTMLDocument(): boolean
        {
                if ($this->_isHTMLDocument === true) {
                        $elt = $this->documentElement();
                        if ($elt !== NULL && $elt->isHTMLElement()) {
                                return true;
                        }
                }
                return false;
        }

        /**
         * _subclass_cloneNodeShallow()
         * ````````````````````````````
         * Delegated method called by Node::cloneNode()
         * Performs the shallow clone branch.
         *
         * Returns: new Document with same invocation as $this
         * Part_Of: DOMO1
         */
        protected function _subclass_cloneNodeShallow(): Document
        {
                $shallow = new Document($this->isHTMLDocument, $this->_address);
                $shallow->_quirks = $this->_quirks;
                $shallow->_contentType = $this->_contentType;
                return $shallow;
        }

        /**
         * _subclass_isEqualNode()
         * ```````````````````````
         * Delegated method called by Node::isEqualNode()
         *
         * @other: The other Document to compare
         * Return: True
         * PartOf: DOMO1
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
           TODO TODO TODO TODO TODO TODO TODO TODO TODO TODO TODO TODO TODO
           Simplify all this state handling with a simple class
           or something and two methods called _internal_state_add and
           _internal_state_remove or something to handle both id and nid
           table tracking.
           TODO TODO TODO TODO TODO TODO TODO TODO TODO TODO TODO TODO TODO
         */

        /* Add a mapping from  id to n for n.ownerDocument */
        public function addId($id, $n)
        {
                $val = $this->byId[$id];
                if (!$val) {
                        $this->byId[$id] = $n;
                } else {
                        // TODO: Add a way to opt-out console warnings
                        //console.warn('Duplicate element id ' + id);
                        if (!($val instanceof MultiId)) {
                                $val = new MultiId($val);
                                $this->byId[$id] = $val;
                        }
                        $val->add($n);
                }
        }

        /* Delete the mapping from id to n for n.ownerDocument */
        public function delId($id, $n)
        {
                $val = $this->byId[$id];
                domo\utils\assert($val);

                if ($val instanceof MultiId) {
                        $val->del($n);
                        if (count($val) === 1) { // convert back to a single node
                                $this->byId[$id] = $val->downgrade();
                        }
                } else {
                        $this->byId[$id] = NULL;
                }
        }

        static private function _helper_root($node)
        {
                $node->_nid = $node->ownerDocument()->_nid_next++;
                $node->ownerDocument()->_nid_table[$node->_nid] = $node;

                /* Manage id to element mapping */
                if ($node->nodeType === domo\Node\ELEMENT_NODE) {
                        $id = $node->getAttribute("id");
                        if ($id) {
                                $node->ownerDocument->addId($id, $node);
                        }
                        /*
                         * Script elements need to know when they're inserted
                         * into the document
                         */
                        if ($node->_roothook) {
                                $node->_roothook();
                        }
                }
        }

        static private function _helper_recursive_root($node)
        {
                Document::_helper_root($node);

                if ($node->nodeType === domo\Node\ELEMENT_NODE) {
                        for ($n=$node->firstChild(); $n!==NULL; $n=$n->nextSibling()) {
                                Document::_helper_recursive_root($n);
                        }
                }
        }

        /* And this removes them */
        static private function _helper_uproot($node)
        {
                /* Manage id to element mapping */
                if ($node->nodeType() === domo\Node\ELEMENT_NODE) {
                        $id = $node->getAttribute("id");
                        if ($id) {
                                $node->ownerDocument()->delId($id, $node);
                        }

                        /* TODO Are we intending to unset this??? */
                        unset($node->ownerDocument()->_nid_table[$node->_nid]);
                        unset($node->_nid);
                }
        }

        static private function _helper_recursive_uproot($node)
        {
                Document::_helper_uproot($node);

                for ($n=$node->firstChild(); $n!==NULL; $n=$n->nextSibling()) {
                        Document::_helper_recursive_uproot($n);
                }
        }

        static private function _helper_recursivelySetOwner($node, $owner)
        {
                $node->_ownerDocument = $owner;
                /* TODO: How to handle this undefined thing? */
                $node->_lastModTime = undefined; // mod times are document-based

                /* TODO: Fix this!! */
                if (Object.prototype.hasOwnProperty.call(node, "_tagName")) {
                        node._tagName = undefined; // Element subclasses might need to change case
                }

                for ($n=$n->firstChild(); $n!==NULL; $n=$n->nextSibling()) {
                        _helper_recursivelySetOwner($n, $owner);
                }
        }

        /*
         * TODO: Is this slowing down DOM operations?
         * The only benefit I can see is that we're
         * able to signal a handler callback. Why bother,
         * if we're not debugging?
         */

        /*
         * Implementation-specific function.  Called when a text, comment,
         * or pi value changes.
         */
        public function mutateValue($node)
        {
                if ($this->mutationHandler) {
                        $this->mutationHandler(array(
                                "type" => MUTATE.VALUE,
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
        public function mutateAttr($attr, $oldval)
        {
                if ($this->mutationHandler) {
                        $this->mutationHandler(array(
                                "type" => MUTATE.ATTR,
                                "target" => $attr->ownerElement(),
                                "attr" => $attr
                        ));
                }
        }

        /* Used by removeAttribute and removeAttributeNS for attributes. */
        public function mutateRemoveAttr($attr)
        {
                if ($this->mutationHandler) {
                        $this->mutationHandler(array(
                                "type" => MUTATE.REMOVE_ATTR,
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
        public function mutateRemove($node)
        {
                /* Send a single mutation event */
                if ($this->mutationHandler) {
                        $this->mutationHandler(array(
                                "type" => MUTATE.REMOVE,
                                "target" => $node->parentNode(),
                                "node" => $node
                        )
                }

                /* Mark this and all descendants as not rooted */
                Document::_helper_recursive_uproot($node);
        }

        /*
         * Called when a new element becomes rooted.  It must recursively
         * generate mutation events for each of the children, and mark
         * them all as rooted.
         *
         * Called in Node::_insertOrReplace.
         */
        public function mutateInsert($node)
        {
                /* Mark node and its descendants as rooted */
                Document::_helper_recursive_root($node);

                /* Send a single mutation event */
                if ($this->mutationHandler) {
                        $this->mutationHandler(array(
                                "type" => MUTATE.INSERT,
                                "target" => $node->parentNode(),
                                "node" => $node
                        ));
                }
        }

        /*
         * Called when a rooted element is moved within the document
         */
        public function mutateMove($node)
        {
                if ($this->mutationHandler) {
                        $this->mutationHandler(array(
                                "type" => MUTATE.MOVE,
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


/* TODO: Event stuff */
//$eventHandlerTypes = array(
        //'abort', 'canplay', 'canplaythrough', 'change', 'click', 'contextmenu',
        //'cuechange', 'dblclick', 'drag', 'dragend', 'dragenter', 'dragleave',
        //'dragover', 'dragstart', 'drop', 'durationchange', 'emptied', 'ended',
        //'input', 'invalid', 'keydown', 'keypress', 'keyup', 'loadeddata',
        //'loadedmetadata', 'loadstart', 'mousedown', 'mousemove', 'mouseout',
        //'mouseover', 'mouseup', 'mousewheel', 'pause', 'play', 'playing',
        //'progress', 'ratechange', 'readystatechange', 'reset', 'seeked',
        //'seeking', 'select', 'show', 'stalled', 'submit', 'suspend',
        //'timeupdate', 'volumechange', 'waiting',
        //'blur', 'error', 'focus', 'load', 'scroll'
//);

//// Add event handler idl attribute getters and setters to Document
//eventHandlerTypes.forEach(function(type) {
  //// Define the event handler registration IDL attribute for this type
  //Object.defineProperty(Document.prototype, 'on' + type, {
    //get: function() {
      //return this._getEventHandler(type);
    //},
    //set: function(v) {
      //this._setEventHandler(type, v);
    //}
  //});
//});

function namedHTMLChild($parent, $name)
{
        if ($parent && $parent->isHTMLDocument) {
                for ($n=$parent->firstChild(); $n!==NULL; $n=$n->nextSibling()) {
                        if ($n->nodeType === domo\Node\ELEMENT_NODE
                        && $n->localName === $name
                        && $n->namespaceURI === domo\NAMESPACE_HTML) {
                                return $n;
                        }
                }
        }
        return NULL;
}


/* TODO PORT: Do we need all this? Just to handle >1 node with same ID? */
/* TODO PORT: Should these just extend ArrayObject I guess?? */

// A class for storing multiple nodes with the same ID
function MultiId($node)
{
  this.nodes = Object.create(null);
  this.nodes[node._nid] = node;
  this.length = 1;
  this.firstNode = undefined;
}

// Add a node to the list, with O(1) time
MultiId.prototype.add = function(node) {
  if (!this.nodes[node._nid]) {
    this.nodes[node._nid] = node;
    this.length++;
    this.firstNode = undefined;
  }
};

// Remove a node from the list, with O(1) time
MultiId.prototype.del = function(node) {
  if (this.nodes[node._nid]) {
    delete this.nodes[node._nid];
    this.length--;
    this.firstNode = undefined;
  }
};

// Get the first node from the list, in the document order
// Takes O(N) time in the size of the list, with a cache that is invalidated
// when the list is modified.
MultiId.prototype.getFirst = function() {
  /* jshint bitwise: false */
  if (!this.firstNode) {
    var nid;
    for (nid in this.nodes) {
      if (this.firstNode === undefined ||
        this.firstNode.compareDocumentPosition(this.nodes[nid]) & Node.DOCUMENT_POSITION_PRECEDING) {
        this.firstNode = this.nodes[nid];
      }
    }
  }
  return this.firstNode;
};

// If there is only one node left, return it. Otherwise return "this".
MultiId.prototype.downgrade = function() {
  if (this.length === 1) {
    var nid;
    for (nid in this.nodes) {
      return this.nodes[nid];
    }
  }
  return this;
};
