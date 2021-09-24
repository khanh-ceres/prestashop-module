<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

class OrderModule extends Module
{
    public function __construct()
    {
        $this->name = 'ordermodule';
        $this->tab = 'shipping_logistics';
        $this->version = '1.0.0';
        $this->author = 'John Doe';
        $this->need_instance = 1;
        $this->ps_versions_compliancy = [
            'min' => '1.6',
            'max' => '1.7.99'
        ];
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Order Module');
        $this->description = $this->l('Numbering the orders');

        $this->confirmUninstall = $this->l('Are you sure you want to uninstall?');

        if (!Configuration::get('ORDERMODULE_NAME')) {
            $this->warning = $this->l('No name provided');
        }
    }

    public function install()
    {
        if (Shop::isFeatureActive()) {
            Shop::setContext(Shop::CONTEXT_ALL);
        }

        return (
            parent::install() && 
            $this->registerHook('actionValidateOrder') &&
            $this->alterTable('add') && // add an extra column named order_carrier_number to orders table
            Configuration::updateValue('ORDERMODULE_NAME', 'order module')
        );
    }

    public function uninstall()
    {
        return (
            parent::uninstall() && 
            $this->alterTable('remove') &&
            Configuration::deleteByName('ORDERMODULE_NAME')
        );
    }

    public function hookActionValidateOrder($params)
    {
        $db = Db::getInstance();
        // get the biggest order's number of the current carrier
        $biggestOrderNumber = $db->getValue('
            SELECT order_carrier_number FROM '._DB_PREFIX_.'orders
            WHERE id_carrier = '. $params['order']->id_carrier .'
            ORDER BY order_carrier_number DESC
        ');

        if (!$biggestOrderNumber) {
            $biggestOrderNumber = 1;
        } else {
            $biggestOrderNumber++;
        }

        return $db->execute('
            UPDATE '._DB_PREFIX_.'orders
            SET order_carrier_number = '.$biggestOrderNumber.'
            WHERE id_order = '. $params['order']->id .' AND id_carrier = '. $params['order']->id_carrier .'
        ');
    }

    public function getContent()
    {
        $currentCarrier = (string) Tools::getValue('carrier');

        return $this->displayForm($currentCarrier);
    }

    public function displayForm($currentCarrier)
    {
        $carriers = Db::getInstance()->executeS('SELECT id_carrier, name FROM '._DB_PREFIX_.'carrier');
        if (!$currentCarrier) {
            $currentCarrier = $carriers[0]['id_carrier'];
        }

        $form = [
            'form' => [
                'legend' => [
                    'title' => $this->l('Settings'),
                    'icon' => 'icon-cogs',
                ],
                'input' => [
                    [
                        'type' => 'select',
                        'label' => $this->l('Carriers'),
                        'name' => 'carrier',
                        'desc' => $this->l('Please select an carrier'),
                        'onchange' => 'this.form.submit()',
                        'options' => [
                            'query' => $carriers,
                            'id' => 'id_carrier',
                            'name' => 'name',
                        ],
                    ],
                ],
            ],
        ];

        $helper = new HelperForm();
        $helper->table = $this->table;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex . '&' . http_build_query(['configure' => $this->name]);
        $helper->default_form_language = (int) Configuration::get('PS_LANG_DEFAULT');

        $helper->fields_value['carrier'] = $currentCarrier;

        return $helper->generateForm([$form]) . $this->displayOrderList($currentCarrier);
    }

    public function displayOrderList($carrier_id) 
    {
        $orders = Db::getInstance()->executeS('
            SELECT o.order_carrier_number, o.id_order,  o.reference, CONCAT_WS("", cl.symbol, o.total_paid) AS total_paid, o.payment, o.date_add, CONCAT_WS(" ", c.firstname, c.lastname) AS customer
            FROM '. _DB_PREFIX_ .'orders o
            LEFT JOIN '. _DB_PREFIX_ .'customer c ON o.id_customer = c.id_customer
            LEFT JOIN '. _DB_PREFIX_ .'currency_lang cl ON o.id_currency = cl.id_currency AND cl.id_lang = '. (int) Configuration::get('PS_LANG_DEFAULT') .'
            WHERE o.id_carrier = '. $carrier_id .'
                AND o.order_carrier_number IS NOT NULL
            ORDER BY o.order_carrier_number DESC
        ');

        $this->fields_list = array(
            'order_carrier_number' => array(
                'title' => $this->l('#'),
                'type' => 'text',
            ),
            'id_order' => array(
                'title' => $this->l('ID'),
                'type' => 'text',
            ),
            'reference' => array(
                'title' => $this->l('Reference'),
                'type' => 'text',
            ),
            'total_paid' => array(
                'title' => $this->l('Total'),
                'type' => 'text',
            ),
            'payment' => array(
                'title' => $this->l('Payment'),
                'type' => 'text',
            ),
            'date_add' => array(
                'title' => $this->l('Date'),
                'type' => 'datetime',
            ),
            'customer' => array(
                'title' => $this->l('Customer'),
                'type' => 'text',
            ),
        );
        $helper = new HelperList();
         
        $helper->shopLinkType = '';
         
        $helper->simple_header = true;
         
        $helper->identifier = 'id_order';
        $helper->show_toolbar = true;
        $helper->title = $this->l('Orders');
        $helper->table = $this->name.'_orders';
         
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex.'&configure='.$this->name;

        return $helper->generateList($orders, $this->fields_list);
    }

    private function alterTable($method)
    {
        if ($method == 'add') {
            $sql = 'DESCRIBE '._DB_PREFIX_.'orders';        
            $columns = Db::getInstance()->executeS($sql);
            $found = false;
            foreach($columns as $col){
                if($col['Field']=='order_carrier_number'){
                    $found = true;
                    break;
                }
            }
            if(!$found){
                $sql = 'ALTER TABLE `'._DB_PREFIX_.'orders` ADD `order_carrier_number` int(11) DEFAULT NULL AFTER `id_carrier`';
            }

        } else {
            $sql = 'ALTER TABLE `'._DB_PREFIX_.'orders` DROP `order_carrier_number`';
        }

        if (!Db::getInstance()->execute($sql)) {
            return false;
        }

        return true;
    }

    public function isUsingNewTranslationSystem()
    {
        return false;
    }
}
