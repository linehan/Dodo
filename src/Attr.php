<?php
/******************************************************************************
 * Attr.php
 * --------
 * The Attr class represents a single attribute node.
 *
 * NOTE
 * The definition of the Attr class has undergone some changes in recent
 * revisions of the DOM spec.
 *
 *      DOM-2: Introduced namespaces, and the properties 'namespaceURI',
 *             'localName', and 'prefix' were defined on the Node class.
 *             As a subclass of Node, Attr inherited these.
 *
 *      DOM-4: Attr was no longer classified as a Node. The properties
 *             'namespaceURI', 'localName', and 'prefix' were removed
 *             from the Node class. They were now defined on the Attr
 *             class itself, as well as on the Element class, which
 *             remained a subclass of Node.
 *
 *      DOM-LS: Attr was re-classified as a Node, but the properties
 *              'namespaceURI', 'localName', and 'prefix' remained on
 *              the Attr class (and Element class), and did not re-appear
 *              on the Node class..
 ******************************************************************************/
/******************************************************************************
 * Qualified Names, Local Names, and Namespace Prefixes
 *
 * An Element or Attribute's qualified name is its local name if its
 * namespace prefix is null, and its namespace prefix, followed by ":",
 * followed by its local name, otherwise.
 ******************************************************************************/
/******************************************************************************
 * NOTES (taken from Domino.js)
 *
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
 ******************************************************************************/
namespace domo;
require_once('Node.php');

/* 
 * SPEC NOTE
 * Attr has gone back and forth between
 * extending Node and being its own 
 * class in recent specs. As of the
 * most recent DOM-LS at the time of this
 * writing (05/03/2019), it extends Node.
 */
class Attr extends Node 
{
        protected const _nodeType = ATTRIBUTE_NODE;

        protected $namespaceURI = NULL;   /* readonly (NULL or non-empty) */
        protected $prefix = NULL;         /* readonly (NULL or non-empty) */
        protected $localName = NULL;      /* readonly, (non-empty) */
        protected $name;                  /* readonly, (non-empty) */
        protected $value = "";            /* (string) */
        protected $ownerElement = NULL;   /* readonly (NULL or Element) */
        protected const specified = true; /* readonly const true */

        public function __construct(
                ?Element $ownerElement,
                string $localName,
                ?string $prefix=NULL,
                ?string $namespaceURI=NULL,
                string $value=""
        ) {
                if ($localName !== '') {
                        /* DOM-LS: Non-empty string */
                        $this->localName = $localName;
                } else {
                        throw Exception("Attr local name must be non-empty");
                }

		if ($namespaceURI !== '') {
                        /* DOM-LS: NULL or non-empty string */
			$this->namespaceURI = $namespaceURI;
		}

		if ($prefix !== '') {
                        /* DOM-LS: NULL or non-empty string */
			$this->prefix = $prefix;
		}

                if ($this->prefix === NULL) {
                        /* 
                         * DOM-LS: qualified name:
                         *      localName if prefix is NULL 
                         */
                        $this->name = $this->localName;
                } else {
                        /* 
                         * DOM-LS: qualified name:
                         *      namespace prefix, followed by ":", 
                         *      followed by local name, otherwise.
                         */
                        $this->name = "$this->prefix:$this->localName";
                }

                /* DOM-LS: NULL or Element */
                $this->ownerElement = $ownerElement;

                /* DOM-LS: String */
                $this->value = $value; 
        }

        /**********************************************************************
         * ACCESSORS
         **********************************************************************/
        public function getNamespaceURI(): ?string
        {
                return $this->namespaceURI;
        }
        public function getSpecified(): boolean
        {
                return $this->specified;
        }
        public function getOwnerElement(): ?Element
        {
                return $this->ownerElement;
        }
        public function getPrefix(): ?string
        {
                return $this->prefix;
        }
        public function getLocalName(): string
        {
                return $this->localName;
        }
        public function getName(): string
        {
                return $this->name;
        }
        public function getValue(): ?string
        {
                return $this->value;
        }
        public function setValue(?string $value = NULL)
        {
                /*
                 * NOTE
                 * You can unset an attribute by calling Attr::value("");
                 */
                $old = $this->value;
                $new = $value;

                if ($new === $old) {
                        return;
                }

                $this->value = $new;

                if ($this->ownerElement
                && (isset($this->ownerElement->__onchange_attr[$this->localName]))) {
                        /*
                         * Elements must take special action if the
                         * value of certain attributes are updated.
                         * This allows the Attr to inform the Element
                         * it has been updated, so the Element can
                         * take the appropriate steps.
                         *
                         * For example, updating the 'id' attribute
                         * will cause a rooted Element to delete its
                         * old id from and add its new id to its
                         * ownerDocument's node id cache.
                         *
                         * WARNING: This is only fired when we modify
                         * the attribute using .value(). This is not
                         * fired when we call Element::removeAttribute,
                         * but that's okay for 'id' and 'class'.
                         */
                        $this->ownerElement->__onchange_attr[$this->localName](
                                $this->ownerElement,
                                $old,
                                $new
                        );
                }

                if ($this->ownerElement->__is_rooted()) {
                        /*
                         * Documents must also sometimes take special action
                         * and be aware of mutations occurring in their tree.
                         * These methods are for that.
                         *
                         * WARNING: This is only fired when we modify
                         * the attribute using .value(). This is not
                         * fired when we call Element::removeAttribute,
                         * but that's okay for 'id' and 'class'.
                         *
                         * TODO: These two mutation handling things
                         * should be combined.
                         *
                         * TODO: Is this trying to implement spec,
                         * or are we just doing this for our own use?
                         */
                        $this->ownerElement->ownerDocument()->__mutate_attr($this, $old);
                }
        }

        /* Delegated from Node */ 
        public function getTextContent(?string $value = NULL)
        {
                return $this->getValue();
        }

        /* Delegated from Node */ 
        public function setTextContent(?string $value = NULL)
        {
                return $this->setValue($value);
        }

        /* Delegated from Node */
        public function _subclass_cloneNodeShallow(): ?Node
        {
                return new Attr(
                        NULL,
                        $this->localName,
                        $this->prefix,
                        $this->namespaceURI,
                        $this->value
                );
        }

        /* Delegated from Node */
        public function _subclass_isEqualNode(Node $node): bool
        {
                return ($this->namespaceURI === $node->namespaceURI
                && $this->localName === $node->localName
                && $this->value === $node->value);
        }
}

?>
