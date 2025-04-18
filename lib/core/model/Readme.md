# How it use
## Example model

```

class Article extends CoreModel
{
    /**
     * @var string The table's name that will be used by this model.
     */
    protected static $tableName = 'articles';
}

```

## Create

```
$article = new Article([
    'name' => 'Example #1',
    'state' => 'active',
    'created_at' => date('Y-m-d H:i:s'),
]);

// save model to the database
$article->update();  

// after save a new model you can get it ID
echo $article->getID(); 

// you can change attributes
$article->set('state', 'closed');
$article->set('name', 'Example #1 (closed)');

// you need to save the model after you chaned it
$article->update();

// You can set fields that do not exist in the database, 
// but they will not be written to the database (for avoid DB errors).
$article->set('not_exists_column_name', 'value');
var_dump($article->get('not_exists_column_name')); // (string) "value"


// Use the fill method to set multiple fields.
// By default it will convert empty values to NULL for nullable columns 
// (set the second argument to false if this don't need).
$article->fill([
    'name' => (string)$_REQUEST['state'],
    'state' => (string)$_REQUEST['state'],
    'code' => (int)$_REQUEST['code'],
]);

```

## Select

```
$articles = Article::findBy([
    'active' => 1,
    'status' => 'completed'
]);
// -> SELECT * FROM `articles` WHERE `active` = '1' AND `status` = 'completed'

$articles = Article::findBy([
    ['name', 'LIKE', 'Test%'],
    'status' => ['proccessing', 'completed'],
]);
// -> SELECT * FROM `articles` WHERE `name` LIKE 'Test%' AND `status` IN ('proccessing', 'completed')

var_dump($articles); // Article[]

// Also you can use the short condition to find one model by ID
// @throws \ModelNotFoundException When the model not found 
$article = Article::firstOrFail(1123);
// -> SELECT * FROM `articles` WHERE `id` = '1123' LIMIT 1

```

## Delete

```
$article = Article::firstOrFail(1123);

// Deletes the model's data from the database
$article->delete();
```

## Use model features without inherits 

```
$tableName = 'articles';
$attributes = [
    'name' => 'Example #1',
    'state' => 'active',
    'created_at' => date('Y-m-d H:i:s'),
];

$model = new Model($tableName, $attributes);
$model->update();

$models = Model::findBy($tableName, [
    'active' => 1,
    'status' => 'completed'
]); // Model[]

/** @throws \ModelNotFoundException When the model not found */
$model = Model::firstOrFail($tableName, 1123);

```

## Additional protected method for inherited models

### findBySql

```

    /**
     * @param string $sql The SELECT SQL-query to get the models data from a database.
     *
     * @return static[]   Array with model instances
     */
    protected static function findBySql($sql);

```

#### Example use-case

```

class Article extends CoreModel
{
    /**
     * @var string The table's name that will be used by this model.
     */
    protected static $tableName = 'articles';
    
    /**
     * Returns the Articles by something conditions.
     *
     * @param int $param1 Just for example.
     * @param int $param2 Just for example.
     *
     * @return Article[] An array with instances of the Article.
     */
    public static function findBySomething($param1, $param2)
    {
       $sql = 'very big sql query';
       return static::findBySql($sql);
    }
    
    /**
     * Returns the Articles by another conditions.
     *
     * @param int $param1 Just for example.
     * @param int $param2 Just for example.
     *
     * @return Article[] An array with instances of the Article.
     */
    public static function findBySomethingElse($param1, $param2)
    {
       $sql = 'very big sql query';
       return static::findBySql($sql);
    }
}

```

### firstBy

```

   /**
     * Returns the first model by the conditions or NULL when it not found.
     *
     * @param string $tableName
     *  The table name for the SELECT query.
     *
     * @param integer|array $conditions
     *  The conditions for the SELECT query.
     *  When it is an integer, then it will used as the model's ID.
     *  When it is an array, then it will used as the where conditions (such as in the findBy method)
     *
     * @return null|static The model instance or NULL when it not found
     */
    protected static function firstBy($sql);

```

#### Example use-case

```

class Auction extends CoreModel
{
    /**
     * @var string The table's name that will be used by this model.
     */
    protected static $tableName = 'auctions';
    
    /**
     * Returns the Auction by something unique key.
     *
     * @param int $auctionNumber 
     * @param int $txd
     *
     * @return null|Auction The Auction instance or NULL when it not found
     */
    public static function firstByNumberAndTxd($auctionNumber, $txd)
    {
        return static::firstBy([
            'auction_number' => (int)$auctionNumber,
            'txd' => (int)$txd,
        ]);
    }
}


