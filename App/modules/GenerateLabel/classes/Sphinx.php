<?php
namespace label;

use \label\DebugToolbar\DebugToolbar;
use \DebugBar\DataCollector\PDO\TraceablePDO;
/**
 * Class to work with sphinx. Singleton.
 */
class Sphinx
{
    private static $instance;
    private $provider;

    /**
     * Return instance of current class, singleton
     * @return Sphinx
     */
    public static function getInstance()
    {
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Search for similar queries within all previous search queries on shop with language
     * @param string $keyword
     * @param string $language
     * @return array where values are object with 'keyword' field
     */
    public function findSimilarSearchRequests($keyword, $language)
    {
        if (SPHINX_ENABLED === false) {
            return array();
        }

        $keyword = $this->provider->quote($keyword);
        $limit = 10;

        $ps = $this->provider->prepare('
            SELECT keyword
            FROM idx_search_words_history
            WHERE
                lang = :language
                AND keyword != :keyword
                AND MATCH(:keyword)
            ORDER BY requests DESC
            LIMIT :limit
        ');
        $ps->bindParam('language', $language, \PDO::PARAM_STR);
        $ps->bindParam('keyword', $keyword, \PDO::PARAM_STR);
        $ps->bindParam('limit', $limit, \PDO::PARAM_INT);

        $ps->execute();

        return $ps->fetchAll();
    }

    /**
     * Search for keywords in current shop offers
     * @param string $keyword needle
     * @param string $language
     * @param int $shopId
     * @return array of int
     * @throws null
     */
    public function findSimilarOffers($keywords, $language, $shopId)
    {
        if (SPHINX_ENABLED === false) {
            return array();
        }

        $shopId = (int)$shopId;

        $query = '
            SELECT id_sa
            FROM idx_offers' . $shopId . '
            WHERE
                MATCH(:keyword)
                AND language = :language
            GROUP BY id_sa
            LIMIT 1000';

        $ps = $this->provider->prepare($query);
        $searchLine = self::prepareSearchKeysForRequest($keywords);
        $ps->bindParam('keyword', $searchLine, \PDO::PARAM_STR);
        $ps->bindParam('language', $language, \PDO::PARAM_STR);

        $ps->execute();

        /**
         * Return only ids in flat array
         */
        return array_map(function($row) {return $row->id_sa;}, $ps->fetchAll());
    }

    /**
     * Get found row for last query without limit
     * @return int
     */
    public function getLastQueryRowsCount()
    {
        $result = $this->provider->query('SHOW META LIKE \'total_found\'');
        $row = $result->fetch();
        return $row->Value;
    }

    /**
     * Prepare search keys for request.
     * Key can contain quotes (for phrases) and/or operator "+" (mandatory word/phrase)
     * @param array $keys array of search words/phrases, f.e.
     *  1st example: ['first phrase', 'word2', '+word3', '+fourth phrase']
     *  2nd example: ['some phrase', '2nd_word', '3rd phrase']
     * @return string prepared to use with MATCH function, f.e. for inputs above
     *  1st example: (word3 "fourth phrase" ) MAYBE "first phrase" MAYBE word2
     *  2nd example: "some phrase" | 2nd_word | "3rd phrase"
     */
    private function prepareSearchKeysForRequest($keys)
    {
        $required = array();
        $optional = array();

        foreach ($keys as $var) {
            $var = $this->provider->quote($var);
            
            if (strpos($var, '+') === 0) {
                $required[] = substr($var, 1);
            } else {
                $optional[] = $var;
            }
        }

        foreach ($required as $k => $v) {
            if (strpos($v, ' ') !== false) {
                $required[$k] = '"'.$v.'"';
            }
        }
        foreach ($optional as $k => $v) {
            if (strpos($v, ' ') !== false) {
                $optional[$k] = '"'.$v.'"';
            }
        }

        $searchLine = '';
        if (count($required) > 0) {
            $searchLine .= '(';
            foreach ($required as $item) {
                $searchLine .= $item.' ';
            }
            $searchLine .= ')';
            foreach ($optional as $item) {
                $searchLine .= ' MAYBE '.$item;
            }
        } else {
            $searchLine = implode(' | ', $optional);
        }

        return $searchLine;
    }

    /**
     * Create connection with sphinx server
     */
    private function __construct()
    {
        if (SPHINX_ENABLED === true) {
            $options = [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_OBJ,
                \PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8',
            ];
            $this->provider = new \PDO('mysql:host=' . SPHINX_HOST . ';port=' . SPHINX_PORT_SQL, null, null, $options);

            if (DebugToolbar::isEnabled(DebugToolbar::TRACE_SPHINX)) {
                /**
                 * Add cover for PDO connection to trace it
                 */
                $debugToolbar = DebugToolbar::getInstance();
                $databaseCollector = $debugToolbar->getDatabaseCollector();
                $this->provider = new TraceablePDO($this->provider);
                $databaseCollector->addConnection($this->provider, 'Sphinx');
                $debugToolbar->addCollector($databaseCollector);
            }
        }
    }

    private function __clone() {}

}