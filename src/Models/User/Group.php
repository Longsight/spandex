<?php

namespace Spandex\Models\User;

use Doctrine\Common\Collections\ArrayCollection;

class Group
{
    /**
     * @Id
     * @Column(type="integer")
     * @GeneratedValue
     */
    protected $id;
    
    /**
     * @Column(type="string", length=255, unique=true)
     */
    protected $name;
    
    public function getId()
    {
        return $this->id;
    }

    public function getName()
    {
        return $this->name;
    }

    public function setName($name)
    {
        $this->name = $name;
    }
}
