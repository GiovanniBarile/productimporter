<?php

namespace ProductImporter\Controller;

use Category;
use Configuration;
use Db;
use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use ProductImporter\Entity\CategoryMapping;
use ProductImporter\Entity\RemoteCategories;
use ProductImporter\Forms\ConfigType;
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
            $remoteCategoryId = (int) $remoteCategory['id'];

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
}
