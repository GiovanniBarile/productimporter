<?php

namespace ProductImporter\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Table()
 * @ORM\Entity()
 */

class CategoryMapping
{
    /**
     * @var int
     *
     * @ORM\Id
     * @ORM\Column(name="id", type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;


    /**
     * @var int
     * 
     * @ORM\Column(name="id_local_category", type="integer")
     * 
     */

    private $idLocalCategory;

    /**
     * @var int
     * 
     * @ORM\Column(name="id_remote_category", type="integer")
     * 
     */
    private $idRemoteCategory;


   


    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }


    /**
     * @return int
     */

    public function getIdLocalCategory()
    {
        return $this->idLocalCategory;
    }


    /**
     * @param int $idLocalCategory
     *
     * @return CategoryMapping
     */

    public function setIdLocalCategory($idLocalCategory)
    {
        $this->idLocalCategory = $idLocalCategory;

        return $this;
    }


    /**
     * @return int
     */

    public function getIdRemoteCategory()
    {
        return $this->idRemoteCategory;
    }

    
    /**
     * @param int $idRemoteCategory
     *
     * @return CategoryMapping
     */

    public function setIdRemoteCategory($idRemoteCategory)
    {
        $this->idRemoteCategory = $idRemoteCategory;

        return $this;
    }


    public function createMapping($idLocalCategory, $idRemoteCategory)
    {
        $this->setIdLocalCategory($idLocalCategory);
        $this->setIdRemoteCategory($idRemoteCategory);
    }


    /**
     * @return array
     */
    public function toArray()
    {
        return [
            'id' => $this->getId(),
            'id_local_category' => $this->getIdLocalCategory(),
            'id_remote_category' => $this->getIdRemoteCategory(),
        ];
    }
}
