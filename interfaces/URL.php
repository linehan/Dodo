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

?>
