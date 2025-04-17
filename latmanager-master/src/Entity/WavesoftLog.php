<?php

namespace App\Entity;

//use App\Repository\WavesoftLogRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(/*repositoryClass: WavesoftLogRepository::class*/)]
class WavesoftLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;
    #[ORM\Column(type: Types::TEXT)]
    private ?string $automateFile = null;

    #[ORM\Column(length: 10)]
    private ?int $trsId = null;
    #[ORM\Column(length: 50)]
    private ?string $userName = null;

    #[ORM\Column(length: 10)]
    private ?string $status = null;

    #[ORM\Column(length: 255)]
    private ?string $messageError = null;

    #[ORM\Column(length: 10)]
    private ?int $aboId = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAutomateFile(): ?string
    {
        return $this->automateFile;
    }

    public function setAutomateFile(string $automateFile): static
    {
        $this->automateFile = $automateFile;
        return $this;
    }

    public function getTrsId(): ?int
    {
        return $this->trsId;
    }

    public function setTrsId(int $trsId): static
    {
        $this->trsId = $trsId;
        return $this;
    }

    public function getUserName(): ?string
    {
        return $this->userName;
    }

    public function setUserName(string $userName): static
    {
        $this->userName = $userName;
        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getMessageError(): ?string
    {
        return $this->messageError;
    }

    public function setMessageError(string $messageError): static
    {
        $this->messageError = $messageError;
        return $this;
    }

    public function getAboId(): ?int
    {
        return $this->aboId;
    }

    public function setAboId(int $aboId): static
    {
        $this->aboId = $aboId;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }
}