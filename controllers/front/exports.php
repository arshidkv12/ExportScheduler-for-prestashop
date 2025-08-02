<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

class ExportSchedulerExportsModuleFrontController extends ModuleFrontController
{
    public $auth = false;
    private $id_lang;

    public function display()
    {
        $this->id_lang = (int) Configuration::get('PS_LANG_DEFAULT');
        $providedToken = Tools::getValue('token');
        $storedToken = Configuration::get('CSVEXPORTSCHEDULER_TOKEN');

        if (!$providedToken || $providedToken !== $storedToken) {
            exit('Invalid token.');
        }

        $interval = Tools::getValue('interval') ?: 'all';

        if (Tools::getValue('csv') === '1' && empty(Tools::getValue('download'))) {
            $filepath = $this->exportCsv($interval);
            $this->sendEmailWithAttachment($filepath);
            $this->ajaxRender(json_encode(['msg' => $this->module->l('CSV Export done')]));

            return;
        }

        if (Tools::getValue('xlsx') === '1' && empty(Tools::getValue('download'))) {
            $filepath = $this->exportXLSX($interval);
            $this->sendEmailWithAttachment($filepath);
            $this->ajaxRender(json_encode(['msg' => $this->module->l('XLSX Export done')]));

            return;
        }

        if (Tools::getValue('csv') === '1' && Tools::getValue('download') === '1') {
            $filepath = $this->exportCsv($interval);
            $this->download($filepath, 'text/csv');
        }

        if (Tools::getValue('xlsx') === '1' && Tools::getValue('download') === '1') {
            $filepath = $this->exportXLSX($interval);
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $filepath);
            finfo_close($finfo);
            $this->download($filepath, $mimeType);
        }

        if (!empty(Tools::getValue('file')) && Tools::getValue('download') === '1') {
            $file = Tools::getValue('file');
            $filePath = _PS_DOWNLOAD_DIR_ . $this->module->name . '/' . $file;
            if (!file_exists($filePath)) {
                return;
            }
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $filePath);
            finfo_close($finfo);
            $this->download($filePath, $mimeType);
        }

        $this->ajaxRender(json_encode(['msg' => false]));
    }

    private function download($filepath, $contentType = 'application/octet-stream')
    {
        if (!file_exists($filepath)) {
            header('HTTP/1.0 404 Not Found');
            exit($this->module->l('File not found.'));
        }

        $filename = basename($filepath);

        while (ob_get_level()) {
            ob_end_clean();
        }

        header('Content-Description: File Transfer');
        header('Content-Type: ' . $contentType);
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($filepath));

        flush();
        readfile($filepath);
        exit;
    }

    public function exportCsv(string $interval): string
    {
        $orders = $this->getOrders($interval);
        $fileName = 'orders-' . date('Ymd-His') . '.csv';
        $filePath = _PS_DOWNLOAD_DIR_ . $this->module->name;
        $export_columns = Configuration::get('CSVEXPORTSCHEDULER_EXPORT_COLUMNS');
        $export_columns = $export_columns ? json_decode($export_columns) : [];

        if (!is_dir($filePath)) {
            mkdir($filePath, 0755, true);
        }

        $filePath = _PS_DOWNLOAD_DIR_ . $this->module->name . '/' . $interval . '-' . $fileName;

        $titles = array_map(function ($item) {
            return $item->value;
        }, $export_columns);

        $columnIds = array_map(function ($item) {
            return $item->id;
        }, $export_columns);

        $fp = fopen($filePath, 'w');
        fputcsv($fp, $titles);

        foreach ($orders as $order) {
            $mapOrder = $this->mapOrderFields($order, $export_columns);
            $row = [];
            foreach ($columnIds as $id) {
                $row[] = $mapOrder[$id] ?? '';
            }

            fputcsv($fp, $row);
        }

        fclose($fp);

        return $filePath;
    }

    private function exportXLSX($interval)
    {
        $orders = $this->getOrders($interval);
        $fileName = 'orders-' . date('Ymd-His') . '.xlsx';
        $filePath = _PS_DOWNLOAD_DIR_ . $this->module->name;

        $export_columns = Configuration::get('CSVEXPORTSCHEDULER_EXPORT_COLUMNS');
        $export_columns = $export_columns ? json_decode($export_columns) : [];

        if (!is_dir($filePath)) {
            mkdir($filePath, 0755, true);
        }

        $titles = array_map(function ($item) {
            return $item->value;
        }, $export_columns);

        $columnIds = array_map(function ($item) {
            return $item->id;
        }, $export_columns);

        $orderList = [];
        $orderList[] = $titles;
        foreach ($orders as $order) {
            $mapOrder = $this->mapOrderFields($order, $export_columns);
            $row = [];
            foreach ($columnIds as $id) {
                $row[] = $mapOrder[$id] ?? '';
            }
            $orderList[] = $row;
        }
        unset($orders);
        $filePath = _PS_DOWNLOAD_DIR_ . $this->module->name . '/' . $interval . '-' . $fileName;
        \Shuchkin\SimpleXLSXGen::fromArray($orderList)->saveAs($filePath);

        return $filePath;
    }

    private function sendEmailWithAttachment($filePath)
    {
        if (empty(Configuration::get('CSVEXPORTSCHEDULER_ENABLE_MAIL'))) {
            return;
        }
        $to = Configuration::get('CSVEXPORTSCHEDULER_TO_EMAIL');
        $subject = $this->module->l('Scheduled CSV Export');
        $link = $this->context->link;
        $storedToken = Configuration::get('CSVEXPORTSCHEDULER_TOKEN');
        $filename = basename($filePath);
        $download_link = $link->getModuleLink(
            $this->module->name,
            'exports',
            [
                'token' => $storedToken,
                'download' => '1',
                'file' => $filename,
            ]
        );

        $templateVars = [
            '{export_type}' => 'Orders',
            '{export_date}' => date('Y-m-d H:i:s'),
            '{shop_name}' => Configuration::get('PS_SHOP_NAME'),
            '{download_link}' => $download_link,
        ];

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $filePath);
        finfo_close($finfo);

        $attachment = [
            [
                'content' => file_get_contents($filePath),
                'name' => $filename,
                'mime' => $mimeType,
            ],
        ];

        Mail::Send(
            $this->id_lang,
            'scheduled_export',
            $subject,
            $templateVars,
            $to,
            null,
            null,
            null,
            $attachment,
            null,
            _PS_MODULE_DIR_ . $this->module->name . '/mails/'
        );
    }

    private function getOrders(string $interval): array
    {
        switch (strtolower($interval)) {
            case 'daily':
                $whereSql = 'o.date_add > (CURDATE() - INTERVAL 1 DAY)';
                break;
            case 'weekly':
                $whereSql = 'o.date_add > (CURDATE() - INTERVAL 1 WEEK)';
                break;
            case 'monthly':
                $whereSql = 'o.date_add > (CURDATE() - INTERVAL 1 MONTH)';
                break;
            case 'yearly':
                $whereSql = 'o.date_add > (CURDATE() - INTERVAL 1 YEAR)';
                break;
            case 'all':
            default:
                $whereSql = '';
                break;
        }

        $query = new DbQuery();
        $query->select(
            'o.*, 
            c.name AS carrier_name,
            a.address1 AS delivery_address1, 
            a.address2 AS delivery_address2, 
            a.city AS delivery_city,
            a.postcode AS delivery_postcode,
            a.phone AS delivery_phone,
            a.phone_mobile AS delivery_phone_mobile,
            sd.name AS delivery_state,
            cd.name AS delivery_country'
        );
        $query->from('orders', 'o');
        $query->leftJoin('address', 'a', 'o.id_address_delivery = a.id_address');
        $query->leftJoin('carrier', 'c', 'o.id_carrier = c.id_carrier');
        $query->leftJoin('state', 'sd', 'a.id_state = sd.id_state');
        $query->leftJoin('country_lang', 'cd', 'a.id_country = cd.id_country AND cd.id_lang = ' . (int) $this->id_lang);

        if (!empty($whereSql)) {
            $query->where($whereSql);
        }
        $ordersRaw = Db::getInstance()->executeS($query);

        if (empty($ordersRaw)) {
            return [];
        }

        $orderIds = array_column($ordersRaw, 'id_order');
        $customerIds = array_unique(array_column($ordersRaw, 'id_customer'));

        $query = new DbQuery();
        $query->select('id_order, product_name, product_quantity, product_price, product_reference');
        $query->from('order_detail');
        $query->where('id_order IN (' . implode(',', array_map('intval', $orderIds)) . ')');
        $detailsRaw = Db::getInstance()->executeS($query);

        $orderDetailsMap = [];
        foreach ($detailsRaw as $detail) {
            $orderDetailsMap[$detail['id_order']][] = $detail;
        }

        unset($detailsRaw);
        $query = new DbQuery();
        $query->select('id_customer, firstname, lastname, email');
        $query->from('customer');
        $query->where('id_customer IN (' . implode(',', array_map('intval', $customerIds)) . ')');
        $customersRaw = Db::getInstance()->executeS($query);

        $customerMap = [];
        foreach ($customersRaw as $customer) {
            $customerMap[$customer['id_customer']] = $customer;
        }
        unset($customersRaw);

        $orders = [];
        foreach ($ordersRaw as $order) {
            $order['details'] = $orderDetailsMap[$order['id_order']] ?? [];
            $order['customer'] = $customerMap[$order['id_customer']] ?? null;
            $orders[] = $order;
        }

        unset($ordersRaw);

        return $orders;
    }

    public function mapOrderFields(array $order, array $fields): array
    {
        $mapped = [];

        foreach ($fields as $field) {
            $key = $field->id;

            switch ($key) {
                case 'customer_name':
                    $mapped[$key] = $order['customer']['firstname'] . ' ' . $order['customer']['lastname'];
                    break;

                case 'customer_email':
                    $mapped[$key] = $order['customer']['email'];
                    break;

                case 'order_state':
                    $order_status = new OrderState($order['current_state']);
                    $mapped[$key] = $order_status->name[$this->id_lang] ?? '';
                    break;

                case 'product_list':
                    $mapped[$key] = '';
                    foreach ($order['details'] as $product) {
                        $mapped[$key] .= $product['product_name'] . ' (Qty:' . $product['product_quantity'] . ')' . PHP_EOL;
                    }
                    break;

                case 'products_reference':
                    $mapped[$key] = '';
                    foreach ($order['details'] as $product) {
                        $mapped[$key] .= $product['product_name'] . ' Ref:' . $product['product_reference'] . ' (Qty:' . $product['product_quantity'] . ')' . PHP_EOL;
                    }
                    break;

                default:
                    $mapped[$key] = $order[$key] ?? '';
            }
        }

        return $mapped;
    }
}
