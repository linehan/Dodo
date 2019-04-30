<?php
/******************************************************************************
 * Reflecting content attributes in IDL attributes
 *
 * http://html.spec.whatwg.org/#reflecting-content-attributes-in-idl-attributes
 *
 * From the spec:
 * "Some IDL attributes are defined to reflect a particular content attribute.
 * This means that on getting, the IDL attribute returns the current value of
 * the content attribute, and on setting, the IDL attribute changes the value
 * of the content attribute to the given value."
 *
 * Many HTML Elements have well-defined interfaces expressed as attributes.
 * These attributes may be typed, have an enumerated set of allowed values,
 * have default values, and so on.
 *
 * This family of classes allows us to implement these reflected attributes.
 ******************************************************************************/
namespace domo;


/*
 * USAGE:
 * For each specialized attribute on an Element, build a reflected
 * attribute object and add its access functions to the magic
 * __get() and/or __set() functions.
 *
 * (As of PHP 7 this is the only way to implement implicit accessors,
 * which must be done to comply with spec.)
 *
 * The switch in Element::__get()/Element::__set() will call
 * ReflectedAttr::get or ReflectedAttr::set after looking the
 * object up in a table or as a class member like
 * Element::$__attr_<attrname>.
 */

function reflected_attribute($owner, $spec)
{
        if (is_array($spec['type'])) {
                return new IDLReflectedAttributeEnumerated($owner, $spec);
        } else {
                switch ($spec['type']) {
                case 'url':
                        return new IDLReflectedAttributeURL($owner, $spec);
                case 'boolean':
                        return new IDLReflectedAttributeBoolean($owner, $spec);
                case 'number':
                case 'long':
                case 'unsigned long':
                case 'limited unsigned long with fallback':
                        return new IDLReflectedAttributeNumeric($owner, $spec);
                case 'function':
                        return new IDLReflectedAttributeFunction($owner, $spec);
                case 'string':
                default:
                        return new IDLReflectedAttributeString($owner, $spec);
                }
        }
}


class IDLReflectedAttributeBoolean
{
        protected $_elem = NULL;
        protected $_name = NULL;

        public function __construct(Element $elem, $spec)
        {
                $this->_elem = $elem;
                $this->_name = $spec['name'];
        }

        public function get()
        {
                return $this->_elem->hasAttribute($this->_name);
        }

        public function set($value)
        {
                if ($value) {
                        $this->_elem->setAttribute($this->_name, '');
                } else {
                        $this->_elem->removeAttribute($this->_name);
                }
        }
}

/*
 * If a reflecting IDL attribute is a USVString attribute whose content
 * attribute is defined to contain a URL, then on getting, if the content
 * attribute is absent, the IDL attribute must return the empty string.
 * Otherwise, the IDL attribute must parse the value of the content attribute
 * relative to the element's node document and if that is successful, return
 * the resulting URL string. If parsing fails, then the value of the content
 * attribute must be returned instead, converted to a USVString. On setting,
 * the content attribute must be set to the specified new value.
 */
class IDLReflectedAttributeURL
{
        protected $_elem = NULL;
        protected $_name = NULL;

        public function __construct(Element $elem, $spec)
        {
                $this->_elem = $owner;
                $this->_name = $spec['name'];
        }

        public function get()
        {
                $v = $this->_elem->getAttribute($this->_name); 

                if ($v === NULL) {
                        return '';
                }

                if ($this->_elem) {
                        $url = new URL($this->_elem->__node_document()->URL());
                        return $url->resolve($v);
                } else {
                        return $v;
                }
        }

        public function set($value)
        {
                return $this->_elem->setAttribute($this->_name, $value);
        }
}


class IDLReflectedAttributeString
{
        protected $_elem = NULL;
        protected $_name = NULL;
        protected $_treat_null_as_empty = true;

        public function __construct(Element $elem, $spec)
        {
                $this->_elem = $owner;
                $this->_name = $spec['name'];
                $this->_treat_null_as_empty = $spec['treatNullAsEmptyString'];
        }

        public function get()
        {
                return $this->_elem->getAttribute($this->_name) ?? '';
        }

        public function set($value)
        {
                if ($value === NULL && $this->_treat_null_as_empty) {
                        $value = '';
                }
                return $this->_elem->setAttribute($this->_name, $value);
        }
}

/*
 * If a reflecting IDL attribute is a DOMString attribute whose content
 * attribute is an enumerated attribute, and the IDL attribute is limited
 * to only known values, then, on getting, the IDL attribute must return
 * the conforming value associated with the state the attribute is in
 * (in its canonical case), if any, or the empty string if the attribute
 * is in a state that has no associated keyword value or if the attribute
 * is not in a defined state (e.g. the attribute is missing and there is
 * no missing value default). On setting, the content attribute must be
 * set to the specified new value.
 *
 * If a reflecting IDL attribute is a nullable DOMString attribute whose
 * content attribute is an enumerated attribute, then, on getting, if the
 * corresponding content attribute is in its missing value default then the
 * IDL attribute must return null, otherwise, the IDL attribute must return
 * the conforming value associated with the state the attribute is in
 * (in its canonical case). On setting, if the new value is null, the content
 * attribute must be removed, and otherwise, the content attribute must be set
 * to the specified new value.
 *
 * array(
 *      'name' => 'foo'
 *      'type' => array('ltr', 'rtl', 'auto'),
 *      ---------------------------------------- optional
 *      'missing_value_default' => ''
 *      'invalid_value_default' => ''
 *      'is_nullable' => [true|false]
 *      'alias' => 'fooalias'
 * )
 *
 *  'type' => array(array('value'=>'ltr', 'alias'=>'a'), 'auto'),
 */
/* TODO: WE do not implement nullable enumerated attributes yet */
class IDLReflectedAttributeEnumerated
{
        protected $_valid = array();
        protected $_missing_value_default = NULL;
        protected $_invalid_value_default = NULL;
        protected $_is_nullable = false;

        public function __construct(Element $elem, $spec)
        {
                $this->_elem = $owner;
                $this->_name = $spec['name'];
                $this->_is_nullable = $spec['is_nullable'] ?? false;

                foreach ($spec['type'] as $t) {
                        $this->_valid[$t['value'] ?? $t] = $t['alias'] ?? $t;
                }

                $this->_missing_value_default = $spec['missing_value_default'] ?? NULL;
                $this->_invalid_value_default = $spec['invalid_value_default'] ?? NULL;
        }

        public function get()
        {
                /* TODO: used to be _getattr fast path */
                $v = $this->_elem->getAttribute($this->_name);

                if ($v === NULL) {
                        return $this->_default_if_missing;
                }

                if (isset($this->_valid[strtolower($v)])) {
                        return $v;
                }

                return $this->_default_if_invalid ?? $v;
        }

        public function set($value)
        {
                /* TODO: used to be _setattr fast path */
                return $this->_elem->setAttribute($this->_name, $value);
        }
}

/*
 * IDLReflectedAttribute SPEC
 *
 * DOMString
 * If a reflecting IDL attribute is a DOMString attribute whose content
 * attribute is an enumerated attribute, and the IDL attribute is limited
 * to only known values, then, on getting, the IDL attribute must return
 * the conforming value associated with the state the attribute is in
 * (in its canonical case), if any, or the empty string if the attribute
 * is in a state that has no associated keyword value or if the attribute
 * is not in a defined state (e.g. the attribute is missing and there is
 * no missing value default). On setting, the content attribute must be
 * set to the specified new value.
 *
 * If a reflecting IDL attribute is a nullable DOMString attribute whose
 * content attribute is an enumerated attribute, then, on getting, if the
 * corresponding content attribute is in its missing value default then the
 * IDL attribute must return null, otherwise, the IDL attribute must return
 * the conforming value associated with the state the attribute is in
 * (in its canonical case). On setting, if the new value is null, the content
 * attribute must be removed, and otherwise, the content attribute must be set
 * to the specified new value.
 *
 * array(
 *      'type' => array('ltr', 'rtl', 'auto'),
 *      'missing' => ''
 *      'invalid' => ''
 *      'nullable' => [true|false]
 * )
 *
 * DOMString; get/set done in "transparent, case-preserving manner" (spec)
 * array(
 *      'type' => string,
 *      'treat_null_as_empty_string' => [true|false]
 * )
 *

    title: String,
    lang: String,
    dir: {type: ["ltr", "rtl", "auto"], missing: ''},
    accessKey: String,
    hidden: Boolean,
    tabIndex: {type: "long", default: function() {
      if (this.tagName in focusableElements ||
        this.contentEditable)
        return 0;
      else
        return -1;
    }}


/*
 * NOTE
 * There are HTML Elements whose default values are a function
 * of the Element. Therefore this spec must allow for default
 * values to be specified as callback functions!
 *
 * In some cases, e.g. 'tabIndex' attribute of an HTML Element,
 * the value of the attribute is *always* computed from the Element,
 * and so the 'default' function is actually just computing the
 * value!
 */

// See http://www.whatwg.org/specs/web-apps/current-work/#reflect
//
// defval is the default value. If it is a function, then that function
// will be invoked as a method of the element to obtain the default.
// If no default is specified for a given attribute, then the default
// depends on the type of the attribute, but since this function handles
// 4 integer cases, you must specify the default value in each call
//
// min and max define a valid range for getting the attribute.
//
// setmin defines a minimum value when setting.  If the value is less
// than that, then throw INDEX_SIZE_ERR.
//
// Conveniently, JavaScript's parseInt function appears to be
// compatible with HTML's 'rules for parsing integers'

/*
 * define({
 *   tag: 'progress',
 *   ctor: function HTMLProgressElement(doc, localName, prefix) {
 *     HTMLFormElement.call(this, doc, localName, prefix);
 *   },
 *   props: formAssociatedProps,
 *   attributes: {
 *     max: {type: 'number', subtype: 'float', 'default': 1.0, 'min': 0}
 *   }
 * });
 */
class IDLReflectedAttributeNumeric
{
        public $_elem;
        public $_name;
        public $_subtype;

        public $_default;
        public $_default_value;
        public $_max = NULL;
        public $_min = NULL;
        public $_setmin = NULL;

        public function __construct(Element $elem, $spec)
        {
                $this->_elem = $elem;
                $this->_name = $spec['name'];
                $this->_type = $spec['type'] ?? 'number';
                $this->_subtype = $spec['subtype'] ?? 'integer';
                $this->_setmin = $spec['setmin'] ?? NULL;

                if (is_callable($spec['default'])) {
                        $this->_default = $spec['default'];
                } else if (is_numeric($spec['default'])) {
                        $this->_default_value = $spec['default'];
                        $this->_default = function($ctx) {
                                return $ctx->_default_value;
                        };
                }

                if (isset($spec['min'])) {
                        $this->_min = $spec['min'];
                } else {
                        switch ($spec['type']) {
                        case 'unsigned long':
                                $this->_min = 0;
                                break;
                        case 'long':
                                $this->_min = -0x80000000;
                                break;
                        case 'limited unsigned long with fallback':
                                $this->_min = 1;
                                break;
                        }
                }

                if (isset($spec['max'])) {
                        $this->_max = $spec['max'];
                } else {
                        switch ($spec['type']) {
                        case 'unsigned long':
                        case 'long':
                        case 'limited unsigned long with fallback':
                                $this->_max = 0x7FFFFFFF;
                                break;
                        }
                }
        }

        public function get()
        {
                /* TODO: This was the fast path _getattr() */
                $v = $this->_elem->getAttribute($this->_name);

                $n = ($this->_subtype === 'float') ? floatval($v) : intval($v, 10);

                if ($v === NULL
                || !is_finite($n)
                || ($this->_min !== NULL && $n < $this->_min)
                || ($this->_max !== NULL && $n > $this->_max)) {
                        return $this->_default_cb($this);
                }

                switch ($this->_type) {
                case 'unsigned long':
                case 'long':
                case 'limited unsigned long with fallback':
                        if (!preg_match('/^[ \t\n\f\r]*[-+]?[0-9]/', $v)) {
                                return $this->_default($this);
                        }
                        break;
                default:
                        $n = $n | 0;
                        break;
                }

                return $n;
        }

        public function set($v)
        {
                if (!$this->_subtype === 'float') {
                        $v = floor($v);
                }

                if ($this->_setmin !== NULL && $v < $this->_setmin) {
                        \domo\error("IndexSizeError", $this->_name.' set to '.$v);
                }

                switch ($this->_type) {
                case 'unsigned_long':
                        if ($v < 0 || $v > 0x7FFFFFFF) {
                                $v = $this->_default($this);
                        } else {
                                $v = $v | 0;
                        }
                        break;
                case 'limited unsigned long with fallback':
                        if ($v < 1 || $v > 0x7FFFFFFF) {
                                $v = $this->_default($this);
                        } else {
                                $v = $v | 0;
                        }
                case 'long':
                        if ($v < -0x80000000 || $v > 0x7FFFFFFF) {
                                $v = $this->_default($this);
                        } else {
                                $v = $v | 0;
                        }
                }

                return $this->_elem->setAttribute($this->_name, strval($v));
        }
}


?>
