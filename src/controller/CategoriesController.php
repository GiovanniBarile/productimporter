<?php

namespace ProductImporter\Controller;

use Category;
use Configuration;
use Db;
use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use ProductImporter\Entity\CategoryMapping;
use ProductImporter\Forms\ConfigType;
use Symfony\Component\HttpFoundation\Request;

class CategoriesController extends FrameworkBundleAdminController{

    public function categoriesActionEdit(Request $request){
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
}