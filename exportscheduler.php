
<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

$autoloadPath = __DIR__ . '/vendor/autoload.php';
if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
}

class ExportScheduler extends Module
{
    public function __construct()
    {
        $this->name = 'exportscheduler';
        $this->version = '1.0.0';
        $this->author = 'Arshid';
        $this->tab = 'administration';

        parent::__construct();

        $this->displayName = $this->l('Scheduled Exporter');
        $this->description = $this->l('Exports orders, sends via email, and saves to disk.');
        $this->ps_versions_compliancy = ['min' => '1.7.1.0', 'max' => _PS_VERSION_];
    }

    public function install(): bool
    {
        return parent::install()
            && Configuration::updateValue('CSVEXPORTSCHEDULER_ENABLE_MAIL', 1)
            && Configuration::updateValue('CSVEXPORTSCHEDULER_EXPORT_COLUMNS', json_encode($this->getDefaultExportColumns()))
            && Configuration::updateValue('CSVEXPORTSCHEDULER_TOKEN', Tools::passwdGen(32));
    }

    public function uninstall(): bool
    {
        return parent::uninstall();
    }

    public function getContent(): string
    {
        $this->context->controller->addCss($this->_path . 'views/css/tagify.min.css');
        $this->context->controller->addJs($this->_path . 'views/js/tagify.min.js');
        $this->context->controller->addJS($this->_path . 'views/js/dashboard.js');

        $output = '';

        if (Tools::isSubmit('submit')) {
            if (Tools::getValue('CSVEXPORTSCHEDULER_ENABLE_MAIL')
                && !Validate::isEmail(Tools::getValue('CSVEXPORTSCHEDULER_TO_EMAIL'))) {
                $output .= $this->displayError($this->l('Invalid to email address.'));

                return $output . $this->renderForm();
            }

            Configuration::updateValue(
                'CSVEXPORTSCHEDULER_ENABLE_MAIL',
                (int) Tools::getValue('CSVEXPORTSCHEDULER_ENABLE_MAIL')
            );

            Configuration::updateValue(
                'CSVEXPORTSCHEDULER_TO_EMAIL',
                Tools::getValue('CSVEXPORTSCHEDULER_TO_EMAIL')
            );

            Configuration::updateValue(
                'CSVEXPORTSCHEDULER_EXPORT_COLUMNS',
                Tools::getValue('CSVEXPORTSCHEDULER_EXPORT_COLUMNS')
            );
            $output .= $this->displayConfirmation($this->l('Settings updated.'));
        }

        if (Tools::isSubmit('regenerate_token')) {
            $newToken = Tools::passwdGen(32);
            Configuration::updateValue('CSVEXPORTSCHEDULER_TOKEN', $newToken);
            $output .= $this->displayConfirmation($this->l('New token generated.'));
        }

        return $output . $this->renderForm();
    }

    private function getOrderFields(): array
    {
        return [
            // Basic order table fields
            ['id' => 'id_order', 'value' => 'Order ID'],
            ['id' => 'reference', 'value' => 'Reference'],
            ['id' => 'date_add', 'value' => 'Order Date'],
            ['id' => 'date_upd', 'value' => 'Last Update'],
            ['id' => 'total_paid', 'value' => 'Total Paid'],
            ['id' => 'total_paid_tax_incl', 'value' => 'Total Paid (incl. tax)'],
            ['id' => 'total_paid_tax_excl', 'value' => 'Total Paid (excl. tax)'],
            ['id' => 'total_shipping', 'value' => 'Shipping Cost'],
            ['id' => 'total_products', 'value' => 'Product Total'],
            ['id' => 'total_discounts', 'value' => 'Discount Total'],
            ['id' => 'payment', 'value' => 'Payment Method'],
            ['id' => 'module', 'value' => 'Payment Module'],
            ['id' => 'current_state', 'value' => 'Order State ID'],

            // Translated or joined data
            ['id' => 'order_state_status', 'value' => 'Order Status'],
            ['id' => 'customer_name', 'value' => 'Customer Name'],
            ['id' => 'customer_email', 'value' => 'Customer Email'],
            ['id' => 'id_customer', 'value' => 'Customer ID'],

            // Shipping details
            ['id' => 'carrier_name', 'value' => 'Carrier'],

            // Address details (billing/shipping)
            ['id' => 'delivery_address1', 'value' => 'Delivery Address 1'],
            ['id' => 'delivery_address2', 'value' => 'Delivery Address 2'],
            ['id' => 'delivery_postcode', 'value' => 'Delivery Postcode'],
            ['id' => 'delivery_city', 'value' => 'Delivery City'],
            ['id' => 'delivery_phone_mobile', 'value' => 'Delivery Mobile'],
            ['id' => 'delivery_phone', 'value' => 'Delivery Phone'],
            ['id' => 'delivery_country', 'value' => 'Delivery Country'],
            ['id' => 'delivery_state', 'value' => 'Delivery State'],

            // Products summary
            ['id' => 'product_list', 'value' => 'Products (Qty: X)'],
            ['id' => 'products_reference', 'value' => 'Products - Reference (Qty: X)'],
        ];
    }

    private function getDefaultExportColumns(): array
    {
        return [
            ['id' => 'id_order',        'value' => 'Order ID'],
            ['id' => 'reference',       'value' => 'Reference'],
            ['id' => 'customer_name',   'value' => 'Customer Name'],
            ['id' => 'customer_email',  'value' => 'Customer Email'],
            ['id' => 'total_paid',      'value' => 'Total Paid'],
            ['id' => 'payment',         'value' => 'Payment Method'],
            ['id' => 'order_state',     'value' => 'Order Status'],
            ['id' => 'product_list',    'value' => 'Products (Qty: X)'],
            ['id' => 'date_add',        'value' => 'Order Date'],
        ];
    }

    private function renderForm(): string
    {
        $defaultLang = (int) Configuration::get('PS_LANG_DEFAULT');
        $export_columns = Configuration::get('CSVEXPORTSCHEDULER_EXPORT_COLUMNS');
        $export_columns = $export_columns ? $export_columns : json_encode($this->getDefaultExportColumns());

        $fieldsForm[0]['form'] = [
            'legend' => [
                'title' => $this->l('Export Scheduler Settings'),
                'icon' => 'icon-cogs',
            ],
            'input' => [
                [
                    'type' => 'text',
                    'label' => $this->l('Cron Security Token'),
                    'name' => 'CSVEXPORTSCHEDULER_TOKEN',
                    'required' => true,
                    'readonly' => true,
                ],
                [
                    'type' => 'switch',
                    'label' => $this->l('Enable Email Notification'),
                    'name' => 'CSVEXPORTSCHEDULER_ENABLE_MAIL',
                    'is_bool' => true,
                    'values' => [
                        [
                            'id' => 'active_on',
                            'value' => 1,
                            'label' => $this->l('Enabled'),
                        ],
                        [
                            'id' => 'active_off',
                            'value' => 0,
                            'label' => $this->l('Disabled'),
                        ],
                    ],
                ],
                [
                    'type' => 'text',
                    'label' => $this->l('To Email'),
                    'name' => 'CSVEXPORTSCHEDULER_TO_EMAIL',
                    'required' => true,
                    'class' => 'esr-to-email',
                ],
                [
                    'type' => 'html',
                    'label' => $this->l('Order Columns'),
                    'desc' => $this->l('You can drag and sort the columns.'),
                    'name' => 'CSVEXPORTSCHEDULER_EXPORT_COLUMNS',
                    'html_content' => '<input type="text" 
                        name="CSVEXPORTSCHEDULER_EXPORT_COLUMNS"  
                        data-items="' . htmlspecialchars(json_encode($this->getOrderFields(), JSON_UNESCAPED_UNICODE)) . '"
                        class="esr-column_input" value="' . htmlspecialchars("$export_columns") .
                    '">',
                ],
            ],
            'buttons' => [
                [
                    'title' => $this->l('Regenerate Token'),
                    'icon' => 'process-icon-refresh',
                    'type' => 'submit',
                    'name' => 'regenerate_token',
                ],
            ],
            'submit' => [
                'name' => 'submit',
                'title' => $this->l('Save'),
                'class' => 'btn btn-default pull-right',
            ],
        ];

        $helper = new HelperForm();
        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;
        $helper->default_form_language = $defaultLang;
        $helper->allow_employee_form_lang = $defaultLang;

        $helper->fields_value = [
            'CSVEXPORTSCHEDULER_TOKEN' => Configuration::get('CSVEXPORTSCHEDULER_TOKEN'),
            'CSVEXPORTSCHEDULER_ENABLE_MAIL' => Configuration::get('CSVEXPORTSCHEDULER_ENABLE_MAIL'),
            'CSVEXPORTSCHEDULER_TO_EMAIL' => Configuration::get('CSVEXPORTSCHEDULER_TO_EMAIL') ?: Configuration::get('PS_SHOP_EMAIL'),
        ];

        return $helper->generateForm($fieldsForm);
    }
}
