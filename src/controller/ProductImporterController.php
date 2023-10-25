<?php

namespace ProductImporter\Controller;

use Category;
use Configuration;
use Db;
use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use ProductImporter\Entity\CategoryMapping;
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
        $remote_categories = $this->orderRemoteCategories();
    
        // get all local mapped categories 
        $sql = "SELECT `id_local_category` FROM ps_category_mapping GROUP BY id_local_category";
        $mapped_local_categories = Db::getInstance()->executeS($sql);
        $mapped_local_categories = array_column($mapped_local_categories, 'id_local_category');

       // Define a recursive function to mark categories
        function markMappedCategories(&$categories, $mapped_local_categories) {
            foreach ($categories as &$category) {
                $category['x_mapped'] = in_array($category['id_category'], $mapped_local_categories);
                if (isset($category['children'])) {
                    markMappedCategories($category['children'], $mapped_local_categories);
                }
            }
        }
    
        // Call the recursive function to mark categories
        markMappedCategories($existing_categories, $mapped_local_categories);
    
        return $this->render('@Modules/productimporter/templates/admin/categories.html.twig', [
            'categories' => $existing_categories,
            'remote_categories' => $remote_categories,
        ]);
    }
    

    public function categoriesActionLink(Request $request)
    {
        $selectedLocalCategoryIds = $request->get('local_categories')[0];
        $selectedRemoteCategoryIds = $request->get('remote_categories');

        $em = $this->getDoctrine()->getManager();

        //check if local category is already mapped to something 
        $existingMapping = $em->getRepository(CategoryMapping::class)->findBy
        ([
            'idLocalCategory' => $selectedLocalCategoryIds[0],
        ]); 


        if ($existingMapping) {
            //if local category is already mapped to remote category, delete the mapping
            foreach ($existingMapping as $mapping) {
                $em->remove($mapping);
            }
            $em->flush();
        }

        foreach ($selectedRemoteCategoryIds as $remoteCategory) {
            // Verifica se il collegamento esiste già
            $existingMapping = $em->getRepository(CategoryMapping::class)->findOneBy([
                'idLocalCategory' => $selectedLocalCategoryIds[0],
                'idRemoteCategory' => $remoteCategory,
            ]);

            //if local category is already mapped to remote category, delete the mapping


            if (!$existingMapping) {
                // Se non esiste, crea e persisti il collegamento
                $categoryMapping = new CategoryMapping();
                $categoryMapping->setIdLocalCategory($selectedLocalCategoryIds[0]);
                $categoryMapping->setIdRemoteCategory($remoteCategory);
                $em->persist($categoryMapping);
            }
        }

        $em->flush();

        return $this->json([
            'success' => true,
            'message' => 'Categories linked successfully',
        ]);
    }


    public function getRemoteCategories()
    {
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

        return $response;
    }


    public function orderRemoteCategories()
    {   
        $sql = "SELECT `id_remote_category` FROM ps_category_mapping GROUP BY id_remote_category";
        $mapped_local_categories = Db::getInstance()->executeS($sql);
        $mapped_local_categories = array_column($mapped_local_categories, 'id_remote_category');
        $categories = $this->getRemoteCategories();
        $final_categories = [];

        foreach ($categories as $category) {
            if (!isset($category['parent'])) {
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


    // CRUD

    // ActionDelete
    public function categoriesActionDelete(Request $request)
    {
        $category_id = $request->get('category_id');

        // Rimuovi la categoria da PrestaShop indipendentemente da tutto
        $prestashopCategory = new Category($category_id);
        if (!$prestashopCategory->delete()) {
            return $this->json([
                'success' => false,
                'message' => 'Failed to delete category from PrestaShop',
            ]);
        }

        $em = $this->getDoctrine()->getManager();
        //remove all associations from category_mapping
        $categoryMapping = $em->getRepository(CategoryMapping::class)->findBy([
            'idLocalCategory' => $category_id,
        ]);


        // Se esiste l'associazione in category_mapping, rimuovila
        if ($categoryMapping) {
            foreach ($categoryMapping as $mapping) {
                $em->remove($mapping);
            }
            $em->flush();
        }


        return $this->json([
            'success' => true,
            'message' => 'Category deleted from PrestaShop and its associations removed if present',
        ]);
    }


    public function categoriesActionGetParents()
    {
        // Recupera un elenco di tutte le categorie
        $categories = Category::getCategories(null, true, false);

        $categoryList = [];
        foreach ($categories as $category) {
            $categoryList[] = [
                'id' => $category['id_category'],
                'name' => $category['name'],
            ];
        }

        return $this->json([
            'success' => true,
            'categories' => $categoryList,
        ]);
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
}
