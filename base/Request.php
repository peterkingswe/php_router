<?php

// https://medium.com/the-andela-way/how-to-build-a-basic-server-side-routing-system-in-php-e52e613cf241
class Request implements IRequest
{
    public string $requestMethod = '';
    public string $httpXRequestedWith = '';
    public bool $isAjax;
    #todo test supportedHttpMethods
    // public static $supportedHttpMethods = [
    public array $supportedHttpMethods = [
      "get",
      "post",
      "put",
      "delete"
    ];

    public function __construct(Closure $initCbf = null)
    {
        if (!empty($initCbf)) {
            $initCbf();
        }
        $this->rebuildGetQueryString();
        $this->fixRequestMethod();
        $this->bootstrapSelf();
        $this->isAjax = ((strtolower(trim($this->httpXRequestedWith))) === "xmlhttprequest");
    }

    /**
     * rebuild query string after GET params have been removed
     *
     * @param string $base optional script base for URL
     */
    private function rebuildGetQueryString(string $base = "")
    {
        $base = !empty($base) ? $base : explode("?", $_SERVER["REQUEST_URI"])[0];
        $queryArr = [];
        // build query string and replace request URI property
        foreach ($_GET as $k => $v) {
            $queryArr[] = "{$k}={$v}";
        }
        $qStr = implode("&", $queryArr);
        $_SERVER["REQUEST_URI"] = "{$base}?{$qStr}";
    }

    /**
     * allows for additional REST methods that get specified in URL
     *
     */
    private function fixRequestMethod(): void
    {
        $method = $_GET["method"];
        // if put or delete specified
        if (!empty($method) && $method === "put" || $method === "delete") {
            // remove from URL params
            unset($_GET["method"]);
            $this->rebuildGetQueryString();
            // assign to requestMethod
            $this->requestMethod = strtolower(trim($method));
        }
    }

    /**
     * Grabs request items from $_SERVER var and makes them a property along with their value
     *
     */
    private function bootstrapSelf(): void
    {
        foreach ($_SERVER as $key => $value) {
            // get the first word before _
            $item = strtolower(trim((explode("_", $key))[0]));
            // limit to only items we need
            if ($item === "request" || $item === "server" || $item === "query" || $item === "http") {
                // camel case then assign to property
                $this->{$this->toCamelCase($key)} = strtolower(trim($value));
            }
        }
    }

    /**
     * camel cases a string
     *
     * @param $string // input to be camel cased
     *
     * @return string return input in camel case format
     */
    private function toCamelCase($string): string
    {
        // remove all non alphabetical characters with white space
        $string = preg_replace("/[^a-z]/", ' ', strtolower(trim($string)));
        // uppercase first letter / remove whitespace / lowecase first char
        return lcfirst(str_replace(" ", "", ucwords($string)));
    }

    /**
     * GETS POST PUT DELETE body
     *
     * @return array returns array of key=>value pairs
     */
    public function getBody(): array
    {
        if ($this->requestMethod === "post" || $this->requestMethod === "put" || $this->requestMethod === "delete") {
            $body = [];
            foreach (array_keys($_POST) as $key) {
                // dont include submit in returns
                if ($key === "submit") {
                    continue;
                }
                $body[$key] = filter_input(INPUT_POST, $key, FILTER_SANITIZE_SPECIAL_CHARS);
            }
            return $body;
        }
        return [];
    }
}
