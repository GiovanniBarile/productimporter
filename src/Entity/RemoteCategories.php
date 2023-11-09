<?php

namespace ProductImporter\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Table()
 * @ORM\Entity()
 */

//  CREATE TABLE categories (
//     id INT AUTO_INCREMENT PRIMARY KEY,
//     name VARCHAR(255) NOT NULL,
//     slug VARCHAR(255),
//     original_id INT,
//     parent_id INT,
//     FOREIGN KEY (parent_id) REFERENCES categories(id)
// );

class RemoteCategories
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
     * @var string
     * 
     * @ORM\Column(name="name", type="string")
     * ß
     */

    private $name;

    /**
     * @var string
     * 
     * @ORM\Column(name="slug", type="string")
     * 
     */

    private $slug;



    /**
     * @var int
     * 
     * @ORM\Column(name="original_id", type="integer")
     * 
     */

    private $original_id;



    /**
     * @var int
     * 
     * @ORM\Column(name="parent_id", type="integer")
     * 
     */

    private $parent_id;

    /**
     * @var int
     * 
     * @ORM\Column(name="remote_id", type="integer")
     * 
     */


    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }


    /**
     * @return string
     */

    public function getName()
    {
        return $this->name;
    }


    /**
     * @param string $name
     *
     * @return RemoteCategories
     */

    public function setName($name)
    {
        $this->name = $name;
        return $this;
    }

    /**
     * @return string
     */

    public function getSlug()
    {
        return $this->slug;
    }

    /**
     * @param string $slug
     *
     * @return RemoteCategories
     */

    public function setSlug($slug)
    {
        $this->slug = $slug;
        return $this;
    }


    /**
     * @return int
     */

    public function getOriginalId()
    {
        return $this->original_id;
    }

    /**
     * @param int $original_id
     *
     * @return RemoteCategories
     */

    public function setOriginalId($original_id)
    
        {
            $this->original_id = $original_id;
            return $this;
        }
        
    /**
     * @return int
     */

    public function getParentId()
    {
        return $this->parent_id;
    }

    /**
     * @param int $parent_id
     *
     * @return RemoteCategories
     */

    public function setParentId($parent_id)

    {
        $this->parent_id = $parent_id;
        return $this;
    }


    /**
     * @return array
     */

    public function toArray()
    {
        return [
            'id' => $this->getId(),
            'name' => $this->getName(),
            'slug' => $this->getSlug(),
            'original_id' => $this->getOriginalId(),
            'parent_id' => $this->getParentId(),
        ];
    }
}
