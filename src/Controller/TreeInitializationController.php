<?php

namespace ProductImporter\Controller;

use Category;
use Configuration;
use Db;
use Exception;
use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;

use ProductImporter\Entity\RemoteCategories;
use Symfony\Component\HttpFoundation\Request;

class TreeInitializationController extends FrameworkBundleAdminController
{


    public function LocalCategoryTree(Request $request)
    {
        $existing_categories = Category::getNestedCategories();
        $sql = "SELECT `id_local_category` FROM ps_category_mapping GROUP BY id_local_category";
        $mapped_local_categories = Db::getInstance()->executeS($sql);
        $mapped_local_categories = array_column($mapped_local_categories, 'id_local_category');

        function markMappedCategories(&$categories, $mapped_local_categories)
        {
            foreach ($categories as &$category) {
                $category['x_mapped'] = in_array($category['id_category'], $mapped_local_categories);
                if (isset($category['children'])) {
                    markMappedCategories($category['children'], $mapped_local_categories);
                }
            }
        }

        markMappedCategories($existing_categories, $mapped_local_categories);
        
        $converted_categories = $this->convertToJstreeFormatRecursive($existing_categories);

        return $this->json(
            [
                $converted_categories[0]
            ]
        );
    }

    public function convertToJstreeFormatRecursive($categories){
        $jstree_categories = [];
    
        foreach ($categories as $category) {
            $jstree_category = $this->convertCategoryToJstree($category);
            $jstree_categories[] = $jstree_category;
        }
    
        return $jstree_categories;
    }
    
    private function convertCategoryToJstree($category) {
        $jstree_category = [];
        $jstree_category['id'] = $category['id_category'];
        $jstree_category['text'] = $category['name'];
        $jstree_category['children'] = [];
        $jstree_category['data'] = ['mapped' => $category['x_mapped'] ?? false];
        $jstree_category['icon'] = $category['x_mapped'] ? 'far fa-check-circle' : '';
        $jstree_category['state'] = ['opened' => true];
    
        if (isset($category['children'])) {
            foreach ($category['children'] as $child) {
                $jstree_child = $this->convertCategoryToJstree($child);
                $jstree_category['children'][] = $jstree_child;
            }
        }
    
        return $jstree_category;
    }
    


    public function RemoteCategoryTree(Request $request){

        $remote_categories = $this->getRemoteCategories();
        // dd($remote_categories);
        $remote_categories = $this->convertToJstreeFormat($remote_categories);
        return $this->json(
            $remote_categories
        );
    }

    public function convertToJstreeFormat($categories)
    {
        $jstree_categories = [];
        foreach ($categories as $category) {
            $jstree_category = [];
            $jstree_category['id'] = $category['id'];
            $jstree_category['text'] = $category['name'];
            $jstree_category['children'] = [];
            $jstree_category['data'] = ['mapped' => false ?? false];
            // Controllo per verificare se l'attributo "x_mapped" è true
            if (isset($category['x_mapped']) && $category['x_mapped'] === true) {
                $jstree_category['data'] = ['mapped' => true ?? false];
                $jstree_category['icon'] = 'far fa-check-circle'; // Imposta l'icona della spunta
            }
    
            if (isset($category['x_children'])) {
                foreach ($category['x_children'] as $child) {
                    $jstree_child = [];
                    $jstree_child['id'] = $child['id'];
                    $jstree_child['text'] = $child['name'];
                    $jstree_child['children'] = [];
                    $jstree_child['data'] = ['mapped' => false ?? false];
    
                    // Controllo anche per i figli
                    if (isset($child['x_mapped']) && $child['x_mapped'] === true) {
                        $jstree_child['data'] = ['mapped' => true ?? false];
                        $jstree_child['icon'] = 'far fa-check-circle'; // Imposta l'icona della spunta
                    }
    
                    if (isset($child['x_children'])) {
                        foreach ($child['x_children'] as $grandchild) {
                            $jstree_grandchild = [];
                            $jstree_grandchild['id'] = $grandchild['id'];
                            $jstree_grandchild['text'] = $grandchild['name'];
                            $jstree_grandchild['children'] = [];
                            $jstree_grandchild['data'] = ['mapped' => false ?? false];
    
                            // Controllo anche per i nipoti
                            if (isset($grandchild['x_mapped']) && $grandchild['x_mapped'] === true) {
                                $jstree_grandchild['data'] = ['mapped' => true ?? false] ;
                                $jstree_grandchild['icon'] = 'far fa-check-circle'; // Imposta l'icona della spunta
                            }
    
                            $jstree_child['children'][] = $jstree_grandchild;
                        }
                    }
                    $jstree_category['children'][] = $jstree_child;
                }
            }
            $jstree_categories[] = $jstree_category;
        }
        return $jstree_categories;
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

        // dd($categories);
        $sql = "SELECT `id_remote_category` FROM ps_category_mapping GROUP BY id_remote_category";
        $mapped_local_categories = Db::getInstance()->executeS($sql);
        $mapped_local_categories = array_column($mapped_local_categories, 'id_remote_category');

        //we have to order the categories by parent id
        $ordered_categories = [];
        //foreach category, if parent_id == null, add it to the ordered_categories array
        foreach ($categories as $category) {
            if ($category['parent_id'] == null) {
                if (in_array($category['id'], $mapped_local_categories)) {
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
                    if (in_array($category['id'], $mapped_local_categories)) {
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
                            if (in_array($category['id'], $mapped_local_categories)) {
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




    public function getConfig($key)
    {
        $config = Configuration::get($key);
        return $config;
    }

    
}


