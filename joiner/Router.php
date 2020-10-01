<?php

// https://medium.com/the-andela-way/how-to-build-a-basic-server-side-routing-system-in-php-e52e613cf241
class Router implements IRouter
{
    private IRequest $req;
    private IResponse $res;
    private IMessage $msg;
    private array $allMw = [];
    private array $globalMiddleware = [];
    private string $defaultView;
    // class setting will error if route is root and this is set to false
    protected bool $allowRootRoute = false;

    public function __construct(IRequest $Request, IResponse $Response, IMessage $Msg, string $defaultView = "index")
    {
        $this->req = $Request;
        $this->res = $Response;
        $this->msg = $Msg;
        $this->defaultView = $defaultView;
    }

    /**
     * called for inaccessible || non existing methods
     * $router->get(route, [routeLevelMiddleware], lastFunction(s))
     *
     * @param string $requestMethod request type (get, post, put, delete)
     * @param array  $args [route,[routerLevelMiddleware], lastFunction(s)]
     */
    public function __call(string $requestMethod, array $args)
    {
        // dont run anything if error - keep returning till deconstruct
        if ($this->msg->hasError() && !$this->req->isAjax) {
            $this->res->render($this->defaultView);
            return;
        }

        //init vars
        $route = "";
        $routeMiddleware = [];
        $method = new stdClass();

        [$route, $routeMiddleware, $method] = $args;

        // format route && handle error
        $route = $this->formatRoute($route);
        if ($this->routeHasError($route)) {
            return;
        }

        #todo Undefined class constant
//        if (!in_array(strtolower($requestMethod), ($this->req)::supportedHttpMethods)) {
        if (!in_array(strtolower($requestMethod), $this->req->supportedHttpMethods)) {
            $this->invalidMethodHandler();
            $this->msg->updateMsg(true, "Error: HTTP Method Not Supported");
            $this->res->render($this->defaultView);
            return;
        }

        // router specific middleware - executes same order it was inputted
        $routeLevelMiddleware = array_merge(array_values($routeMiddleware), [$method]) ?? [];
        // loop through all router level middleware
        foreach ($routeLevelMiddleware as $middleware) {
            // assign middleware to route entry
            $this->{strtolower($requestMethod)}[$route][] = $middleware;
        }
    }

    /**
     * Checks route for error
     *
     * @param string $route route to be checked
     *
     * @return bool returns true for error || false otherwise
     */
    private function routeHasError(string $route): bool
    {
        return (empty($route) || (!$this->allowRootRoute && $route === "/"));
    }

    /**
     * Formats route to /fileName:paramKey:paramKey
     *
     * @param string $route route to format
     *
     * @return string returns formatted route || ("" || "/") depending on $defaultRouteToRoot property
     */
    private function formatRoute(string $route): string
    {
        $route = urldecode($route);

        // short circuit if root
        if ($route === "/") {
            return $route;
        }

        // init vars
        $file = "";
        $params = "";
        $paramArr = [];

        // separate file from param
        [$file, $params] = explode("?", $route);
        // standardize format
        $file = strtolower(trim($file));
        // split up individual params
        $paramArr = !empty($params) ? explode("&", $params) : [];
        $tmp = [];
        // loop through each param
        foreach ($paramArr as $index => $param) {
            // standardize format
            $param = strtolower(trim($param));
            // split key and value
            $tmp = explode("=", trim($param));
            // if key but no value then remove from arr
            if (in_array("", $tmp) || count($tmp) === 1) {
                // remove from arr
                unset($paramArr[$index]);
                // continue to next iteration
                continue;
            }
            // remove = and param value
            $paramArr[$index] = substr($param, 0, strpos($param, "="));
        }
        // reset index in case some got deleted & add to string separate by :
        $params = implode(":", array_values($paramArr));
        // reassign to route / if file empty || starts with : then root
        $route = (!empty($file) && $file[0] !== ":") ? (rtrim($file . ":" . $params, "/:")) : "";
        // clean up
        unset($tmp, $file, $params, $paramArr);
        // if empty then error occurred
        if (empty($route)) {
            $this->invalidRoute();
            $this->msg->updateMsg(true, "Error: HTTP Method Not Supported");
            $this->res->render($this->defaultView);
//            if ($this->defaultRouteToRoot) {
//                return "/";
//            }
//            return "";
        }
        return $route;
    }

    /**
     * sets status && msg properties if method handler is invalid
     */
    private function invalidMethodHandler(): void
    {
        #todo test set status
        $this->statusCodeHandler($this->res->setStatus(405));
    }

    /**
     * sets status && msg properties if route is invalid
     */
    private function invalidRoute(): void
    {
        #todo test set status
        $this->statusCodeHandler($this->res->setStatus(400));
    }

    /**
     * sets status && msg properties if no methods found for route
     */
    private function defaultRequestHandler(): void
    {
        #todo test set status
        $this->statusCodeHandler($this->res->setStatus(404));
    }

    /**
     * Resolves a route
     */
    public function resolve()
    {
        // dont run anything if error
        if ($this->msg->hasError() && !$this->req->isAjax) {
            $this->res->render($this->defaultView);
            return;
        }
        $methodDictionary = $this->{strtolower(($this->req->requestMethod))};
        $formatedRoute = $this->formatRoute(($this->req->requestUri ?? ""));
        if ($this->routeHasError($formatedRoute ?? "")) {
            return;
        }
        // find all middleware for route
        $methods = $methodDictionary[$formatedRoute];
        // checks if methods is empty
        if (empty($methods)) {
            $this->msg->updateMsg(true, "Error: Invalid Request");
            return;
        }
        // merge all middleware arrays into one and reset indexes for loop
        $this->allMw = array_merge(array_values($this->globalMiddleware), array_values($methods));

        // call first middleware
        $this->runCbf();
    }

    /**
     * select router and run all middleware & display msg to view
     *
     */
    public function __destruct()
    {
        // run middleware
        $this->resolve();
        // echo || assign msg to view
        $this->msg->displayMessage($this->req->isAjax);
        // render default template if error and not ajax
        if ($this->msg->hasError() && !$this->req->isAjax) {
            $this->res->render($this->defaultView);
        }
    }

    /**
     * assigns global middleware functions for every route. Executed in order
     *
     * @param $func // function to be called
     */
    public function use($func)
    {
        $this->globalMiddleware[] = $func;
    }

    /**
     * gives permission to move on to next middleware.
     *
     * if next is not called within cbf's then it does not move on and will end
     *
     */
    public function next()
    {
        #todo allow for parameters to be passed from middleware chain through next
        //remove first item in array
        unset($this->allMw[0]);
        //reindex arr
        $this->allMw = array_values($this->allMw);
        // if error then return
        if ($this->msg->hasError() && !$this->req->isAjax) {
            $this->res->render($this->defaultView);
            return;
        }
        // check if empty
        if (!empty($this->allMw)) {
            $this->runCbf();
        }
    }

    /**
     * runs call back function and handles exception
     */
    protected function runCbf(): void
    {
        try {
            call_user_func($this->allMw[0], $this->req, $this->res, $this->msg, [$this, 'next']);
        } catch (Exception $e) {
            $this->msg->updateMsg(true, $e->getMessage());
        }
    }

    /**
     * checks for status code error && sets msg
     *
     * @param $statusCodeResult // result of setStatus method
     */
    protected function statusCodeHandler(int $statusCodeResult): void
    {
        // 0 represents error
        if (!$statusCodeResult) {
            $this->msg->updateMsg(true, "Error: Status Code Not Valid");
            return;
        }
        // get status msg
        $statusMsg = $this->res->getStatusMsg($statusCodeResult);
        // check for error
        if (empty($statusMsg)) {
            $this->msg->updateMsg(true, "Error: Status Code Msg Not Found");
            return;
        }
        // get http status message and assign it to msg property
        $this->msg->updateMsg(false, $statusMsg);
    }
}
