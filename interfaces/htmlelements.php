<?php

namespace domo;

var attributes = require('./attributes');

/*
define({
  tag: 'iframe',
  ctor: function HTMLIFrameElement(doc, localName, prefix) {
    HTMLElement.call(this, doc, localName, prefix);
    var Window = require('./Window'); // Avoid circular dependencies.
    this._contentWindow = new Window();
  },
  props: {
    contentWindow: { get: function() {
      return this._contentWindow;
    } },
    contentDocument: { get: function() {
      return this.contentWindow.document;
    } },
  },
  attributes: {
    src: URL,
    srcdoc: String,
    name: String,
    width: String,
    height: String,
    // XXX: sandbox is a reflected settable token list
    seamless: Boolean,
    allowFullscreen: Boolean,
    allowUserMedia: Boolean,
    allowPaymentRequest: Boolean,
    referrerPolicy: REFERRER,
    // Obsolete
    align: String,
    scrolling: String,
    frameBorder: String,
    longDesc: URL,
    marginHeight: { type: String, treatNullAsEmptyString: true },
    marginWidth: { type: String, treatNullAsEmptyString: true },
  }
});
*/
function build_attributes($owner, $spec_array)
{
        $ret = array();

        foreach ($spec_array as $name => $spec) {
                $ret[$name] = define_attribute($owner, $spec);
        }

        return $ret;
}

class HTMLImgElement extends HTMLElement
{
        private $_prop;
        private $_attr;

        public function __construct ($doc, $lname, $prefix)
        {
                parent::__construct($doc, $lname, $prefix);
                $this->_attr = build_attributes(array(
                        'alt' => string,
                        'src' => URL,
                        'srcset' => string,
                        'crossOrigin' => CORS,
                        'useMap' => string,
                        'isMap' => boolean,
                        'height' => array('type'=>'unsigned long', 'default'=>0),
                        'width' => array('type'=>'unsigned long', 'default'=>0),
                        'referrerPolicy' => REFERRER,
                        /* obsolete */
                        'name' => string,
                        'lowsrc' => URL
                        'align' => string,
                        'hspace' => array('type'=>'unsigned long', 'default'=>0),
                        'vspace' => array('type'=>'unsigned long', 'default'=>0),
                        'longDesc'=> URL,
                        'border' => array('type'=>string, 'is_nullable'=>true)
                ));
        }
        public function __get($name)
        {
                if (isset($this->_attr[$name])) {
                        return $this->_attr[$name]->get();
                }
        }
        public function __set($name, $value)
        {
                if (isset($this->_attr[$name])) {
                        $this->_attr[$name]->set($value);
                }
        }
}

class HTMLIFrameElement extends HTMLElement
{
        private $_attr;

        public function __construct ($doc, $lname, $prefix)
        {
                parent::__construct($doc, $lname, $prefix);
                $this->_attr = build_attributes(array(
                        'src' => 'URL',
                        'srcdoc' => 'string',
                        'name' => 'string',
                        'width' => 'string',
                        'height' => 'string',
                        'seamless' => 'boolean',
                        'allowFullscreen' => 'boolean',
                        'allowUserMedia' => 'boolean',
                        'allowPaymentRequest' => 'boolean',
                        'referrerPolicy' => REFERRER,
                        'align' => 'string',
                        'scrolling' => 'string',
                        'frameBorder' => 'string',
                        'longDesc' => 'URL',
                        'marginHeight' => array('type'=>'string', 'is_nullable'=>true),
                        'marginWidth' => array('type'=>'string', 'is_nullable'=>true)
                ));
        }

        public function __get($name)
        {
                if (isset($this->_attr[$name])) {
                        return $this->_attr[$name]->get();
                }
        }
        public function __set($name, $value)
        {
                if (isset($this->_attr[$name])) {
                        $this->_attr[$name]->set($value);
                }
        }
}
