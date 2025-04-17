<?php

namespace App\Entity;

use App\Repository\CommandExecutionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CommandExecutionRepository::class)]
class CommandExecution
{
    public const STATUS_SUCCESS = 'success';
    public const STATUS_ERROR = 'error';
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'executions')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Command $command = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $startedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $endedAt = null;

    #[ORM\Column(length: 255)]
    private ?string $status = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $output = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $error = null;

    #[ORM\Column(nullable: true)]
    private ?float $duration = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $exitCode = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $stackTrace = null;

    #[ORM\OneToMany(mappedBy: 'execution', targetEntity: PraxedoApiLog::class, orphanRemoval: true)]
    private Collection $apiLogs;

    #[ORM\OneToMany(mappedBy: 'execution', targetEntity: WavesoftLog::class)]
    private Collection $wavesoftLogs;

    public function __construct()
    {
        $this->apiLogs = new ArrayCollection();
        $this->wavesoftLogs = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCommand(): ?Command
    {
        return $this->command;
    }

    public function setCommand(?Command $command): static
    {
        $this->command = $command;

        return $this;
    }

    public function getStartedAt(): ?\DateTimeInterface
    {
        return $this->startedAt;
    }

    public function setStartedAt(\DateTimeInterface $startedAt): static
    {
        $this->startedAt = $startedAt;

        return $this;
    }

    public function getEndedAt(): ?\DateTimeInterface
    {
        return $this->endedAt;
    }

    public function setEndedAt(?\DateTimeInterface $endedAt): static
    {
        $this->endedAt = $endedAt;

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

    public function getOutput(): ?string
    {
        return $this->output;
    }

    public function setOutput(?string $output): static
    {
        $this->output = $output;

        return $this;
    }

    public function getError(): ?string
    {
        return $this->error;
    }

    public function setError(?string $error): static
    {
        $this->error = $error;

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

    public function getExitCode(): ?string
    {
        return $this->exitCode;
    }

    public function setExitCode(?string $exitCode): static
    {
        $this->exitCode = $exitCode;

        return $this;
    }

    public function getStackTrace(): ?string
    {
        return $this->stackTrace;
    }

    public function setStackTrace(?string $stackTrace): static
    {
        $this->stackTrace = $stackTrace;

        return $this;
    }

    /**
     * @return Collection<int, PraxedoApiLog>
     */
    public function getApiLogs(): Collection
    {
        return $this->apiLogs;
    }

    public function addApiLog(PraxedoApiLog $apiLog): static
    {
        if (!$this->apiLogs->contains($apiLog)) {
            $this->apiLogs->add($apiLog);
            $apiLog->setExecution($this);
        }

        return $this;
    }

    public function removeApiLog(PraxedoApiLog $apiLog): static
    {
        if ($this->apiLogs->removeElement($apiLog)) {
            // set the owning side to null (unless already changed)
            if ($apiLog->getExecution() === $this) {
                $apiLog->setExecution(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, WavesoftLog>
     */
    public function getWavesoftLogs(): Collection
    {
        return $this->wavesoftLogs;
    }

    public function addWavesoftLog(WavesoftLog $wavesoftLog): static
    {
        if (!$this->wavesoftLogs->contains($wavesoftLog)) {
            $this->wavesoftLogs->add($wavesoftLog);
            $wavesoftLog->setExecution($this);
        }
        return $this;
    }

    public function removeWavesoftLog(WavesoftLog $wavesoftLog): static
    {
        if ($this->wavesoftLogs->removeElement($wavesoftLog)) {
            if ($wavesoftLog->getExecution() === $this) {
                $wavesoftLog->setExecution(null);
            }
        }
        return $this;
    }
}
