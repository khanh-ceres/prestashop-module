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

        $this->displayName = $this->trans('Order Module', [], 'Modules.Ordermodule.Ordermodule');
        $this->description = $this->trans('Numbering the orders', [], 'Modules.Ordermodule.Ordermodule');

        $this->confirmUninstall = $this->trans('Are you sure you want to uninstall?', [], 'Modules.Ordermodule.Ordermodule');
    }

    public function install()
    {
        if (Shop::isFeatureActive()) {
            Shop::setContext(Shop::CONTEXT_ALL);
        }

        return (
            parent::install() && 
            $this->registerHook('actionValidateOrder') &&
            $this->alterTable('add') // add an extra column named order_carrier_number to orders table
        );
    }

    public function uninstall()
    {
        return (
            parent::uninstall() && 
            $this->alterTable('remove')
        );
    }

    public function hookActionValidateOrder($params)
    {
        $db = Db::getInstance();
        $shop_id = $params['order']->id_shop;
        $carrier_id = $params['order']->id_carrier;
        $carrier_reference = $db->getValue('SELECT id_reference FROM '. _DB_PREFIX_ .'carrier WHERE id_carrier = ' . $carrier_id);
        // get the biggest order's number of the current carrier
        $biggestOrderNumber = $db->getValue('
            SELECT order_carrier_number 
            FROM '._DB_PREFIX_.'orders o
            WHERE id_carrier IN (SELECT id_carrier
                                FROM '._DB_PREFIX_.'carrier
                                WHERE id_reference = '. $carrier_reference .')
                AND id_shop = '. $shop_id .'
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
            WHERE id_order = '. $params['order']->id .'
        ');
    }

    public function getContent()
    {
        $currentCarrier = (string) Tools::getValue('carrier');

        return $this->displayForm($currentCarrier);
    }

    public function displayForm($currentCarrier)
    {
        // get all carriers from selected shops
        $shop_ids = implode(", ", Shop::getContextListShopID());
        $carriers_query = '
            SELECT DISTINCT c.id_carrier, c.name 
            FROM '._DB_PREFIX_.'carrier c
            LEFT JOIN '._DB_PREFIX_.'carrier_shop cs ON c.id_carrier = cs.id_carrier
            WHERE c.deleted = 0 AND cs.id_shop IN ('. $shop_ids .')
        ';
        $carriers = Db::getInstance()->executeS($carriers_query);

        if (!$currentCarrier) {
            $currentCarrier = $carriers[0]['id_carrier'];
        }

        return $this->displayCarrierSelector($carriers, $currentCarrier) . $this->displayOrderTables($currentCarrier);
    }

    public function displayCarrierSelector($carriers, $currentCarrier) {
        $form = [
            'form' => [
                'legend' => [
                    'title' => $this->trans('Settings', [], 'Modules.Ordermodule.Ordermodule'),
                    'icon' => 'icon-cogs',
                ],
                'input' => [
                    [
                        'type' => 'select',
                        'label' => $this->trans('Carriers', [], 'Modules.Ordermodule.Ordermodule'),
                        'name' => 'carrier',
                        'desc' => $this->trans('Please select a carrier', [], 'Modules.Ordermodule.Ordermodule'),
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

        return $helper->generateForm([$form]);
    }

    public function displayOrderTables($carrier_id) 
    {
        $db = Db::getInstance();
        // get id_reference from current id_carrier
        $carrier_reference = $db->getValue('SELECT id_reference FROM '. _DB_PREFIX_ .'carrier WHERE id_carrier = ' . $carrier_id);
        // ids of selected shops
        $shop_ids = Shop::getContextListShopID();

        $order_tables = '';
        // each shop has a table
        foreach ($shop_ids as $shop_id) {
            $shop_name = $db->getValue('SELECT name FROM '._DB_PREFIX_.'shop WHERE id_shop = '. $shop_id);
            // get carrier's orders by id_reference and selected shop
            $orders = $db->executeS('
                SELECT o.order_carrier_number, o.id_order,  o.reference, CONCAT_WS("", cl.symbol, o.total_paid) AS total_paid, o.payment, o.date_add, CONCAT_WS(" ", c.firstname, c.lastname) AS customer
                FROM '. _DB_PREFIX_ .'orders o
                LEFT JOIN '. _DB_PREFIX_ .'customer c ON o.id_customer = c.id_customer
                LEFT JOIN '. _DB_PREFIX_ .'currency_lang cl ON o.id_currency = cl.id_currency AND cl.id_lang = '. (int) Configuration::get('PS_LANG_DEFAULT') .'
                WHERE o.id_carrier IN (SELECT c.id_carrier FROM '. _DB_PREFIX_ .'carrier c
                                        WHERE c.id_reference = '. $carrier_reference .')
                    AND o.order_carrier_number IS NOT NULL
                    AND o.id_shop = '. $shop_id .'
                ORDER BY o.order_carrier_number DESC
            ');

            $this->fields_list = array(
                'order_carrier_number' => array(
                    'title' => $this->trans('#', [], 'Modules.Ordermodule.Ordermodule'),
                    'type' => 'text',
                ),
                'id_order' => array(
                    'title' => $this->trans('ID', [], 'Modules.Ordermodule.Ordermodule'),
                    'type' => 'text',
                ),
                'reference' => array(
                    'title' => $this->trans('Reference', [], 'Modules.Ordermodule.Ordermodule'),
                    'type' => 'text',
                ),
                'total_paid' => array(
                    'title' => $this->trans('Total', [], 'Modules.Ordermodule.Ordermodule'),
                    'type' => 'text',
                ),
                'payment' => array(
                    'title' => $this->trans('Payment', [], 'Modules.Ordermodule.Ordermodule'),
                    'type' => 'text',
                ),
                'date_add' => array(
                    'title' => $this->trans('Date', [], 'Modules.Ordermodule.Ordermodule'),
                    'type' => 'datetime',
                ),
                'customer' => array(
                    'title' => $this->trans('Customer', [], 'Modules.Ordermodule.Ordermodule'),
                    'type' => 'text',
                ),
            );
            $helper = new HelperList();
             
            $helper->shopLinkType = '';
             
            $helper->simple_header = true;
             
            $helper->identifier = 'id_order';
            $helper->show_toolbar = true;
            $helper->title = $this->trans($shop_name, [], 'Modules.Ordermodule.Ordermodule');
            $helper->table = $this->name.'_orders';
             
            $helper->token = Tools::getAdminTokenLite('AdminModules');
            $helper->currentIndex = AdminController::$currentIndex.'&configure='.$this->name;

            $order_tables .= $helper->generateList($orders, $this->fields_list);
        }

        return $order_tables;
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
        return true;
    }
}
