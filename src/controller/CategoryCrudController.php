<?php

namespace ProductImporter\Controller;

use Category;
use Configuration;
use Db;
use Exception;
use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use Symfony\Component\HttpFoundation\Request;

class CategoryCrudController extends FrameworkBundleAdminController
{

    // ActionCreate
    public function categoriesActionCreate(Request $request)
    {
        $category_name = $request->get('categoryName');
        $parent_id = $request->get('parentCategory');

        if (Category::categoryExists($category_name, $parent_id)) {
            return $this->json([
                'success' => false,
                'message' => 'Category already exists',
            ]);
        }
        // create a new category array
        try {
            $category = new Category();
            $category->name = array((int)Configuration::get('PS_LANG_DEFAULT') => $category_name);
            $category->link_rewrite = array((int)Configuration::get('PS_LANG_DEFAULT') => $category_name);
            $category->id_parent = $parent_id;
            $category->active = true;

            $category->add();

        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Failed to create category ' . $e->getMessage(),
            ]);
        }

        return $this->json([
            'success' => true,
            'message' => 'Category created successfully',
        ]);
    }



    public function categoriesActionEdit(Request $request)
    {
        $category_id = $request->get('category_id');
        $new_category_name = $request->get('new_category_name');
        $category = new Category($category_id);
        $category->name = array((int)Configuration::get('PS_LANG_DEFAULT') => $new_category_name);
        $category->link_rewrite = array((int)Configuration::get('PS_LANG_DEFAULT') => $new_category_name);
        $category->save();

        return $this->json([
            'success' => true,
            'category_id' => $category_id,
            'new_category_name' => $new_category_name,
        ]);
    }

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

    // ActionGetParents
    
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
  
}
