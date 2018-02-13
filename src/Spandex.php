<?php

namespace Spandex;

use Doctrine\ORM\Tools\Setup;
use Doctrine\ORM\EntityManager;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use Predis;

class Spandex
{
    const HASH_STRETCH = 14;
    public $debug = false;
    public $config;
    private $doctrineConfig;
    private $connections = array();
    private $entityManagers = array();
    private $appRoot;
    private $routes = array();
    private $defaultHook = '';
    private $missingHook = '';
    private $js = '';
    private $request;
    private $twigCache = false;
    private $twig;
    private $session = null;
    private $sessionClass = '';
    private $sessionPrefix = 'session';
    private $sessionExpiry = 2592000;
    private $cache;

    private $queue = null;
    private $redis = null;

    /**
     * @param string $appRoot The application root
     */
    public function setAppRoot($appRoot)
    {
        $this->appRoot = $appRoot;
    }

    /**
     * @return string The application root
     */
    public function getAppRoot()
    {
        return $this->appRoot;
    }

    /**
     * @param string $pattern The prefix pattern to match against for requests for these hooks
     * @param string $path The path to the folder containing the hook classes, in named folders
     * @param string $namespace The PHP namespace for these hooks
     */
    public function registerRoute($pattern, $path, $namespace)
    {
        $this->routes[] = new Route($pattern, $path, $namespace);
    }

    /**
     * @param string $filename The virtual filename to use to identify requests for hook-based JS
     */
    public function setJS($filename)
    {
        $this->js = $filename;
    }

    /**
     * @param string $hook The hook to load if no other is specified
     */
    public function setDefaultHook($hook)
    {
        $this->defaultHook = $hook;
    }

    /**
     * @param string $hook The hook to load if a missing hook is called
     */
    public function setMissingHook($hook)
    {
        $this->missingHook = $hook;
    }

    /**
     * @param string $path The Twig cache path
     */
    public function setTwigCache($path)
    {
        $this->twigCache = rtrim($path, " \t\n\r\0\x0B/");
    }

    /**
     * @return \Twig\Twig_Environment The twig environment
     */
    public function getTwig()
    {
        return $this->twig;
    }

    /**
     * @param string $className
     */
    public function setSessionClass($className)
    {
        $this->sessionClass = $className;
    }

    /**
     * @param string $prefix
     */
    public function setSessionPrefix($prefix)
    {
        $this->sessionPrefix = $prefix;
    }

    /**
     * @param integer $seconds
     */
    public function setSessionExpiry($seconds)
    {
        $this->sessionExpiry = $seconds;
    }

    /**
     * @return Session The current session
     */
    public function getSession()
    {
        return $this->session;
    }

    /**
     * @param Session $session The new session
     * @param boolean $persist Persist session
     */
    public function setSessionCookie($session, $persist)
    {
        setcookie("{$this->sessionPrefix}-id", $session->getId(), ($persist? time() + $this->sessionExpiry: 0), '/');
    }

    public function clearSessionCookie()
    {
        setcookie("{$this->sessionPrefix}-id", '', 1, '/');
    }

    /**
     * @param string $name The connection name to use to reference this connection
     * @param string $connection The connection string to use
     */
    public function registerConnection($name, $connection)
    {
        $this->connections[$name] = $connection;
    }

    /**
     * @return Cache The cache
     */
    public function getCache()
    {
        return $this->cache;
    }

    /**
     * @return Object The queue
     */
    public function getQueue()
    {
        return $this->queue;
    }

    /**
     * @return Object The redis connection
     */
    public function getRedis()
    {
        return $this->redis;
    }

    private function bootstrapDoctrine()
    {
        $this->doctrineConfig = Setup::createAnnotationMetadataConfiguration(array(
            "{$this->appRoot}/Models"
        ), $this->debug);
    }

    /**
     * @param string $name The connection name to use
     * @return \Doctrine\ORM\EntityManager The EntityManager referenced by this name
     */
    public function managerFactory($name)
    {
        if (!array_key_exists($name, $this->entityManagers)) {
            $this->entityManagers[$name] = EntityManager::create(array(
                'url' => $this->connections[$name]
            ), $this->doctrineConfig);
        }
        return $this->entityManagers[$name];
    }

    /**
     * This is where the magic happens. run() handles the incoming request, determines where it should go,
     * and returns relevant content.
     *
     * Special case: JS compilation request ($this->js)
     *
     * The filename registered with setJS() is a virtual file representing the JS functionality relevant to each
     * hook; this file is included itself by the base template, and requesting it results in the compilation and
     * serving of the JS required to render and run each hook. This leaves almost complete control of the JS to
     * the hooks, allowing for easy encapsulation whilst allowing the JS to be cached on the client, as well as
     * on the server once the app is complete.
     *
     * <hook>.js should return a JS object called $.Spandex.Hooks.<hook>, containing (at the very least):
     *   prefix: string
     *       - the request prefix to use when serving requests to this hook, which should match the pattern
     *         defined in the relevant registerRoute() call - this ensures that template / RPC requests are
     *         served correctly, without requiring the client to know beforehand what the routes are
     *   postRender: function
     *       - a function to call after the templated content has been rendered
     *
     * Requests for $this->js glob all the hooks from all the registered locations, and concatenate the JS for each.
     *
     * @return string Base template, hook template, hook JSON
     */
    public function run()
    {
        ini_set('session.use_cookies', '0');
        $this->bootstrapDoctrine();
        $this->cache = new Cache($this->config);
        $this->request = trim($_REQUEST['r'], " \t\n\r\0\x0B/");

        /**
         * Initialise Twig
         *
         * At this point, initialise the filesystem loader with the app root; we add other folders depending on
         * the determined hook, and only initialise the Twig environment when it's needed
         */
        \Twig_Autoloader::register();
        $twigLoader = new \Twig_Loader_Filesystem();
        $twigLoader->addPath($this->appRoot . '/Templates', 'App');
        $twigLoader->addPath(__DIR__ . '/Templates', 'Spandex');

        /**
         * JS compilation
         */
        if ($this->js !== '' && $this->request === $this->js) {
            $scripts = [];
            foreach ($this->routes as $route) {
                foreach (glob("{$this->appRoot}{$route->getPath()}/*/*.js") as $filename) {
                    $scripts[] = file_get_contents($filename);
                }
            }
            $this->twig = new \Twig_Environment($twigLoader, array(
               'cache' => $this->twigCache,
               'debug' => $this->debug,
               'autoescape' => false
            ));
            header('Cache-Control: public, max-age=300');
            header('Content-Type: text/javascript; charset=utf-8');
            print($this->twig->render('@Spandex/hooks.js', array(
                'defaultHook' => $this->defaultHook,
                'missingHook' => $this->missingHook,
                'scripts' => $scripts
            )));
            return;
        }

        /**
         * Cookie auth and sessions
         */
        $sessionId = "{$this->sessionPrefix}-id";
        if (array_key_exists($sessionId, $_COOKIE)) {
            $emSession = $this->managerFactory('app');
            $this->session = $emSession->find($this->sessionClass, $_COOKIE[$sessionId]);
            if ($this->session) {
                $this->session->setLastSeen(new \DateTime);
                $emSession->flush();
            } else {
                $this->clearSessionCookie();
            }
        }

        /**
         * Hooks
         */
        foreach ($this->routes as $route) {
            if ($route->testRoute($this->request)) {

                /**
                 * At this stage we might be dealing with a POST that could do something, so CSRF check
                 */
                if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
                    try {
                        if (!array_key_exists('csrf-protect', $_COOKIE) || !$_COOKIE['csrf-protect']) {
                            throw new \Exception;
                        }
                        if (!array_key_exists('csrf-protect', $_REQUEST) || !$_REQUEST['csrf-protect']) {
                            throw new \Exception;
                        }
                        if ($_COOKIE['csrf-protect'] !== $_REQUEST['csrf-protect']) {
                            throw new \Exception;
                        }
                    }
                    catch (\Exception $e) {
                        http_response_code(403);
                        return;
                    }
                }

                /**
                 * Hook breakdown
                 */
                $request = explode('/', trim(substr($this->request, strlen($route->getPattern())), " \t\n\r\0\x0B/"), 3);
                $hook = $request[0];
                $action = isset($request[1])? strtolower($_SERVER['REQUEST_METHOD']) . ucwords($request[1]): 'getRender';
                $params = isset($request[2])? explode('/', $request[2]): array();
                $hookPath = "{$this->appRoot}{$route->getPath()}/{$hook}";
                $errorPath = "{$this->appRoot}{$route->getPath()}/Error";
                if (!file_exists($hookPath) || !is_dir($hookPath) || !is_readable($hookPath)) {
                    $hookPath = $errorPath;
                    $params = array($hook);
                    $hook = 'Error';
                    $action = 'getRender';
                }
                $hookFile = "{$hookPath}/{$hook}.php";
                $type = "\\{$route->getNamespace()}\\{$hook}\\{$hook}";
                $hookClass = new $type($this);
                if (!method_exists($hookClass, $action)) {
                    $hookPath = $errorPath;
                    $type = "\\{$route->getNamespace()}\\Error\\Error";
                    $hookClass = new $type($this);
                    $params = array($action, $hook);
                    $hook = 'Error';
                    $action = 'getAction';
                }
                $twigLoader->addPath($hookPath, $hook);
                $this->twig = new \Twig_Environment($twigLoader, array(
                    'cache' => $this->twigCache,
                    'debug' => $this->debug
                ));
                $response = $hookClass->$action($params, $this);
                header('Cache-Control: private, max-age=0, no-cache');
                setcookie('csrf-protect', bin2hex(openssl_random_pseudo_bytes(32)), 0, '/');
                if (is_array($response)) {
                    $response = json_encode($response, JSON_FORCE_OBJECT | JSON_NUMERIC_CHECK | JSON_PRETTY_PRINT);
                    header('Content-type: application/json; charset=utf-8');
                }
                else {
                    $setType = false;
                    foreach (headers_list() as $header) {
                        if (preg_match('/^Content-type\:/i', $header)) {
                            $setType = true;
                        }
                    }
                    if (!$setType) {
                        header('Content-type: text/html; charset=utf-8');
                    }
                }
                print($response);
                return;
            }
        }

        /**
         * Base template
         */
        $this->twig = new \Twig_Environment($twigLoader, array(
           'cache' => $this->twigCache,
           'debug' => $this->debug
        ));
        header('Cache-Control: private, max-age=0, no-cache');
        header('Content-Type: text/html; charset=utf-8');
        print($this->twig->render('@App/base.html', array(
            'jsPath' => $this->js,
            'user' => ($this->getSession()? $this->getSession()->getUser(): false)
        )));
    }

    public function bootstrapQueue()
    {
        $rabbitmq = parse_url($this->config['AMQP']);
        $this->queue = new AMQPStreamConnection(
            $rabbitmq['host'],
            isset($rabbitmq['port'])? $rabbitmq['port']: 5672,
            $rabbitmq['user'],
            $rabbitmq['pass'],
            substr($rabbitmq['path'], 1) ?: '/'
        );
        $this->redis = new Predis\Client($this->config['Redis']);
    }

    /**
     * @param string $connection The connection to use when bootstrapping
     */
    public function bootstrap($connection)
    {
        $this->bootstrapDoctrine();
        return $this->managerFactory($connection);
    }
}
