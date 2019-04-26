<?php
/******************************************************************************
 * Node.php
 * ````````
 * Defines a "Node", the primary datatype of the W3C Document Object Model.
 *
 * Conforms to W3C Document Object Model (DOM) Level 1 Recommendation
 * (see: https://www.w3.org/TR/2000/WD-DOM-Level-1-20000929)
 *
 *****************************************************************************/
/******************************************************************************
 * PORT NOTES
 * CHANGED:
 *      Attributes had to become functions because PHP does not
 *      support getters and setters for property access.
 *      Node.baseURI            => Node->baseURI()
 *      Node.rooted             => Node->rooted()
 *      Node.outerHTML          => Node->outerHTML()
 *      Node.doc                => Node->doc()
 *      Node.previousSibling    => Node->previousSibling()
 *      Node.nextSibling        => Node->nextSibling()
 *
 *      TODO: The array splicing that happens needs to be cleaned up
 *      TODO: The "private" methods can be made private and static in PHP
 *
 *      Merged ContainerNode with Node to better match spec
 *      Moved _ensureChildNodes() into childNode() as a memoization branch
 *      and re-write do-while loop as for loop to match other traversals.
 *
 *      clone()                 => _subclass_cloneNode()
 *      isEqual()               => _subclass_isEqualNode()
 *      Changed the names of these delegated subclass things to be more
 *      clear about their purpose and role.
 *
 *      _lookupNamespaceURI() => static in algorithms.php
 *      _lookupPrefix()() => static in algorithms.php
 *
 *      compareDocumentPosition() => now includes attribute, bringing up to
 *                                   DOM-LS spec (was not conformant before)
 *
 *      removed isAncestor because it was only used by EnsureInsertValid,
 *      and had a weird 'inclusive ancestor' behavior. Now hard-coded into
 *      the algorithms for ensure_insert_valid, etc.
 *
 *      ensureSameDoc wasn't even being called anywhere...
 *
 *      doc() => nodeDocument(), mirroring spec: https://dom.spec.whatwg.org/#concept-node-document
 *
 *      __ for our functions
 *      removeChildren => __remove_children, and so on.
 *
 * NOT CHANGED:
 *      Node.parentNode         => Node->parentNode
 *      this one is kept as an attribute. Is this wise?
 *
 * TODO
 *      Find a way to signal that methods and properties are part of
 *      our extension, and not the DOM. E.g. removeChildren(), doc(),
 *      and so on.
 *
 *      Perhaps name them x_removeChildren(), or have them be in some
 *      kind of sub-object, like $node->domo->removeChildren() or something.
 *
 *****************************************************************************/
namespace domo;

//require_once("EventTarget.php");
//require_once("NodeUtils.php");
require_once(__DIR__.'/../lib/LinkedList.php');
require_once(__DIR__.'/../lib/util.php');
require_once("NodeList.php");
require_once("algorithms.php");

abstract class Node /* extends EventTarget // try factoring events out? */
{
        /* Delegated subclass method called by Node::isEqualNode() */
        abstract protected function _subclass_isEqualNode();

        /* Delegated subclass method called by Node::cloneNode() */
        abstract protected function _subclass_cloneNodeShallow();

        /**********************************************************************
         * DOMO internal book-keeping layer
         **********************************************************************/
        /* Used for caching (Node::__lastmod_update, Node::__lastmod) */
        protected $___lastmod = 0;

        /* Assigned by Document::adopt() as a node index in the Document */
        protected $__nid;

        /**********************************************************************
         * BOOK-KEEPING: What Node knows about its ancestors
         **********************************************************************/

        /* DOMO: Top-level Document object of the Node */
        public $_ownerDocument;

        /* DOMO: Parent node (NULL if no parent) */
        public $_parentNode;

        /**********************************************************************
         * BOOK-KEEPING: What Node knows about the childNodes of its parent
         **********************************************************************/

        /* DOMO: Node's index in childNodes of parent (NULL if no parent) */
        public $_siblingIndex;

        /* DOMO: Next sibling in childNodes of parent ($this if none) */
        public $_nextSibling;

        /* DOMO: Prev sibling in childNodes of parent ($this if none) */
        public $_previousSibling;

        /**********************************************************************
         * BOOK-KEEPING: What Node knows about its own childNodes
         **********************************************************************/

        /* DOMO: Reference to first child Node (NULL if no children) */
        public $_firstChild;

        /* DOMO: Array form of childNodes (NULL if no children or using LL) */
        public $_childNodes;

        /**********************************************************************
         * BOOK-KEEPING: What Node knows about itself
         **********************************************************************/

        public function __construct()
        {
                /* Our ancestors */
		$this->_ownerDocument = NULL;
                $this->_parentNode = NULL;

                /* Our children */
                $this->_firstChild = NULL;
                $this->_childNodes = NULL;

                /* Our siblings */
                $this->_nextSibling = $this; // for LL
                $this->_previousSibling = $this; // for LL
                $this->_siblingIndex = NULL;
        }

        /**********************************************************************
         * UNSUPPORTED
         **********************************************************************/
        public function baseURI(){}
        public function baseURIObject(){}

        /**********************************************************************
         * ACCESSORS
         **********************************************************************/

        /*
         * TODO: These accessors rely on subclasses to set values on
         * the properties _nodeType, _nodeName, and _nodeValue, _ownerDocument,
         * and so on, in order to function.
         *
         * However sometimes the subclasses simply override/extend these
         * methods when it suits them. This is a bit confusing.
         *
         * This could perhaps better be done by declaring them abstract
         * methods and asking the subclasses to implement the accessors
         * there, like how textContent is done now.
         */
        public function nodeType(): integer
        {
                return $this->_nodeType;
        }
        public function nodeName()
        {
                return $this->_nodeName;
        }
        public function nodeValue()
        {
                return $this->_nodeValue;
        }

        /* TODO: Hmm. */
        abstract public function textContent(string $value=NULL);

        /**
         * Document that this node belongs to, or NULL if node is a Document
         *
         * @return Document or NULL
         * @spec DOM-LS
         */
        public function ownerDocument(): ?Document
        {
                if ($this->_ownerDocument === NULL
                ||  $this->_nodeType === DOCUMENT_NODE) {
                        return NULL;
                }
                return $this->_ownerDocument;
        }

        /**
         * Node that is the parent of this node, or NULL if DNE.
         *
         * @return Node or NULL
         * @spec DOM-LS
         *
         * NOTE
         * Nodes may not have a parentNode if they are at the top of the
         * tree (e.g. Document Nodes), or if they don't participate in a
         * tree.
         */
        public function parentNode(): ?Node
        {
                return $this->_parentNode ?? NULL;
        }

        /**
         * Element that is the parent of this node, or NULL if DNE.
         *
         * @return Element or NULL
         * @spec DOM-LS
         *
         * NOTE
         * Computed from _parentNode, has no state of its own to mutate.
         */
        public function parentElement(): ?Element
        {
                if ($this->_parentNode === NULL
                || $this->_parentNode->_nodeType !== ELEMENT_NODE) {
                        return NULL;
                }
                return $this->_parentNode;
        }

        /**
         * Node representing previous sibling, or NULL if DNE.
         *
         * @return Node or NULL
         * @spec DOM-LS
         *
         * CAUTION
         * Directly accessing _previousSibling is NOT a substitute
         * for this method. When Node is an only child, _previousSibling
         * is set to $this, but DOM-LS needs it to be NULL.
         */
        public function previousSibling(): ?Node
        {
                if ($this->_parentNode === NULL
                || $this === $this->_parentNode->firstChild()) {
                        return NULL;
                }
                return $this->_previousSibling;
        }

        /**
         * Node representing next sibling, or NULL if DNE.
         *
         * @return Node or NULL
         * @spec DOM-LS
         *
         * CAUTION
         * Directly accessing _nextSibling is NOT a substitute
         * for this method. When Node is an only child, _nextSibling
         * is set to $this, but DOM-LS needs it to be NULL.
         */
        public function nextSibling(): ?Node
        {
                if ($this->_parentNode === NULL
                || $this->_nextSibling === $this->_parentNode->_firstChild) {
                        return NULL;
                }
                return $this->_nextSibling;
        }

        /**
         * Live NodeList containing this Node's children (NULL if no children)
         *
         * @return NodeList or NULL
         * @spec DOM-LS
         *
         * NOTE
         * For performance, the creation of this NodeList is done lazily,
         * and only triggered the first time this method is called. Until
         * then, other functions rely on the LinkedList representation of
         * a Node's child nodes.
         *
         * So when we test if ($this->_childNodes), we are testing to see
         * if we have to mutate or work with a live NodeList.
         *
         * TODO
         * It's annoying how we can do foreach (Document::attributes as $a)
         * but not foreach (Node::childNodes as $c).
         */
        public function childNodes(): ?NodeList
        {
                if ($this->_childNodes !== NULL) {
                        return $this->_childNodes; /* memoization */
                }

                /* Lazy evaluation to build the child nodes */
                $this->_childNodes = new NodeList();

                for ($n=$this->firstChild(); $n!==NULL; $n=$n->nextSibling()) {
                        $this->_childNodes[] = $n;
                }

                $this->_firstChild = NULL; /* Signals we are not using LL */
                return $this->_childNodes;
        }

        /**
         * Determine if the Node has any children
         *
         * @return boolean
         * @spec DOM-LS
         *
         * CAUTION
         * Testing _firstChild or _childNodes alone is *not* a shortcut
         * for this method. Depending on whether we are in NodeList or
         * LinkedList mode, one or the other or both may be NULL.
         */
        public function hasChildNodes(): boolean
        {
                if ($this->_childNodes !== NULL) {
                        return !empty($this->_childNodes); /* NodeList */
                } else {
                        return $this->_firstChild !== NULL; /* LinkedList */
                }
        }

        /**
         * Node representing the Node's first child node, or NULL if DNE
         *
         * @return Node or NULL
         * @spec DOM-LS
         *
         * CAUTION
         * Directly accessing _firstChild is *not* a substitute for this
         * method. Where to find the first child depends on whether we
         * are in NodeList or LinkedList mode.
         */
        public function firstChild(): ?Node
        {
                if ($this->_childNodes !== NULL) {
                        if (isset($this->_childNodes[0])) {
                                return $this->_childNodes[0]; /* NodeList */
                        } else {
                                return NULL; /* NodeList */
                        }
                }
                return $this->_firstChild; /* LinkedList */
        }

        /**
         * Node representing the Node's last child node, or NULL if DNE
         *
         * @return Node or NULL
         * @spec DOM-LS
         *
         * NOTE
         * See note for firstChild()
         */
        public function lastChild(): ?Node
        {
                if ($this->_childNodes !== NULL) {
                        if (!empty($this->_childNodes)) {
                                return end($this->_childNodes); /* NodeList */
                        } else {
                                return NULL; /* NodeList */
                        }
                }
                if ($this->_firstChild !== NULL) {
                        return $this->_firstChild->previousSibling(); /* LinkedList */
                } else {
                        return NULL; /* LinkedList */
                }
        }

	/**********************************************************************
	 * MUTATION ALGORITHMS
	 *********************************************************************/

        /**
         * Insert a Node as a child of this one, before a given reference node
         *
         * @param Node $node To be inserted
         * @param Node $refChild Child of this node before which to insert $node
         * @return Newly inserted Node or empty DocumentFragment
         * @throw DOMException "HierarchyRequestError" or "NotFoundError"
         * @spec DOM-LS
         *
         * NOTES
         * DOM-LS: If $node already exists in this Document, this function
         * moves it from its current position to its new position ('move'
         * means 'remove' followed by 're-insert').
         *
         * DOM-LS: If $refNode is NULL, then $node is added to the end of the
         * list of children of $this. In other words, insertBefore($node, NULL)
         * is equivalent to appendChild($node).
         *
         * DOM-LS: If $node is a DocumentFragment, the children of the
         * DocumentFragment are moved into the child list of $this, and the
         * empty DocumentFragment is returned.
         *
         * SORRY
         * Despite its weird syntax (blame the spec) this is a real workhorse,
         * used to implement all of the insertion mutations.
         */
        public function insertBefore(Node $node, ?Node $refChild): Node
        {
                /* DOM-LS #1. Ensure pre-insertion validity */
                \domo\whatwg\ensure_insert_valid($node, $this, $refChild);

                /* DOM-LS #3. If $refChild is node, set to $node next sibling */
                if ($refChild === $node) {
                        $refChild = $node->nextSibling();
                }

                /* DOM-LS #4. Adopt $node into parent's node document. */
                $this->__node_document()->adoptNode($node);

                /* DOM-LS #5. Insert $node into parent before $refChild . */
                \domo\whatwg\insert_before_or_replace($node, $this, $refChild, false);

                /* DOM-LS #6. Return $node */
                return $node;
        }

        /**
         * Append the given Node to the child list of this one
         *
         * @param Node $node
         * @return Newly inserted Node or empty DocumentFragment
         * @spec DOM-LS
         * @throw DOMException "HierarchyRequestError", "NotFoundError"
         */
        public function appendChild(Node $node): Node
        {
                return $this->insertBefore($node, NULL);
        }

        /**
         * NO CHECKS!
         * See
        public function __unsafe_appendChild(Node $node): Node
        {
                return \domo\whatwg\insert_before_or_replace($node, $this, NULL);
        }

        /**
         * Replaces a given child Node with another Node
         *
         * @param Node $newChild to be inserted
         * @param Node $oldChild to be replaced
         * @return Reference to $oldChild
         * @throw DOMException "HierarchyRequestError", "NotFoundError"
         * @spec DOM-LS
         */
        public function replaceChild(Node $newChild, ?Node $oldChild): ?Node
        {
                \domo\whatwg\ensure_replace_valid($newChild, $this, $oldChild);

                /* Adopt node into parent's node document. */
                if ($newChild->__node_document() !== $this->__node_document()) {
                        /*
                         * XXX adoptNode has side-effect of removing node from
                         * its parent and generating a mutation event, causing
                         * _insertOrReplace to generate 2 deletes and 1 insert
                         * instead of a 'move' event.  It looks like the new
                         * MutationObserver stuff avoids this problem, but for
                         * now let's only adopt (ie, remove 'node' from its
                         * parent) here if we need to.
                         */
                        $this->__node_document()->adoptNode($newChild);
                }

                \domo\whatwg\insert_before_or_replace($newChild, $this, $oldChild, true);

                return $oldChild;
        }

        /**
         * Removes a child node from the DOM and returns it.
         *
         * @param ChildNode $node
         * @return ChildNode
         * @throw DOMException "NotFoundError"
         * @spec DOM-LS
         *
         * NOTE
         * It must be the case that the returned value === $node
         */
        public function removeChild(ChildNode $node): Node
        {
                if ($this !== $node->_parentNode) {
                        \domo\error("NotFoundError");
                }
                $node->remove(); /* ChildNode method */
                return $node;
        }

        /**
         * TODO: What do you do?
         */
        public function normalize()
        {
                $next=NULL;

                for ($n=$this->firstChild(); $n!==NULL; $n=$n->nextSibling()) {

                        /* TODO: HOW TO FIX THIS IN PHP? */
                        if (method_exists($n, "normalize")) {
                                $n->normalize();
                        }

                        if ($n->_nodeType !== TEXT_NODE) {
                                continue;
                        }

                        if ($n->_nodeValue === "") {
                                $this->removeChild($n);
                                continue;
                        }

                        $prevChild = $n->previousSibling();

                        if ($prevChild === NULL) {
                                continue;
                        } else {
                                if ($prevChild->_nodeType === TEXT_NODE) {
                                        /*
                                         * merge this with previous and
                                         * remove the child
                                         */
                                        $prevChild->appendData($n->_nodeValue);
                                        $this->removeChild($n);
                                }
                        }
                }
        }

	/**********************************************************************
	 * COMPARISONS AND PREDICATES
	 *********************************************************************/

        /**
         * Indicates whether a node is a descendant of this one
         *
         * @param Node $node
         * @returns boolean
         * @spec DOM-LS
         *
         * NOTE: see http://ejohn.org/blog/comparing-document-position/
         */
        public function contains(?Node $node): boolean
        {
                if ($node === NULL) {
                        return false;
                }
                if ($this === $node) {
                        return true; /* inclusive descendant, see spec */
                }

                return ($this->compareDocumentPosition($node) & DOCUMENT_POSITION_CONTAINED_BY) !== 0;
        }

        /**
         * Compares position of this Node against another (in any Document)
         *
         * @param Node $that
         * @return One of the document position constants
         * @spec DOM-LS
         */
        public function compareDocumentPosition(Node $that): integer
        {
                /* CAUTION: The order of these args matters */
                return \domo\whatwg\compare_document_position($that, $this);
        }

        /**
         * Whether this node and another are the same one in the same DOM
         *
         * @param Node $node to compare to this one
         * @return boolean
         * @spec DOM-LS
         */
        public function isSameNode(Node $node): boolean
        {
                return $this === $node;
        }

        /**
         * Determine whether this node and $other are equal
         *
         * @param Node $other - will be compared to $this
         * @return boolean
         * @spec: DOM-LS
         *
         * NOTE:
         * Each subclass of Node has its own criteria for equality.
         * Rather than extend   Node::isEqualNode(),  subclasses
         * must implement   _subclass_isEqualNode(),  which is called
         * from   Node::isEqualNode()  and handles all of the equality
         * testing specific to the subclass.
         *
         * This allows the recursion and other fast checks to be
         * handled here and written just once.
         *
         * Yes, we realize it's a bit weird.
         */
        public function isEqualNode(?Node $node = NULL): boolean
        {
                if ($node === NULL) {
                        /* We're not equal to NULL */
                        return false;
                }
                if ($node->_nodeType !== $this->_nodeType) {
                        /* If we're not the same nodeType, we can stop */
                        return false;
                }

                if (!$this->_subclass_isEqualNode($node)) {
                        /* Run subclass-specific equality comparison */
                        return false;
                }

                /* Call this method on the children of both nodes */
                for (
                        $a=$this->firstChild(), $b=$node->firstChild();
                        $a!==NULL && $b!==NULL;
                        $a=$a->nextSibling(), $b=$b->nextSibling()
                ) {
                        if (!$a->isEqualNode($b)) {
                                return false;
                        }
                }

                /* If we got through all of the children (why wouldn't we?) */
                return $a === NULL && $b === NULL;
        }


        /**
         * Clone this Node
         *
         * @param bool $deep - if true, clone entire subtree
         * @return Node (clone of $this)
         * @spec DOM-LS
         *
         * NOTE:
         * 1. If $deep is false, then no child nodes are cloned, including
         *    any text the node contains (since these are Text nodes).
         * 2. The duplicate returned by this method is not part of any
         *    document until it is added using ::appendChild() or similar.
         * 3. Initially (DOM4)   , $deep was optional with default of 'true'.
         *    Currently (DOM4-LS), $deep is optional with default of 'false'.
         * 4. Shallow cloning is delegated to   _subclass_cloneNodeShallow(),
         *    which needs to be implemented by the subclass.
         *    For a similar pattern, see Node::isEqualNode().
         * 5. All "deep clones" are a shallow clone followed by recursion on
         *    the tree structure, so this suffices to capture subclass-specific
         *    behavior.
         */
        public function cloneNode(bool $deep = false): Node
        {
                /* Make a shallow clone using the delegated method */
                $clone = $this->_subclass_cloneNodeShallow();

                /* If the shallow clone is all we wanted, we're done. */
                if ($deep === false) {
                        return $clone;
                }

                /* Otherwise, recurse on the children */
                for ($n=$this->firstChild(); $n!==NULL; $n=$n->nextSibling()) {
                        /* APPEND DIRECTLY; NO CHECKINSERTVALID */
                        \domo\whatwg\insert_before_or_replace($clone, $n->cloneNode(true), NULL, false);
                }

                return $clone;
        }

        /**
         * Return DOMString containing prefix for given namespace URI.
         *
         * @param string $ns
         * @return string or NULL
         * @spec DOM-LS
         *
         * NOTE
         * Think this function looks weird? It's actually spec:
         * https://dom.spec.whatwg.org/#locate-a-namespace
         */
        public function lookupPrefix(?string $ns): ?string
        {
                return \domo\whatwg\locate_prefix($this, $ns);
        }

        /**
         * Return DOMString containing namespace URI for a given prefix
         *
         * @param string $prefix
         * @return string or NULL
         *
         * NOTE
         * Inverse of Node::lookupPrefix
         */
        public function lookupNamespaceURI(?string $prefix): ?string
        {
                return \domo\whatwg\locate_namespace($this, $prefix);
        }

        /**
         * Determine whether this is the default namespace
         *
         * @param string $ns
         * @return boolean
         */
        public function isDefaultNamespace(?string $ns): boolean
        {
                return ($ns ?? NULL) === $this->lookupNamespaceURI(NULL);
        }


	/**********************************************************************
	 * UTILITY METHODS AND DOMO EXTENSIONS
	 *********************************************************************/
        /* Called by Document::adoptNode */
        protected function __set_owner(Document $doc)
        {
                $this->_ownerDocument = $doc;

                /* lastmod is based on owner document */
                $this->__lastmod_zero();

                if (method_exists($this, "tagName")) {
                        /* Element subclasses might need to change case */
                        $this->_tagName = NULL;
                }

                for ($n=$this->firstChild(); $n!==NULL; $n=$n->nextSibling()) {
                        $n->__set_owner($n, $owner);
                }
        }

        /**
         * Determine whether this Node is rooted (belongs to a tree)
         *
         * return: boolean
         *
         * NOTE
         * A Node is rooted if it belongs to a tree, in which case it will
         * have an ownerDocument. Document nodes maintain a list of all the
         * nodes inside their tree, assigning each an index, Node::_nid.
         *
         * Therefore if we are currently rooted, we can tell by checking that
         * we have one of these.
         *
         * TODO: This should be Node::isConnected(), see spec.
         */
        public function __is_rooted(): boolean
        {
                return !!$this->__nid;
        }

        /* Called by \domo\whatwg\insert_before_or_replace */
        protected function __root(): void
        {
                $doc = $this->ownerDocument();

                $doc->__add_to_node_table($this);

                if ($this->_nodeType === ELEMENT_NODE) {
                        /* getElementById table */
                        if (NULL !== ($id = $this->getAttribute('id'))) {
                                $doc->__add_to_id_table($id, $this);
                        }
                        /* <SCRIPT> elements use this hook */
                        /* TODO This hook */
                        if ($this->_roothook) {
                                $this->_roothook();
                        }

                        /*
                         * TODO: Why do we only do this for Element?
                         * This is how it was written in Domino. Is this
                         * a bug?
                         */
                        /* RECURSE ON CHILDREN */
                        for ($n=$this->firstChild(); $n!==NULL; $n=$n->nextSibling()) {
                                $n->__root();
                        }
                }
        }

        protected function __uproot(): void
        {
                $doc = $this->ownerDocument();

                /* Manage id to element mapping */
                if ($this->_nodeType === ELEMENT_NODE) {
                        if (NULL !== ($id = $this->getAttribute('id'))) {
                                $doc->__remove_from_id_table($id, $this);
                        }
                }

                /*
                 * TODO: And here we don't restrict to ELEMENT_NODE.
                 * Why not? I think this is the intended behavior, no?
                 * Then does that make the behavior in root() a bug?
                 * Go over with Scott.
                 */
                for ($n=$this->firstChild(); $n!==NULL; $n=$n->nextSibling()) {
                        $n->__uproot();
                }

                $doc->__remove_from_node_table($this);
        }

        /**
         * The document this node is associated to.
         *
         * @return Document
         * @spec DOM-LS
         *
         * NOTE
         * How is this different from ownerDocument? According to DOM-LS,
         * Document::ownerDocument() must equal NULL, even though it's often
         * more convenient if a document is its own owner.
         *
         * What we're looking for is the "node document" concept, as laid
         * out in the DOM-LS spec:
         *
         *      -"Each node has an associated node document, set upon creation,
         *       that is a document."
         *
         *      -"A node's node document can be changed by the 'adopt'
         *       algorithm."
         *
         *      -"The node document of a document is that document itself."
         *
         *      -"All nodes have a node document at all times."
         *
         * TODO
         * Does the DOM-LS method Node::getRootNode (not implemented here)
         * in its non-shadow-tree branch, do the same thing?
         */
        public function __node_document(): Document
        {
                return $this->_ownerDocument ?? $this;
        }

        /**
         * The index of this Node in its parent's childNodes list
         *
         * @return integer index
         * @throw Something if we have no parent
         *
         * NOTE
         * Calling Node::__index() will automatically trigger a switch
         * to the NodeList representation (see Node::childNodes()).
         */
        public function __index(): integer
        {
                if ($this->_parentNode === NULL) {
                        return 0; /* ??? TODO: throw or make an error ??? */
                }

                if ($this === $this->_parentNode->firstChild()) {
                        return 0;
                }

                /* We fire up the NodeList mode */
                $childNodes = $this->_parentNode->childNodes();

                /* We end up re-indexing here if we ever run into trouble */
                if ($this->_index === NULL || $childNodes[$this->_index] !== $this) {
                        /*
                         * Ensure that we don't have an O(N^2) blowup
                         * if none of the kids have defined indices yet
                         * and we're traversing via nextSibling or
                         * previousSibling
                         */
                        foreach ($childNodes as $i => $child) {
                                $child->_index = $i;
                        }

                        \domo\assert($childNodes[$this->_index] === $this);
                }
                return $this->_index;
        }

        /**
         * Remove all of the Node's children.
         *
         * NOTE
         * Provides minor optimization over iterative calls to
         * Node::removeChild(), since it calls Node::modify() once.
         */
        public function __remove_children()
        {
                if ($this->__is_rooted()) {
                        $root = $this->_ownerDocument;
                } else {
                        $root = NULL;
                }

                /* Go through all the children and remove me as their parent */
                for ($n=$this->firstChild(); $n!==NULL; $n=$n->nextSibling()) {
                        if ($root !== NULL) {
                                /* If we're rooted, mutate */
                                $root->__mutate_remove($n);
                        }
                        $n->_parentNode = NULL;
                }

                /* Remove the child node memory or references on this node */
                if ($this->_childNodes !== NULL) {
                        /* BRANCH: NodeList (array-like) */
                        $this->_childNodes = new NodeList();
                } else {
                        /* BRANCH: circular linked list */
                        $this->_firstChild = NULL;
                }

                /* Update last modified type once only (minor optimization) */
                $this->__lastmod_update();
        }


        /*
         * Convert the children of a node to an HTML string.
         * This is used by the innerHTML getter
         */
        public function serialize()
        {
                $s = "";

                for ($n=$this->firstChild(); $n!==NULL; $n=$n->nextSibling()) {
                        $s += \domo\whatwg\serialize_node($n, $this);
                }

                return $s;
        }

        public function outerHTML(string $value = NULL)
        {
                if ($value == NULL) {
                        /* TODO Need a way to instantiate a mock parent Node
                         * with nodeType = 0, like this */
                        //return \domo\whatwg\serialize_node($this, { $nodeType: 0 });
                } else {
                        /* not yet implemented */
                }
        }

        /**
         * Return lastModTime value for this Node
         *
         * @return integer
         *
         * NOTE The _lastModTime value is used as a cache invalidation
         * mechanism. If the node does not already have one, it will be
         * initialized from the owner document's modclock property.
         * (modclock does not return the actual time; it is simply a
         * counter incremented on each document modification)
         */
        public function __lastmod(): integer
        {
                if (!$this->___lastmod) {
                        $this->___lastmod = $this->__node_document()->__modclock;
                }
                return $this->___lastmod;
        }

        /* Called when being adopted into a new owner document, since
         * the modtimes are by-owner.
         */
        public function __lastmod_zero(): void
        {
                $this->___lastmod = 0;
        }

        /**
         * Assigns a new _lastModTime to this Node and all ancestors.
         *
         * @return void
         *
         * NOTE
         * Increments the owner document's modclock and uses the new
         * value to update the lastModTime value for this node and
         * all of its ancestors.
         *
         * Nodes that have never had their lastModTime value queried
         * do not need to have a lastModTime property set on them since
         * there is no previously queried value to ever compare the new
         * value against, so this will only update nodes that already
         * have a _lastModTime property.
         */
        public function __lastmod_update(): void
        {
                /* Skip while doc.modclock == 0 */
                if ($this->__node_document()->__modclock) {
                        $time = ++$this->__node_document()->__modclock;

                        for ($n=$this; $n!==NULL; $n=$n->parentElement()) {
                                if ($n->___lastmod) {
                                        $n->___lastmod = $time;
                                }
                        }
                }
        }
}


?>
