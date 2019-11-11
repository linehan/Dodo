<?php
/******************************************************************************
 * NamedNodeMap.php
 * ----------------
 * Implements a NamedNodeMap. Used to represent Element::attributes.
 *
 * NOTE: Why is it called NamedNodeMap?
 *
 *      NamedNodeMap has nothing to do with Nodes, it's a collection
 *      of Attrs. But once upon a time, an Attr was a type of Node called a
 *      NamedNode. But then DOM-4 came along and said that an Attr is no
 *      longer a subclass of Node. But then DOM-LS came and change it again,
 *      and said it was a subclass of Node. NamedNode was forgotten, but it
 *      lives on in this interface's name! How confusing!
 *
 * NOTE: This looks different from Domino.js!
 *
 *      In Domino.js, NamedNodeMap was only implemented to satisfy
 *      'instanceof' type-checking. Almost all of the methods were
 *      stubbed, except for 'length' and 'item'. The tables that
 *      stored an Element's attributes were located directly on the
 *      Element itself.
 *
 *      Because there are so many attribute handling methods on an
 *      Element, each with little differences, this meant replicating
 *      a bunch of the book-keeping inside those methods. The negative
 *      impact on code maintainability was pronounced, so the book-keeping
 *      was transferred to the NamedNodeMap itself, and its methods were
 *      properly implemented, which made it much easier to read and write
 *      the attribute methods on the Element class.
 *
 ******************************************************************************/
namespace Dodo;

require_once('utilities.php');

class NamedNodeMap extends \ArrayObject
{
        private $__qname_to_attr = array(); /* qname => Attr */
        private $__lname_to_attr = array(); /* ns|lname => Attr */
        private $__lname_to_index = array(); /* ns|lname => N */
        /* NOW IMPLEMENTED AS $this[], the default */
        //public $index_to_attr = array(); [> N => Attr <]

        /* DOM-LS associated element, defined in spec but not given property. */
        public $_element= NULL;

        public function __construct(?Element $element=NULL)
        {
                $this->_element = $element;
        }

        /**********************************************************************
         * Dodo INTERNAL BOOK-KEEPING
         **********************************************************************/
        private function __append(Attr $a)
        {
                $qname = $a->name();

                /* NO COLLISION */
                if (!isset($this->__qname_to_attr[$qname])) {
                        $this->__qname_to_attr[$qname] = $a;
                /* COLLISION */
                } else {
                        if (is_array($this->__qname_to_attr[$qname])) {
                                $this->__qname_to_attr[$qname][] = $a;
                        } else {
                                $this->__qname_to_attr[$qname] = array(
                                        $this->__qname_to_attr[$qname],
                                        $a
                                );
                        }
                }

                $key = $a->namespaceURI() . '|' . $a->localName();

                $this->__lname_to_attr[$key] = $a;
                $this->__lname_to_index[$key] = count($this);
                $this[] = $a;
        }

        private function __replace(Attr $a)
        {
                $qname = $a->name();

                /* NO COLLISION */
                if (!isset($this->__qname_to_attr[$qname])) {
                        $this->__qname_to_attr[$qname] = $a;
                /* COLLISION */
                } else {
                        if (is_array($this->__qname_to_attr[$qname])) {
                                $this->__qname_to_attr[$qname][] = $a;
                        } else {
                                $this->__qname_to_attr[$qname] = array(
                                        $this->__qname_to_attr[$qname],
                                        $a
                                );
                        }
                }

                $key = $a->namespaceURI() . '|' . $a->localName();

                $this->__lname_to_attr[$key] = $a;
                $this[$this->__lname_to_index[$key]] = $a;
        }

        private function __remove(Attr $a)
        {
                $qname = $a->name();
                $key = $a->namespaceURI() . '|' . $a->localName();

                unset($this->__lname_to_attr[$key]);
                $i = $this->__lname_to_index[$key];
                unset($this->__lname_to_index[$key]);

                array_splice($this, $i, 1);

                if (isset($this->__qname_to_attr[$qname])) {
                        if (is_array($this->__qname_to_attr[$qname])) {
                                $i = array_search($a, $this->__qname_to_attr[$qname]);
                                if ($i !== false) {
                                        array_splice($this->__qname_to_attr[$qname], $i, 1);
                                }
                        } else {
                                unset($this->__qname_to_attr[$qname]);
                        }
                }
        }

        /**********************************************************************
         * DOM-LS Methods
         **********************************************************************/

        public function length(): int
        {
                return count($this);
        }

        public function item(int $index): ?Attr
        {
                return $this[$index] ?? NULL;
        }

        public function hasNamedItem(string $qname): bool
        {
                /*
                 * Per HTML spec, we normalize qname before lookup,
                 * even though XML itself is case-sensitive.
                 */
                if (!ctype_lower($qname) && $this->_element->isHTMLElement()) {
                        $qname = \Dodo\to_ascii_lower_case($qname);
                }

                return isset($this->__qname_to_attr[$qname]);
        }

        public function hasNamedItemNS(?string $ns, string $lname): bool
        {
                $ns = $ns ?? "";
                return isset($this->__lname_to_attr["$ns|$lname"]);
        }

        public function getNamedItem(string $qname): ?Attr
        {
                /*
                 * Per HTML spec, we normalize qname before lookup,
                 * even though XML itself is case-sensitive.
                 */
                if (!ctype_lower($qname) && $this->_element->isHTMLElement()) {
                        $qname = \Dodo\to_ascii_lower_case($qname);
                }

                if (!isset($this->__qname_to_attr[$qname])) {
                        return NULL;
                }

                if (is_array($this->__qname_to_attr[$qname])) {
                        return $this->__qname_to_attr[$qname][0];
                } else {
                        return $this->__qname_to_attr[$qname];
                }
        }

        public function getNamedItemNS(?string $ns, string $lname): ?Attr
        {
                $ns = $ns ?? "";
                return $this->__lname_to_attr["$ns|$lname"] ?? NULL;
        }

        public function setNamedItem(Attr $attr): ?Attr
        {
                $owner = $attr->ownerElement();

                if ($owner !== NULL && $owner !== $this->_element) {
                        \Dodo\error("InUseAttributeError");
                }

                $oldAttr = $this->getNamedItem($attr->name());

                if ($oldAttr == $attr) {
                        return $attr;
                }

                if ($oldAttr !== NULL) {
                        $this->__replace($attr);
                } else {
                        $this->__append($attr);
                }

                return $oldAttr;
        }

        public function setNamedItemNS(Attr $attr): ?Attr
        {
                $owner = $attr->ownerElement();

                if ($owner !== NULL && $owner !== $this->_element) {
                        \Dodo\error("InUseAttributeError");
                }

                $oldAttr = $this->getNamedItemNS($attr->namespaceURI(), $attr->localName());

                if ($oldAttr == $attr) {
                        return $attr;
                }

                if ($oldAttr !== NULL) {
                        $this->__replace($attr);
                } else {
                        $this->__append($attr);
                }

                return $oldAttr;
        }

        /* NOTE: qname may be lowercase or normalized in various ways */
        public function removeNamedItem(string $qname): ?Attr
        {
                $attr = $this->getNamedItem($qname);
                if ($attr !== NULL) {
                        $this->__remove($attr);
                } else {
                        \Dodo\error("NotFoundError");
                }
                return $attr;
        }

        /* NOTE: qname may be lowercase or normalized in various ways */
        public function removeNamedItemNS(?string $ns, string $lname): ?Attr
        {
                $attr = $this->getNamedItemNS($ns, $lname);
                if ($attr !== NULL) {
                        $this->__remove($attr);
                } else {
                        \Dodo\error("NotFoundError");
                }
                return $attr;
        }
}

?>
