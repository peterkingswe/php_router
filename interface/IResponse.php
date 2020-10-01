<?php

/**
 * standard methods for response. Used in conjunction with router
 */
interface IResponse // extends IDisplayMsg
{
    /**
     * sets default file extensions for front end files
     *
     * @param string $ext extension that should be set as default
     */
    public function setDefaultViewExt(string $ext): int;

    /**
     * render/displays template to browser
     *
     * @param string $fileName path/name of file that should be rendered
     */
    public function render(string $fileName): int;

    /**
     * assign variables to template engine
     *
     * @param array $vars array of var names and values
     */
    public function assignViewVars(array $vars): int;

    /**
     * Sets HTTP Status Code
     *
     * @param int $statusCode http status code
     *
     * @return int returns status code || -1 for error
     */
    public function setStatus(int $statusCode = 0): int;

    /**
     * gets the message for a given status code
     *
     * @param int $statusCode http status code
     *
     * @return string status code message || "" if error
     */
    public function getStatusMsg(int $statusCode = 0): string;
}
