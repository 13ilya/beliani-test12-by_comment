<?php
namespace label\AliasCorrector;

use label\DB;

/**
 * Class Corrector
 * It works with aliases of content, services, offers etc.
 * It has rules to process aliases to proper url and mechanism to catch bad untracked aliases.
 */
class Corrector
{
    const AREA_CONTENT = 'content';
    const AREA_SERVICE = 'service';
    const AREA_CATEGORY = 'category';
    const AREA_OFFER = 'offer';
    const AREA_NEWS = 'news';

    private static $areas = [
        self::AREA_CONTENT => [
            'table' => 'shop_content',
            'field' => 'alias',
        ],
        self::AREA_SERVICE => [
            'table' => 'shop_service',
            'field' => 'alias',
        ],
        self::AREA_CATEGORY => [
            'table' => 'shop_catalogue',
            'field' => 'alias',
        ],
        self::AREA_OFFER => [
            'table' => 'sa',
            'field' => 'ShopSAAlias',
        ],
        self::AREA_NEWS => [
            'table' => 'shop_news',
            'field' => 'alias',
        ],
    ];

    /**
     * All aliases actually stored in db
     * @var string[]
     */
    private $aliasesRaw;

    /**
     * List of aliases should be stored
     * It contain only incorrect aliases that could be changed
     * @var string[]
     */
    private $aliasesManageable;

    /**
     * List of aliases could not be processed with current rules
     * @var string[]
     */
    private $aliasesProblem;

    /**
     * Linker to connect alias id to essence id (offer, news etc.)
     * @var int[]
     */
    private $aliasesIDs;

    /**
     * List to store language of alias
     * @var string[]
     */
    private $aliasesLanguages;

    /**
     * Corrector constructor.
     * @param string $area what area aliases shall we correct
     */
    public function __construct($area)
    {
        if (!key_exists($area, self::$areas)) {
            throw new \Exception('Bad param $area');
        }

        $dbr = DB::getInstance(DB::USAGE_WRITE);

        $aliases = $dbr->getAll(
            '
                SELECT iid, value, id, language 
                FROM translation 
                WHERE 
                    table_name = ?
                    AND field_name = ?',
            null,
            [
                self::$areas[$area]['table'],
                self::$areas[$area]['field'],
            ]
        );
        foreach ($aliases as $alias) {
            $this->aliasesRaw[$alias->iid] = $alias->value;
            $this->aliasesIDs[$alias->iid] = $alias->id;
            $this->aliasesLanguages[$alias->iid] = $alias->language;
        }
    }

    /**
     * Store processed aliases instead of wrong aliases
     * @throws \Exception
     * @todo implement
     * @return bool if it was correctly executed
     */
    public function correct()
    {
        throw new \Exception('Correction did not implemented yet', 502);

        if (is_null($this->aliasesManageable)) {
            $this->findManageableAliases();
        }
        if (count($this->aliasesManageable) > 0) {
            $db = DB::getInstance(DB::USAGE_READ);
            foreach ($this->aliasesManageable as $iid => $alias) {
            }
            //$db->query();//@todo check return
        }
        //@todo return something
        return true;
    }

    /**
     * Getter for all currently stored aliases
     * @return \string[]
     */
    public function getRawAliases()
    {
        return $this->aliasesRaw;
    }

    /**
     * Getter for aliases that should be stored instead of wrong aliases.
     * @return \string[]
     */
    public function getManageableAliases()
    {
        if (is_null($this->aliasesManageable)) {
            $this->findManageableAliases();
        }
        return $this->aliasesManageable;
    }

    /**
     * Getter for problem aliases
     * Returns list of aliases that should but could not be processed with current rules
     * @return \string[]
     */
    public function getProblemAlieases()
    {
        if (is_null($this->aliasesProblem)) {
            $this->findProblemAliases();
        }
        return $this->aliasesProblem;
    }

    /**
     * Return essence id of alias (essence could be offer, news etc.)
     * @param int $iid alias id
     * @return int
     */
    public function getEssenceId($iid)
    {
        return $this->aliasesIDs[$iid];
    }

    /**
     * Return alias language
     * @param int $iid alias id
     * @return string
     */
    public function getLanguage($iid)
    {
        return $this->aliasesLanguages[$iid];
    }

    /**
     * Generating list of problem aliases
     */
    private function findProblemAliases()
    {
        $this->aliasesProblem = [];
        foreach ($this->aliasesRaw as $iid => $alias) {
            if (isset($this->getManageableAliases()[$iid])) {
                $alias = $this->getManageableAliases()[$iid];
            }
            if (urlencode($alias) !== $alias) {
                $this->aliasesProblem[$iid] = urlencode($alias);
            }
        }
    }

    /**
     * Generating list of manageable aliases
     */
    private function findManageableAliases()
    {
        $this->aliasesManageable = [];
        foreach ($this->aliasesRaw as $iid => $alias) {
            $processedAlias = $this->replaceChars($alias);
            if ($alias !== $processedAlias) {
                $this->aliasesManageable[$iid] = $processedAlias;
            }
        }
    }

    /**
     * Replace wrong url characters with proper chars
     * @param string $alias
     * @return string
     */
    private function replaceChars($alias)
    {
        $rules = [
            'À' => 'A', 'Á' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'Ae', 'Å' => 'A', 'Æ' => 'A', 'Ă' => 'A', 'Ą' => 'A', 'ą' => 'a',
            'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'ae', 'å' => 'a', 'ă' => 'a', 'æ' => 'ae',
            'þ' => 'b', 'Þ' => 'B',
            'Ç' => 'C', 'ç' => 'c', 'Ć' => 'C', 'ć' => 'c', 'č' => 'c', 'Č' => 'C',
            'È' => 'E', 'É' => 'E', 'Ê' => 'E', 'Ë' => 'E', 'Ę' => 'E', 'ę' => 'e',
            'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e', 'ě' => 'e', 'Ě' => 'E',
            'Ğ' => 'G', 'ğ' => 'g',
            'Ì' => 'I', 'Í' => 'I', 'Î' => 'I', 'Ï' => 'I', 'İ' => 'I', 'ı' => 'i', 'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
            'Ł' => 'L', 'ł' => 'l',
            'Ñ' => 'N', 'Ń' => 'N', 'ń' => 'n',
            'Ò' => 'O', 'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'Oe', 'Ø' => 'O', 'ö' => 'oe', 'ø' => 'o', 'Ő' => 'O', 'Ό' => 'O',
            'ð' => 'o', 'ñ' => 'n', 'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ő' => 'o', 'ό' => 'o',
            'ř' => 'r', 'Ř' => 'R',
            'Š' => 'S', 'š' => 's', 'Ş' => 'S', 'ș' => 's', 'Ș' => 'S', 'ş' => 's', 'ß' => 'ss', 'Ś' => 'S', 'ś' => 's',
            'ț' => 't', 'Ț' => 'T',
            'Ù' => 'U', 'Ú' => 'U', 'Û' => 'U', 'Ü' => 'Ue', 'Ű' => 'U',
            'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'ue', 'ű' => 'u',
            'Ý' => 'Y',
            'ý' => 'y', 'ÿ' => 'y',
            'Ž' => 'Z', 'ž' => 'z', 'Ż' => 'Z', 'ż' => 'z', 'Ź' => 'Z', 'ź' => 'z',
            '&ouml;' => 'o', '&uuml;' => 'u',
            ';' => '-', '&' => '-',
            '\'' => '-', ',' => '-', '(' => '-', ')' => '-', '+' => '-', '®' => '-', '–' => '-',
            ' ' => '-', '!' => '', '%' => '', '#' => '-', '/' => '-', '’' => '-', '>' => '-', '?' => '-', '´' => '-',
            '`' => '-', ':' => '-', '=' => '-', '€' => '-', '*' => '-', '™' => '-', '<' => '-', '[' => '-', ']' => '-', "\t" => '-',
            '{' => '-', '}' => '-',
        ];
        return strtr(strtr(strtr($alias, $rules), ['---' => '-']), ['--' => '-']);
    }
}