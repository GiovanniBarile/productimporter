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
            //create slug from category name 
            $slug = preg_replace('/[^A-Za-z0-9-]+/', '-', $category_name);

            $category = new Category();
            $category->name = array((int)Configuration::get('PS_LANG_DEFAULT') => $category_name);
            $category->link_rewrite = array((int)Configuration::get('PS_LANG_DEFAULT') => $slug);
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
        $slug = preg_replace('/[^A-Za-z0-9-]+/', '-', $request->get('new_category_name'));
        $category_id = $request->get('category_id');
        $new_category_name = $request->get('new_category_name');
        $category = new Category($category_id);
        $category->name = array((int)Configuration::get('PS_LANG_DEFAULT') => $new_category_name);
        $category->link_rewrite = array((int)Configuration::get('PS_LANG_DEFAULT') => $slug);
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
        $category_ids = $request->get('category_id');

        foreach ($category_ids as $category_id) {
            // Rimuovi la categoria da PrestaShop indipendentemente da tutto
            $prestashopCategory = new Category($category_id);
            //check if the category exists, if exista delete it, if not, continue
            if (!Category::categoryExists($prestashopCategory->name, $prestashopCategory->id_parent)) {
                continue;
            } else {

                //Check if the category has children, if it has, delete the mappings for the children
                $sql = "SELECT id_category FROM ps_category WHERE id_parent = $category_id";

                $result = Db::getInstance()->executeS($sql);

                if ($result) {
                    foreach ($result as $row) {
                        $sql = "DELETE FROM ps_category_mapping WHERE id_local_category = $row[id_category]";

                        Db::getInstance()->execute($sql);
                    }
                }

                //then delete the category
            

                if (!$prestashopCategory->delete()) {
                    return $this->json([
                        'success' => false,
                        'message' => 'Failed to delete category from PrestaShop',
                    ]);
                }
            }

            try {
                $em = $this->getDoctrine()->getManager();
                // Rimuovi tutte le associazioni da category_mapping

                $sql = "DELETE FROM ps_category_mapping WHERE id_local_category = $category_id";

                $em->getConnection()->executeQuery($sql);
            } catch (Exception $e) {
                return $this->json([
                    'success' => false,
                    'message' => 'Failed to delete category associations ' . $e->getMessage(),
                ]);
            }
        }

        return $this->json([
            'success' => true,
            'message' => 'Categories deleted from PrestaShop and their associations removed if present',
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
