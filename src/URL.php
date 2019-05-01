<?php
/******************************************************************************
 * URL
 *
 * TODO: This is currently NOT implementing https://url.spec.whatwg.org/#api,
 * but it could, and probably should.
 ******************************************************************************/
namespace domo;

// Return a percentEncoded version of s.
// S should be a single-character string
// XXX: needs to do utf-8 encoding?
function percent_encode(string $s) 
{
        return rawurlencode($s); /* Yes? */
        //var c = s.charCodeAt(0);
        //if (c < 256) return "%" + c.toString(16);
        //else throw Error("can't percent-encode codepoints > 255 yet");
}

function merge(URL $basepath, URL $refpath) 
{
        if ($base->host !== NULL && !$base->path) {
                return "/$refPath";
        }

        if (false === ($lastslash = strrpos($basepath, '/'))) {
                return $refpath;
        } else {
                return substr($basepath, 0, $lastslash+1) . $refpath;
        }
}

function remove_dot_segments($path) 
{
        if (!$path) {
                return $path; // For "" or NULL 
        }

        $output = "";
        $match = array();
      
        while (strlen($path) > 0) {
                if ($path === "." || $path === "..") {
                        $path = "";
                        break;
                }

                $twochars = substr($path, 0, 2);
                $threechars = substr($path, 0, 3);
                $fourchars = substr($path, 0, 4);

                if ($threechars === "../") {
                        $path = substr($path, 3);
                } else if ($twochars === "./") {
                        $path = substr($path, 2);
                } else if ($threechars === "/./") {
                        $path = "/" . substr($path, 3);
                } else if ($twochars === "/." && strlen($path) === 2) {
                        $path = "/";
                } else if ($fourchars === "/../" || ($threechars === "/.." && strlen($path) === 3)) {
                        $path = "/" . substr($path, 4);
                        
                        $output = preg_replace('/\/?[^\/]*$/', "", $output);
                } else {
                        preg_match('/(\/?([^\/]*))/', $path, $match);
                        $segment = $match[0];
          
                        $output .= $segment;
          
                        $path = substr($path, strlen($segment));
                }
        }

        return $output;
}


class URL
{
        const pattern = '/^(([^:\/?#]+):)?(\/\/([^\/?#]*))?([^?#]*)(\?([^#]*))?(#(.*))?$/';
        const userinfoPattern = '/^([^@:]*)(:([^@]*))?@/';
        const portPattern = '/:\d+$/';
        const authorityPattern = '/^[^:\/?#]+:\/\//';
        const hierarchyPattern = '/^[^:\/?#]+:\//';

        public $scheme = NULL;
        public $host = NULL; /* contains the hostname followed by ':' and port if specified */
        public $port = NULL;
        public $username = NULL;
        public $password = NULL;
        public $path = NULL;
        public $query = NULL;
        public $fragment = NULL;

        /* TODO: Others from the spec (not covered) */
        public $hostname = NULL; /* just the hostname (no port) */
        public $pathname = NULL; /* contains '/' followed by path of URL */
        public $search = NULL; /* contains '?' followed by parameters of URL */
        public $searchParams = NULL; /* URLSearchParams allowing access to GET args */
        public $hash = NULL; /* Containing '#' followed by the fragment identifier */

        public function __construct(string $url='')
        {
                /*
                 * BEWARE: Can't use trim() since it defines whitespace 
                 * differently than HTML
                 *
                 * TODO: Is this still valid in PHP? 
                 */
                $this->url = preg_replace('/^[ \t\n\r\f]+|[ \t\n\r\f]+$/', '', $url);

                /* See http://tools.ietf.org/html/rfc3986#appendix-B */
                /* and https://url.spec.whatwg.org/#parsing */
                $match = array();
                if (preg_match(URL::pattern, $this->url, $match)) {
                        if (isset($match[2]) && $match[2] !== '') {
                                $this->scheme = $match[2];
                        }
                        if (isset($match[4]) && $match[4] !== '') {
                                // parse username/password
                                $userinfo = array();
                                if (preg_match(URL::userinfoPattern, $match[4], $userinfo)) {
                                        $this->username = $userinfo[1];
                                        $this->password = $userinfo[3];

                                        $match[4] = substr($match[4], strlen($userinfo[0]));
                                }
                                if (preg_match(URL::portPattern, $match[4])) {
                                        $pos = strrpos($match[4], ':');
                                        $this->host = substr($match[4], 0, $pos);
                                        $this->port = substr($match[4], $pos+1);
                                } else {
                                        $this->host = $match[4];
                                }
                        }
                        if (isset($match[5]) && $match[5] !== '') {
                                $this->path = $match[5];
                        }
                        if (isset($match[6]) && $match[6] !== '') {
                                $this->query = $match[7];
                        }
                        if (isset($match[8]) && $match[8] !== '') {
                                $this->fragment = $match[9];
                        }
                }
        }

        // XXX: not sure if this is the precise definition of absolute
        public function isAbsolute()
        { 
                return !!$this->scheme; 
        }
        public function isAuthorityBased() 
        {
                return !!preg_match(URL::authorityPattern, $this->url);
        }
  
        public function isHierarchical() 
        {
                return !!preg_match(URL::hierarchyPattern, $this->url);
        }

        public function toString() 
        {
                $s = "";

                if ($this->scheme !== NULL) {
                        $s .= $this->scheme . ':';
                }
                if ($this->isAbsolute()) {
                        $s .= '//';
                        if ($this->username || $this->password) {
                                /* BEWARE: tested NULL and '' */ 
                                if ($this->username !== NULL) {
                                        $s .= $this->username; 
                                }
                                if ($this->password) { 
                                        /* BEWARE: tested NULL and '' */ 
                                        $s .= ':' . $this->pass;
                                }
                                $s .= '@';
                        }
                        if ($this->host !== NULL) {
                                $s .= $this->host; 
                        }
                }
                if ($this->port !== NULL) {
                        $s .= ':' . $this->port;
                }
                if ($this->path !== NULL) {
                        $s .= $this->path;
                }
                if ($this->query !== NULL) {
                        $s .= '?' . $this->query;
                }
                if ($this->fragment !== NULL) {
                        $s .= '#' . $this->fragment;
                }
                return $s;
        }

        /* See: http://tools.ietf.org/html/rfc3986#section-5.2 */
        /* and https://url.spec.whatwg.org/#constructors */
        public function resolve($relative) 
        {
                $base = $this;            // The base url we're resolving against
                $r = new URL($relative);  // The relative reference url to resolve
                $t = new URL();           // The absolute target url we will return

                if ($r->scheme !== NULL) {
                        $t->scheme = $r->scheme;
                        $t->username = $r->username;
                        $t->password = $r->password;
                        $t->host = $r->host;
                        $t->port = $r->port;
                        $t->path = remove_dot_segments($r->path);
                        $t->query = $r->query;
                } else {
                        $t->scheme = $base->scheme;

                        if ($r->host !== NULL) {
                                $t->username = $r->username;
                                $t->password = $r->password;
                                $t->host = $r->host;
                                $t->port = $r->port;
                                $t->path = remove_dot_segments($r->path);
                                $t->query = $r->query;
                        } else {
                                $t->username = $base->username;
                                $t->password = $base->password;
                                $t->host = $base->host;
                                $t->port = $base->port;
                        
                                if (!$r->path) { 
                                        /* non-NULL and non-empty */
                                        $t->path = $base->path;
                                        $t->query = $r->query ?? $base->query; 
                                } else {
                                        if ($r->path[0] === '/') {
                                                $t->path = remove_dot_segments($r->path);
                                        } else {
                                                $t->path = merge($base->path, $r->path);
                                                $t->path = remove_dot_segments($t->path);
                                        }
                                        $t->query = $r->query;
                                }
                        }
                }
                $t->fragment = $r->fragment;

                return $t->toString();
        }
}


/* TODO TODO TODO */

//abstract class URLUtils
//{
        //abstract function href(string $val=NULL);

        //private function _url()
        //{
                /*
                 * XXX: this should do the "Reinitialize url" steps,
                 * and "null" should be a valid return value.
                 */
                //return new URL($this->href());
        //}

        //public function protocol(string $val=NULL)
        //{
                //if ($val == NULL) {
                        //[> GET <]
                        //$url = $this->_url();
                        //if ($url && $url->scheme) {
                                //return $url->scheme . ":";
                        //} else {
                                //return ":";
                        //}
                //} else {
                        //[> SET <]
                        //$out = $this->href();
                        //$url = new URL($out);
                        //if ($url->isAbsolute()) {
                                //$val = $val


                //}
        //}
    //},
    //set: function(v) {
      //var output = this.href;
      //var url = new URL(output);
      //if (url.isAbsolute()) {
        //v = v.replace(/:+$/, "");
        //v = v.replace(/[^-+\.a-zA-Z0-9]/g, URL.percentEncode);
        //if (v.length > 0) {
          //url.scheme = v;
          //output = url.toString();
        //}
      //}
      //this.href = output;
    //},
  //},

  //host: {
    //get: function() {
      //var url = this._url;
      //if (url.isAbsolute() && url.isAuthorityBased())
        //return url.host + (url.port ? (":" + url.port) : "");
      //else
        //return "";
    //},
    //set: function(v) {
      //var output = this.href;
      //var url = new URL(output);
      //if (url.isAbsolute() && url.isAuthorityBased()) {
        //v = v.replace(/[^-+\._~!$&'()*,;:=a-zA-Z0-9]/g, URL.percentEncode);
        //if (v.length > 0) {
          //url.host = v;
          //delete url.port;
          //output = url.toString();
        //}
      //}
      //this.href = output;
    //},
  //},

  //hostname: {
    //get: function() {
      //var url = this._url;
      //if (url.isAbsolute() && url.isAuthorityBased())
        //return url.host;
      //else
        //return "";
    //},
    //set: function(v) {
      //var output = this.href;
      //var url = new URL(output);
      //if (url.isAbsolute() && url.isAuthorityBased()) {
        //v = v.replace(/^\/+/, "");
        //v = v.replace(/[^-+\._~!$&'()*,;:=a-zA-Z0-9]/g, URL.percentEncode);
        //if (v.length > 0) {
          //url.host = v;
          //output = url.toString();
        //}
      //}
      //this.href = output;
    //},
  //},

  //port: {
    //get: function() {
      //var url = this._url;
      //if (url.isAbsolute() && url.isAuthorityBased() && url.port!==undefined)
        //return url.port;
      //else
        //return "";
    //},
    //set: function(v) {
      //var output = this.href;
      //var url = new URL(output);
      //if (url.isAbsolute() && url.isAuthorityBased()) {
        //v = '' + v;
        //v = v.replace(/[^0-9].*$/, "");
        //v = v.replace(/^0+/, "");
        //if (v.length === 0) v = "0";
        //if (parseInt(v, 10) <= 65535) {
          //url.port = v;
          //output = url.toString();
        //}
      //}
      //this.href = output;
    //},
  //},

  //pathname: {
    //get: function() {
      //var url = this._url;
      //if (url.isAbsolute() && url.isHierarchical())
        //return url.path;
      //else
        //return "";
    //},
    //set: function(v) {
      //var output = this.href;
      //var url = new URL(output);
      //if (url.isAbsolute() && url.isHierarchical()) {
        //if (v.charAt(0) !== "/")
          //v = "/" + v;
        //v = v.replace(/[^-+\._~!$&'()*,;:=@\/a-zA-Z0-9]/g, URL.percentEncode);
        //url.path = v;
        //output = url.toString();
      //}
      //this.href = output;
    //},
  //},

  //search: {
    //get: function() {
      //var url = this._url;
      //if (url.isAbsolute() && url.isHierarchical() && url.query!==undefined)
        //return "?" + url.query;
      //else
        //return "";
    //},
    //set: function(v) {
      //var output = this.href;
      //var url = new URL(output);
      //if (url.isAbsolute() && url.isHierarchical()) {
        //if (v.charAt(0) === "?") v = v.substring(1);
        //v = v.replace(/[^-+\._~!$&'()*,;:=@\/?a-zA-Z0-9]/g, URL.percentEncode);
        //url.query = v;
        //output = url.toString();
      //}
      //this.href = output;
    //},
  //},

  //hash: {
    //get: function() {
      //var url = this._url;
      //if (url == null || url.fragment == null || url.fragment === '') {
        //return "";
      //} else {
        //return "#" + url.fragment;
      //}
    //},
    //set: function(v) {
      //var output = this.href;
      //var url = new URL(output);

      //if (v.charAt(0) === "#") v = v.substring(1);
      //v = v.replace(/[^-+\._~!$&'()*,;:=@\/?a-zA-Z0-9]/g, URL.percentEncode);
      //url.fragment = v;
      //output = url.toString();

      //this.href = output;
    //},
  //},

  //username: {
    //get: function() {
      //var url = this._url;
      //return url.username || '';
    //},
    //set: function(v) {
      //var output = this.href;
      //var url = new URL(output);
      //if (url.isAbsolute()) {
        //v = v.replace(/[\x00-\x1F\x7F-\uFFFF "#<>?`\/@\\:]/g, URL.percentEncode);
        //url.username = v;
        //output = url.toString();
      //}
      //this.href = output;
    //},
  //},

  //password: {
    //get: function() {
      //var url = this._url;
      //return url.password || '';
    //},
    //set: function(v) {
      //var output = this.href;
      //var url = new URL(output);
      //if (url.isAbsolute()) {
        //if (v==='') {
          //url.password = null;
        //} else {
          //v = v.replace(/[\x00-\x1F\x7F-\uFFFF "#<>?`\/@\\]/g, URL.percentEncode);
          //url.password = v;
        //}
        //output = url.toString();
      //}
      //this.href = output;
    //},
  //},

  //origin: { get: function() {
    //var url = this._url;
    //if (url == null) { return ''; }
    //var originForPort = function(defaultPort) {
      //var origin = [url.scheme, url.host, +url.port || defaultPort];
      //// XXX should be "unicode serialization"
      //return origin[0] + '://' + origin[1] +
        //(origin[2] === defaultPort ? '' : (':' + origin[2]));
    //};
    //switch (url.scheme) {
    //case 'ftp':
      //return originForPort(21);
    //case 'gopher':
      //return originForPort(70);
    //case 'http':
    //case 'ws':
      //return originForPort(80);
    //case 'https':
    //case 'wss':
      //return originForPort(443);
    //default:
      //// this is what chrome does
      //return url.scheme + '://';
    //}
  //} },

  //[>
  //searchParams: {
    //get: function() {
      //var url = this._url;
      //// XXX
    //},
    //set: function(v) {
      //var output = this.href;
      //var url = new URL(output);
      //// XXX
      //this.href = output;
    //},
  //},
  //*/
//});

?>
