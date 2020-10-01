<?php

abstract class Response implements IResponse
{
    protected string $fileExt = "";
    protected string $defaultRoute = "";
    protected string $absViewDirPath = "";
    protected $TemplateClass = null;
    protected int $currentStatusCode = 0;
    protected static array $validHttpStatusCodes = [
      100 => "Continue",
      101 => "Switching Protocols",
      102 => "Processing",
      103 => "Early Hints",
      200 => "OK",
      201 => "Created",
      202 => "Accepted",
      203 => "Non-Authoritative Information",
      204 => "No Content",
      205 => "Reset Content",
      206 => "Partial Content",
      207 => "Multi-Status",
      208 => "Already Reported",
      226 => "IM Used",
      300 => "Multiple Choices",
      301 => "Moved Permanently",
      302 => "Found",
      303 => "See Other",
      304 => "Not Modified",
      305 => "Use Proxy",
      307 => "Temporary Redirect",
      308 => "Permanent Redirect",
      400 => "Bad Request",
      401 => "Unauthorized",
      402 => "Payment Required",
      403 => "Forbidden",
      404 => "Not Found",
      405 => "Method Not Allowed",
      406 => "Not Acceptable",
      407 => "Proxy Authentication Required",
      408 => "Request Timeout",
      409 => "Conflict",
      410 => "Gone",
      411 => "Length Required",
      412 => "Precondition Failed",
      413 => "Payload Too Large",
      414 => "URI Too Long",
      415 => "Unsupported Media Type",
      416 => "Range Not Satisfiable",
      417 => "Expectation Failed",
      421 => "Misdirected Request",
      422 => "Unprocessable Entity",
      423 => "Locked",
      424 => "Failed Dependency",
      425 => "Too Early",
      426 => "Upgrade Required",
      427 => "Unassigned",
      428 => "Precondition Required",
      429 => "Too Many Requests",
      431 => "Request Header Fields Too Large",
      500 => "Internal Server Error",
      501 => "Not Implemented",
      502 => "Bad Gateway",
      503 => "Service Unavailable",
      504 => "Gateway Timeout",
      505 => "HTTP Version Not Supported",
      506 => "Variant Also Negotiates",
      507 => "Insufficient Storage",
      508 => "Loop Detected",
      510 => "Not Extended",
      511 => "Network Authentication Required"
    ];

    /**
     * Response constructor.
     *
     * @param $TemplateClass // init template class specific to portal. Child portal specific class passes it in
     */
    public function __construct($TemplateClass)
    {
        $this->TemplateClass = $TemplateClass;
    }

    /**
     * renders view. Specific to portal
     *
     * @param string $fileName name of file
     */
    abstract public function render(string $fileName): int;

    /**
     * assign variables to view
     *
     * @param array $vars contains variable names as key and value as array value ---Format: ["varName"=>"varValue"]
     */
    abstract public function assignViewVars(array $vars): int;

    /**
     * sanitize template variable names
     *
     * @param array  $vars array of variable names && variable values ---Format: ["variableName", "variableValues"]
     * @param string $varNamingStyle
     *
     * @return array returns array where all var names (array keys) are sanitized
     */
    protected function sanitizeViewVarNames(array $vars, string $varNamingStyle = "camel"): array
    {
        if (empty($vars)) {
            return [];
        }
        foreach (array_keys($vars) as $varName) {
            // remove all non alphabetical characters with white space
            $varName = preg_replace("/[^a-z]/", ' ', strtolower(trim($varName)));
            // check for error
            if (empty($varName)) {
                return [];
            }
            // naming conventions only work on varName's with whitespace
            if (strpos($varName, ' ') !== false) {
                switch (strtolower(trim($varNamingStyle))) {
                    case "camel":
                        $varName = $this->camelCase($varName);
                        break;
                    case "snake":
                        $varName = $this->snakeCase($varName);
                        break;
                    default:
                        return [];
                }
            }
        }
        return $vars;
    }

    /**
     * converts string to camel case style
     *
     * @param string $varName variable name | must have white space to work
     *
     * @return string camel cased version of varName
     */
    private function camelCase(string $varName): string
    {
        // uppercase first letter / remove whitespace / lowecase first char
        return lcfirst(str_replace(" ", "", ucwords($varName)));
    }

    /**
     * converts string to snake case style
     *
     * @param string $varName variable name | must have white space to work
     *
     * @return string snake cased version of varName
     */
    private function snakeCase(string $varName): string
    {
        return str_replace(" ", "_", $varName);
    }

    /**
     * sanitizes a file name. File name extension is optional
     *
     * @param string $fileName name of file with or without extension
     *
     * @return string sanitized file name ready to be used
     */
    protected function sanitizeViewFileName(string $fileName): string
    {
        [$fileName, $ext] = explode(".", $fileName);

        // replace white space with underscore
        $fileName = preg_replace('/\s+/', '_', trim(strtolower($fileName)));

        // remove all non (alphabetical characters || underscore characters)
        $fileName = preg_replace("/[^a-z0-9_-]/", '', $fileName);

        // if ext is empty then use default if set
        if (empty($ext) && !empty($this->fileExt)) {
            $ext = $this->fileExt;
        // use user supplied ext
        } elseif (!empty($ext)) {
            // ext must be alphabetical, lowercase
            $ext = preg_replace("/[^a-z]/", '', trim(strtolower($ext)));
            // ext must have length of 3
            if (strlen($ext) !== 3) {
                return "";
            }
            // if no extension set then error
        } elseif (empty($ext) && empty($this->fileExt)) {
            return "";
        }

        // rejoin full file name
        $fileName = $fileName . "." . $ext;
        unset($ext);

        // if last char is / then remove
        $this->absViewDirPath = rtrim($this->absViewDirPath, "/");

        // check if template exists
        if (!(file_exists($this->absViewDirPath . "/" . $fileName))) {
            return "";
        }
        return $fileName;
    }

    /**
     * sets default view extension property if extension is valid
     * extension is considered valid if it is made up of 3 alphabetic characters.
     *
     * @param string $ext extension to be set as default | can be passed with . or without
     *
     * @return int return 0 if error || 1 if success
     */
    public function setDefaultViewExt(string $ext): int
    {
        // limit to only characters
        $ext = preg_replace("/[^a-z]/", '', trim(strtolower($ext)));

        // check ext length
        if (strlen($ext) !== 3) {
            return 0;
        }
        // assign default ext to prop
        $this->fileExt = (string)$ext;
        return 1;
    }

    /**
     * sets the HTTP return status
     *
     * @param int $statusCode HTTP status code
     *
     * @return int returns 0 if error else status code
     */
    public function setStatus(int $statusCode = 0): int
    {
        // if not valid then single to joiner class that error occurred
        if (!$this->isValidHttpStatusCode($statusCode)) {
            return 0;
        }
        $this->currentStatusCode = $statusCode;
        http_response_code($statusCode);
        return $statusCode;
    }

    /**
     * gets the message for a given status code
     *
     * @param int $statusCode HTTP status code
     *
     * @return string returns HTTP status message associated with code
     */
    public function getStatusMsg(int $statusCode = 0): string
    {
        if (!$this->isValidHttpStatusCode($statusCode)) {
            return "";
        }
        return self::$validHttpStatusCodes[$statusCode];
    }

    /**
     * Checks to make sure http code is valid
     *
     * @param int $statusCode http status code
     *
     * @return bool return true if valid || false otherwise
     */
    private function isValidHttpStatusCode(int $statusCode): bool
    {
        return in_array($statusCode, array_keys(self::$validHttpStatusCodes));
    }
}
