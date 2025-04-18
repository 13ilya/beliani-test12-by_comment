<?php

/**
 * filters options controller
 */
class filtersOptionsController extends apiController {

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
        require_once ROOT_DIR . '/plugins/function.imageurl.php';
    }
    
    /**
     * @descripton get all filters
     */
    public function allFiltersAction()
    {
        $filters = [
            'users'
            , 'warehouses'
            , 'active_sellers'
            , 'countries'
            , 'source_sellers'
            , 'shipping_methods'
            , 'departments'
            , 'responsible_persons'
            , 'shipping_costs'
            , 'monitored_shipping_price_article'
            , 'active_users'
            , 'inactive_users'
            , 'companies'
            , 'issue_created_where'
            , 'issue_types'
            , 'bank_settings_status'
            , 'shipping_username'
            , 'accounts'
            , 'currency'
            , 'booking_settings_status'
            , 'colours'
            , 'bank_settings'
            , 'booking_settings'
            , 'issue_states'
            , 'countries_with_code'
            , 'date_formats'
            , 'issue_types_inspection'
            , 'suppliers'
            , 'ebay_shop_categories'
        ];
        print_r($filters);
    }

    /**
     * Get filter options
     */
    public function indexAction()
    {
        global $smarty;
        
        $types = $this->_input['type'];
        if ( !is_array($types))
        {
            $types = [$types];
        }

        $this->_result['options'] = [];

        foreach ($types as $type)
        {
            switch ($type) 
            {   
                case 'ebay_shop_categories':
                    $siteid = (int)$this->_input['siteid'];
                    $this->_result['options']['ebay_shop_categories'] = $this->_dbr->getAssoc("select
                        p.CategoryID, IF(pp.ParentID, CONCAT(' -- ', p.Name), IF(p.ParentID, CONCAT(' - ', p.Name), p.Name)) Name
                            from ebay_shop_categories p
                        left join ebay_shop_categories pp on p.ParentID=pp.CategoryID	and p.siteid=pp.siteid
                        left join ebay_shop_categories ppp on pp.ParentID=ppp.CategoryID	and pp.siteid=ppp.siteid
                            where p.siteid=$siteid
                        order by IFNULL(ppp.`order`, IFNULL(pp.`order`, p.`order`))
                        , IF(ppp.order is null and pp.order is null, -1, IF(ppp.order is null and pp.order is not null, p.order, pp.order))
                        , IF(ppp.order is null and pp.order is null, -1, IF(ppp.order is null and pp.order is not null, -1, p.order))");
                break;
                case 'suppliers':
                    $this->_result['options']['suppliers'] = [];
                    $suppliers = \op_Order::listCompaniesAll($this->_db, $this->_dbr);
                    foreach ($suppliers as $supplier) {
                        $this->_result['options']['suppliers'][$supplier->id] = $supplier->name;
                    }
                    break;
                case 'issue_states':
                    $this->_result['options']['issue_states'] = ['open' => 'Open', 'close' => 'Closed', 'on_hold' => 'On hold'];
                    break;
                case 'users':

                    $this->_result['options']['users'] = [];
                    foreach (\User::listAll($this->_db, $this->_dbr) as $_user) 
                    {
                        $this->_result['options']['users'][$_user->username] = $_user->name;
                    }

                    break;

                case 'warehouses':

                    $this->_result['options']['warehouses'] = [];
                    foreach (\Warehouse::listAll($this->_db, $this->_dbr) as $_warehouse) 
                    {
                        $this->_result['options']['warehouses'][$_warehouse->warehouse_id] = $_warehouse->country_code_name;
                    }

                    break;

                case 'employees':
                    
                    $global_users_colors = $smarty->get_template_vars('global_users_colors');
                    $global_users_status = $smarty->get_template_vars('global_users_status');

                    $this->_result['options']['emploees'] = [];
                    foreach (\Employees::listAll($this->_db, $this->_dbr) as $_employee) 
                    {
                        $this->_result['options']['emploees'][$_employee->username] = [
                            'value' => $_employee->name, 
                            'color' => $global_users_colors[$_employee->username], 
                            'status' => $global_users_status[$_employee->username], 
                            'url' => smarty_function_imageurl([
                                'src' => 'employee',
                                'picid' => $_employee->id,
                                'x' => 200], $smarty), 
                        ];
                    }

                    break;

                case 'active_sellers':
                    $this->_result['options']['active_sellers'] = [];
                    foreach(\SellerInfo::listArrayActive($this->_db, $this->_dbr) as $key => $val){
                        $this->_result['options']['active_sellers'][$key] = [$val];
                    }
                    break;

                case 'countries':
                    $this->_result['options']['countries'] = [];
                    $all_countries = allCountries();
                    foreach ($all_countries as $country){
                        $this->_result['options']['countries'][$country] = $country;
                    }
                    break;
                case 'countries_with_code':
                    $this->_result['options']['countries'] = [];
                    $all_countries = $this->_dbr->getAll("select code, `name` from country");
                    foreach ($all_countries as $country){
                        $this->_result['options']['countries'][$country->code] = $country->name;
                    }
                    break;
                    
                case 'source_sellers':
                    $this->_result['options']['source_sellers'] = [];
                    $source_sellers = $this->_dbr->getAssoc("SELECT id, name FROM source_seller WHERE inactive = 0 ORDER BY name");
                    foreach ($source_sellers as $key => $val) {
                        $this->_result['options']['source_sellers'][$key] = $val;
                    }
                    break;

                case 'shipping_methods':
                    $this->_result['options']['shipping_methods'] = [];
                    foreach(\ShippingMethod::listArray($this->_db, $this->_dbr) as $key => $val) {
                        $this->_result['options']['shipping_methods'][$key] = $val;
                    }
                    break;
                    
                case 'departments';
                    $this->_result['options']['departments'] = [];
                    foreach (\Departments::listAllAssoc($this->_db, $this->_dbr, 'AND deleted = 0') as $key => $val){
                        $this->_result['options']['departments'][$key] = $val;
                    }
                    break;
                    
                case 'responsible_persons';
                    $this->_result['options']['responsible_persons'] = [];
                    foreach (\User::listAssoc($this->_db, $this->_dbr, 0) as $key => $val){
                        $this->_result['options']['responsible_persons'][$key] = $val;
                    }
                    break;
                case 'companies':
                    $companies = $this->_dbr->getAssoc("
                        select c.id, a.company 
                        from company c
                        left join address_obj ao on ao.obj_id=c.id and ao.obj='company'
                        left join address a on a.id=ao.address_id
                        order by 2");
                    $this->_result['options']['companies'] = [];
                    foreach ($companies as $key=>$val) {
                        $this->_result['options']['companies'][$key] = $val;
                    }
                    break;
                case 'active_users':
                    $active_users = $this->_dbr->getAssoc("select username, name from users where deleted=0 order by name");
                    $this->_result['options']['active_users'] = [];
                    foreach ($active_users as $key=>$val) {
                        $this->_result['options']['active_users'][$key] = $val;
                    }
                    break;
                case 'inactive_users':
                    $inactive_users = $this->_dbr->getAssoc("select username, name from users where deleted=1 order by name");
                    $this->_result['options']['inactive_users'] = [];
                    foreach ($inactive_users as $key=>$val) {
                        $this->_result['options']['inactive_users'][$key] = $val;
                    }
                    break;
                case 'shipping_costs':
                    $q = "SELECT id, name 
                    FROM shipping_cost 
                    WHERE NOT inactive 
                    ORDER BY name ASC";
                    $shipping_costs = $this->_dbr->getAssoc($q);
                    foreach ($shipping_costs as $key=>$val) {
                        $this->_result['options']['shipping_costs'][$key] = $val;
                    }
                    break;
                case 'monitored_shipping_price_article':
                    $q = "SELECT DISTINCT a.article_id, t.value as article_name 
                    FROM monitored_shipping_price msp
                      JOIN article a ON msp.article_id = a.article_id
                      JOIN translation t ON a.article_id = t.id
                    WHERE msp.monitoring = 1
                      AND t.table_name='article'
                      AND t.field_name = 'name'
                      AND t.language = 'english'
                    ORDER BY updated DESC";
                    $monitored_shipping_price_article = $this->_dbr->getAssoc($q);
                    foreach ($monitored_shipping_price_article as $key=>$val) {
                        $this->_result['options']['monitored_shipping_price_article'][$key] = $val;
                    }
                    break;
                case 'issue_created_where':
                    $pages = ['auction' => 'Auftrag'
                        , 'rma' => 'Ticket'
                        , 'ww_order' => 'WWO'
                        , 'manual' => 'Manual'
                        , 'op_order' => 'OP order'
                        , 'route' => 'Route'
                        , 'insurance' => 'Insurance'
                        , 'rating' => 'Rating'];
                    $this->_result['options']['issue_created_where'] = $pages;
                    break;
                case 'issue_types_inspection':
                    $query = "SELECT id, name FROM issue_type WHERE inactive = 0 and inspection = 1";
                    $types = $this->_dbr->getAssoc($query);
                    foreach ($types as $key => $val) {
                        $this->_result['options']['issue_types_inspection'][$key] = $val;
                    }
                    break;
                case 'issue_types':
                    $query = "SELECT id, name FROM issue_type WHERE inactive = 0";
                    $types = $this->_dbr->getAssoc($query);
                    foreach ($types as $key => $val) {
                        $this->_result['options']['issue_types'][$key] = $val;
                    }
                    break;
                case 'bank_settings_status':
                    $q = "SELECT id, inactive 
                    FROM prologis2.autopay_bank_settings";
                    $res = $this->_dbr->getAssoc($q);
                    foreach ($res as $key => $val) {
                        $this->_result['options']['bank_settings_status'][$key] = $val;
                    }
                    break;
                case 'shipping_username':
                    $q = "SELECT username, name
                    FROM users
                    WHERE 
                        NOT deleted
                        AND use_as_shipping_company = 2
                    ORDER BY name";
                    $res = $this->_dbr->getAssoc($q);
                    foreach ($res as $key => $val) {
                        $this->_result['options']['shipping_username'][$key] = $val;
                    }
                    break;
                case 'accounts':
                    $q = "SELECT iid number 
                    , CONCAT(number, ': ', name) name
                    FROM accounts";
                    $res = $this->_dbr->getAssoc($q);
                    foreach ($res as $key => $val) {
                        $this->_result['options']['accounts'][$key] = $val;
                    }
                    break;
                case 'currency':
                    $q = "SELECT v.value, v.description
                    FROM config_api_values v
                      LEFT JOIN config_api_values_user_ordering uo ON uo.par_id = v.par_id 
                      AND uo.value=v.value
                    WHERE v.par_id=7
                    ORDER BY IFNULL(uo.ordering, v.ordering), v.value";
                    $res = $this->_dbr->getAssoc($q);
                    foreach ($res as $key => $val) {
                        $this->_result['options']['currency'][$key] = $val;
                    }
                    break;
                case 'booking_settings_status':
                    $q = "SELECT id, inactive 
                    FROM prologis2.autopay_booking_settings";
                    $res = $this->_dbr->getAssoc($q);
                    foreach ($res as $key => $val) {
                        $this->_result['options']['booking_settings_status'][$key] = $val;
                    }
                    break;
                case 'colours':
                    $colors = [];
                    for ($r=0; $r<16; $r+=3) {
                        for ($g=0; $g<16; $g+=3) {
                            for ($b=0; $b<16; $b+=3) {
                                $rr = dechex($r);
                                $gg = dechex($g);
                                $bb = dechex($b);
                                $bgcolor = strtoupper('#' . $rr . $rr . $gg . $gg . $bb . $bb);
                                $colors[$bgcolor] = $bgcolor;
                            }
                        }
                    }
                    $this->_result['options']['colours'] = $colors;
                    break;
                case 'bank_settings':
                    $q = "SELECT abs.id, abs.name
                    FROM prologis2.autopay_bank_settings abs";
                    $this->_result['options']['bank_settings'] = $this->_dbr->getAssoc($q);
                    break;
                case 'booking_settings':
                    $q = "SELECT id, name 
                    FROM prologis2.autopay_booking_settings";
                    $this->_result['options']['booking_settings'] = $this->_dbr->getAssoc($q);
                    break;
                case 'date_formats':
                    $q = "select format, title from date_format";
                    $this->_result['options']['date_formats'] = $this->_dbr->getAssoc($q);
                    break;
            }
        }
        $this->output();
    }
    
    /**
     * save filters sets
     * @var $title
     * @var $fields
     * @var $this
     */
    public function saveFilterSetAction() {
        $title = mysqli_real_escape_string($this->_input['title']);
        $fields = mysqli_real_escape_string($this->_input['fields']);
        $this->_db->query("replace saved_filters set title = '$title', filter_set = '" . serialize($fields) . "'");
        $this->getFilterSetAction();
    }
    
    /**
     * get filters sets
     * @var $filters
     * @var $filter
     * @var $this
     */
    public function getFilterSetAction() {
        $this->_result['filters'] = [];
        $filters = $this->_dbr->getAll("select * from saved_filters");
        foreach ($filters as $filter) {
            $this->_result['filters'][$filter->title] = unserialize($filter->filter_set);
        }
        $this->output();
    }
}