<?php

namespace App\Entity;

use App\Repository\PraxedoApiLogRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PraxedoApiLogRepository::class)]
class PraxedoApiLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'apiLogs')]
    #[ORM\JoinColumn(nullable: false)]
    private ?CommandExecution $execution = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $requestXml = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $responseXml = null;

    #[ORM\Column(length: 255)]
    private ?string $endpoint = null;

    #[ORM\Column(length: 10)]
    private ?string $method = null;

    #[ORM\Column]
    private ?int $statusCode = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    private ?float $duration = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getExecution(): ?CommandExecution
    {
        return $this->execution;
    }

    public function setExecution(?CommandExecution $execution): static
    {
        $this->execution = $execution;
        return $this;
    }

    public function getRequestXml(): ?string
    {
        return $this->requestXml;
    }

    public function setRequestXml(string $requestXml): static
    {
        $this->requestXml = $requestXml;
        return $this;
    }

    public function getResponseXml(): ?string
    {
        return $this->responseXml;
    }

    public function setResponseXml(string $responseXml): static
    {
        $this->responseXml = $responseXml;
        return $this;
    }

    public function getEndpoint(): ?string
    {
        return $this->endpoint;
    }

    public function setEndpoint(string $endpoint): static
    {
        $this->endpoint = $endpoint;
        return $this;
    }

    public function getMethod(): ?string
    {
        return $this->method;
    }

    public function setMethod(string $method): static
    {
        $this->method = $method;
        return $this;
    }

    public function getStatusCode(): ?int
    {
        return $this->statusCode;
    }

    public function setStatusCode(int $statusCode): static
    {
        $this->statusCode = $statusCode;
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

    public function getDuration(): ?float
    {
        return $this->duration;
    }

    public function setDuration(?float $duration): static
    {
        $this->duration = $duration;
        return $this;
    }
}
