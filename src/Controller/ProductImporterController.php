<?php

namespace ProductImporter\Controller;

use Category;
use Configuration;
use Db;
use Hook;
use Image;
use ImageManager;
use ImageType;
use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use Product;
use ProductImporter\Entity\CategoryMapping;
use ProductImporter\Entity\ImportStatus;
use ProductImporter\Entity\RemoteCategories;
use ProductImporter\Forms\ConfigType;
use Shop;
use Symfony\Component\HttpFoundation\Request;


class ProductImporterController extends FrameworkBundleAdminController
{
    public function indexAction()
    {
        return $this->render('@Modules/productimporter/templates/admin/index.html.twig');
    }

    public function configAction(Request $request)
    {
        $existing_key = $this->getConfig('european_resource_api_key');

        $form = $this->createForm(ConfigType::class, [
            'european_resource_api_key' => $existing_key,
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $saved = $this->saveConfig('european_resource_api_key', $data['european_resource_api_key']);
            if ($saved) {
                $this->addFlash('success', 'Configuration saved successfully');
            } else {
                $this->addFlash('error', 'Error saving configuration');
            }
        }

        return $this->render('@Modules/productimporter/templates/admin/config.html.twig', [
            'form' => $form->createView(),
        ]);
    }


    public function categoriesAction(Request $request)
    {
        $existing_categories = Category::getNestedCategories();
        $remote_categories = $this->getRemoteCategories();

        // dd($remote_categories);
        // dd($remote_categories); 

        // dd($remote_categories);
        // get all local mapped categories 
        $sql = "SELECT `id_local_category` FROM ps_category_mapping GROUP BY id_local_category";
        $mapped_local_categories = Db::getInstance()->executeS($sql);
        $mapped_local_categories = array_column($mapped_local_categories, 'id_local_category');

        // Define a recursive function to mark categories
        function markMappedCategories(&$categories, $mapped_local_categories)
        {
            foreach ($categories as &$category) {
                $category['x_mapped'] = in_array($category['id_category'], $mapped_local_categories);
                if (isset($category['children'])) {
                    markMappedCategories($category['children'], $mapped_local_categories);
                }
            }
        }

        // dd($existing_categories);

        // Call the recursive function to mark categories
        markMappedCategories($existing_categories, $mapped_local_categories);


        return $this->render('@Modules/productimporter/templates/admin/categories.html.twig', [
            'categories' => $existing_categories,
            'remote_categories' => $remote_categories,
        ]);
    }

    public function importProductsPageAction()
    {

        return $this->render('@Modules/productimporter/templates/admin/import_products.html.twig');
    }


    public function getRemoteCategories()
    {
        //check if RemoteCategories table is empty 
        $sql = "SELECT * FROM ps_remote_categories";
        $result = Db::getInstance()->executeS($sql);
        if ($result) {

            // dd("vuoto");
            // dd($this->orderCategories($result));
            return $this->orderCategories($result);
        } else {

            $api_key = $this->getConfig('european_resource_api_key');
            $url = 'https://product-api.europeansourcing.com/api/v1.1/categories/it';
            $headers = [
                'Content-Type: application/json',
                'accept: application/json',
                'x-auth-token: ' . $api_key,
            ];


            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

            $response = curl_exec($ch);
            curl_close($ch);
            $response = json_decode($response, true);
            $em = $this->getDoctrine()->getManager();
            //do it 3 times 
            $counter = 0;
            foreach ($response as $key => $value) {
                //add category



                $remoteCategory = $em->getRepository(RemoteCategories::class)->findOneBy(['original_id' => $value['id']]);
                if (!$remoteCategory) {
                    $remoteCategory = new RemoteCategories();
                }
                $remoteCategory->setName($value['name']);
                $remoteCategory->setSlug($value['slug']);
                $remoteCategory->setOriginalId($value['id']);
                $remoteCategory->setParentId($value['parent'] ?? null);
                $em->persist($remoteCategory);
                $em->flush();

                $counter++;
            }

            return $response;
        }
    }

    public function orderCategories($categories)
    {

        $sql = "SELECT `id_remote_category` FROM ps_category_mapping GROUP BY id_remote_category";
        $mapped_local_categories = Db::getInstance()->executeS($sql);
        $mapped_local_categories = array_column($mapped_local_categories, 'id_remote_category');

        //we have to order the categories by parent id
        $ordered_categories = [];
        //foreach category, if parent_id == null, add it to the ordered_categories array
        foreach ($categories as $category) {
            if ($category['parent_id'] == null) {
                if (in_array($category['original_id'], $mapped_local_categories)) {
                    $category['x_mapped'] = true;
                } else {
                    $category['x_mapped'] = false;
                }
                $ordered_categories[] = $category;
            }
        }

        //foreach category, if parent_id == ordered_category['id'], add it to the ordered_categories['x_children'] array and do the same for the children of the children
        foreach ($ordered_categories as &$ordered_category) {
            foreach ($categories as $category) {
                if ($category['parent_id'] == $ordered_category['original_id']) {
                    if (in_array($category['original_id'], $mapped_local_categories)) {
                        $category['x_mapped'] = true;
                    } else {
                        $category['x_mapped'] = false;
                    }
                    $ordered_category['x_children'][] = $category;
                }
            }

            if (isset($ordered_category['x_children'])) {
                foreach ($ordered_category['x_children'] as &$child) {
                    foreach ($categories as $category) {
                        if ($category['parent_id'] == $child['original_id']) {
                            if (in_array($category['original_id'], $mapped_local_categories)) {
                                $category['x_mapped'] = true;
                            } else {
                                $category['x_mapped'] = false;
                            }
                            $child['x_children'][] = $category;
                        }
                    }
                }
            }
        }


        // dd($ordered_categories);
        return $ordered_categories;
    }




    public function orderRemoteCategories()
    {
        $sql = "SELECT `id_remote_category` FROM ps_category_mapping GROUP BY id_remote_category";
        $mapped_local_categories = Db::getInstance()->executeS($sql);
        $mapped_local_categories = array_column($mapped_local_categories, 'id_remote_category');
        $categories = $this->getRemoteCategories();
        $final_categories = [];

        foreach ($categories as $category) {
            if (!isset($category['parent_id'])) {
                $category['x_children'] = $this->getChildren($category['id'], $categories, $mapped_local_categories);
                if (in_array($category['id'], $mapped_local_categories)) {
                    $category['x_mapped'] = true;
                } else {
                    $category['x_mapped'] = false;
                }
                $final_categories[] = $category;
            }
        }
        $categories = array_filter($categories, function ($category) {
            return !isset($category['parent']);
        });

        return $final_categories;
    }

    public function getChildren($id, $categories, $mapped_local_categories)
    {

        $children = [];

        foreach ($categories as $category) {

            if (isset($category['parent']) && $category['parent'] == $id) {
                $category['x_children'] = $this->getChildren($category['id'], $categories, $mapped_local_categories);
                if (in_array($category['id'], $mapped_local_categories)) {
                    $category['x_mapped'] = true;
                } else {
                    $category['x_mapped'] = false;
                }
                $children[] = $category;
            }
        }
        return $children;
    }


    public function saveConfig($key, $data)
    {
        $config = Configuration::updateValue($key, $data);
        return $config;
    }

    public function getConfig($key)
    {
        $config = Configuration::get($key);
        return $config;
    }

    public function categoriesActionSync(Request $request)
    {

        $l_categories = Category::getCategories(intval(Configuration::get('PS_LANG_DEFAULT')), true, false);
        $localCategorySlugs = array_map(function ($category) {
            return pSQL($category['link_rewrite']);
        }, $l_categories);


        $remoteCategories = array();
        if (!empty($localCategorySlugs)) {
            $sql = "SELECT * FROM ps_remote_categories WHERE slug IN ('" . implode("','", $localCategorySlugs) . "')";
            $remoteCategories = Db::getInstance()->executeS($sql);
        }

        foreach ($l_categories as $l_category) {
            $localCategoryId = (int) $l_category['id_category'];
            $localCategorySlug = pSQL($l_category['link_rewrite']);

            foreach ($remoteCategories as $remoteCategory) {
                if ($remoteCategory['slug'] === $localCategorySlug) {
                    $remoteCategoryId = (int) $remoteCategory['original_id'];

                    $sql = "SELECT * FROM ps_category_mapping WHERE id_local_category = {$localCategoryId} AND id_remote_category = {$remoteCategoryId}";
                    $mapping = Db::getInstance()->executeS($sql);

                    if (!$mapping) {
                        $sql = "INSERT INTO ps_category_mapping (id_local_category, id_remote_category) VALUES ({$localCategoryId}, {$remoteCategoryId})";
                        Db::getInstance()->execute($sql);
                    }
                }
            }
        }
        return $this->json([
            'success' => true,
            'message' => 'Categories synced successfully',
        ]);


        //remove all the categories from the database, except the root and home categories
        $sql = "DELETE FROM ps_category WHERE id_category > 2";
        Db::getInstance()->execute($sql);

        //remove all the category mappings from the database
        $sql = "DELETE FROM ps_category_mapping";
        Db::getInstance()->execute($sql);


        $remote_categories = $this->orderCategories($this->getRemoteCategories());

        foreach ($remote_categories as $remote_category) {
            $this->syncCategory($remote_category);
        }
        return $this->json([
            'success' => true,
            'message' => 'Categories synced successfully',
        ]);
    }

    public function syncCategory($remoteCategory, $parentId = 2)
    {
        $category = new Category();

        $category->name = array(intval(Configuration::get('PS_LANG_DEFAULT')) => $remoteCategory['name']);
        $category->id_parent = $parentId;
        $category->link_rewrite = array(intval(Configuration::get('PS_LANG_DEFAULT')) => $remoteCategory['slug']);
        $category->active = 1;

        if ($category->add()) {
            $categoryId = $category->id;

            if (!empty($remoteCategory['x_children'])) {
                foreach ($remoteCategory['x_children'] as $childCategory) {
                    $this->syncCategory($childCategory, $categoryId);
                }
            }
        }

        // Aggiorna la categoria appena creata, in modo da poter avere l'ID corretto
        $category = new Category($categoryId);
        $category->update();
    }


    public function importProductsAction(Request $request)
    {
        $api_key = $this->getConfig('european_resource_api_key');
        $url = 'https://product-api.europeansourcing.com/api/v1.1/search/scroll';
        $input = '{
            "lang": "it",
            "limit": 100,
            "search_handlers": [
                ]
        }';
        $ch = curl_init();

        // configure request with the API url,
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        // Content-type, accept type, your token,
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/ld+json',
            'Accept: application/ld+json',
            'X-AUTH-TOKEN: ' . $api_key,
        ));

        // the request method (POST for the europeansourcing API),
        curl_setopt($ch, CURLOPT_POST, 1);

        // and the parameters to pass as POST
        curl_setopt($ch, CURLOPT_POSTFIELDS, $input);

        // Now, execute the request. $response contains the output JSON
        $response = curl_exec($ch);

        if (false === $response) {
            echo 'Curl error: ' . curl_error($ch);
            die();
        }

        // close connection
        curl_close($ch);

        $response = json_decode($response, true);
        $products = $response['products'];
        $counter = 0;

        foreach ($products as $product) {
            // if ($counter == 5) {
            //     break;
            // }
            $this->importProduct($product);
            $counter++;
        }

        return $this->json([
            'success' => true,
            'message' => 'Products imported successfully',
        ]);
    }


    public function importProduct($productData)
    {

        //check if product is already imported
        $sql = "SELECT * FROM ps_import_status WHERE original_product_id = {$productData['id']}";
        $result = Db::getInstance()->executeS($sql);
        if ($result) {
            return;
        }

        // Crea una nuova istanza di Product
        $product = new Product();

        // Imposta le proprietà del prodotto
        $product->name = array(intval(Configuration::get('PS_LANG_DEFAULT')) => $productData['variants'][0]['name']);
        $product->link_rewrite = array(intval(Configuration::get('PS_LANG_DEFAULT')) => $productData['variants'][0]['slug']);
        // Imposta le dimensioni
        $product->width = $productData['variants'][0]['variant_sizes']['width'] ?? null;
        $product->height = $productData['variants'][0]['variant_sizes']['height'] ?? null;
        $product->depth = $productData['variants'][0]['variant_sizes']['length'] ?? null;

        // Imposta la descrizione
        $product->description = array(intval(Configuration::get('PS_LANG_DEFAULT')) =>   $productData['variants'][0]['raw_description']);

        // Imposta il peso
        $product->weight = isset($productData['variants'][0]['weight']) ? $productData['variants'][0]['weight'] : 0;

        // Imposta il prezzo
        $product->price =  isset($productData['variants'][0]['variant_prices'][0]['value']) ? $productData['variants'][0]['variant_prices'][0]['value'] : 0;

        //imposta la quantità
        $product->quantity = isset($productData['variants'][0]['stock']) ? $productData['variants'][0]['stock'] : 100;

        //imposta quantità minimi e massimi

        //Aggiungi le immagini

        // Salva il prodotto
        $product->add();
        
        $this->handleCategories($product, $productData);
        $importStatus = new ImportStatus();

        $importStatus->setProductId($product->id);
        $importStatus->setOriginalProductId($productData['id']);        
        $importStatus->setPhotoImported(0);
        $importStatus->setAttributesImported(0);
        $importStatus->setStatus('pending');
        $importStatus->setTimestamp(date('Y-m-d H:i:s'));
        $em = $this->getDoctrine()->getManager();
        $em->persist($importStatus);
        $em->flush();

        // $this->addProductImages($product, $productData);

    }


    public function addProductImages($product, $productData)
    {
        $shops = Shop::getShops(true, null, true);
        // Aggiungi le immagini
        $img_counter = 0;
        foreach ($productData['variants'][0]['variant_images'] as $img) {
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


    function handleCategories($product, $productData)
    {
        $categories = $productData['categories'];
        $em = $this->getDoctrine()->getManager();
        foreach ($categories as $category) {
            $categoryMapping = $em->getRepository(CategoryMapping::class)->findOneBy(['idRemoteCategory' => $category['id']]);
            // dd($category, $categoryMapping);
            if ($categoryMapping) {
                $category = new Category($categoryMapping->getIdLocalCategory());
                $product->addToCategories($category->id);
            }
        }
    }
}
