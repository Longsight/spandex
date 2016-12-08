<?php

namespace Spandex\Models\User;

use Spandex\Spandex;
use Doctrine\Common\Collections\ArrayCollection;
use Hautelook\Phpass\PasswordHash;

class User
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
    protected $email;

    /**
     * @Column(type="string", length=60)
     */
    protected $password;

    /**
     * @Column(type="string", length=255)
     */
    protected $name;

    /**
     * @Column(type="boolean")
     */
    protected $admin;

    public function __construct()
    {
        $this->admin = false;
    }

    public function getId()
    {
        return $this->id;
    }

    public function getEmail()
    {
        return $this->email;
    }

    public function setEmail($email)
    {
        $this->email = $email;
    }

    public function getPassword()
    {
        return $this->password;
    }

    public function setPassword($password)
    {
        $this->password = $password;
    }

    public function hashPassword($password)
    {
        $hasher = new PasswordHash(Spandex::HASH_STRETCH, false);
        $this->setPassword($hasher->HashPassword($password));
    }

    public function checkPassword($password)
    {
        $hasher = new PasswordHash(Spandex::HASH_STRETCH, false);
        return $hasher->CheckPassword($password, $this->getPassword());
    }

    public function getName()
    {
        return $this->name;
    }

    public function setName($name)
    {
        $this->name = $name;
    }

    public function getAdmin()
    {
        return $this->admin;
    }

    public function setAdmin($admin)
    {
        $this->admin = $admin;
    }
}
