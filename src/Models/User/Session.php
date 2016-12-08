<?php

namespace Spandex\Models\User;

class Session
{
    /**
     * @Id
     * @Column(type="string", length=64)
     * @GeneratedValue(strategy="NONE")
     */
    protected $id;
    
    /**
     * @Column(type="datetime")
     */
    protected $created;

    /**
     * @Column(type="datetime")
     */
    protected $lastSeen;

    /**
     * @Column(type="string", columnDefinition="INET NOT NULL")
     */
    protected $ip;

    public function __construct()
    {
        $this->id = bin2hex(openssl_random_pseudo_bytes(32));
        $this->setLastSeen(new \DateTime);
    }

    public function getId()
    {
        return $this->id;
    }

    public function getCreated()
    {
        return $this->created;
    }

    public function setCreated($created)
    {
        $this->created = $created;
    }

    public function getLastSeen()
    {
        return $this->lastSeen;
    }

    public function setLastSeen($lastSeen)
    {
        $this->lastSeen = $lastSeen;
    }

    public function getIp()
    {
        return $this->ip;
    }

    public function setIp($ip)
    {
        $this->ip = $ip;
    }
}
