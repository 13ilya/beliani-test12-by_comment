
To get modules and composer Autoloaders:


1. Composer autoload (read the docs - https://getcomposer.org/)

    include_once(./App/utility/autoload.php);

    $libName = new LibraryClassName();

2. Modules autoload (used Namespaces)

    include_once(./App/modules/GenerateLabel/autoload.php);

   Then it could be called this way -

    \label\HandlerFabric::handle('HandlerName', $auction, $config)
   				->setParam('DB', $db)
   				->setParam('method', $method)
                ->setParam('param_N', $param_N)
   				->action();

3. Global bootstrap.php could be included this way

    include_once(./App/bootstrap.php);

