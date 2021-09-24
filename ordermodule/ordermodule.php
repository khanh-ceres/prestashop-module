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
        $db = Db::getInstance();
        $carriers = $db->executeS('SELECT id_carrier, name FROM '._DB_PREFIX_.'carrier');

        if (!$currentCarrier) {
            $currentCarrier = $carriers[0]['id_carrier'];
        }
        $orders = $db->executeS('
            SELECT o.order_carrier_number, o.id_order,  o.reference, o.total_paid, o.payment, o.date_add, CONCAT_WS(" ", c.firstname, c.lastname)
            FROM ps_orders o
            LEFT JOIN ps_customer c ON o.id_customer = c.id_customer
            WHERE o.id_carrier = '. $currentCarrier .' AND o.order_carrier_number IS NOT NULL
            ORDER BY o.order_carrier_number DESC
        ');

        $this->context->smarty->assign([
            'carriers' => $carriers,
            'currentCarrier' => $currentCarrier,
            'orders' => $orders,
        ]);

        return $this->display(__FILE__, '/views/templates/admin/config.tpl');
    }

    // public function displayForm()
    // {
    //     $carriers = Db::getInstance()->executeS('SELECT id_carrier, name FROM '._DB_PREFIX_.'carrier');
    //     $form = [
    //         'form' => [
    //             'legend' => [
    //                 'title' => $this->l('Settings'),
    //                 'icon' => 'icon-cogs',
    //             ],
    //             'input' => [
    //                 [
    //                     'type' => 'select',
    //                     'label' => 'Carriers',
    //                     'name' => 'CARRIER',
    //                     'desc' => 'Please select an carrier',
    //                     'options' => [
    //                         'query' => $carriers,
    //                         'id' => 'id_carrier',
    //                         'name' => 'name',
    //                     ],
    //                 ],
    //             ],
    //         ],
    //     ];

    //     $helper = new HelperForm();
    //     $helper->table = $this->table;
    //     $helper->name_controller = $this->name;
    //     $helper->token = Tools::getAdminTokenLite('AdminModules');
    //     $helper->currentIndex = AdminController::$currentIndex . '&' . http_build_query(['configure' => $this->name]);
    //     $helper->default_form_language = (int) Configuration::get('PS_LANG_DEFAULT');

    //     if ($carriers && count($carriers) > 0) {
    //         $helper->fields_value['CARRIER'] = $carriers[0]['id_carrier'];
    //     }

    //     return $helper->generateForm([$form]);
    // }

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
}
