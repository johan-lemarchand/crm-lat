<?php

namespace App\Entity;

use App\ODF\Infrastructure\Repository\OdfExecutionRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: OdfExecutionRepository::class)]
#[ORM\Table(name: 'odf_execution')]
class OdfExecution
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'odfExecutions')]
    #[ORM\JoinColumn(nullable: false)]
    private OdfLog $odfLog;

    #[ORM\Column(type: 'json')]
    private array $step = [];

    #[ORM\Column(type: 'integer')]
    private int $duration = 0;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'integer')]
    private int $attemptNumber = 1;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $sessionId = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $stepStatus = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $userName = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOdfLog(): OdfLog
    {
        return $this->odfLog;
    }

    public function setOdfLog(OdfLog $odfLog): self
    {
        $this->odfLog = $odfLog;
        
        // Calculer le numéro de tentative
        $executions = $odfLog->getOdfExecutions();
        if ($executions) {
            $this->attemptNumber = count($executions) + 1;
        }
        
        // Récupérer le sessionId de l'OdfLog seulement si l'exécution n'a pas déjà un sessionId
        if ($odfLog->getSessionId() && $this->sessionId === null) {
            $this->sessionId = $odfLog->getSessionId();
        }
        
        return $this;
    }

    public function getStep(): array
    {
        return $this->step;
    }

    public function setStep(array $step): self
    {
        $this->step = $step;
        return $this;
    }

    public function getDuration(): int
    {
        return $this->duration;
    }

    public function setDuration(int $duration): self
    {
        $this->duration = $duration;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getAttemptNumber(): int
    {
        return $this->attemptNumber;
    }

    public function setAttemptNumber(int $attemptNumber): self
    {
        $this->attemptNumber = $attemptNumber;
        return $this;
    }

    public function getSessionId(): ?string
    {
        return $this->sessionId;
    }

    public function setSessionId(?string $sessionId): self
    {
        $this->sessionId = $sessionId;
        return $this;
    }

    public function getStepStatus(): ?int
    {
        return $this->stepStatus;
    }

    public function setStepStatus(?int $stepStatus): self
    {
        $this->stepStatus = $stepStatus;
        return $this;
    }

    public function __toString(): string
    {
        return sprintf('OdfExecution #%d (Tentative #%d pour %s)', 
            $this->id ?? 0, 
            $this->attemptNumber, 
            $this->odfLog?->getName() ?? 'inconnu'
        );
    }

    public function getUserName(): ?string
    {
        return $this->userName;
    }

    public function setUserName(?string $user): self
    {
        $this->userName = $user;
        return $this;
    }
} 