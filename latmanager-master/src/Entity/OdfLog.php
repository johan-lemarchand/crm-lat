<?php

namespace App\Entity;

use App\ODF\Infrastructure\Repository\OdfLogRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: OdfLogRepository::class)]
#[ORM\Table(name: 'odf_log')]
class OdfLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 50)]
    private ?string $status = null;

    #[ORM\Column]
    private ?float $executionTime = null;

    #[ORM\Column(nullable: true)]
    private ?float $executionTimePause = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\OneToMany(targetEntity: OdfExecution::class, mappedBy: 'odfLog', orphanRemoval: true, cascade: ['persist'])]
    private Collection $odfExecutions;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $sessionId = null;

    #[ORM\Column(nullable: true)]
    private ?int $errorCount = 0;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $errorsByStep = [];

    public function __construct()
    {
        $this->odfExecutions = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->errorsByStep = [];
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

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

    public function getExecutionTime(): ?float
    {
        return $this->executionTime;
    }

    public function setExecutionTime(float $executionTime): static
    {
        $this->executionTime = $executionTime;

        return $this;
    }

    public function getExecutionTimePause(): ?float
    {
        return $this->executionTimePause;
    }

    public function setExecutionTimePause(?float $executionTimePause): static
    {
        $this->executionTimePause = $executionTimePause;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    /**
     * @return Collection<int, OdfExecution>
     */
    public function getOdfExecutions(): Collection
    {
        return $this->odfExecutions;
    }

    public function addOdfExecution(OdfExecution $odfExecution): static
    {
        if (!$this->odfExecutions->contains($odfExecution)) {
            $this->odfExecutions->add($odfExecution);
            $odfExecution->setOdfLog($this);
        }

        return $this;
    }

    public function removeOdfExecution(OdfExecution $odfExecution): static
    {
        if ($this->odfExecutions->removeElement($odfExecution)) {
            // set the owning side to null (unless already changed)
            if ($odfExecution->getOdfLog() === $this) {
                $odfExecution->setOdfLog(null);
            }
        }

        return $this;
    }

    public function getSessionId(): ?string
    {
        return $this->sessionId;
    }

    public function setSessionId(?string $sessionId): static
    {
        $this->sessionId = $sessionId;

        return $this;
    }

    public function getErrorCount(): ?int
    {
        return $this->errorCount;
    }

    public function setErrorCount(?int $errorCount): static
    {
        $this->errorCount = $errorCount;

        return $this;
    }

    public function incrementError(string $step): static
    {
        $this->errorCount = ($this->errorCount ?? 0) + 1;
        
        $errorsByStep = $this->errorsByStep ?? [];
        $errorsByStep[$step] = ($errorsByStep[$step] ?? 0) + 1;
        $this->errorsByStep = $errorsByStep;

        return $this;
    }

    public function getErrorsByStep(): ?array
    {
        return $this->errorsByStep;
    }

    public function setErrorsByStep(?array $errorsByStep): static
    {
        $this->errorsByStep = $errorsByStep;

        return $this;
    }
} 