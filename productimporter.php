<?php

if (!defined('_PS_VERSION_')) {
    exit;
}


class ProductImporter extends Module
{

    public $tabs = [
        [
            'name' => 'Product Importer',
            'class_name' => 'AdminProductImporter',
            'visible' => true,
            'icon' => 'shopping_basket',
            // 'parent_class_name' => 'AdminParentModulesSf', // Classe genitore per il sottomenu
        ],
        [
            'name' => 'Settings',
            'class_name' => 'AdminProductImporterSettings',
            'visible' => true,
            'parent_class_name' => 'AdminProductImporter', // Collega a 'AdminProductImporter'
        ],
        [
            'name' => 'Manage categories',
            'class_name' => 'AdminCategoryMapping',
            'visible' => true,
            'parent_class_name' => 'AdminProductImporter', // Collega a 'AdminProductImporter'
        ],
        [
            'name' => 'Import products',
            'class_name' => 'AdminProductImport',
            'visible' => true,
            'parent_class_name' => 'AdminProductImporter', // Collega a 'AdminProductImporter'
        ],

    ];
    
    public function __construct()
    {
        $this->name = 'productimporter';
        $this->tab = 'administration';
        $this->version = '1.0.0';
        $this->author = '';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = [
            'min' => '1.7',
            'max' => _PS_VERSION_
        ];

        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Product Importer');
        $this->description = $this->l('Import products from external sources');

        $this->confirmUninstall = $this->l('Are you sure you want to uninstall?');

        if (!Configuration::get('MYMODULE_NAME')) {
            $this->warning = $this->l('No name provided');
        }
    }
    //load js 



    public function install()
    {
        if (!parent::install() || !$this->installDb()) {
            return false;
        }

        //register hook
        if (!$this->registerHook('displayBackOfficeHeader')) {
            return false;
        }

        if(!$this->registerHook('completeProductImportProcess')){
            return false;
        }

        return true;
    }


    public function hookCompleteProductImportProcess(){
        //create a file in the module directory
        $file = fopen($this->_path . 'import_complete.txt', 'w');
        fwrite($file, 'Import complete');
        fclose($file);

        
    }

    public function uninstall()
    {
        if (!parent::uninstall() || !Configuration::deleteByName('MYMODULE_NAME') || !$this->uninstallDb()) {
            return false;
        }

        return true;
    }


    public function getContent()
    {
        //must redirect to module controller  admin6411iq3kh196f8gyx32/modules/productimporter/config?_token=BcZ0l47G8DkY3DVFCbgjDtOIc27rB_XclL2HELcEIJg
        Tools::redirectAdmin($this->context->link->getAdminLink('AdminProductImporter'));
        
    }   



    //displaybackofficeheader
    public function hookDisplayBackOfficeHeader()
    {

        //jsTree
        $this->context->controller->addJS('https://cdnjs.cloudflare.com/ajax/libs/jstree/3.2.1/jstree.min.js');
        $this->context->controller->addCSS('https://cdnjs.cloudflare.com/ajax/libs/jstree/3.2.1/themes/default/style.min.css');

        //Fontawesome 
        $this->context->controller->addCSS('https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css');
        //SweetAlert2
        $this->context->controller->addJS('https://cdn.jsdelivr.net/npm/sweetalert2@11');

        //bootstrap selectPicker 
        
        $this->context->controller->addCSS('https://cdn.jsdelivr.net/npm/bootstrap-select@1.13.14/dist/css/bootstrap-select.min.css');
        $this->context->controller->addJS('https://cdn.jsdelivr.net/npm/bootstrap-select@1.13.14/dist/js/bootstrap-select.min.js');
        
        //custom JS
        $this->context->controller->addJS($this->_path . 'views/js/script.js');
        $this->context->controller->addJS($this->_path . 'views/js/crud.js');
        $this->context->controller->addJS($this->_path . 'views/js/link_category_modal.js');
        $this->context->controller->addJS($this->_path . 'views/js/product_importer.js');
    }

    // install db 
    // CREATE TABLE ps_category_mapping (id INT AUTO_INCREMENT NOT NULL, id_local_category INT NOT NULL, id_remote_category INT NOT NULL, local_category_name VARCHAR(64) NOT NULL, remote_category_name VARCHAR(64) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
    public function installDb()
    {
        $product_mapping_sql = "CREATE TABLE IF NOT EXISTS `" . _DB_PREFIX_ . "category_mapping` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `id_local_category` int(11) NOT NULL,
            `id_remote_category` int(11) NOT NULL,
            PRIMARY KEY (`id`)
          ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

          $remote_categories_sql = "CREATE TABLE IF NOT EXISTS `" . _DB_PREFIX_ . "remote_categories` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `name` varchar(255) NOT NULL,
            `slug` varchar(255) NOT NULL,
            `original_id` int(11) NOT NULL,
            `parent_id` int(11) NULL,
            PRIMARY KEY (`id`)
          ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
            
          //keep track of imported products, track product id, photo_imported, attributes_imported,  status, timestamp
          $import_status_sql = "CREATE TABLE IF NOT EXISTS `" . _DB_PREFIX_ . "import_status` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `product_id` int(11) NOT NULL,
            `original_product_id` int(11) NOT NULL,
            `photo_imported` tinyint(1) NOT NULL,
            `attributes_imported` tinyint(1) NOT NULL,
            `status` varchar(255) NOT NULL,
            `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
          ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

          try {
            Db::getInstance()->execute($product_mapping_sql);
            Db::getInstance()->execute($remote_categories_sql);
            Db::getInstance()->execute($import_status_sql);
        } catch (Exception $e) {
            return false;
        }
        
        return true;

        // return Db::getInstance()->execute($sql);
    }

    public function uninstallDb()
    {
        $sql = "DROP TABLE IF EXISTS `" . _DB_PREFIX_ . "category_mapping`";
        $sql2 = "DROP TABLE IF EXISTS `" . _DB_PREFIX_ . "remote_categories`";
        $sql3 = "DROP TABLE IF EXISTS `" . _DB_PREFIX_ . "import_status`";
        try {
            Db::getInstance()->execute($sql);
            Db::getInstance()->execute($sql2);
            Db::getInstance()->execute($sql3);
        } catch (Exception $e) {
            return false;
        }
        return true;

    }
}
