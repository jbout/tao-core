<?php
/*
 * This program is free software; you can redistribute it and/or
 *  modify it under the terms of the GNU General Public License
 *  as published by the Free Software Foundation; under version 2
 *  of the License (non-upgradable).
 *
 * This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program; if not, write to the Free Software
 *  Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 *
 *  Copyright (c) 2017 (original work) Open Assessment Technologies SA (under the project TAO-PRODUCT);
 */
namespace oat\tao\model\mvc\Application;


use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\ServerRequest;
use oat\oatbox\service\ConfigurableService;
use oat\tao\model\mvc\Application\Config\Route;
use oat\tao\model\mvc\Application\Exception\InvalidResponse;
use oat\tao\model\mvc\Application\Exception\RouteNotFound;
use oat\tao\model\mvc\middleware\AbstractTaoMiddleware;
use oat\tao\model\mvc\middleware\TaoErrorHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class TaoApplication extends ConfigurableService implements ApplicationInterface
{
    /**
     * @var array
     */
    protected $routes = [];

    /**
     * @var ServerRequest
     */
    protected $request;
    /**
     * @var string
     */
    protected $errorHandler = TaoErrorHandler::class;

    /**
     * @var Resolution
     */
    protected $resolution;

    public function __construct(array $options = array())
    {
        parent::__construct($options);
        foreach ($options['routes'] as $routeOption) {
            $this->addRoute($routeOption['ext'] , $routeOption['className'] , $routeOption['preProcess'] , $routeOption['process'], $routeOption['postProcess']  , $routeOption['options']);
        }
        if($this->hasOption('errorHandler')) {
            $this->errorHandler = $this->getOption('errorHandler');
        }

    }

    /**
     * @return Resolution
     */
    public function getResolution()
    {
        return $this->resolution;
    }

    /**
     * @param Resolution $resolution
     * @return $this
     */
    public function setResolution($resolution)
    {
        $this->resolution = $resolution;
        return $this;
    }



    /**
     * @return ServerRequest
     */
    public function getRequest()
    {
        if(is_null($this->request)) {
            $this->request = $request  = ServerRequest::fromGlobals();
        }
        return $this->request;
    }

    /**
     * @param ServerRequest $request
     * @return $this
     */
    public function setRequest($request)
    {
        $this->request = $request;
        return $this;
    }


    /**
     * @param $extension
     * @param $routeClassName
     * @param array $preProcess
     * @param array $process
     * @param array $postProcess
     * @param $errorHandler
     * @param $routeOptions
     * @return $this
     */
    protected function addRoute($extension , $routeClassName , $preProcess = [] , $process = [] , $postProcess = []  , $routeOptions) {

        $newRoute = new Route();

        $this->routes[] = $newRoute->setExtension($extension)
            ->setRouteClass($routeClassName)
            ->setPreProcess($preProcess)
            ->setProcess($process)
            ->setPostProcess($postProcess)
            ->setRouteOptions($routeOptions);

        return $this;
    }

    /**
     * @param $path
     * @return null|Route
     * @throws RouteNotFound
     */
    public function resolve($path)
    {
        $selectRoute = null;
        /**
         * @var $route Route
         */
        foreach ($this->routes as $route) {

            if($route->match($path)) {
                $selectRoute = $route;
            }
        }
        if(is_null($selectRoute)) {
            throw new RouteNotFound($path);
        }
        return $selectRoute;
    }

    protected function getPrefix() {
        $docRoot = $_SERVER['DOCUMENT_ROOT'];
        $appPath = dirname($_SERVER['SCRIPT_FILENAME']);

        return trim(str_replace($docRoot , '' , $appPath) , '/');

    }

    public function getPath(ServerRequestInterface $request) {
        $prefix = $this->getPrefix();
        \common_Logger::i('prefix : ' . $prefix);
        $path = preg_replace('#^' . $prefix . '#u' , '' ,$request->getUri()->getPath());
        return trim($path , '/');
    }

    /**
     * @param string $path
     * @return null|Route
     */
    public function getRoute($path) {

        return $this->resolve($path);
    }

    /**
     * @param $className
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @param array $args
     * @return ResponseInterface
     */
    protected function executeProcess($className , ServerRequestInterface $request , ResponseInterface $response , array $args = []) {

        /**
         * @var $process AbstractTaoMiddleware
         */
        $process = new $className();
        $process->setServiceLocator($this->getServiceManager());
        $response = $process($request , $response , $args);
        if(!is_a($response , ResponseInterface::class)) {
            throw new InvalidResponse($className);
        }
        return $response;
    }


    public function process(ServerRequestInterface $request , ResponseInterface $response) {
        $path     = $this->getPath($request);

        $args     = [];

        if(($route = $this->getRoute($path)) !== null) {
            $args['route'] = $route;

            $resolution         = $route->resolve($path);

            $args['resolution'] = $resolution;
            $args['controller'] = $resolution->getController();
            $this->setResolution($resolution);

            foreach ($route->getPreProcess() as $preProcess) {
                $response = $this->executeProcess($preProcess , $request , $response , $args);
            }

            foreach($route->getProcess() as $process) {
                $response = $this->executeProcess($process , $request , $response , $args);

            }
            foreach($route->getPostProcess() as $postProcess) {
                $response = $this->executeProcess($postProcess , $request , $response , $args);
            }
        }
        return $response;
    }

    /**
     * @param $request
     * @param $response
     * @param $Exception
     * @return ResponseInterface
     */
    public function error($request  , $Exception) {
        $args = [
            'exception' => $Exception
        ];
        $response = new Response();
        return $this->executeProcess($this->errorHandler , $request , $response , $args);
    }

    /**
     * @return $this
     */
    public function run()
    {
        $response = new Response();
        $request  = $this->getRequest();
        try {
            $response = $this->process($request , $response);
        } catch (\Exception $e) {
            $response = $this->error($request  , $e);
        }
        $this->finalise($response);
        return $this;
    }

    public function forward($newUri) {
        $newRequest = new ServerRequest(
            'GET',
            $newUri
        );

        parse_str(parse_url($newUri , PHP_URL_QUERY) , $queryParams);

        $newUri = $newRequest->getUri();
        $request = $this->getRequest()->withUri($newUri)->withQueryParams($queryParams);
        $this->setRequest($request);
        $this->run()->end();
    }

    public function finalise(Response $response) {

        header('HTTP/' . $response->getProtocolVersion() . ' ' . $response->getStatusCode() . ' ' . $response->getReasonPhrase() , $response->getStatusCode());

        foreach ($response->getHeaders() as $name => $value) {

           header($name . ': ' . $response->getHeaderLine($name) );
        }

        $body = $response->getBody();
        $body->rewind();
        echo $body->getContents();

    }

    public function end() {
        exit();
    }


}