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

        if (!$this->registerHook('actionProductDelete')) {
            return false;
        }


        return true;
    }

    //Delete product from import_status table when product is deleted
    public function hookActionProductDelete($params)
    {
        // $product_id = $params['id_product'];
        // $sql = "DELETE FROM `" . _DB_PREFIX_ . "import_status` WHERE product_id = $product_id";
        // Db::getInstance()->execute($sql);

        // delete ALL products from prestashop
        $sql = "DELETE FROM `" . _DB_PREFIX_ . "product`";
        Db::getInstance()->execute($sql);

        // delete ALL product images from prestashop
        $sql = "DELETE FROM `" . _DB_PREFIX_ . "image`";
        Db::getInstance()->execute($sql);

        // delete ALL product attributes from prestashop
        $sql = "DELETE FROM `" . _DB_PREFIX_ . "product_attribute`";

        Db::getInstance()->execute($sql);

        // delete ALL product attributes from prestashop

        $sql = "DELETE FROM `" . _DB_PREFIX_ . "product_attribute_combination`";

        Db::getInstance()->execute($sql);
        


    }

    public function hookCompleteProductImportProcess($params)
    {

        // Write params to log file
        // $log = fopen(_PS_ROOT_DIR_ . "/log.txt", "a");
        // fwrite($log, print_r($params, true));
        // fclose($log);

        // die(); 


        // while import_status table has products with photo_imported = 0
        // get product id and original product id
        // get product photo from remote server
        //add photo to product
        // do this in blocks of 10 products at a time 


        // while ($products = Db::getInstance()->executeS("SELECT * FROM `" . _DB_PREFIX_ . "import_status` WHERE photo_imported = 0 LIMIT 10")) {
        //     foreach ($products as $product) {
        //         $product_id = $product['product_id'];
        //         // $original_product_id = $product['original_product_id'];
        //         $product_photo = $this->getProductPhoto($original_product_id);
        //         $this->addProductImages($product_id, $product_photo);
        //         $this->updateImportStatus($product_id, 'photo_imported');
        //     }
        // }
    }

    function uploadImage($id_entity, $id_image = null, $imgUrl)
    {
        $tmpfile = tempnam(_PS_TMP_IMG_DIR_, 'ps_import');
        $watermark_types = explode(',', Configuration::get('WATERMARK_TYPES'));
        $image_obj = new Image((int)$id_image);
        $path = $image_obj->getPathForCreation();
        $imgUrl = str_replace(' ', '%20', trim($imgUrl));
        // Evaluate the memory required to resize the image: if it's too big we can't resize it.
        if (!ImageManager::checkImageMemoryLimit($imgUrl)) {
            return false;
        }
        if (@copy($imgUrl, $tmpfile)) {
            ImageManager::resize($tmpfile, $path . '.jpg');
            $images_types = ImageType::getImagesTypes('products');
            foreach ($images_types as $image_type) {
                ImageManager::resize($tmpfile, $path . '-' . stripslashes($image_type['name']) . '.jpg', $image_type['width'], $image_type['height']);
                if (in_array($image_type['id_image_type'], $watermark_types)) {
                    Hook::exec('actionWatermark', array('id_image' => $id_image, 'id_product' => $id_entity));
                }
            }
        } else {
            unlink($tmpfile);
            return false;
        }
        unlink($tmpfile);
        return true;
    }


    public function getProductPhoto($product_id)
    {

        // return an array of 4 photos from picsum
        $photos = [];
        for ($i = 0; $i < 4; $i++) {
            $photos[] = "https://picsum.photos/200/300?random=$i";
        }
        return $photos;
    }

    public function addProductImages($product, $images)
    {

        $shops = Shop::getShops(true, null, true);
        // Aggiungi le immagini
        $img_counter = 0;
        foreach ($images as $img) {
            $image = new Image();
            $image->id_product = $product->id;
            $image->position = Image::getHighestPosition($product->id) + 1;
            $image->cover = ($img_counter == 0) ? true : false;
            if (($image->validateFields(false, true)) === true && ($image->validateFieldsLang(false, true)) === true && $image->add()) {
                $image->associateTo($shops);
                if (!$this->uploadImage($product->id, $image->id, $img['url'])) {
                    $image->delete();
                }
            }
            $img_counter++;
        }
    }

    public function updateImportStatus($product_id, $field)
    {
        $sql = "UPDATE `" . _DB_PREFIX_ . "import_status` SET $field = 1 WHERE product_id = $product_id";
        Db::getInstance()->execute($sql);
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
