<?php
/**
 * Router class to define which API controller to use
 */ 
class Router {
	/**
	 * Loads controller class and run action
	 */
	function __construct()
	{
		if (!empty($_GET['controller']))
		{
			$controller_name = $_GET['controller'];
            $controller_name = str_replace(' ', '', ucwords(str_replace(['_', '-'], ' ', $controller_name)));
            $controller_name[0] = strtolower($controller_name[0]);
    					
			$controller_name = $controller_name . 'Controller';
			$controller = new $controller_name();

			$action_name = (!empty($_GET['action']) ? $_GET['action'] : 'index') . 'Action';
			$param = !empty($_GET['param']) ? $_GET['param'] : null;
			
			if (method_exists($controller, $action_name))
			{
				$controller->$action_name($param);
			}
			else
			{
				die('Incorrect call');
			}
		}
		else 
		{
			die('Incorrect call');
		}
	}
}

/**
 * Function to autoload API controller classes
 */
function __autoload($class)
{
    $class = str_replace(' ', '', ucwords(str_replace(['_', '-'], ' ', $class)));
    $class[0] = strtolower($class[0]);
            
	$path = './api/' . $class . '.php';
	if (file_exists($path))
	{
		require_once($path);	
	}
	else 
	{
		die('Class not found');
	}
}