<?php

namespace ProductImporter\Controller;

use Category;
use Configuration;
use Db;
use Exception;
use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use ProductImporter\Entity\CategoryMapping;
use Symfony\Component\HttpFoundation\Request;

class CategoryActionsController extends FrameworkBundleAdminController
{

    // categoriesActionGetLocalMapped
    public function categoriesActionGetLocalMapped(Request $request)
    {
        $local_category_id = $request->get('category_id');

        try {

            $sql = "SELECT id_remote_category FROM ps_category_mapping WHERE id_local_category = $local_category_id";
            // dd($sql);
            $result = Db::getInstance()->executeS($sql);

            //create array of remote category ids 
            $remote_category_ids = array();
            foreach ($result as $row) {
                $remote_category_ids[] = $row['id_remote_category'];
            }

            $result = $remote_category_ids;
            
            if ($result) {
                return $this->json([
                    'success' => true,
                    'result' => $result,
                ]);
            } else {
                return $this->json([
                    'success' => false,
                    'result' => $result,
                ]);
            }
        } catch (Exception $e) {
            return $this->json([
                'success' => false,
                'result' => $e->getMessage(),
            ]);
        }
    }


    // categoriesActionGetRemoteMapped
    public function categoriesActionGetRemoteMapped(Request $request)
    {
        $remote_category_id = $request->get('category_id');

        try {

            $sql = "SELECT id_local_category FROM ps_category_mapping WHERE id_remote_category = $remote_category_id";

            $result = Db::getInstance()->executeS($sql);

            $mapped_names = [];
            //create array of remote category ids 
            $local_category_ids = array();
            foreach ($result as $row) {
                $local_category_ids[] = $row['id_local_category'];
                $category = new Category($row['id_local_category']);
                $mapped_names[] = $category->name[1];
            }

            //return mapped names as string 
            $mapped_names = implode(', ', $mapped_names);


            $result = $local_category_ids;

            if ($result) {
                return $this->json([
                    'success' => true,
                    'result' => $result,
                    'mapped_names' => $mapped_names,
                ]);
            } else {
                return $this->json([
                    'success' => false,
                    'result' => $result,
                    'mapped_names' => $mapped_names,
                ]);
            }
        } catch (Exception $e) {
            return $this->json([
                'success' => false,
                'result' => $e->getMessage(),
                'mapped_names' => $mapped_names,
            ]);
        }
    }


    public function categoriesActionLink(Request $request)
    {

        $link_type = $request->get('type');
        //types can be "locale" and "remota"
        //if type is locale, $request->selectedCategory will be the id of the local one, otherwise it will be the id of the remote one
        $selectedCategory = $request->get('selectedCategory');
        //$request->data will be an array of ids of the categories to link to
        //it will be a json_string like  "data" => "["5473","40"]"
        $selectedCategories = $request->get('data');
        //turn the json_string into an array
        $selectedCategories = json_decode($selectedCategories, true);

        $em = $this->getDoctrine()->getManager();

        if ($link_type == 'locale') {
            //if type is locale, $request->selectedCategory will be the id of the local one, otherwise it will be the id of the remote one
            $selectedLocalCategoryIds = $selectedCategory;
            $selectedRemoteCategoryIds = $selectedCategories;
        } else {
            $selectedLocalCategoryIds = $selectedCategories;
            $selectedRemoteCategoryIds = [$selectedCategory];


        }

        // Verifica se il collegamento esiste già
        $existingMapping = $em->getRepository(CategoryMapping::class)->findBy([
            'idLocalCategory' => $selectedLocalCategoryIds[0],
        ]);


        if ($existingMapping && $link_type == 'locale') {
            //if local category is already mapped to remote category, delete the mapping
            foreach ($existingMapping as $mapping) {
                $em->remove($mapping);
            }
            $em->flush();
        }
        //if it's comma separated string, turn it into an array
        is_string($selectedLocalCategoryIds) ? $selectedLocalCategoryIds = explode(',', $selectedLocalCategoryIds) : null;
        
        foreach ($selectedLocalCategoryIds as $localCategory) {
            foreach ($selectedRemoteCategoryIds as $remoteCategory) {
                $existingMapping = $em->getRepository(CategoryMapping::class)->findOneBy([
                    'idLocalCategory' => $localCategory,
                    'idRemoteCategory' => $remoteCategory,
                ]);
        
                // If local category is already mapped to remote category, delete the mapping
        
                if (!$existingMapping) {
                    // If it doesn't exist, create and persist the mapping
                    $categoryMapping = new CategoryMapping();
                    $categoryMapping->setIdLocalCategory($localCategory);
                    $categoryMapping->setIdRemoteCategory($remoteCategory);
                    $em->persist($categoryMapping);
                }
            }
        }
        
        $em->flush();
        return $this->json([
            'success' => true,
            'message' => 'Categories linked successfully',
        ]);
    }


    public function categoriesActionUnlink(Request $request){
        $link_type = $request->get('type');
        //types can be "locale" and "remota"
        //if type is locale, $request->selectedCategory will be the id of the local one, otherwise it will be the id of the remote one
        $selectedCategory = $request->get('category_id');

        $em = $this->getDoctrine()->getManager();

        //if type is remota, get all mapped categories with the same remote id
        if ($link_type == 'remota') {
            $existingMapping = $em->getRepository(CategoryMapping::class)->findBy([
                'idRemoteCategory' => $selectedCategory,
            ]);
        } else {
            $existingMapping = $em->getRepository(CategoryMapping::class)->findBy([
                'idLocalCategory' => $selectedCategory,
            ]);
        }

        //remove all associations from category_mapping
        if ($existingMapping) {
            foreach ($existingMapping as $mapping) {
                $em->remove($mapping);
            }
            $em->flush();

        }



        return $this->json([
            'success' => true,
            'message' => 'Categories unlinked successfully',
        ]);
    }
}
