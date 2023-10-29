<?php

namespace ProductImporter\Controller;

use Category;
use Configuration;
use Db;
use Exception;
use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use Symfony\Component\HttpFoundation\Request;

class CategoryActionsController extends FrameworkBundleAdminController
{

      // categoriesActionGetLocalMapped
      public function categoriesActionGetLocalMapped(Request $request)
      {
          $local_category_id = $request->get('category_id');
  
          try {
  
              $sql = "SELECT id_remote_category FROM ps_category_mapping WHERE id_local_category = $local_category_id";
  
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
  
              //create array of remote category ids 
              $local_category_ids = array();
              foreach ($result as $row) {
                  $local_category_ids[] = $row['id_local_category'];
              }
  
              $result = $local_category_ids;
  
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
      
}
