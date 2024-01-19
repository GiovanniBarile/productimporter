<?php

namespace ProductImporter\Controller;

use Category;
use Configuration;
use Db;
use Exception;
use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use Product;
use ProductImporter\Entity\CategoryMapping;
use ProductImporter\Entity\ImportStatus;
use ProductImporter\Entity\RemoteCategories;
use ProductImporter\Forms\ConfigType;
use Symfony\Component\HttpFoundation\Request;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

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
        $total_imported_products = 0;

        $connection = new AMQPStreamConnection('dog-01.lmq.cloudamqp.com', 5672, 'jqlfytoc', '2v7Sl8yCuVrQ-aDkh5geClDNHpbFQnSl', 'jqlfytoc');
        $channel = $connection->channel();

        $api_key = $this->getConfig('european_resource_api_key');
        $url = 'https://product-api.europeansourcing.com/api/v1.1/search/scroll';
        $input = '{"lang": "it","search_handlers": []}';

        $scrollId = $this->getConfig('scroll_id') ?? null;
        // dump($scrollId);
        // die(); 
        $scrollId == 0 ? $scrollId = null : $scrollId = $scrollId;
        
        $i = 1;

        do {
            // If it's not the first iteration, set the scroll_id parameter
            if ($scrollId !== null) {
                $input = json_encode(['scroll_id' => $scrollId]);
            }

            // Make the API request
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/ld+json',
                'Accept: application/ld+json',
                'X-AUTH-TOKEN: ' . $api_key,
            ));
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $input);

            $response = curl_exec($ch);

            if (false === $response) {
                echo 'Curl error: ' . curl_error($ch);
                die();
            }

            curl_close($ch);

            // Decode the response
            $response = json_decode($response, true);

            // Check if there are products in the response
            $products = $response['products'];
            if (count($products) > 0) {

                // Process products
                $this->processProducts($products, $channel);
                $total_imported_products += count($products);
                // Get the scroll_id for the next iteration
                $scrollId = $response['pagination']['scroll_id'];
            }

            // Increment page counter
            ++$i;

            // Save the scroll_id for the next iteration
            $this->saveConfig('scroll_id', $scrollId);

        } while (count($products) > 0 && $total_imported_products < 1500 );

        $channel->close();
        $connection->close();

        // set scroll_id to 0
        $this->saveConfig('scroll_id', 0);

        return $this->json([
            'success' => true,
            'message' => 'Products imported successfully',
        ]);
    }

    private function processProducts($products, $channel)
    {
        foreach ($products as $product) {
            try {
                $this->importProduct($product, $channel);
            } catch (Exception $e) {
                dump($e);
            }
        }
    }

    public function removeKeysRecursive(&$array, $keys_to_remove)
    {
        foreach ($array as $key => &$value) {
            if (in_array($key, $keys_to_remove)) {
                unset($array[$key]);
            } else {
                if (is_array($value)) {
                    $this->removeKeysRecursive($value, $keys_to_remove);
                }
            }
        }
    }

    public function importProduct($productData, $channel)
    {

        //check if product is already imported
        $sql = "SELECT * FROM ps_import_status WHERE original_product_id = {$productData['id']}";
        $result = Db::getInstance()->executeS($sql);
        if ($result) {
            return;
        }

        //send product to queue

        // remove labels, extremum_price, supplier, project, keywords, supplier_profiles, variant_packaging, variant_minimum_quantities
        //variant_sample_prices, variant_external_links, variant_list_prices, variant_delivery_times
        //and brand from productData

        //search inside product_data and its children for the keys to remove
        $keys_to_remove = ['labels', 'extremum_price', 'supplier', 'project', 'keywords', 'supplier_profiles','supplier_profile', 'variant_packaging', 'variant_minimum_quantities', 'variant_sample_prices', 'variant_external_links', 'variant_list_prices', 'variant_delivery_times', 'brand', 'hierarchy'];

    
        $this->removeKeysRecursive($productData, $keys_to_remove);

        $categories = $this->returnCategories($productData);

        $productData['mapped_categories'] = $categories;

        $msg = new AMQPMessage(json_encode($productData));
        $channel->basic_publish($msg, '', 'products_queue');
        
        //after sending the product to the queue, add the product to the import_status table
        $importStatus = new ImportStatus();
        $importStatus->setProductId($productData['id']);
        $importStatus->setOriginalProductId($productData['id']);
        $importStatus->setPhotoImported(0);
        $importStatus->setAttributesImported(0);
        $importStatus->setStatus('pending');
        $importStatus->setTimestamp(date('Y-m-d H:i:s'));
        $em = $this->getDoctrine()->getManager();
        $em->persist($importStatus);
        $em->flush();




        // // Crea una nuova istanza di Product
        // $product = new Product();

        // // Imposta le proprietà del prodotto
        // $product->name = array(intval(Configuration::get('PS_LANG_DEFAULT')) => $productData['variants'][0]['name']);
        // $product->link_rewrite = array(intval(Configuration::get('PS_LANG_DEFAULT')) => $productData['variants'][0]['slug']);
        // // Imposta le dimensioni
        // $product->width = $productData['variants'][0]['variant_sizes']['width'] ?? null;
        // $product->height = $productData['variants'][0]['variant_sizes']['height'] ?? null;
        // $product->depth = $productData['variants'][0]['variant_sizes']['length'] ?? null;

        // // Imposta la descrizione
        // $product->description = array(intval(Configuration::get('PS_LANG_DEFAULT')) =>   $productData['variants'][0]['raw_description'] ?? "");

        // // Imposta il peso
        // $product->weight = isset($productData['variants'][0]['weight']) ? $productData['variants'][0]['weight'] : 0;

        // // Imposta il prezzo
        // $product->price =  isset($productData['variants'][0]['variant_prices'][0]['value']) ? $productData['variants'][0]['variant_prices'][0]['value'] : 0;

        // //imposta la quantità
        // $product->quantity = isset($productData['variants'][0]['stock']) ? $productData['variants'][0]['stock'] : 100;

        // //imposta quantità minimi e massimi

        // //Aggiungi le immagini

        // // Salva il prodotto
        // $product->add();

        // // add product to queue
        // $productQueue = [
        //     'id' => $product->id,
        //     'images' => $productData['variants'][0]['variant_images'],
        // ];

        // $msg = new AMQPMessage(json_encode($productQueue));
        // $channel->basic_publish($msg, '', 'product_images');

        // $this->handleCategories($product, $productData);
        // $importStatus = new ImportStatus();

        // $importStatus->setProductId($product->id);
        // $importStatus->setOriginalProductId($productData['id']);
        // $importStatus->setPhotoImported(0);
        // $importStatus->setAttributesImported(0);
        // $importStatus->setStatus('pending');
        // $importStatus->setTimestamp(date('Y-m-d H:i:s'));
        // $em = $this->getDoctrine()->getManager();
        // $em->persist($importStatus);
        // $em->flush();


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


    function returnCategories($productData)
    {
        $categories_to_return = [];
        $categories = $productData['categories'];
        $em = $this->getDoctrine()->getManager();
        foreach ($categories as $category) {
            $categoryMapping = $em->getRepository(CategoryMapping::class)->findOneBy(['idRemoteCategory' => $category['id']]);
            // dd($category, $categoryMapping);
            if ($categoryMapping) {
                $category = new Category($categoryMapping->getIdLocalCategory());
                $categories_to_return[] = $category->id;
            }
        }

        return $categories_to_return;
    }
}
