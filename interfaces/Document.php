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

/*
 TODO: Need a way to port this silly fancy code.

var mirrorAttr = function(f, name, defaultValue) {
  return {
    get: function() {
      var o = f.call(this);
      if (o) { return o[name]; }
      return defaultValue;
    },
    set: function(value) {
      var o = f.call(this);
      if (o) { o[name] = value; }
    },
  };
};
*/

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




class Document extends Node
{
        public function __construct(bool $isHTML, string $address)
        {
                $this->nodeType = Node\DOCUMENT_NODE;
                $this->isHTML = $isHTML;
                $this->_address = $address || "about:blank";
                $this->readyState = "loading";
                $this->implementation = new domo\w3c\DOMImplementation($this);

                /* DOMCore says that documents are always associated with themselves */
                $this->ownerDocument = NULL; /* ... but W3C tests expect null */
                $this->_contentType = $isHTML ? "text/html" : "application/xml";

                // These will be initialized by our custom versions of
                // appendChild and insertBefore that override the inherited
                // Node methods.
                // XXX: override those methods!
                $this->doctype = null;
                $this->documentElement = null;

                // "Associated inert template document"
                $this->_templateDocCache = null;

                // List of active NodeIterators, see NodeIterator#_preremove()
                /* TODO : REMOVED */
                //$this->_nodeIterators = null;

                // Documents are always rooted, by definition
                $this->_nid = 1;
                $this->_nextnid = 2; // For numbering children of the document
                $this->_nodes = array(null, $this);  // nid to node map

                /*
                 * This maintains the mapping from element ids to element nodes.
                 * We may need to update this mapping every time a node is rooted
                 * or uprooted, and any time an attribute is added, removed or changed
                 * on a rooted element.
                 */
                $this->byId = Object.create(null);

                /*
                 * This property holds a monotonically increasing value
                 * akin to a timestamp used to record the last modification
                 * time of nodes and their subtrees. See the lastModTime
                 * attribute and modify() method of the Node class. And see
                 * FilteredElementList for an example of the use of
                 * lastModTime
                 */
                $this->modclock = 0;
        }


        // This method allows dom.js to communicate with a renderer
        // that displays the document in some way
        // XXX: I should probably move this to the window object
        /* PORT TODO: should stub? */
        protected function _setMutationHandler($handler)
        {
                $this->mutationHandler = $handler;
        }

        // This method allows dom.js to receive event notifications
        // from the renderer.
        // XXX: I should probably move this to the window object
        /* PORT TODO: should stub? */
        protected function _dispatchRendererEvent($targetNid, $type, $details)
        {
                $target = $this->_nodes[$targetNid];
                if (!$target) {
                        return;
                }
                $target->_dispatchEvent(new Event($type, $details), true);
        }

        public $nodeName = "#document";

        public function nodeValue($value=NULL)
        {
                /* Not implemented ? */
                return NULL;
        }

        // XXX: DOMCore may remove documentURI, so it is NYI for now
        public function documentURI($value=NULL)
        {
                if ($value === NULL) {
                        return $this->_address;
                } else {
                        domo\utils\nyi();
                }
        }

        /* PORT TODO: should stub? */
        public function compatMode()
        {
                /* The _quirks property is set by the HTML parser */
                return $this->_quirks ? "BackCompat" : "CSS1Compat";
        }

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
                if (!domo\xml\isValidName($target) || strpos($data, "?>") !== false) {
                        utils.InvalidCharacterError();
                }
                return new ProcessingInstruction($this, $target, $data);
        }

        public function createAttribute($localName)
        {
                $localName = strval($localName);

                if (!domo\xml\isValidName($localName)) {
                        domo\utils\InvalidCharacterError();
                }
                if ($this->isHTML) {
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
                if ($this->isHTML) {
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


        /******************** BEGIN DECORATED FUNCTIONS *****************/
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
                $this->doctype = $this->documentElement = NULL;

                for ($n=$this->firstChild(); $n!==NULL; $n=$n->nextSibling()) {
                        if ($n->nodeType === domo\util\DOCUMENT_TYPE_NODE) {
                                $this->doctype = $n;
                        } else if ($n->nodeType === domo\util\ELEMENT_NODE) {
                                $this->documentElement = $n;
                        }
                }
        }

        /*
         * TODO PORT: Note that this is a method defined on Node, and we
         * want to extend it here... but we want to use the one on Node
         * to extend it... so... how ?
         */
        public function insertBefore($child, $refChild)
        {
                /*
                 * In PHP inheritance, you can call the method you're
                 * overriding using parent::
                 *
                 * Here, parent is the Node class.
                 */
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

        /******************** END DECORATED FUNCTIONS *****************/

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

        public function adoptNode($node)
        {
                if ($node->nodeType === domo\Node\DOCUMENT_NODE) {
                        domo\utils\NotSupportedError();
                }
                if ($node->nodeType === domo\Node\ATTRIBUTE_NODE) {
                        return $node;
                }
                if ($node->parentNode()) {
                        $node->parentNode()->removeChild($node);
                }
                if ($node->ownerDocument !== $this) {
                        _recursively_set_owner($node, $this);
                }

                return $node;
        }

        public function importNode($node, $deep)
        {
                return $this->adoptNode($node->cloneNode($deep));
        }
        //}, writable: isApiWritable }, TODO PORT what is this

        /****************************************************************
         * The following attributes and methods are from the HTML spec
         ****************************************************************/

        public function origin()
        {
                return NULL;
        }

        public function characterSet()
        {
                /* TODO PORT: This isn't a character set... bleh */
                return "UTF-8";
        }

        public function contentType()
        {
                return $this->_contentType;
        }

        public function URL()
        {
                return $this->_address;
        }

        public function domain($value = NULL)
        {
                domo\utils\nyi();
        }

        public function referrer()
        {
                domo\utils\nyi();
        }

        public function cookie()
        {
                domo\utils\nyi();
        }

        public function lastModified()
        {
                \domo\utils\nyi();
        }

        public function location($value = NULL)
        {
                if ($value === NULL) {
                        /* GET */
                        if ($this->defaultView) {
                                return $this->defaultView->location();
                        } else {
                                return NULL; // gh #75
                        }
                } else {
                        /* SET */
                        domo\utils\nyi();
	}

        public function _titleElement()
        {
                /*
                 * The title element of a document is the first title
                 * element in the document in tree order, if there is
                 * one, or null otherwise.
                 */
                return $this->getElementsByTagName("title")->item(0) || null;
        }

        public function title($value = NULL)
        {
                if ($value === NULL) {
                        /* GET */
                        $elt = $this->_titleElement;
                        /* The child text content of the title element, or '' if null. */
                        $value = $elt ? $elt->textContent : "";
                        // Strip and collapse whitespace in value
                        return $value->replace(/[ \t\n\r\f]+/g, ' ')->replace(/(^ )|( $)/g, '');
                } else {
                        /* SET */
                        $elt = $this->_titleElement;
                        $head = $this->head;
                        if (!$elt && !$head) {
                                return; /* according to spec */
                        }
                        if (!$elt) {
                                $elt = $this->createElement("title");
                                $head->appendChild($elt);
                        }
                        $elt->textContent = $value;
                }
        }

        /*
         * MirrorAttr stuff
         */

        dir: mirrorAttr(function() {
                var htmlElement = this.documentElement;
                if (htmlElement && htmlElement.tagName === 'HTML') { return htmlElement; }
        }, 'dir', ''),
        fgColor: mirrorAttr(function() { return this.body; }, 'text', ''),
        linkColor: mirrorAttr(function() { return this.body; }, 'link', ''),
        vlinkColor: mirrorAttr(function() { return this.body; }, 'vLink', ''),
        alinkColor: mirrorAttr(function() { return this.body; }, 'aLink', ''),
        bgColor: mirrorAttr(function() { return this.body; }, 'bgColor', ''),

        /*
         * Historical aliases of Document#characterSet
         */
        //charset: { get: function() { return this.characterSet; } },
        //inputEncoding: { get: function() { return this.characterSet; } },

        public function scrollingElement()
        {
                return $this->_quirks ? $this->body : $this->documentElement;
        }

        // Return the first <body> child of the document element.
        // XXX For now, setting this attribute is not implemented.
        public function body($value = NULL)
        {
                if ($value === NULL) {
                        /* GET */
                        return namedHTMLChild($this->documentElement, "body");
                } else {
                        /* SET */
                        domo\utils\nyi();
                }
        }

        // Return the first <head> child of the document element.
        public function head()
        {
                return namedHTMLChild($this->documentElement, "head");
        }

        public function images()
        {
                domo\utils\nyi();
        }
        public function embeds()
        {
                domo\utils\nyi();
        }
        public function plugins()
        {
                domo\utils\nyi();
        }
        public function links()
        {
                domo\utils\nyi();
        }
        public function forms()
        {
                domo\utils\nyi();
        }
        public function scripts()
        {
                domo\utils\nyi();
        }
        public function applets()
        {
                return array();
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
                if (!$this->isHTML) {
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

        /* Utility methods */
        public function clone()
        {
                $d = new Document($this->isHTML, $this->_address);
                $d->_quirks = $this->_quirks;
                $d->_contentType = $this->_contentType;
                return $d;
        }

        /* We need to adopt the nodes if we do a deep clone */
        public function cloneNode($deep)
        {
                $clone = parent::cloneNode(false);

                if ($deep) {
                        for ($n=$this->firstChild(); $n!==NULL; $n=$n->nextSibling()) {
                                $clone->_appendChild($clone->importNode($n, true));
                        }
                }
                $clone->_updateDocTypeElement();
                return $clone;
        }

        public function isEqual($n)
        {
                // Any two documents are shallowly equal.
                // Node.isEqualNode will also test the children
                return true;
        }

  //[> TODO: STUB MUTATION JUNK??? <]

  //// Implementation-specific function.  Called when a text, comment,
  //// or pi value changes.
  //mutateValue: { value: function(node) {
    //if (this.mutationHandler) {
      //this.mutationHandler({
        //type: MUTATE.VALUE,
        //target: node,
        //data: node.data
      //});
    //}
  //}},

  //// Invoked when an attribute's value changes. Attr holds the new
  //// value.  oldval is the old value.  Attribute mutations can also
  //// involve changes to the prefix (and therefore the qualified name)
  //mutateAttr: { value: function(attr, oldval) {
    //// Manage id->element mapping for getElementsById()
    //// XXX: this special case id handling should not go here,
    //// but in the attribute declaration for the id attribute
    //[>
    //if (attr.localName === 'id' && attr.namespaceURI === null) {
      //if (oldval) delId(oldval, attr.ownerElement);
      //addId(attr.value, attr.ownerElement);
    //}
    //*/
    //if (this.mutationHandler) {
      //this.mutationHandler({
        //type: MUTATE.ATTR,
        //target: attr.ownerElement,
        //attr: attr
      //});
    //}
  //}},

  //// Used by removeAttribute and removeAttributeNS for attributes.
  //mutateRemoveAttr: { value: function(attr) {
//[>
//* This is now handled in Attributes.js
    //// Manage id to element mapping
    //if (attr.localName === 'id' && attr.namespaceURI === null) {
      //this.delId(attr.value, attr.ownerElement);
    //}
//*/
    //if (this.mutationHandler) {
      //this.mutationHandler({
        //type: MUTATE.REMOVE_ATTR,
        //target: attr.ownerElement,
        //attr: attr
      //});
    //}
  //}},

  //// Called by Node.removeChild, etc. to remove a rooted element from
  //// the tree. Only needs to generate a single mutation event when a
  //// node is removed, but must recursively mark all descendants as not
  //// rooted.
  //mutateRemove: { value: function(node) {
    //// Send a single mutation event
    //if (this.mutationHandler) {
      //this.mutationHandler({
        //type: MUTATE.REMOVE,
        //target: node.parentNode,
        //node: node
      //});
    //}

    //// Mark this and all descendants as not rooted
    //recursivelyUproot(node);
  //}},

  //// Called when a new element becomes rooted.  It must recursively
  //// generate mutation events for each of the children, and mark them all
  //// as rooted.
  //mutateInsert: { value: function(node) {
    //// Mark node and its descendants as rooted
    //recursivelyRoot(node);

    //// Send a single mutation event
    //if (this.mutationHandler) {
      //this.mutationHandler({
        //type: MUTATE.INSERT,
        //target: node.parentNode,
        //node: node
      //});
    //}
  //}},

  //// Called when a rooted element is moved within the document
  //mutateMove: { value: function(node) {
    //if (this.mutationHandler) {
      //this.mutationHandler({
        //type: MUTATE.MOVE,
        //target: node
      //});
    //}
  //}},

        // Add a mapping from  id to n for n.ownerDocument
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

        // Delete the mapping from id to n for n.ownerDocument
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

        public function _resolve($href)
        {
                //XXX: Cache the URL
                return new URL($lthis->_documentBaseURL)->resolve($href);
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

        public function _templateDoc()
        {
                if (!$this->_templateDocCache) {
                        /* "associated inert template document" */
                        $newDoc = new Document($this->isHTML, $this->_address);
                        $this->_templateDocCache = $newDoc->_templateDocCache = $newDoc;
                }
                return $this->_templateDocCache;
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
        if ($parent && $parent->isHTML) {
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

function root($n)
{
        $n->_nid = $n->ownerDocument->_nextnid++;

        $n->ownerDocument->_nodes[$n->_nid] = $n;

        /* Manage id to element mapping */
        if ($n->nodeType === domo\Node\ELEMENT_NODE) {
                $id = $n->getAttribute("id");
                if ($id) {
                        $n->ownerDocument->addId($id, $n);
                }
                /*
                 * Script elements need to know when they're inserted
                 * into the document
                 */
                if ($n->_roothook) {
                        $n->_roothook();
                }
        }
}

function uproot($n)
{
        /* Manage id to element mapping */
        if ($n->nodeType === domo\Node\ELEMENT_NODE) {
                $id = $n->getAttribute("id");
                if ($id) {
                        $n->ownerDocument->delId($id, $n);
                }

                /* TODO Are we intending to unset this??? */
                $n->ownerDocument->_nodes[$n->_nid] = undefined;
                $n->_nid = undefined;
        }
}

function recursivelyRoot($node)
{
        root($node);

        /* XXX:
         * accessing childNodes on a leaf node creates a new array the
         * first time, so be careful to write this loop so that it
         * doesn't do that. node is polymorphic, so maybe this is hard to
         * optimize?  Try switching on nodeType?
         */
        /*
          if (node.hasChildNodes()) {
            var kids = node.childNodes;
            for(var i = 0, n = kids.length;  i < n; i++)
              recursivelyRoot(kids[i]);
          }
        */
        if ($node->nodeType === domo\Node\ELEMENT_NODE) {
                for ($n=$node->firstChild(); $n!==NULL; $n=$n->nextSibling()) {
                        recursivelyRoot($n);
                }
        }
}

function recursivelyUproot($node)
{
        uproot($node);

        for ($n=$node->firstChild(); $n!==NULL; $n=$n->nextSibling()) {
                recursivelyUproot($n);
        }
}

function _recursively_set_owner($node, $owner)
{
        $node->ownerDocument = $owner;
        /* TODO: How to handle this undefined thing? */
        $node->_lastModTime = undefined; // mod times are document-based

        /* TODO: Fix this!! */
        if (Object.prototype.hasOwnProperty.call(node, "_tagName")) {
                node._tagName = undefined; // Element subclasses might need to change case
        }

        for ($n=$n->firstChild(); $n!==NULL; $n=$n->nextSibling()) {
                _recursively_set_owner($kid, $owner);
        }
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
