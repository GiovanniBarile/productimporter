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

    ];
    
    public function __construct()
    {
        $this->name = 'productimporter';
        $this->tab = 'administration';
        $this->version = '1.0.0';
        $this->author = 'Giovanni Barile';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = [
            'min' => '1.7',
            'max' => _PS_VERSION_
        ];

        // $this->tabs = [
        //     [
        //         'name' => 'Product Importer',
        //         'class_name' => 'AdminProductImporter',
        //         'parent_class_name' => 'AdminParentModulesSf',
        //         'visible' => true,
        //         'icon' => 'import'
        //     ]
        // ];

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

        return true;
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
    }

    // install db 
    // CREATE TABLE ps_category_mapping (id INT AUTO_INCREMENT NOT NULL, id_local_category INT NOT NULL, id_remote_category INT NOT NULL, local_category_name VARCHAR(64) NOT NULL, remote_category_name VARCHAR(64) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
    public function installDb()
    {
        $sql = "CREATE TABLE IF NOT EXISTS `" . _DB_PREFIX_ . "category_mapping` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `id_local_category` int(11) NOT NULL,
            `id_remote_category` int(11) NOT NULL,
            PRIMARY KEY (`id`)
          ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

        return Db::getInstance()->execute($sql);
    }

    public function uninstallDb()
    {
        $sql = "DROP TABLE IF EXISTS `" . _DB_PREFIX_ . "category_mapping`";
        return Db::getInstance()->execute($sql);
    }
}
