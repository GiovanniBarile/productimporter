<?php

namespace ProductImporter\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Table()
 * @ORM\Entity()
 */

//  "productimporter_import_status` (
//     `id` int(11) NOT NULL AUTO_INCREMENT,
//     `product_id` int(11) NOT NULL,
//     `original_product_id` int(11) NOT NULL,
//     `photo_imported` tinyint(1) NOT NULL,
//     `attributes_imported` tinyint(1) NOT NULL,
//     `status` varchar(255) NOT NULL,
//     `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
//     PRIMARY KEY (`id`)
//   ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";


class ImportStatus
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
     * @ORM\Column(name="product_id", type="integer")
     * 
     */

    private $productId;

    /**
     * @var int
     * 
     * @ORM\Column(name="original_product_id", type="integer")
     */

    private $originalProductId;


    /**
     * @var int
     * 
     * @ORM\Column(name="photo_imported", type="integer")
     * 
     */

    private $photoImported;

    /**
     * @var int
     * 
     * @ORM\Column(name="attributes_imported", type="integer")
     * 
     */

    private $attributesImported;

    /**
     * @var string
     * 
     * @ORM\Column(name="status", type="string")
     * 
     */

    private $status;

    /**
     * @var string
     * 
     * @ORM\Column(name="timestamp", type="string")
     * 
     */

    private $timestamp;

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

    public function getProductId()
    {
        return $this->productId;
    }


    /**
     * @return int
     */

    public function getOriginalProductId()
    {
        return $this->originalProductId;
    }


    /**
     * @param int $originalProductId
     *
     * @return ImportStatus
     */

    public function setOriginalProductId($originalProductId)
    {
        $this->originalProductId = $originalProductId;

        return $this;
    }

    /**
     * @param int $productId
     *
     * @return ImportStatus
     */

    public function setProductId($productId)
    {
        $this->productId = $productId;

        return $this;
    }


    /**
     * @return int
     */

    public function getPhotoImported()

    {
        return $this->photoImported;
    }


    /**
     * @param int $photoImported
     *
     * @return ImportStatus
     */

    public function setPhotoImported($photoImported)
    {
        $this->photoImported = $photoImported;

        return $this;
    }


    /**
     * @return int
     */

    public function getAttributesImported()
    {
        return $this->attributesImported;
    }


    /**
     * @param int $attributesImported
     *
     * @return ImportStatus
     */

    public function setAttributesImported($attributesImported)
    {
        $this->attributesImported = $attributesImported;

        return $this;
    }


    /**
     * @return string
     */

    public function getStatus()

    {
        return $this->status;
    }


    /**
     * @param string $status
     *
     * @return ImportStatus
     */

    public function setStatus($status)

    {
        $this->status = $status;
        return $this;
    }


    /**
     * @return string
     */

    public function getTimestamp()

    {
        return $this->timestamp;
    }


    /**
     * @param string $timestamp
     *
     * @return ImportStatus
     */

    public function setTimestamp($timestamp)

    {
        $this->timestamp = $timestamp;
        return $this;
    }

}
