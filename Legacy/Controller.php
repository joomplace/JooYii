<?php
/**
 * Copyright (c) 2018. Alexandr Kosarev, @kosarev.by
 */

namespace Joomplace\X\Legacy;


use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomplace\X\Helper\Injector;


class Controller extends BaseController
{
    use Injector;

    protected $name = null;
    protected $app = null;
    protected $_namespace = null;

    public function __construct(array $config = array()) {
        $this->name = (new \ReflectionClass($this))->getShortName();
        $this->app = Factory::getApplication();
        $this->_namespace = $config['namespace'];
        parent::__construct($config);
        $this->input = $this->getInput();
    }

    public function execute($task = null)
    {
        $this->task = $task;

        $task = strtolower($task);

        if (isset($this->taskMap[$task]))
        {
            $task = $this->taskMap[$task];
        }
        elseif (isset($this->taskMap['__default']))
        {
            $task = $this->taskMap['__default'];
        }
        else
        {
            throw new \Exception(\JText::sprintf('JLIB_APPLICATION_ERROR_TASK_NOT_FOUND', $task), 404);
        }

        if ($this->methodExists($task)) {
            $arguments = array();
            foreach ($this->getArgs($task) as $param) {
                /** @var \ReflectionParameter $param */
                /*
                 * TODO: set initial filter from config
                 */
                $filter = null;
                if ($param->isArray()) {
                    $filter = 'array';
                }

                if($param->isDefaultValueAvailable()){
                    $value = $this->injectArg($param->name, $param->getDefaultValue(), $filter, null);
                }else{
                    $value = $this->injectRequiredArg($param->name, $filter, null);
                }

                $arguments[] = $value;
            }

            return call_user_func_array(array($this, $task), $arguments);
        } else {
            // TODO: improve
            throw new \Exception("Method `" . $this->getClassName() . '::' . $task . "` doesn't exist");
        }
    }

    public function index($cachable = false, $urlparams = array())
    {
        $view = $this->getView();
        return $view->render();
    }

    public function getModel($name = '', $prefix = '', $config = array()){
        if(!$name){
            $name = $this->name;
        }
        $client = ucfirst($this->app->getName());
        if($client == 'Administrator'){
            $client = 'Admin';
        }
        $modelClass = $this->_namespace . ucfirst($client) . '\\Model' .
            ($prefix ? '\\' . ucfirst($prefix) : '') . '\\' . ucfirst($name);
        return new $modelClass();
    }

    protected function createView($name, $prefix = '', $type = '', $config = array())
    {
        if(strpos($prefix,'\\')!==false || strpos($prefix,'View')!==false){
            $prefix = '';
        }
        if (!isset($config['base_path'])) {
            $config['base_path'] = $this->basePath;
        }
        $document = \JFactory::getDocument();
        $type = $document->getType();


        $client = ucfirst($this->app->getName());
        if($client == 'Administrator'){
            $client = 'Admin';
        }

        $viewClass = $this->_namespace . $client . '\\View' .
            ($prefix ? '\\' . ucfirst($prefix) : '') . '\\'. ucfirst($name) .
            '\\' . ucfirst($type);
        $view = new $viewClass(['name' => $this->getName() . ':' . $this->input->get('layout', $this->task, 'cmd')]);
        return $view;
    }

}